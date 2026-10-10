<?php

use App\Models\Appointment;
use App\Models\ClinicalNote;
use App\Models\Doctor;
use App\Models\Invoice;
use App\Models\User;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 15)->setTime(12, 0));
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->patient = User::factory()->create(['role' => 'patient']);
    $this->receptionist = User::factory()->create(['role' => 'receptionist']);
    $this->doctor = Doctor::create(['name' => 'Dr. Reports', 'email' => 'reports@example.test', 'specialization' => 'General Medicine']);
    $this->visit = Appointment::create(['patient_id' => $this->patient->id, 'doctor_id' => $this->doctor->id, 'appointment_date' => '2026-10-10', 'appointment_time' => '10:00', 'status' => 'completed', 'reason' => 'Private reason never in reports']);
});

test('reports and exports are restricted to administrators', function ($role) {
    $user = User::factory()->create(['role' => $role]);
    $this->actingAs($user)->get('/reports')->assertForbidden();
    $this->get('/reports/export')->assertForbidden();
})->with(['patient', 'doctor', 'receptionist']);

test('reports use payment date for collections and visit date for outstanding balances', function () {
    Invoice::create(['appointment_id' => $this->visit->id, 'status' => 'paid', 'amount' => 123.45, 'paid_at' => '2026-10-01 00:00:00', 'payment_method' => 'cash']);
    $outside = Appointment::create(['patient_id' => $this->patient->id, 'doctor_id' => $this->doctor->id, 'appointment_date' => '2026-09-01', 'appointment_time' => '10:00', 'status' => 'completed']);
    Invoice::create(['appointment_id' => $outside->id, 'status' => 'paid', 'amount' => 200, 'paid_at' => '2026-10-31 23:59:59', 'payment_method' => 'card']);
    $next = Appointment::create(['patient_id' => $this->patient->id, 'doctor_id' => $this->doctor->id, 'appointment_date' => '2026-10-12', 'appointment_time' => '10:00', 'status' => 'completed']);
    Invoice::create(['appointment_id' => $next->id, 'status' => 'paid', 'amount' => 900, 'paid_at' => '2026-11-01 00:00:00', 'payment_method' => 'cash']);
    $unpaid = Appointment::create(['patient_id' => $this->patient->id, 'doctor_id' => $this->doctor->id, 'appointment_date' => '2026-10-18', 'appointment_time' => '10:00', 'status' => 'pending']);
    Invoice::create(['appointment_id' => $unpaid->id, 'status' => 'unpaid', 'amount' => 99.50]);
    $response = $this->actingAs($this->admin)->get('/reports?from=2026-10-01&to=2026-10-31')->assertOk();
    $response->assertViewHas('collected', fn ($value) => (float) $value === 323.45)
        ->assertViewHas('outstanding', fn ($value) => (float) $value === 99.50)
        ->assertViewHas('total', 3)->assertDontSee($this->patient->email)->assertDontSee('Private reason never in reports');
});

test('reports validate dates and render a useful empty state in Arabic', function () {
    $this->actingAs($this->admin)->get('/reports?from=bad')->assertSessionHasErrors('from');
    $this->get('/reports?from=2026-10-20&to=2026-10-01')->assertSessionHasErrors('to');
    $this->get('/reports?from=2024-01-01&to=2026-10-01')->assertStatus(422);
    $this->withSession(['locale' => 'ar'])->get('/reports?from=2026-01-01&to=2026-01-02')->assertOk()->assertSee('dir="rtl"', false)->assertSee('لا توجد مواعيد في هذه الفترة.');
});

test('a single day report includes appointments on its ending date', function () {
    Invoice::create(['appointment_id' => $this->visit->id, 'status' => 'unpaid', 'amount' => 150]);
    $this->actingAs($this->admin)->get('/reports?from=2026-10-10&to=2026-10-10')->assertOk()
        ->assertViewHas('total', 1)->assertViewHas('outstanding', fn ($value) => (float) $value === 150.0);
});

test('coming up next excludes visits whose start time has already passed today', function () {
    $this->visit->update(['appointment_date' => '2026-10-15', 'appointment_time' => '11:00', 'status' => 'confirmed']);
    $future = Appointment::create(['patient_id' => $this->patient->id, 'doctor_id' => $this->doctor->id, 'appointment_date' => '2026-10-15', 'appointment_time' => '13:00', 'status' => 'confirmed']);
    $this->actingAs($this->admin)->get('/dashboard')->assertOk()->assertViewHas('upcoming', fn ($visits) => $visits->modelKeys() === [$future->id]);
});

test('export is audited and contains aggregate data without patient identities or formulas', function () {
    config(['clinic.currency' => '=HYPERLINK("bad")']);
    $response = $this->actingAs($this->admin)->get('/reports/export?from=2026-10-01&to=2026-10-31')->assertOk()->assertDownload('clinic-report-2026-10-01-2026-10-31.csv');
    $csv = $response->streamedContent();
    expect($csv)->toContain('Scheduled visits')->toContain("'=HYPERLINK")->not->toContain($this->patient->email)->not->toContain('Private reason never in reports');
    $this->assertDatabaseHas('audit_logs', ['action' => 'report.exported', 'user_id' => $this->admin->id]);
});

test('patient pages exclude clinical notes and allow audited contact corrections', function () {
    ClinicalNote::create(['appointment_id' => $this->visit->id, 'author_id' => $this->admin->id, 'notes' => 'Secret clinical content']);
    $this->actingAs($this->receptionist)->get(route('patients.show', $this->patient))->assertOk()->assertSee('Visit history')->assertDontSee('Secret clinical content')->assertDontSee('Private reason never in reports');
    $originalEmail = $this->patient->email;
    $this->patch(route('patients.update', $this->patient), ['name' => 'Corrected name', 'phone' => '01012345678', 'date_of_birth' => '1990-01-02', 'email' => 'hijack@example.test', 'role' => 'admin', 'is_active' => false])->assertSessionHasNoErrors()->assertRedirect(route('patients.show', $this->patient));
    $patient = $this->patient->fresh();
    expect($patient->name)->toBe('Corrected name')->and($patient->email)->toBe($originalEmail)->and($patient->role)->toBe('patient')->and($patient->is_active)->toBeTrue();
    $this->assertDatabaseHas('audit_logs', ['action' => 'patient.updated', 'subject_id' => $patient->id]);
});

test('patients and doctors cannot access or change directory profiles', function ($role) {
    $this->actingAs(User::factory()->create(['role' => $role]))->get(route('patients.show', $this->patient))->assertForbidden();
    $this->patch(route('patients.update', $this->patient), ['name' => 'Tampered', 'phone' => '123'])->assertForbidden();
})->with(['patient', 'doctor']);

test('staff identities and future birth dates cannot be edited through patient endpoints', function () {
    $this->actingAs($this->admin)->get(route('patients.show', $this->admin))->assertNotFound();
    $this->patch(route('patients.update', $this->admin), ['name' => 'Tampered', 'phone' => '123'])->assertNotFound();
    $this->patch(route('patients.update', $this->patient), ['name' => 'Name', 'phone' => '123', 'date_of_birth' => '2027-01-01'])->assertSessionHasErrors('date_of_birth');
});
