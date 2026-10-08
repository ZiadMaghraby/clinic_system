<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\ClinicalNote;
use App\Models\Doctor;
use App\Models\Invoice;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class ClinicController extends Controller
{
    private function appointmentsFor(User $user)
    {
        return Appointment::query()
            ->when($user->role === 'patient', fn ($q) => $q->where('patient_id', $user->id))
            ->when($user->role === 'doctor', fn ($q) => $q->where('doctor_id', $user->doctor?->id ?? 0));
    }

    private function authorizeAppointment(Appointment $appointment): void
    {
        abort_unless($this->appointmentsFor(auth()->user())->whereKey($appointment->id)->exists(), 403);
    }

    public function dashboard()
    {
        $query = $this->appointmentsFor(auth()->user());
        $today = now(config('clinic.timezone'))->toDateString();
        $upcoming = (clone $query)->with(['doctor', 'patient'])->whereDate('appointment_date', '>=', $today)
            ->whereIn('status', ['pending', 'confirmed'])->orderBy('appointment_date')->orderBy('appointment_time')->limit(6)->get();
        $stats = [
            'Today' => (clone $query)->whereDate('appointment_date', $today)->where('status', '!=', 'cancelled')->count(),
            'Awaiting confirmation' => (clone $query)->where('status', 'pending')->count(),
            'Completed visits' => (clone $query)->where('status', 'completed')->count(),
            'Care team' => Doctor::where('status', 'active')->count(),
        ];

        return view('clinic.dashboard', compact('upcoming', 'stats', 'today'));
    }

    public function appointments(Request $request)
    {
        $filters = $request->validate(['q' => 'nullable|string|max:100', 'date' => 'nullable|date_format:Y-m-d', 'status' => ['nullable', Rule::in(['pending', 'confirmed', 'completed', 'cancelled'])]]);
        $appointments = $this->appointmentsFor($request->user())->with(['patient', 'doctor'])
            ->when($filters['q'] ?? null, fn ($q, $search) => $q->whereHas('patient', fn ($p) => $p->where('name', 'like', '%'.$search.'%')))
            ->when($filters['date'] ?? null, fn ($q, $date) => $q->whereDate('appointment_date', $date))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('appointment_date')->orderBy('appointment_time')->paginate(15)->withQueryString();

        return view('clinic.appointments', compact('appointments'));
    }

    public function createAppointment(Request $request, BookingService $booking)
    {
        $request->validate(['doctor_id' => 'nullable|integer|exists:doctors,id', 'date' => 'nullable|date_format:Y-m-d|after_or_equal:today|before_or_equal:'.now(config('clinic.timezone'))->addDays(config('clinic.booking_days'))->toDateString()]);
        $doctors = Doctor::where('status', 'active')->orderBy('name')->get();
        $doctor = $doctors->firstWhere('id', $request->integer('doctor_id')) ?? $doctors->first();
        $date = $request->input('date', now(config('clinic.timezone'))->toDateString());
        $slots = $doctor ? $booking->slots($doctor, $date) : [];
        $patients = $request->user()->isStaff() ? User::where('role', 'patient')->where('is_active', true)->orderBy('name')->get(['id', 'name', 'email']) : collect();

        return view('clinic.book', compact('doctors', 'doctor', 'date', 'slots', 'patients'));
    }

    public function storeAppointment(Request $request, BookingService $booking)
    {
        $data = $request->validate([
            'doctor_id' => 'required|integer|exists:doctors,id',
            'patient_id' => $request->user()->role === 'patient' ? 'nullable' : 'required|integer|exists:users,id',
            'appointment_date' => 'required|date_format:Y-m-d|after_or_equal:today|before_or_equal:'.now(config('clinic.timezone'))->addDays(config('clinic.booking_days'))->toDateString(),
            'appointment_time' => 'required|date_format:H:i', 'reason' => 'nullable|string|max:2000',
        ]);
        if ($request->user()->role === 'patient') {
            $data['patient_id'] = $request->user()->id;
        }
        $appointment = $booking->book($data, $request->user());

        return to_route('appointments.show', $appointment)->with('success', __('Appointment requested. The clinic will confirm your visit.'));
    }

    public function showAppointment(Appointment $appointment)
    {
        $this->authorizeAppointment($appointment);
        $appointment->load(['patient', 'doctor', 'invoice']);
        $note = in_array(auth()->user()->role, ['admin', 'doctor']) ? $appointment->clinicalNote : null;
        if ($note) {
            AuditLog::record('clinical_note.viewed', $note);
        }

        return view('clinic.appointment', compact('appointment', 'note'));
    }

    public function updateAppointment(Request $request, Appointment $appointment, BookingService $booking)
    {
        $this->authorizeAppointment($appointment);
        $data = $request->validate(['status' => ['required', Rule::in(['confirmed', 'completed', 'cancelled'])]]);
        abort_if($request->user()->role === 'receptionist' && $data['status'] === 'completed', 403);
        $booking->transition($appointment, $data['status'], $request->user());

        return back()->with('success', __('Appointment updated.'));
    }

    public function saveNote(Request $request, Appointment $appointment)
    {
        $this->authorizeAppointment($appointment);
        $data = $request->validate(['notes' => 'required|string|max:15000']);
        DB::transaction(function () use ($data, $appointment, $request) {
            $appointment = Appointment::lockForUpdate()->findOrFail($appointment->id);
            abort_if($appointment->status === 'cancelled', 422);
            $note = ClinicalNote::updateOrCreate(['appointment_id' => $appointment->id], ['notes' => $data['notes'], 'author_id' => $request->user()->id]);
            AuditLog::record('clinical_note.saved', $note);
        });

        return back()->with('success', __('Clinical note saved securely.'));
    }

    public function reschedule(Request $request, Appointment $appointment, BookingService $booking)
    {
        $this->authorizeAppointment($appointment);
        $data = $request->validate(['appointment_date' => 'required|date_format:Y-m-d|after_or_equal:today|before_or_equal:'.now(config('clinic.timezone'))->addDays(config('clinic.booking_days'))->toDateString(), 'appointment_time' => 'required|date_format:H:i']);
        $booking->reschedule($appointment, $data);

        return back()->with('success', __('Appointment rescheduled. Please confirm the new time.'));
    }

    public function patients(Request $request)
    {
        $request->validate(['q' => 'nullable|string|max:100']);
        $patients = User::where('role', 'patient')->when($request->filled('q'), fn ($q) => $q->where(function ($q) use ($request) {
            $q->where('name', 'like', '%'.$request->q.'%')->orWhere('phone', 'like', '%'.$request->q.'%')->orWhere('email', 'like', '%'.$request->q.'%');
        }))->withCount('appointmentsAsPatient')->orderBy('name')->paginate(15)->withQueryString();

        return view('clinic.patients', compact('patients'));
    }

    public function storePatient(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:100', 'email' => 'required|email|max:255|unique:users', 'phone' => 'required|string|max:30', 'date_of_birth' => 'nullable|date|before_or_equal:today']);
        // Reception never chooses or sees the patient's password; patient uses the reset flow to claim access.
        DB::transaction(function () use ($data) {
            $patient = User::create([...$data, 'password' => Hash::make(bin2hex(random_bytes(32)))]);
            AuditLog::record('patient.created', $patient);
        });

        return back()->with('success', __('Patient added. They can use Forgot password to activate portal access.'));
    }

    public function doctors()
    {
        return view('clinic.doctors', ['doctors' => Doctor::orderBy('name')->get()]);
    }

    public function saveDoctor(Request $request, ?Doctor $doctor = null)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100', 'email' => 'required|email|max:255', 'specialization' => 'required|string|max:100',
            'consultation_fee' => 'required|numeric|min:0|max:999999', 'status' => ['required', Rule::in(['active', 'inactive'])],
            'starts_at' => 'required|date_format:H:i', 'ends_at' => 'required|date_format:H:i|after:starts_at',
            'slot_minutes' => ['required', Rule::in([15, 20, 30, 60])], 'working_days' => 'required|array|min:1|max:7',
            'working_days.*' => 'integer|between:0,6|distinct',
        ]);
        DB::transaction(function () use ($data, $doctor) {
            if ($doctor?->exists) {
                $doctor = Doctor::lockForUpdate()->findOrFail($doctor->id);
            }
            $doctor ??= new Doctor;
            $doctor->fill($data)->save();
            AuditLog::record('doctor.saved', $doctor);
        });

        return back()->with('success', __('Doctor details saved. Existing appointments remain unchanged.'));
    }

    public function invoices()
    {
        $invoices = Invoice::with(['appointment.patient', 'appointment.doctor'])
            ->when(auth()->user()->role === 'patient', fn ($q) => $q->whereHas('appointment', fn ($a) => $a->where('patient_id', auth()->id())))
            ->latest()->paginate(15);

        return view('clinic.invoices', compact('invoices'));
    }

    public function showInvoice(Invoice $invoice)
    {
        abort_if(auth()->user()->role === 'patient' && $invoice->appointment->patient_id !== auth()->id(), 403);

        return view('clinic.invoice', compact('invoice'));
    }

    public function payInvoice(Request $request, Invoice $invoice)
    {
        $data = $request->validate(['payment_method' => ['required', Rule::in(['cash', 'card', 'transfer'])]]);
        DB::transaction(function () use ($invoice, $data) {
            // Lock ordering matches cancellation, avoiding payment/cancellation races.
            $appointment = Appointment::lockForUpdate()->findOrFail($invoice->appointment_id);
            $invoice = Invoice::lockForUpdate()->findOrFail($invoice->id);
            if ($invoice->status !== 'unpaid' || $appointment->status === 'cancelled') {
                throw ValidationException::withMessages(['payment' => __('Only unpaid, active invoices can be recorded as paid.')]);
            }
            $invoice->update([...$data, 'status' => 'paid', 'paid_at' => now()]);
            AuditLog::record('invoice.paid', $invoice);
        });

        return back()->with('success', __('Payment recorded.'));
    }

    public function team()
    {
        return view('clinic.team', ['users' => User::whereIn('role', ['admin', 'doctor', 'receptionist'])->with('doctor')->orderBy('name')->get(), 'doctors' => Doctor::whereNull('user_id')->get()]);
    }

    public function storeStaff(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:100', 'email' => 'required|email|max:255|unique:users',
            'role' => ['required', Rule::in(['doctor', 'receptionist'])], 'doctor_id' => 'required_if:role,doctor|nullable|integer|exists:doctors,id']);
        DB::transaction(function () use ($data) {
            $doctor = $data['role'] === 'doctor' ? Doctor::lockForUpdate()->findOrFail($data['doctor_id']) : null;
            if ($doctor?->user_id) {
                throw ValidationException::withMessages(['doctor_id' => __('This doctor already has an account.')]);
            }
            $user = User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => Hash::make(bin2hex(random_bytes(32)))]);
            $user->forceFill(['role' => $data['role']])->save();
            $doctor?->update(['user_id' => $user->id]);
            AuditLog::record('staff.created', $user);
        });

        return back()->with('success', __('Staff account created. Use Forgot password to set a password and verify the email.'));
    }

    public function toggleStaff(User $user)
    {
        abort_if($user->role === 'admin' || $user->id === auth()->id() || $user->role === 'patient', 403);
        $user->forceFill(['is_active' => ! $user->is_active])->save();
        AuditLog::record('staff.access_changed', $user);

        return back()->with('success', __('Staff access updated.'));
    }

    public function audit()
    {
        return view('clinic.audit', ['logs' => AuditLog::with('user')->latest('id')->paginate(25)]);
    }
}
