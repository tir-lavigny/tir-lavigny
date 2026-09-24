<?php

namespace Tests\Feature;

use App\Models\OperatingDay;
use App\Models\Season;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get('back-office/dashboard');
        $response->assertRedirect('/login');
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get('back-office/dashboard');
        $response->assertStatus(200);
    }

    public function test_daily_sales_is_empty_when_no_current_season()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Season::factory()->create([
            'start_date' => now()->subYear(),
            'end_date' => now()->subMonth(),
        ]);

        $this->get('back-office/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->component('backOffice/Dashboard')
                ->where('dailySales', [])
            );
    }

    public function test_daily_sales_returns_cash_and_twint_totals_per_day()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $season = Season::factory()->create([
            'start_date' => now()->startOfMonth(),
            'end_date' => now()->endOfMonth(),
        ]);

        OperatingDay::factory()->create([
            'season_id' => $season->id,
            'date' => now()->startOfMonth(),
            'total_cash' => 130,
            'total_twint' => 70,
        ]);

        OperatingDay::factory()->create([
            'season_id' => $season->id,
            'date' => now()->addDay(),
            'total_cash' => 200,
            'total_twint' => 80,
        ]);

        $this->get('back-office/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->component('backOffice/Dashboard')
                ->where('dailySales', fn ($sales) => $sales->count() === 2
                    && $sales[0]['cash'] == 130
                    && $sales[0]['twint'] == 70
                    && $sales[1]['cash'] == 200
                    && $sales[1]['twint'] == 80
                )
            );
    }

    public function test_daily_sales_ignores_operating_days_from_other_seasons()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $currentSeason = Season::factory()->create([
            'start_date' => now()->startOfMonth(),
            'end_date' => now()->endOfMonth(),
        ]);

        $otherSeason = Season::factory()->create([
            'start_date' => now()->subYear()->startOfMonth(),
            'end_date' => now()->subYear()->endOfMonth(),
        ]);

        OperatingDay::factory()->create([
            'season_id' => $currentSeason->id,
            'date' => now()->startOfMonth(),
            'total_cash' => 100,
            'total_twint' => 50,
        ]);

        OperatingDay::factory()->create([
            'season_id' => $otherSeason->id,
            'date' => now()->subYear()->startOfMonth(),
            'total_cash' => 999,
            'total_twint' => 999,
        ]);

        $this->get('back-office/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->component('backOffice/Dashboard')
                ->where('dailySales', fn ($sales) => $sales->count() === 1
                    && $sales[0]['cash'] == 100
                    && $sales[0]['twint'] == 50
                )
            );
    }
}
