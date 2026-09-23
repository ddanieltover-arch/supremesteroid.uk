<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\CheckoutSession;
use Illuminate\Support\Facades\Log;

class ExpiredReservationReleaser
{
    public function __construct(
        protected InventoryReservationService $reservations
    ) {}

    /**
     * Release expired inventory holds and expire abandoned checkout sessions.
     *
     * Safe to run repeatedly. Already released rows are ignored.
     *
     * @return array{started_at: string, finished_at: string, reservations_examined: int, reservations_released: int, checkout_sessions_expired: int, errors: int}
     */
    public function release(): array
    {
        $startedAt = now()->toIso8601String();
        $result = $this->reservations->releaseExpiredReservations();
        $expiredSessions = CheckoutSession::query()
            ->where('status', CheckoutSession::STATUS_OPEN)
            ->where('expires_at', '<', now())
            ->update(['status' => CheckoutSession::STATUS_EXPIRED]);

        $payload = [
            'started_at' => $startedAt,
            'finished_at' => now()->toIso8601String(),
            'reservations_examined' => $result['examined'],
            'reservations_released' => $result['released'],
            'checkout_sessions_expired' => $expiredSessions,
            'errors' => 0,
        ];

        Log::info('inventory.reservations.released', $payload);

        return $payload;
    }
}
