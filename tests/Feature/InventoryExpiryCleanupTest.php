<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Inventory\InventoryReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Concerns\CreatesCatalogueFixtures;
use Tests\TestCase;

class InventoryExpiryCleanupTest extends TestCase
{
    use RefreshDatabase;
    use CreatesCatalogueFixtures;

    public function test_expired_reservation_is_released_and_cleanup_is_idempotent(): void
    {
        $product = $this->makeProduct([], 1);
        $service = app(InventoryReservationService::class);

        $reservation = $service->reserveItem($product, null, 1, null, 'sess-expiry', 30);
        $reservation->update(['expires_at' => now()->subMinutes(5)]);

        $inventory = $product->inventory()->first();
        $this->assertSame(1, (int) $inventory->quantity_reserved);

        Artisan::call('inventory:release-expired');
        $inventory->refresh();
        $this->assertSame(0, (int) $inventory->quantity_reserved);
        $this->assertSame(1, (int) $inventory->quantity_on_hand);
        $this->assertSame('RELEASED', $reservation->fresh()->status);

        Artisan::call('inventory:release-expired');
        $inventory->refresh();
        $this->assertSame(0, (int) $inventory->quantity_reserved);
        $this->assertSame(1, (int) $inventory->quantity_on_hand);
    }
}
