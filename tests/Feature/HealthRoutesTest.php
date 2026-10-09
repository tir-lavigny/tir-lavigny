<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthRoutesTest extends TestCase
{
    public function test_up_returns_no_content(): void
    {
        $this->get('/up')->assertNoContent();
    }

    public function test_health_returns_ok_with_checks(): void
    {
        $this->getJson('/health')
            ->assertOk()
            ->assertJsonStructure([
                'status',
                'checks' => ['database', 'storage'],
            ])
            ->assertJson(['status' => 'ok', 'checks' => ['database' => 'ok', 'storage' => 'ok']]);
    }

    public function test_health_alias_for_register_pwa(): void
    {
        $this->head('/api/health')->assertOk();
        $this->getJson('/api/health')->assertOk()->assertJson(['status' => 'ok']);
    }
}
