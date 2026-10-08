<?php

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\ClinicalNote;
use App\Models\Doctor;
use App\Models\Invoice;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->travelTo(now(config('clinic.timezone'))->setDate(2026, 10, 12)->setTime(8, 0));
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->patient = User::factory()->create(['role' => 'patient']);
    $this->receptionist = User::factory()->create(['role' => 'receptionist']);
    $this->doctorUser = User::factory()->create(['role' => 'doctor']);
    $this->doctor = Doctor::create(['name' => 'Dr. Test', 'email' => 'doctor@example.test', 'specialization' => 'General Medicine', 'user_id' => $this->doctorUser->id, 'consultation_fee' => 350, 'status' => 'active', 'starts_at' => '09:00', 'ends_at' => '17:00', 'slot_minutes' => 30, 'working_days' => [0, 1, 2, 3, 4]]);
    $this->booking = ['doctor_id' => $this->doctor->id, 'patient_id' => $this->patient->id, 'appointment_date' => '2026-10-13', 'appointment_time' => '10:00', 'reason' => 'Private visit reason'];
});

test('a patient booking creates exactly one invoice and an audit entry', function () {
    $this->actingAs($this->patient)->post(route('appointments.store'), $this->booking)->assertSessionHasNoErrors()->assertRedirect();
    $a = Appointment::first();
    expect($a->patient_id)->toBe($this->patient->id)->and($a->invoice->amount)->toBe('350.00')->and($a->reason)->toBe('Private visit reason');
    expect(DB::table('appointments')->value('reason'))->not->toContain('Private visit reason');
    $this->assertDatabaseCount('invoices', 1);
    $this->assertDatabaseHas('audit_logs', ['action' => 'appointment.booked']);
});

test('patients cannot book for another patient or forge status or fee', function () {
    $this->actingAs($this->patient)->post(route('appointments.store'), [...$this->booking, 'patient_id' => $this->admin->id, 'status' => 'completed', 'amount' => 1, 'created_by' => $this->admin->id])->assertSessionHasNoErrors();
    expect(Appointment::first()->patient_id)->toBe($this->patient->id)->and(Appointment::first()->status)->toBe('pending')->and(Invoice::first()->amount)->toBe('350.00');
});

test('duplicate bookings are rejected and cancellation releases the slot', function () {
    $this->actingAs($this->patient)->post(route('appointments.store'), $this->booking)->assertSessionHasNoErrors();
    $this->post(route('appointments.store'), $this->booking)->assertSessionHasErrors('appointment_time');
    $a = Appointment::first();
    $this->patch(route('appointments.update', $a), ['status' => 'cancelled'])->assertSessionHasNoErrors();
    expect($a->fresh()->slot_key)->toBeNull()->and($a->invoice->fresh()->status)->toBe('cancelled');
    $this->post(route('appointments.store'), $this->booking)->assertSessionHasNoErrors();
    $this->assertDatabaseCount('appointments', 2);
});

test('database uniqueness protects reservations even outside booking service', function () {
    $a = app(BookingService::class)->book($this->booking, $this->patient);
    expect(fn () => Appointment::create($a->only(['patient_id', 'doctor_id', 'appointment_date', 'appointment_time', 'slot_key', 'status'])))->toThrow(QueryException::class);
});

test('invalid schedule requests are rejected', function ($changes, $field) {
    $this->actingAs($this->patient)->post(route('appointments.store'), [...$this->booking, ...$changes])->assertSessionHasErrors($field);
    $this->assertDatabaseCount('appointments', 0);
})->with([
    [['appointment_date' => '2026-10-11'], 'appointment_date'],
    [['appointment_date' => '2027-01-01'], 'appointment_date'],
    [['appointment_date' => '2026-10-16'], 'appointment_time'],
    [['appointment_time' => '10:05'], 'appointment_time'],
    [['appointment_time' => '17:00'], 'appointment_time'],
    [['appointment_time' => '08:30'], 'appointment_time'],
]);

test('inactive doctors cannot receive bookings', function () {
    $this->doctor->update(['status' => 'inactive']);
    $this->actingAs($this->patient)->post(route('appointments.store'), $this->booking)->assertSessionHasErrors('appointment_time');
});

test('patients cannot access staff pages', function ($url) {
    $this->actingAs($this->patient)->get($url)->assertForbidden();
})->with(['/patients', '/team', '/audit', '/admin/admindashboard']);

test('patients and other doctors cannot see another persons appointment', function () {
    $a = app(BookingService::class)->book($this->booking, $this->patient);
    foreach ([User::factory()->create(), User::factory()->create(['role' => 'doctor'])] as $other) {
        $this->actingAs($other)->get(route('appointments.show', $a))->assertForbidden();
        $this->patch(route('appointments.update', $a), ['status' => 'cancelled'])->assertForbidden();
        $this->get(route('appointments.index'))->assertDontSee('Private visit reason');
    }
});

test('only authorized clinicians can write and view encrypted clinical notes', function () {
    $a = app(BookingService::class)->book($this->booking, $this->patient);
    $this->actingAs($this->doctorUser)->post(route('appointments.note', $a), ['notes' => 'Confidential clinical assessment'])->assertSessionHasNoErrors();
    expect(DB::table('clinical_notes')->value('notes'))->not->toContain('Confidential clinical assessment');
    $this->get(route('appointments.show', $a))->assertSee('Confidential clinical assessment');
    foreach ([$this->patient, $this->receptionist] as $user) {
        $this->actingAs($user)->get(route('appointments.show', $a))->assertOk()->assertDontSee('Confidential clinical assessment');
        $this->post(route('appointments.note', $a), ['notes' => 'tampered'])->assertForbidden();
    }
    expect(ClinicalNote::first()->notes)->toBe('Confidential clinical assessment');
    $this->assertDatabaseHas('audit_logs', ['action' => 'clinical_note.viewed']);
});

test('reception can confirm but cannot complete a visit', function () {
    $a = app(BookingService::class)->book($this->booking, $this->patient);
    $this->actingAs($this->receptionist)->patch(route('appointments.update', $a), ['status' => 'confirmed'])->assertSessionHasNoErrors();
    $this->patch(route('appointments.update', $a), ['status' => 'completed'])->assertForbidden();
    $this->travelTo(now(config('clinic.timezone'))->setDate(2026, 10, 13)->setTime(10, 30));
    $this->actingAs($this->doctorUser)->patch(route('appointments.update', $a), ['status' => 'completed'])->assertSessionHasNoErrors();
    $this->patch(route('appointments.update', $a), ['status' => 'confirmed'])->assertSessionHasErrors('status');
});

test('payment is recorded once and paid visits cannot be silently cancelled', function () {
    $a = app(BookingService::class)->book($this->booking, $this->patient);
    $invoice = $a->invoice;
    $this->actingAs($this->patient)->patch(route('invoices.pay', $invoice), ['payment_method' => 'cash'])->assertForbidden();
    $this->actingAs($this->receptionist)->patch(route('invoices.pay', $invoice), ['payment_method' => 'cash'])->assertSessionHasNoErrors();
    $this->patch(route('invoices.pay', $invoice), ['payment_method' => 'cash'])->assertSessionHasErrors('payment');
    $this->patch(route('appointments.update', $a), ['status' => 'cancelled'])->assertSessionHasErrors('status');
    expect($invoice->fresh()->status)->toBe('paid')->and(AuditLog::where('action', 'invoice.paid')->count())->toBe(1);
});

test('invoice ownership is enforced', function () {
    $a = app(BookingService::class)->book($this->booking, $this->patient);
    $this->actingAs(User::factory()->create())->get(route('invoices.show', $a->invoice))->assertForbidden();
});

test('records cannot be deleted by closing a patient or staff account', function () {
    app(BookingService::class)->book($this->booking, $this->patient);
    foreach ([$this->patient, $this->admin] as $user) {
        $this->actingAs($user)->delete(route('profile.destroy'), ['password' => 'password'])->assertSessionHasErrorsIn('userDeletion', 'account');
        expect($user->fresh())->not->toBeNull();
    }
});

test('all admin screens render and Arabic direction is applied', function () {
    $appointment = app(BookingService::class)->book($this->booking, $this->patient);
    foreach (['/dashboard', '/appointments', '/appointments/create', '/patients', '/doctors', '/invoices', '/team', '/audit', '/profile', route('appointments.show', $appointment), route('invoices.show', $appointment->invoice)] as $url) {
        $this->actingAs($this->admin)->get($url)->assertOk();
        $this->withSession(['locale' => 'ar'])->get($url)->assertOk()->assertSee('dir="rtl"', false);
    }
});

test('inactive sessions are revoked and unknown roles are denied', function () {
    $this->patient->forceFill(['is_active' => false])->save();
    $this->actingAs($this->patient)->get('/dashboard')->assertRedirect(route('login'));
    $this->assertGuest();
    $this->actingAs(User::factory()->create(['role' => 'unknown']))->get('/appointments')->assertForbidden();
});

test('public registration cannot grant staff privileges', function () {
    $this->post('/register', ['name' => 'New Patient', 'email' => 'new@example.test', 'password' => 'StrongPassword2026', 'password_confirmation' => 'StrongPassword2026', 'role' => 'admin', 'is_admin' => true])->assertSessionHasNoErrors();
    expect(User::where('email', 'new@example.test')->first()->role)->toBe('patient');
    $this->get('/dashboard')->assertRedirect(route('verification.notice'));
});

test('insecure mail endpoint and old admin mutations are not reachable', function () {
    $this->get('/test-mail')->assertNotFound();
    $this->actingAs($this->patient)->post('/admin/doctors/store', [])->assertNotFound();
});

test('new databases have no default admin password', function () {
    $this->assertDatabaseMissing('users', ['email' => 'admin@hospital.com']);
});

test('rescheduling preserves invoice, releases old time and rejects conflicts', function () {
    $a = app(BookingService::class)->book($this->booking, $this->patient);
    $invoiceId = $a->invoice->id;
    $this->actingAs($this->receptionist)->patch(route('appointments.reschedule', $a), ['appointment_date' => '2026-10-13', 'appointment_time' => '11:00'])->assertSessionHasNoErrors();
    expect($a->fresh()->appointment_time)->toStartWith('11:00')->and($a->fresh()->invoice->id)->toBe($invoiceId);
    $this->assertDatabaseCount('invoices', 1);
    expect(app(BookingService::class)->slots($this->doctor, '2026-10-13'))->toContain('10:00')->not->toContain('11:00');
    $this->actingAs($this->patient)->patch(route('appointments.reschedule', $a), ['appointment_date' => '2026-10-13', 'appointment_time' => '12:00'])->assertForbidden();
});

test('schedule changes cannot create overlapping appointments', function () {
    app(BookingService::class)->book($this->booking, $this->patient);
    $this->doctor->update(['slot_minutes' => 15]);
    $other = User::factory()->create();
    $this->actingAs($other)->post(route('appointments.store'), [...$this->booking, 'appointment_time' => '10:15'])->assertSessionHasErrors('appointment_time');
    $this->post(route('appointments.store'), [...$this->booking, 'appointment_time' => '10:30'])->assertSessionHasNoErrors();
});

test('a patient cannot book overlapping visits with different doctors', function () {
    app(BookingService::class)->book($this->booking, $this->patient);
    $other = $this->doctor->replicate(['user_id']);
    $other->save();
    $this->actingAs($this->patient)->post(route('appointments.store'), [...$this->booking, 'doctor_id' => $other->id])->assertSessionHasErrors('appointment_time');
});

test('a future appointment cannot be marked completed', function () {
    $a = app(BookingService::class)->book($this->booking, $this->patient);
    $a->update(['status' => 'confirmed']);
    $this->actingAs($this->doctorUser)->patch(route('appointments.update', $a), ['status' => 'completed'])->assertSessionHasErrors('status');
});

test('staff accounts are linked to one doctor and access can be disabled', function () {
    $unlinked = $this->doctor->replicate(['user_id']);
    $unlinked->save();
    $this->actingAs($this->admin)->post(route('team.store'), ['name' => 'New Doctor', 'email' => 'newdoctor@example.test', 'role' => 'doctor', 'doctor_id' => $unlinked->id])->assertSessionHasNoErrors();
    $staff = User::where('email', 'newdoctor@example.test')->first();
    expect($unlinked->fresh()->user_id)->toBe($staff->id)->and($staff->role)->toBe('doctor');
    $this->patch(route('team.toggle', $staff))->assertSessionHasNoErrors();
    expect($staff->fresh()->is_active)->toBeFalse();
    $this->patch(route('team.toggle', $this->admin))->assertForbidden();
});

test('doctor schedule updates validate hours and preserve existing reservations', function () {
    $a = app(BookingService::class)->book($this->booking, $this->patient);
    $data = ['name' => 'Updated Doctor', 'email' => 'updated@example.test', 'specialization' => 'General Medicine', 'status' => 'active', 'consultation_fee' => 400, 'starts_at' => '09:00', 'ends_at' => '17:00', 'slot_minutes' => 30, 'working_days' => [0, 1, 2, 3, 4]];
    $this->actingAs($this->receptionist)->put(route('doctors.update', $this->doctor), $data)->assertForbidden();
    $this->actingAs($this->admin)->put(route('doctors.update', $this->doctor), [...$data, 'ends_at' => '08:00'])->assertSessionHasErrors('ends_at');
    $this->put(route('doctors.update', $this->doctor), $data)->assertSessionHasNoErrors();
    expect($a->fresh()->invoice->amount)->toBe('350.00');
});

test('sensitive responses are private and noncacheable', function () {
    $this->actingAs($this->patient)->get('/dashboard')->assertHeader('X-Frame-Options', 'DENY')->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Cache-Control', 'no-store, private');
});
