<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Doctor;
use App\Models\DoctorTimeOff;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DoctorTimeOffController extends Controller
{
    public function index()
    {
        return view('clinic.time-off', [
            'doctors' => Doctor::orderBy('name')->get(['id', 'name']),
            'periods' => DoctorTimeOff::with('doctor')->whereNull('cancelled_at')->where('ends_at', '>', now(config('clinic.timezone')))->orderBy('starts_at')->paginate(15),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'doctor_id' => 'required|integer|exists:doctors,id',
            'starts_at' => 'required|date_format:Y-m-d\TH:i|after:now',
            'ends_at' => 'required|date_format:Y-m-d\TH:i|after:starts_at',
        ]);
        $start = CarbonImmutable::parse($data['starts_at'], config('clinic.timezone'));
        $end = CarbonImmutable::parse($data['ends_at'], config('clinic.timezone'));
        if ($start->diffInDays($end) > 366) {
            throw ValidationException::withMessages(['ends_at' => __('Choose a range of up to 366 days.')]);
        }

        DB::transaction(function () use ($data, $start, $end) {
            // Same doctor lock as booking and rescheduling: an absence cannot race a new reservation.
            $doctor = Doctor::lockForUpdate()->findOrFail($data['doctor_id']);
            if (DoctorTimeOff::where('doctor_id', $doctor->id)->whereNull('cancelled_at')->where('starts_at', '<', $end)->where('ends_at', '>', $start)->exists()) {
                throw ValidationException::withMessages(['starts_at' => __('This period overlaps existing time off.')]);
            }
            $conflict = $doctor->appointments()->whereIn('status', ['pending', 'confirmed'])
                ->whereDate('appointment_date', '>=', $start->toDateString())->whereDate('appointment_date', '<=', $end->toDateString())
                ->get()->contains(function ($visit) use ($start, $end) {
                    $visitStart = CarbonImmutable::parse($visit->appointment_date->format('Y-m-d').' '.$visit->appointment_time, config('clinic.timezone'));

                    return $visitStart->lt($end) && $visitStart->addMinutes($visit->duration_minutes)->gt($start);
                });
            if ($conflict) {
                throw ValidationException::withMessages(['starts_at' => __('Existing appointments overlap this period. Reschedule or cancel them first.')]);
            }
            $period = DoctorTimeOff::create(['doctor_id' => $doctor->id, 'starts_at' => $start->format('Y-m-d H:i:s'), 'ends_at' => $end->format('Y-m-d H:i:s')]);
            AuditLog::record('doctor_time_off.created', $period);
        });

        return to_route('time-off.index')->with('success', __('Time off added. These times are no longer bookable.'));
    }

    public function cancel(DoctorTimeOff $period)
    {
        DB::transaction(function () use ($period) {
            Doctor::lockForUpdate()->findOrFail($period->doctor_id);
            $period = DoctorTimeOff::lockForUpdate()->findOrFail($period->id);
            if ($period->cancelled_at === null) {
                $period->update(['cancelled_at' => now()]);
                AuditLog::record('doctor_time_off.cancelled', $period);
            }
        });

        return to_route('time-off.index')->with('success', __('Time off cancelled. Availability follows the regular schedule.'));
    }
}
