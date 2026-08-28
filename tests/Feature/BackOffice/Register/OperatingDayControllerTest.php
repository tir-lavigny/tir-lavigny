<?php

namespace Tests\Feature\BackOffice\Register;

use App\Models\OperatingDay;
use App\Models\Season;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperatingDayControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
        $this->actingAs($this->admin);
    }

    public function test_index_returns_paginated_days(): void
    {
        $season = Season::factory()->create();
        OperatingDay::factory()->create(['season_id' => $season->id]);

        $response = $this->get(route('bo.register.operating-days.index'));

        $response->assertOk();
    }

    public function test_index_filters_by_season(): void
    {
        $season1 = Season::factory()->create();
        $season2 = Season::factory()->create();
        OperatingDay::factory()->create(['season_id' => $season1->id]);
        OperatingDay::factory()->create(['season_id' => $season2->id]);

        $response = $this->get(route('bo.register.operating-days.index', ['season_id' => $season1->id]));

        $response->assertOk();
    }

    public function test_show_returns_day_with_tickets(): void
    {
        $season = Season::factory()->create();
        $day = OperatingDay::factory()->create(['season_id' => $season->id]);
        Ticket::factory()->cash()->create(['operating_day_id' => $day->id]);

        $response = $this->get(route('bo.register.operating-days.show', $day));

        $response->assertOk();
    }

    public function test_update_modifies_cash_remark(): void
    {
        $season = Season::factory()->create();
        $day = OperatingDay::factory()->create([
            'season_id' => $season->id,
            'cash_remark' => null,
        ]);

        $response = $this->put(route('bo.register.operating-days.update', $day), [
            'cash_remark' => 'Short 5 CHF',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('operating_days', [
            'id' => $day->id,
            'cash_remark' => 'Short 5 CHF',
        ]);
    }

    public function test_update_modifies_closing_balance(): void
    {
        $season = Season::factory()->create();
        $day = OperatingDay::factory()->create([
            'season_id' => $season->id,
            'closing_balance' => null,
        ]);

        $response = $this->put(route('bo.register.operating-days.update', $day), [
            'closing_balance' => 250.00,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('operating_days', [
            'id' => $day->id,
            'closing_balance' => 250.00,
        ]);
    }

    public function test_update_requires_permission(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view register');
        $this->actingAs($user);

        $season = Season::factory()->create();
        $day = OperatingDay::factory()->create(['season_id' => $season->id]);

        $response = $this->put(route('bo.register.operating-days.update', $day), [
            'cash_remark' => 'Test',
        ]);

        $response->assertForbidden();
    }
}
