<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StaticPagesController;
use Illuminate\Support\Facades\Route;

// ==================================
// Public / Welcome Route
// ==================================
Route::get('/', function () {
    return view('welcome');
});

// ==================================
// Authenticated Dashboard
// ==================================
Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// ==================================
// Admin Routes
// ==================================
Route::prefix('admin')->middleware(['auth', 'verified'])->group(function () {

    // Admin Dashboard 
    Route::get('/admindashboard', [AdminController::class, 'index'])->name('adminDashboard');

    // Doctors
    Route::get('/doctors', [AdminController::class, 'doctors'])->name('admin.doctors');
    Route::get('/doctors/create', [AdminController::class, 'createDoctor'])->name('admin.doctors.create');
    Route::post('/doctors/store', [AdminController::class, 'storeDoctor'])->name('admin.doctors.store');
    Route::get('/doctors/{id}', [AdminController::class, 'doctorShow'])->name('admin.doctor.show');

    // Patients
    Route::get('/patients', [AdminController::class, 'patients'])->name('admin.patients');
    Route::get('/patients/{id}/appointments', [AdminController::class, 'patientAppointments'])->name('admin.patient.appointments');
    Route::get('/patients/{id}/medical-history', [AdminController::class, 'patientMedicalHistory'])->name('admin.patient.medical-history');

    // Invoices
    Route::get('/invoices', [PatientController::class, 'AdminInvoices'])->name('admin.invoices');

    // Appointments
    Route::get('/appointments', [AdminController::class, 'appointments'])->name('admin.appointments');
    Route::get('/appointments/{appointment}/edit', [AdminController::class, 'appointmentsEdit'])->name('admin.appointments.edit');
    Route::put('/appointments/{appointment}', [AdminController::class, 'appointmentsUpdate'])->name('admin.appointments.update');
    Route::delete('/appointments/{appointment}', [AdminController::class, 'appointmentsDestroy'])->name('admin.appointments.destroy');

// ✅ Confirm appointment
Route::post('/appointments/{appointment}/confirm', [AdminController::class, 'appointmentsConfirm'])
    ->name('admin.appointments.confirm'); // keep as is

// ✅ Cancel appointment
Route::patch('/appointments/{appointment}/cancel', [AdminController::class, 'appointmentsCancel'])
    ->name('admin.appointments.cancel');

});

// ==================================
// Patients Routes
// ==================================
Route::prefix('patient')->middleware(['auth', 'verified'])->group(function () {

    // Dashboard 
    Route::get('/dashboard', [PatientController::class, 'index'])->name('patientDashboard');

    // Doctors
    Route::get('/doctors', [PatientController::class, 'doctors'])->name('patient.doctors');
    Route::get('/doctors/{id}', [PatientController::class, 'doctorShow'])->name('patient.doctor.show');

    // Invoices
    Route::get('/invoices', [PatientController::class, 'userInvoices'])->name('patient.invoices');

    // Appointments
    Route::get('/appointments', [PatientController::class, 'appointments'])->name('patient.appointments');
    Route::get('/appointments/available', [PatientController::class, 'availableSlots'])->name('patient.appointments.available');
    Route::post('/appointments', [PatientController::class, 'appointmentsStore'])->name('patient.appointments.store');
    Route::delete('/appointments/{appointment}', [PatientController::class, 'appointmentsCancel'])->name('patient.appointments.cancel');

    // Medical History
    Route::get('/medical_history', [PatientController::class, 'medicalHis'])->name('patient.medical_history');
});

// ==================================
// Profile Routes
// ==================================
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// ==================================
// Auth routes
// ==================================
require __DIR__.'/auth.php';

// ==================================
// Static Pages Routes
// ==================================
Route::group([], function () {
    // About Section Routes
    Route::get('/about', [StaticPagesController::class, 'about'])->name('about');
    Route::get('/mission', [StaticPagesController::class, 'mission'])->name('mission');
    Route::get('/team', [StaticPagesController::class, 'team'])->name('team');
    Route::get('/careers', [StaticPagesController::class, 'careers'])->name('careers');

    // Services Section Routes
    Route::get('/services', [StaticPagesController::class, 'services'])->name('services');
    Route::get('/services/appointments', [PatientController::class, 'availableSlots'])->name('services.appointments');
    Route::get('/services/emergency', [StaticPagesController::class, 'emergency'])->name('services.emergency');
    Route::get('/services/specialties', [StaticPagesController::class, 'specialties'])->name('services.specialties');
    Route::get('/services/insurance', [StaticPagesController::class, 'insurance'])->name('services.insurance');

    // Quick Links Routes
    Route::get('/patients', [StaticPagesController::class, 'patientPortal'])->name('patients.portal');
    Route::get('/faq', [StaticPagesController::class, 'faq'])->name('faq');
    Route::get('/privacy', [StaticPagesController::class, 'privacy'])->name('privacy');
    Route::get('/terms', [StaticPagesController::class, 'terms'])->name('terms');
    Route::get('/contact', [StaticPagesController::class, 'contact'])->name('contact');
});

