<?php

namespace Tests\Unit\Http\Controllers\BackOffice\Register;

use App\Http\Controllers\BackOffice\Register\OperatingDayController;
use App\Models\OperatingDay;
use App\Models\Season;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Inertia\Response;
use Tests\TestCase;

class OperatingDayControllerTest extends TestCase
{
    use RefreshDatabase;

    private OperatingDayController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new OperatingDayController;
    }

    public function test_index_returns_inertia_response(): void
    {
        $season = Season::factory()->create();
        OperatingDay::factory()->count(3)->create(['season_id' => $season->id]);

        $response = $this->controller->index(Request::create('/'));

        $this->assertInstanceOf(Response::class, $response);
    }

    public function test_index_with_season_filter_returns_inertia_response(): void
    {
        $season1 = Season::factory()->create();
        $season2 = Season::factory()->create();
        OperatingDay::factory()->create(['season_id' => $season1->id]);
        OperatingDay::factory()->create(['season_id' => $season2->id]);

        $request = Request::create('/', 'GET', ['season_id' => $season1->id]);
        $response = $this->controller->index($request);

        $this->assertInstanceOf(Response::class, $response);
    }

    public function test_show_returns_inertia_response(): void
    {
        $season = Season::factory()->create();
        $day = OperatingDay::factory()->create(['season_id' => $season->id]);

        $response = $this->controller->show($day);

        $this->assertInstanceOf(Response::class, $response);
    }

    public function test_show_with_tickets_returns_inertia_response(): void
    {
        $season = Season::factory()->create();
        $day = OperatingDay::factory()->create(['season_id' => $season->id]);
        Ticket::factory()->cash()->create(['operating_day_id' => $day->id]);

        $response = $this->controller->show($day);

        $this->assertInstanceOf(Response::class, $response);
    }

    public function test_operating_day_model_can_be_updated(): void
    {
        $season = Season::factory()->create();
        $day = OperatingDay::factory()->create([
            'season_id' => $season->id,
            'cash_remark' => null,
        ]);

        $day->update(['cash_remark' => 'Short 5 CHF']);

        $this->assertDatabaseHas('operating_days', [
            'id' => $day->id,
            'cash_remark' => 'Short 5 CHF',
        ]);
    }

    public function test_operating_day_model_update_closing_balance(): void
    {
        $season = Season::factory()->create();
        $day = OperatingDay::factory()->create([
            'season_id' => $season->id,
            'closing_balance' => null,
        ]);

        $day->update(['closing_balance' => 250.00]);

        $this->assertDatabaseHas('operating_days', [
            'id' => $day->id,
            'closing_balance' => 250.00,
        ]);
    }
}
