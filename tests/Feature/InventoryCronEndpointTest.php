<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryCronEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_cron_request_is_rejected(): void
    {
        $this->getJson('/internal/cron/release-expired-inventory')->assertForbidden();
    }

    public function test_incorrect_cron_secret_is_rejected_without_internal_detail(): void
    {
        config(['store.cron_secret' => 'test-cron-secret']);

        $this->withHeaders([
            'Authorization' => 'Bearer wrong-secret',
            'Accept' => 'application/json',
        ])->getJson('/internal/cron/release-expired-inventory')
            ->assertForbidden()
            ->assertJson(['ok' => false])
            ->assertJsonMissingPath('exception');
    }

    public function test_authorized_cron_request_runs_cleanup(): void
    {
        config(['store.cron_secret' => 'test-cron-secret']);

        $this->withHeaders([
            'Authorization' => 'Bearer test-cron-secret',
            'Accept' => 'application/json',
        ])->getJson('/internal/cron/release-expired-inventory')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure([
                'started_at',
                'finished_at',
                'reservations_examined',
                'reservations_released',
            ]);
    }

    public function test_readiness_endpoint_rejects_missing_secret(): void
    {
        $this->getJson('/internal/readiness')
            ->assertForbidden()
            ->assertJsonMissingPath('checks');
    }
}
