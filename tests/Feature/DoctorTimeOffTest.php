<?php

use App\Models\AuditLog;
use App\Models\Doctor;
use App\Models\DoctorTimeOff;
use App\Models\User;
use App\Services\BookingService;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 12)->setTime(8, 0));
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->patient = User::factory()->create();
    $this->doctor = Doctor::create(['name' => 'Dr. Leave', 'email' => 'leave@example.test', 'specialization' => 'General', 'status' => 'active', 'starts_at' => '09:00', 'ends_at' => '17:00', 'working_days' => [0, 1, 2, 3, 4], 'slot_minutes' => 30]);
    $this->period = ['doctor_id' => $this->doctor->id, 'starts_at' => '2026-10-13T10:15', 'ends_at' => '2026-10-13T11:00'];
    $this->booking = ['patient_id' => $this->patient->id, 'doctor_id' => $this->doctor->id, 'appointment_date' => '2026-10-13', 'appointment_time' => '10:00'];
});

test('time off removes partially overlapping slots while preserving adjacent slots', function () {
    $this->actingAs($this->admin)->post('/time-off', $this->period)->assertSessionHasNoErrors()->assertRedirect('/time-off');
    $slots = app(BookingService::class)->slots($this->doctor, '2026-10-13');
    expect($slots)->not->toContain('10:00', '10:30')->toContain('09:30', '11:00');
    $this->assertDatabaseHas('audit_logs', ['action' => 'doctor_time_off.created']);
    $this->withSession(['locale' => 'ar'])->get('/time-off')->assertOk()->assertSee('dir="rtl"', false)->assertSee('إجازات الأطباء');
});

test('direct booking and rescheduling cannot bypass time off', function () {
    $visit = app(BookingService::class)->book([...$this->booking, 'appointment_time' => '12:00'], $this->patient);
    $this->actingAs($this->admin)->post('/time-off', $this->period)->assertSessionHasNoErrors();
    $this->actingAs($this->patient)->post('/appointments', $this->booking)->assertSessionHasErrors('appointment_time');
    $this->actingAs($this->admin)->patch(route('appointments.reschedule', $visit), ['appointment_date' => '2026-10-13', 'appointment_time' => '10:30'])->assertSessionHasErrors('appointment_time');
    expect($visit->fresh()->appointment_time)->toStartWith('12:00');
});

test('time off cannot silently displace existing appointments', function () {
    $visit = app(BookingService::class)->book($this->booking, $this->patient);
    $this->actingAs($this->admin)->post('/time-off', $this->period)->assertSessionHasErrors('starts_at');
    $this->assertDatabaseCount('doctor_time_offs', 0);
    expect($visit->fresh()->status)->toBe('pending');
    $this->patch(route('appointments.update', $visit), ['status' => 'cancelled'])->assertSessionHasNoErrors();
    $this->post('/time-off', $this->period)->assertSessionHasNoErrors();
});

test('multiple day absences and duplicate periods are respected', function () {
    $this->actingAs($this->admin)->post('/time-off', [...$this->period, 'starts_at' => '2026-10-13T00:00', 'ends_at' => '2026-10-15T00:00'])->assertSessionHasNoErrors();
    expect(app(BookingService::class)->slots($this->doctor, '2026-10-14'))->toBe([]);
    expect(app(BookingService::class)->slots($this->doctor, '2026-10-15'))->toContain('09:00');
    $this->post('/time-off', $this->period)->assertSessionHasErrors('starts_at');
});

test('cancelling time off restores availability without deleting its audit trail', function () {
    $this->actingAs($this->admin)->post('/time-off', $this->period)->assertSessionHasNoErrors();
    $period = DoctorTimeOff::first();
    $this->patch(route('time-off.cancel', $period))->assertRedirect('/time-off');
    $this->patch(route('time-off.cancel', $period))->assertRedirect('/time-off');
    expect($period->fresh()->cancelled_at)->not->toBeNull();
    expect(app(BookingService::class)->slots($this->doctor, '2026-10-13'))->toContain('10:00');
    expect(AuditLog::where('action', 'doctor_time_off.cancelled')->count())->toBe(1);
});

test('non administrators cannot manage absences', function ($role) {
    $period = DoctorTimeOff::create(['doctor_id' => $this->doctor->id, 'starts_at' => '2026-10-13 10:00', 'ends_at' => '2026-10-13 11:00']);
    $this->actingAs(User::factory()->create(['role' => $role]))->get('/time-off')->assertForbidden();
    $this->post('/time-off', $this->period)->assertForbidden();
    $this->patch(route('time-off.cancel', $period))->assertForbidden();
})->with(['patient', 'doctor', 'receptionist']);

test('invalid absence dates are rejected', function ($changes, $field) {
    $this->actingAs($this->admin)->post('/time-off', [...$this->period, ...$changes])->assertSessionHasErrors($field);
    $this->assertDatabaseCount('doctor_time_offs', 0);
})->with([
    [['starts_at' => '2026-10-11T10:00'], 'starts_at'],
    [['ends_at' => '2026-10-13T10:00'], 'ends_at'],
    [['ends_at' => '2028-10-13T10:00'], 'ends_at'],
    [['starts_at' => 'invalid'], 'starts_at'],
]);
