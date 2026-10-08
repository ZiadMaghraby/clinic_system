<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Doctor;
use App\Models\Invoice;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingService
{
    public function slots(Doctor $doctor, string $date, ?int $except = null): array
    {
        $day = CarbonImmutable::parse($date, config('clinic.timezone'));
        if ($doctor->status !== 'active' || ! in_array($day->dayOfWeek, $doctor->working_days ?? [0, 1, 2, 3, 4])) {
            return [];
        }
        $cursor = $day->setTimeFromTimeString($doctor->starts_at);
        $end = $day->setTimeFromTimeString($doctor->ends_at);
        $occupied = $doctor->appointments()->whereDate('appointment_date', $date)
            ->where('status', '!=', 'cancelled')->when($except, fn ($q) => $q->where('id', '!=', $except))->get(['appointment_time', 'duration_minutes']);
        $slots = [];
        while ($cursor->addMinutes($doctor->slot_minutes)->lte($end)) {
            $overlaps = $occupied->contains(function ($a) use ($cursor, $day, $doctor) {
                $start = $day->setTimeFromTimeString($a->appointment_time);

                return $cursor->lt($start->addMinutes($a->duration_minutes)) && $cursor->addMinutes($doctor->slot_minutes)->gt($start);
            });
            if ($cursor->isFuture() && ! $overlaps) {
                $slots[] = $cursor->format('H:i');
            }
            $cursor = $cursor->addMinutes($doctor->slot_minutes);
        }

        return $slots;
    }

    public function book(array $data, User $actor): Appointment
    {
        try {
            return DB::transaction(function () use ($data, $actor) {
                // Serialize bookings and schedule changes for this doctor; the unique key is a second guard.
                $patient = User::where('role', 'patient')->where('is_active', true)->lockForUpdate()->findOrFail($data['patient_id']);
                $doctor = Doctor::lockForUpdate()->findOrFail($data['doctor_id']);
                if (! in_array($data['appointment_time'], $this->slots($doctor, $data['appointment_date']))) {
                    throw ValidationException::withMessages(['appointment_time' => __('This time is unavailable. Choose another slot.')]);
                }
                $this->assertPatientAvailable($patient, $data, $doctor->slot_minutes);
                $appointment = Appointment::create([
                    ...$data, 'patient_id' => $patient->id, 'status' => 'pending', 'created_by' => $actor->id,
                    'slot_key' => $doctor->id.'|'.$data['appointment_date'].'|'.$data['appointment_time'],
                    'duration_minutes' => $doctor->slot_minutes,
                ]);
                Invoice::create(['appointment_id' => $appointment->id, 'amount' => $doctor->consultation_fee, 'status' => 'unpaid']);
                AuditLog::record('appointment.booked', $appointment);

                return $appointment;
            });
        } catch (QueryException $e) {
            if (in_array((string) $e->getCode(), ['23000', '23505'])) {
                throw ValidationException::withMessages(['appointment_time' => __('This time is unavailable. Choose another slot.')]);
            }
            throw $e;
        }
    }

    public function transition(Appointment $appointment, string $status, User $actor): void
    {
        DB::transaction(function () use ($appointment, $status, $actor) {
            $appointment = Appointment::lockForUpdate()->findOrFail($appointment->id);
            $allowed = ['pending' => ['confirmed', 'cancelled'], 'confirmed' => ['completed', 'cancelled']];
            if (! in_array($status, $allowed[$appointment->status] ?? [])) {
                throw ValidationException::withMessages(['status' => __('This appointment can no longer be changed.')]);
            }
            if ($actor->role === 'patient') {
                abort_unless($appointment->patient_id === $actor->id && $status === 'cancelled', 403);
                if (CarbonImmutable::parse($appointment->appointment_date->format('Y-m-d').' '.$appointment->appointment_time, config('clinic.timezone'))->isPast()) {
                    throw ValidationException::withMessages(['status' => __('Please contact the clinic to change a past appointment.')]);
                }
            }
            $invoice = Invoice::where('appointment_id', $appointment->id)->lockForUpdate()->first();
            if ($status === 'completed' && CarbonImmutable::parse($appointment->appointment_date->format('Y-m-d').' '.$appointment->appointment_time, config('clinic.timezone'))->isFuture()) {
                throw ValidationException::withMessages(['status' => __('A future visit cannot be marked completed.')]);
            }
            if ($status === 'cancelled' && $invoice?->status === 'paid') {
                throw ValidationException::withMessages(['status' => __('Paid appointments require a billing review before cancellation.')]);
            }
            $appointment->update(['status' => $status, 'slot_key' => $status === 'cancelled' ? null : $appointment->slot_key]);
            if ($status === 'cancelled') {
                $invoice?->update(['status' => 'cancelled']);
            }
            AuditLog::record('appointment.'.$status, $appointment);
        });
    }

    private function assertPatientAvailable(User $patient, array $data, int $minutes, ?int $except = null): void
    {
        $start = CarbonImmutable::parse($data['appointment_date'].' '.$data['appointment_time'], config('clinic.timezone'));
        $conflict = $patient->appointmentsAsPatient()->whereDate('appointment_date', $data['appointment_date'])
            ->where('status', '!=', 'cancelled')->when($except, fn ($q) => $q->where('id', '!=', $except))->get()->contains(function ($a) use ($start, $minutes) {
                $existing = CarbonImmutable::parse($a->appointment_date->format('Y-m-d').' '.$a->appointment_time, config('clinic.timezone'));

                return $start->lt($existing->addMinutes($a->duration_minutes)) && $start->addMinutes($minutes)->gt($existing);
            });
        if ($conflict) {
            throw ValidationException::withMessages(['appointment_time' => __('The patient already has a visit at this time.')]);
        }
    }

    public function reschedule(Appointment $appointment, array $data): void
    {
        DB::transaction(function () use ($appointment, $data) {
            $patient = User::lockForUpdate()->findOrFail($appointment->patient_id);
            $doctor = Doctor::lockForUpdate()->findOrFail($appointment->doctor_id);
            $appointment = Appointment::lockForUpdate()->findOrFail($appointment->id);
            if (! in_array($appointment->status, ['pending', 'confirmed'])) {
                throw ValidationException::withMessages(['status' => __('This appointment can no longer be changed.')]);
            }
            if (! in_array($data['appointment_time'], $this->slots($doctor, $data['appointment_date'], $appointment->id))) {
                throw ValidationException::withMessages(['appointment_time' => __('This time is unavailable. Choose another slot.')]);
            }
            $this->assertPatientAvailable($patient, $data, $doctor->slot_minutes, $appointment->id);
            $appointment->update([...$data, 'status' => 'pending', 'duration_minutes' => $doctor->slot_minutes, 'slot_key' => $doctor->id.'|'.$data['appointment_date'].'|'.$data['appointment_time']]);
            AuditLog::record('appointment.rescheduled', $appointment);
        });
    }
}
