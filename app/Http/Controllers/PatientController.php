<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PatientController extends Controller
{
    public function show(User $patient)
    {
        abort_unless($patient->role === 'patient', 404);
        $appointments = $patient->appointmentsAsPatient()->with(['doctor', 'invoice'])->orderByDesc('appointment_date')->orderByDesc('appointment_time')->paginate(12);
        $total = $patient->appointmentsAsPatient()->count();
        $completed = $patient->appointmentsAsPatient()->where('status', 'completed')->count();
        $outstanding = Invoice::where('status', 'unpaid')->whereHas('appointment', fn ($q) => $q->where('patient_id', $patient->id)->where('status', '!=', 'cancelled'))->sum('amount');
        AuditLog::record('patient.viewed', $patient);

        return view('clinic.patient', compact('patient', 'appointments', 'total', 'completed', 'outstanding'));
    }

    public function update(Request $request, User $patient)
    {
        abort_unless($patient->role === 'patient', 404);
        // Portal email is an identity credential and remains under the patient's verified account flow.
        $data = $request->validate(['name' => 'required|string|max:100', 'phone' => 'required|string|max:30', 'date_of_birth' => 'nullable|date_format:Y-m-d|before_or_equal:today']);
        DB::transaction(function () use ($patient, $data) {
            $patient->update($data);
            AuditLog::record('patient.updated', $patient);
        });

        return to_route('patients.show', $patient)->with('success', __('Patient details updated.'));
    }
}
