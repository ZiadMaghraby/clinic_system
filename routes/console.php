<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

Artisan::command('clinic:admin {email} {--name=Clinic Administrator}', function () {
    $data = ['email' => strtolower($this->argument('email')), 'name' => $this->option('name'), 'password' => $this->secret('Choose a strong password (minimum 12 characters)')];
    $validator = Validator::make($data, ['email' => 'required|email|unique:users', 'name' => 'required|string|max:100', 'password' => ['required', Password::min(12)->letters()->numbers()]]);
    if ($validator->fails()) {
        foreach ($validator->errors()->all() as $error) {
            $this->error($error);
        }

        return 1;
    }
    $user = User::create($data);
    $user->forceFill(['role' => 'admin', 'is_admin' => true, 'email_verified_at' => now()])->save();
    $this->info('Administrator created. Store the password securely.');

    return 0;
})->purpose('Create an administrator without default credentials');

Artisan::command('clinic:preflight', function () {
    $checks = [
        'Production environment' => app()->isProduction(),
        'Debug disabled' => ! config('app.debug'),
        'HTTPS application URL' => str_starts_with(config('app.url'), 'https://'),
        'Secure session cookies' => (bool) config('session.secure'),
        'Encrypted sessions' => (bool) config('session.encrypt'),
        'Email delivery configured' => ! in_array(config('mail.default'), ['log', 'array']),
        'Application key present' => filled(config('app.key')),
        'Clinic phone configured' => filled(config('clinic.phone')),
        'Clinic address configured' => filled(config('clinic.address')),
        'Administrator exists' => User::where('role', 'admin')->where('is_active', true)->exists(),
        'No demo accounts' => ! User::where('email', 'like', '%@clinic.example')->exists(),
    ];
    foreach ($checks as $label => $passed) {
        $this->line(($passed ? 'PASS ' : 'FAIL ').$label);
    }
    $this->comment('Also verify backups, restore, SMTP delivery, access reviews, monitoring, and local healthcare requirements before real patient use.');

    return in_array(false, $checks, true) ? 1 : 0;
})->purpose('Check launch configuration without changing data');
