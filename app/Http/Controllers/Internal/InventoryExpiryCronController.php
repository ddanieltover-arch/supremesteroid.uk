<?php

declare(strict_types=1);

namespace App\Http\Controllers\Internal;

use App\Models\CheckoutSession;
use App\Services\Inventory\InventoryReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

class InventoryExpiryCronController extends Controller
{
    public function __invoke(Request $request, InventoryReservationService $reservations): JsonResponse
    {
        $expected = (string) config('store.cron_secret', '');
        $provided = $this->extractSecret($request);

        if ($expected === '' || $provided === '' || ! hash_equals($expected, $provided)) {
            return response()->json(['ok' => false], 403);
        }

        $startedAt = now()->toIso8601String();

        try {
            $result = $reservations->releaseExpiredReservations();
            $expiredSessions = CheckoutSession::query()
                ->where('status', CheckoutSession::STATUS_OPEN)
                ->where('expires_at', '<', now())
                ->update(['status' => CheckoutSession::STATUS_EXPIRED]);
            $finishedAt = now()->toIso8601String();

            Log::info('inventory.reservations.released', [
                'started_at' => $startedAt,
                'finished_at' => $finishedAt,
                'reservations_examined' => $result['examined'],
                'reservations_released' => $result['released'],
                'checkout_sessions_expired' => $expiredSessions,
                'errors' => 0,
            ]);

            return response()->json([
                'ok' => true,
                'started_at' => $startedAt,
                'finished_at' => $finishedAt,
                'reservations_examined' => $result['examined'],
                'reservations_released' => $result['released'],
            ]);
        } catch (\Throwable) {
            Log::error('inventory.reservations.release_failed', [
                'started_at' => $startedAt,
                'finished_at' => now()->toIso8601String(),
                'errors' => 1,
            ]);

            return response()->json(['ok' => false], 500);
        }
    }

    protected function extractSecret(Request $request): string
    {
        $authorization = (string) $request->header('Authorization', '');
        if (str_starts_with($authorization, 'Bearer ')) {
            return substr($authorization, 7);
        }

        return (string) $request->header('X-Cron-Secret', '');
    }
}
