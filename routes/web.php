<?php

use App\Http\Controllers\ClinicController;
use App\Http\Controllers\DoctorTimeOffController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::view('/', 'clinic.home')->name('home');
Route::post('/language', function (Request $request) {
    $request->validate(['locale' => 'required|in:ar,en']);
    session(['locale' => $request->locale]);

    return back();
})->name('language');

Route::middleware(['auth', 'verified', 'role:admin,doctor,receptionist,patient'])->group(function () {
    Route::get('/dashboard', [ClinicController::class, 'dashboard'])->name('dashboard');
    Route::get('/time-off', [DoctorTimeOffController::class, 'index'])->middleware('role:admin')->name('time-off.index');
    Route::post('/time-off', [DoctorTimeOffController::class, 'store'])->middleware('role:admin')->name('time-off.store');
    Route::patch('/time-off/{period}/cancel', [DoctorTimeOffController::class, 'cancel'])->middleware('role:admin')->name('time-off.cancel');
    Route::get('/appointments', [ClinicController::class, 'appointments'])->name('appointments.index');
    Route::get('/appointments/create', [ClinicController::class, 'createAppointment'])->middleware('role:admin,receptionist,patient')->name('appointments.create');
    Route::post('/appointments', [ClinicController::class, 'storeAppointment'])->middleware(['role:admin,receptionist,patient', 'throttle:30,1'])->name('appointments.store');
    Route::get('/appointments/{appointment}', [ClinicController::class, 'showAppointment'])->name('appointments.show');
    Route::patch('/appointments/{appointment}', [ClinicController::class, 'updateAppointment'])->name('appointments.update');
    Route::patch('/appointments/{appointment}/reschedule', [ClinicController::class, 'reschedule'])->middleware('role:admin,receptionist')->name('appointments.reschedule');
    Route::post('/appointments/{appointment}/note', [ClinicController::class, 'saveNote'])->middleware('role:admin,doctor')->name('appointments.note');
    Route::get('/doctors', [ClinicController::class, 'doctors'])->name('doctors.index');
    Route::post('/doctors', [ClinicController::class, 'saveDoctor'])->middleware('role:admin')->name('doctors.store');
    Route::put('/doctors/{doctor}', [ClinicController::class, 'saveDoctor'])->middleware('role:admin')->name('doctors.update');
    Route::get('/patients', [ClinicController::class, 'patients'])->middleware('role:admin,receptionist')->name('patients.index');
    Route::post('/patients', [ClinicController::class, 'storePatient'])->middleware('role:admin,receptionist')->name('patients.store');
    Route::get('/patients/{patient}', [PatientController::class, 'show'])->middleware('role:admin,receptionist')->name('patients.show');
    Route::patch('/patients/{patient}', [PatientController::class, 'update'])->middleware('role:admin,receptionist')->name('patients.update');
    Route::get('/reports', [ReportsController::class, 'index'])->middleware('role:admin')->name('reports.index');
    Route::get('/reports/export', [ReportsController::class, 'export'])->middleware(['role:admin', 'throttle:10,1'])->name('reports.export');
    Route::get('/invoices', [ClinicController::class, 'invoices'])->middleware('role:admin,receptionist,patient')->name('invoices.index');
    Route::get('/invoices/{invoice}', [ClinicController::class, 'showInvoice'])->middleware('role:admin,receptionist,patient')->name('invoices.show');
    Route::patch('/invoices/{invoice}/payment', [ClinicController::class, 'payInvoice'])->middleware('role:admin,receptionist')->name('invoices.pay');
    Route::get('/team', [ClinicController::class, 'team'])->middleware('role:admin')->name('team.index');
    Route::post('/team', [ClinicController::class, 'storeStaff'])->middleware('role:admin')->name('team.store');
    Route::patch('/team/{user}', [ClinicController::class, 'toggleStaff'])->middleware('role:admin')->name('team.toggle');
    Route::get('/audit', [ClinicController::class, 'audit'])->middleware('role:admin')->name('audit.index');
    // Preserve existing bookmarks without exposing the legacy unprotected controllers.
    Route::redirect('/admin/admindashboard', '/dashboard')->middleware('role:admin')->name('adminDashboard');
    Route::redirect('/patient/dashboard', '/dashboard')->name('patientDashboard');
});
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});
require __DIR__.'/auth.php';
