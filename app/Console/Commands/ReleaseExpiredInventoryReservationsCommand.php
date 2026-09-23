<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Inventory\ExpiredReservationReleaser;
use Illuminate\Console\Command;

class ReleaseExpiredInventoryReservationsCommand extends Command
{
    protected $signature = 'inventory:release-expired';

    protected $description = 'Release timed-out inventory reservations and expire abandoned checkout sessions.';

    public function handle(ExpiredReservationReleaser $releaser): int
    {
        $result = $releaser->release();

        $this->info('Examined '.$result['reservations_examined'].' expired inventory reservations.');
        $this->info('Released '.$result['reservations_released'].' expired inventory reservations.');
        $this->info('Expired '.$result['checkout_sessions_expired'].' checkout sessions.');

        return self::SUCCESS;
    }
}
