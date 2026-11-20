<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $dbOk = true;
        try {
            DB::select('SELECT 1');
        } catch (\Throwable) {
            $dbOk = false;
        }

        return response()->json([
            'status' => $dbOk ? 'ok' : 'degraded',
            'service' => 'cambodia-game-topup-api',
            'environment' => app()->environment(),
            'database' => $dbOk ? 'connected' : 'error',
            'timestamp' => now()->toIso8601String(),
        ], $dbOk ? 200 : 503);
    }
}
