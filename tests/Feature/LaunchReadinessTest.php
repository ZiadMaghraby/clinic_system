<?php

use App\Models\User;
use App\Services\LaunchReadiness;
use Illuminate\Support\Facades\DB;

test('readiness is stateless and returns a generic unavailable response on database failure', function () {
    DB::shouldReceive('select')->with('SELECT 1')->once()->andThrow(new RuntimeException('secret connection password'));
    $this->get('/ready')->assertStatus(503)->assertExactJson(['status' => 'unavailable'])
        ->assertHeader('Cache-Control', 'no-store, private')->assertDontSee('secret');
});

test('readiness succeeds without authentication or starting a session', function () {
    $response = $this->get('/ready')->assertOk()->assertExactJson(['status' => 'ready']);
    expect($response->headers->getCookies())->toBe([]);
});

test('preflight exposes pending migrations and requires a verified administrator', function () {
    $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => null]);
    $checks = app(LaunchReadiness::class)->checks();
    expect($checks['All migrations applied'])->toBeTrue()->and($checks['Verified active administrator exists'])->toBeFalse();
    $admin->forceFill(['email_verified_at' => now()])->save();
    DB::table('migrations')->where('migration', '2026_10_10_000001_create_doctor_time_offs_table')->delete();
    $checks = app(LaunchReadiness::class)->checks();
    expect($checks['All migrations applied'])->toBeFalse()->and($checks['Verified active administrator exists'])->toBeTrue();
});

test('preflight rejects mail transports that fall back to logging or cycle', function () {
    config(['mail.default' => 'failover']);
    expect(app(LaunchReadiness::class)->checks()['Delivery-capable mail transports configured'])->toBeFalse();
    config(['mail.mailers.failover.mailers' => ['smtp', 'failover']]);
    expect(app(LaunchReadiness::class)->checks()['Delivery-capable mail transports configured'])->toBeFalse();
    config(['mail.mailers.failover.mailers' => ['smtp']]);
    expect(app(LaunchReadiness::class)->checks()['Delivery-capable mail transports configured'])->toBeTrue();
});

test('preflight rejects placeholder email and insecure session settings', function () {
    config(['mail.from.address' => 'hello@example.com', 'session.http_only' => false, 'session.same_site' => 'none', 'clinic.timezone' => 'Invalid/Timezone']);
    $checks = app(LaunchReadiness::class)->checks();
    expect($checks['Non-placeholder sender address'])->toBeFalse()
        ->and($checks['HTTP-only session cookies'])->toBeFalse()
        ->and($checks['Session same-site protection'])->toBeFalse()
        ->and($checks['Clinic and application timezones agree'])->toBeFalse();
});

test('preflight reports a missing database without exposing connection secrets', function () {
    DB::shouldReceive('select')->with('SELECT 1')->once()->andThrow(new RuntimeException('secret connection password'));
    $checks = app(LaunchReadiness::class)->checks();
    expect($checks['Database reachable'])->toBeFalse()->and($checks['All migrations applied'])->toBeFalse();
    expect(json_encode($checks))->not->toContain('secret');
});

test('preflight JSON is suitable for deployment scripts and exits unsuccessfully for unsafe configuration', function () {
    $this->artisan('clinic:preflight --json')->expectsOutputToContain('"ready": false')->assertExitCode(1);
});
