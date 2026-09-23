<?php

declare(strict_types=1);

namespace App\Http\Controllers\Internal;

use App\Services\Inventory\ExpiredReservationReleaser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

class InventoryExpiryCronController extends Controller
{
    public function __invoke(Request $request, ExpiredReservationReleaser $releaser): JsonResponse
    {
        $expected = (string) config('store.cron_secret', '');
        $provided = $this->extractSecret($request);

        if ($expected === '' || $provided === '' || ! hash_equals($expected, $provided)) {
            return response()->json(['ok' => false], 403);
        }

        try {
            $result = $releaser->release();

            return response()->json([
                'ok' => true,
                'started_at' => $result['started_at'],
                'finished_at' => $result['finished_at'],
                'reservations_examined' => $result['reservations_examined'],
                'reservations_released' => $result['reservations_released'],
            ]);
        } catch (\Throwable) {
            Log::error('inventory.reservations.release_failed', [
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
