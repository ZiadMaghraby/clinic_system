<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    public function up(): void
    {
        // These credentials were shipped in the old public seeders. Never permit them on an upgraded installation.
        $defaults = ['admin@hospital.com' => 'admin123', 'a.ahmed@gmail.com' => 'ahmed123', 'sarah.s@gmail.com' => 'sara123', 'j.john@gmail.com' => 'john345', 'patient.test@gmail.com' => 'noor555'];
        foreach ($defaults as $email => $password) {
            $user = DB::table('users')->where('email', $email)->first();
            if ($user && Hash::check($password, $user->password)) {
                DB::table('users')->where('id', $user->id)->update(['is_active' => false, 'password' => Hash::make(bin2hex(random_bytes(32))), 'remember_token' => null]);
                DB::table('sessions')->where('user_id', $user->id)->delete();
            }
        }
    }

    public function down(): void
    { /* Do not restore publicly known credentials on rollback. */
    }
};
