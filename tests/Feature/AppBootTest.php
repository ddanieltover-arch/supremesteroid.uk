<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class AppBootTest extends TestCase
{
    public function test_health_check_endpoint_returns_operational(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'operational',
            ])
            ->assertJsonMissingPath('database')
            ->assertDontSee('password', false);
    }
}
