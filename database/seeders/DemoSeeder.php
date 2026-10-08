<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Demo data is forbidden outside local/testing.');
        }
        if (User::exists()) {
            throw new \RuntimeException('Demo seeding requires an empty database.');
        }
        $password = Hash::make('LocalPreview2026!');
        foreach (['admin' => 'Maya Hassan', 'receptionist' => 'Nour Adel', 'doctor' => 'Dr. Omar Farouk', 'patient' => 'Sara Khaled'] as $role => $name) {
            $user = User::create(['name' => $name, 'email' => $role.'@clinic.example', 'password' => $password, 'phone' => '01000000000']);
            $user->forceFill(['role' => $role, 'is_admin' => $role === 'admin', 'email_verified_at' => now()])->save();
        }
        $doctor = Doctor::create(['name' => 'Dr. Omar Farouk', 'specialization' => 'General Medicine', 'email' => 'doctor@clinic.example', 'user_id' => User::where('role', 'doctor')->first()->id, 'consultation_fee' => 350, 'working_days' => [0, 1, 2, 3, 4, 5, 6], 'starts_at' => '09:00', 'ends_at' => '17:00', 'slot_minutes' => 30, 'status' => 'active']);
        Doctor::create(['name' => 'Dr. Lina Samir', 'specialization' => 'Family Medicine', 'email' => 'lina@clinic.example', 'consultation_fee' => 400, 'working_days' => [0, 1, 2, 3, 4], 'starts_at' => '10:00', 'ends_at' => '18:00', 'slot_minutes' => 30, 'status' => 'active']);
        foreach (['Sara Khaled', 'Adam Mostafa', 'Layla Ahmed', 'Youssef Nabil', 'Salma Tarek', 'Kareem Ali'] as $i => $name) {
            $patient = $i === 0 ? User::where('role', 'patient')->first() : User::create(['name' => $name, 'email' => 'patient'.$i.'@clinic.example', 'password' => $password, 'phone' => '0100000000'.$i]);
            $patient->forceFill(['email_verified_at' => now()])->save();
            $date = now(config('clinic.timezone'))->addDay()->toDateString();
            $time = sprintf('%02d:00', 10 + $i);
            $a = Appointment::create(['patient_id' => $patient->id, 'doctor_id' => $doctor->id, 'appointment_date' => $date, 'appointment_time' => $time, 'status' => $i % 2 ? 'pending' : 'confirmed', 'slot_key' => $doctor->id.'|'.$date.'|'.$time]);
            Invoice::create(['appointment_id' => $a->id, 'amount' => 350, 'status' => 'unpaid']);
        }
        $this->command?->info('Local demo ready. Accounts: admin / receptionist / doctor / patient @clinic.example. Password: LocalPreview2026!');
    }
}
