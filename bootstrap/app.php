<?php

use App\Http\Controllers\ReadinessController;
use App\Http\Middleware\ClinicContext;
use App\Http\Middleware\RequireRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Stateless probe remains available when database-backed sessions are unavailable.
            Route::get('/ready', ReadinessController::class)->name('readiness');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [ClinicContext::class]);
        $middleware->alias(['role' => RequireRole::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
