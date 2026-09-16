<?php

declare(strict_types=1);

namespace App\Http\Controllers\Internal;

use App\Services\Operations\ReadinessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ReadinessController extends Controller
{
    public function __invoke(Request $request, ReadinessService $readiness): JsonResponse
    {
        $expected = (string) config('store.cron_secret', '');
        $provided = (string) $request->bearerToken();

        if ($expected === '' || $provided === '' || ! hash_equals($expected, $provided)) {
            return response()->json([
                'status' => 'NOT_READY',
                'message' => 'Unauthorized.',
            ], 403);
        }

        $report = $readiness->report();

        return response()->json($report, $report['status'] === 'READY' ? 200 : 503);
    }
}
