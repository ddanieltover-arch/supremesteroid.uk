<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CheckoutSession;
use App\Services\Inventory\InventoryReservationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReleaseExpiredInventoryReservationsCommand extends Command
{
    protected $signature = 'inventory:release-expired';

    protected $description = 'Release timed-out inventory reservations and expire abandoned checkout sessions.';

    public function handle(InventoryReservationService $reservationService): int
    {
        $startedAt = now()->toIso8601String();
        $result = $reservationService->releaseExpiredReservations();

        $expiredSessions = CheckoutSession::query()
            ->where('status', CheckoutSession::STATUS_OPEN)
            ->where('expires_at', '<', now())
            ->update(['status' => CheckoutSession::STATUS_EXPIRED]);

        Log::info('inventory.reservations.released', [
            'started_at' => $startedAt,
            'finished_at' => now()->toIso8601String(),
            'reservations_examined' => $result['examined'],
            'reservations_released' => $result['released'],
            'checkout_sessions_expired' => $expiredSessions,
            'errors' => 0,
        ]);

        $this->info('Examined '.$result['examined'].' expired inventory reservations.');
        $this->info('Released '.$result['released'].' expired inventory reservations.');
        $this->info("Expired {$expiredSessions} checkout sessions.");

        return self::SUCCESS;
    }
}
