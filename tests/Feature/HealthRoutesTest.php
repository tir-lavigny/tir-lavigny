<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class HealthRoutesTest extends TestCase
{
    public function test_up_returns_no_content(): void
    {
        $this->get('/up')->assertNoContent();
    }

    public function test_health_returns_ok(): void
    {
        $this->getJson('/health')
            ->assertOk()
            ->assertJson(['status' => 'ok']);
    }

    public function test_health_alias_for_register_pwa(): void
    {
        $this->head('/api/health')->assertOk();
        $this->getJson('/api/health')->assertOk()->assertJson(['status' => 'ok']);
    }

    public function test_health_hides_failure_details_and_logs(): void
    {
        config(['database.connections.broken' => [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => '1',
            'database' => 'nope',
            'username' => 'nope',
            'password' => 'nope',
        ]]);
        config(['database.default' => 'broken']);

        Log::shouldReceive('error')
            ->once()
            ->withArgs(fn ($message, $context) => $message === 'Health check failed' && isset($context['database']));

        $response = $this->getJson('/health');

        $response->assertServiceUnavailable()->assertExactJson(['status' => 'degraded']);
        $this->assertStringNotContainsString('nope', $response->getContent());
    }
}
