<?php

use App\Models\User;
use App\Services\LaunchReadiness;
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

Artisan::command('clinic:preflight {--json : Emit machine-readable check results}', function () {
    $checks = app(LaunchReadiness::class)->checks();
    $passed = ! in_array(false, $checks, true);
    if ($this->option('json')) {
        $this->line(json_encode(['ready' => $passed, 'checks' => $checks], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    } else {
        foreach ($checks as $label => $result) {
            $this->line(($result ? 'PASS ' : 'FAIL ').$label);
        }
        $this->comment('Configuration checks do not prove email delivery, backup restoration or operational readiness. Verify these before real patient use.');
    }

    return $passed ? 0 : 1;
})->purpose('Check launch configuration without changing data');
