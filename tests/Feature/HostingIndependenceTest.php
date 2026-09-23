<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\Api\HealthCheckController;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HostingIndependenceTest extends TestCase
{
    public function test_health_response_does_not_name_a_hosting_provider(): void
    {
        $this->getJson('/api/health')
            ->assertOk()
            ->assertJson(['status' => 'operational'])
            ->assertJsonMissingPath('deployment_target')
            ->assertDontSee('Vercel')
            ->assertDontSee('Render')
            ->assertDontSee('Laravel Cloud')
            ->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_health_reports_missing_application_key_without_leaking_config(): void
    {
        config(['app.key' => '']);

        $response = app(HealthCheckController::class)->check();

        $this->assertSame(503, $response->getStatusCode());
        $this->assertSame('misconfigured', $response->getData()->status);
        $this->assertStringNotContainsString('APP_KEY', $response->getContent());
    }

    public function test_health_reports_database_outage_without_connection_details(): void
    {
        config(['database.default' => 'missing-connection']);
        DB::purge();

        $response = app(HealthCheckController::class)->check();

        $this->assertSame(503, $response->getStatusCode());
        $this->assertSame('unavailable', $response->getData()->status);
        $this->assertStringNotContainsString('SQLSTATE', $response->getContent());
        $this->assertStringNotContainsString('password', $response->getContent());
    }

    public function test_inventory_release_is_scheduled_without_a_provider_specific_runner(): void
    {
        $commands = collect(app(Schedule::class)->events())
            ->map(fn ($event) => (string) $event->command)
            ->implode(' ');

        $this->assertStringContainsString('inventory:release-expired', $commands);
        $this->assertStringNotContainsString('vercel', strtolower($commands));
    }
}
