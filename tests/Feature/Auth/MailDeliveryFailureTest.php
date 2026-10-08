<?php

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Symfony\Component\Mailer\Exception\TransportException;

test('registration survives a mail outage without granting verified access', function () {
    Notification::shouldReceive('send')->once()->andThrow(new TransportException('Private SMTP diagnostic'));

    $this->post('/register', [
        'name' => 'New Patient', 'email' => 'new@example.test',
        'password' => 'StrongPassword2026', 'password_confirmation' => 'StrongPassword2026',
    ])->assertRedirect(route('verification.notice'))->assertSessionHasErrors('email');

    $this->assertAuthenticated();
    $this->assertDatabaseCount('users', 1);
    expect(User::first()->hasVerifiedEmail())->toBeFalse();
    $this->get('/verify-email')->assertOk()->assertDontSee('Private SMTP diagnostic');
    $this->get('/dashboard')->assertRedirect(route('verification.notice'));
});

test('verification resend reports an outage and can be retried after recovery', function () {
    $user = User::factory()->unverified()->create();
    Notification::shouldReceive('send')->once()->andThrow(new TransportException('Private SMTP diagnostic'));
    $this->actingAs($user)->from('/verify-email')->post('/email/verification-notification')
        ->assertRedirect('/verify-email')->assertSessionHasErrors('email')->assertSessionMissing('status');
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();

    Notification::fake();
    $this->post('/email/verification-notification')->assertSessionHas('status', 'verification-link-sent');
    Notification::assertSentTo($user, VerifyEmail::class);
});

test('password reset mail outages preserve the address and show a safe error', function () {
    Password::shouldReceive('sendResetLink')->once()->andThrow(new TransportException('Private SMTP diagnostic'));
    $this->from('/forgot-password')->post('/forgot-password', ['email' => 'patient@example.test'])
        ->assertRedirect('/forgot-password')->assertSessionHasErrors('email')
        ->assertSessionHasInput('email', 'patient@example.test')->assertSessionMissing('status');
    $this->get('/forgot-password')->assertOk()->assertDontSee('Private SMTP diagnostic');
});
