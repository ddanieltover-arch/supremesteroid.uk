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
        if (! $this->criticalConfigurationPresent()) {
            return response()->json(['status' => 'misconfigured'], 503);
        }

        try {
            DB::connection()->getPdo();
            DB::select('select 1');
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'status' => 'unavailable',
                'error' => $e::class.': '.(preg_replace('/(?:postgres|postgresql|mysql|pgsql):\/\/\S+/i', '[redacted-url]', $e->getMessage()) ?? $e->getMessage()),
            ], 503);
        }

        return response()->json(['status' => 'operational'], 200);
    }

    protected function criticalConfigurationPresent(): bool
    {
        $key = config('app.key');

        return is_string($key) && $key !== '' && ! str_contains($key, 'GENERATE');
    }
}
