<?php

namespace App\Http\Controllers;

use App\Services\LaunchReadiness;

class ReadinessController extends Controller
{
    public function __invoke(LaunchReadiness $readiness)
    {
        $ready = $readiness->databaseReady();

        return response()->json(['status' => $ready ? 'ready' : 'unavailable'], $ready ? 200 : 503)
            ->header('Cache-Control', 'no-store');
    }
}
