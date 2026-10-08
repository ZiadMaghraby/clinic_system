<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ClinicContext
{
    public function handle(Request $request, Closure $next)
    {
        app()->setLocale(in_array(session('locale'), ['en', 'ar']) ? session('locale') : config('app.locale'));
        if ($request->user() && ! $request->user()->is_active) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => __('Account unavailable. Contact the clinic.')]);
        }
        $response = $next($request);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        if ($request->user()) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }
}
