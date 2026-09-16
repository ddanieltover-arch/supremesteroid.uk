<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class HealthCheckController extends Controller
{
    public function check(): JsonResponse
    {
        $dbStatus = 'healthy';
        try {
            DB::connection()->getPdo();
        } catch (\Throwable $e) {
            $dbStatus = 'unreachable';
            report($e);
        }

        $healthy = $dbStatus === 'healthy';

        return response()->json([
            'status' => $healthy ? 'operational' : 'degraded',
        ], $healthy ? 200 : 503);
    }
}
