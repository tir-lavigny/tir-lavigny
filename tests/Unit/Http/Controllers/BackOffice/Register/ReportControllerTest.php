<?php

namespace Tests\Unit\Http\Controllers\BackOffice\Register;

use App\Http\Controllers\BackOffice\Register\ReportController;
use App\Models\OperatingDay;
use App\Models\Season;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Inertia\Response;
use Tests\TestCase;

class ReportControllerTest extends TestCase
{
    use RefreshDatabase;

    private ReportController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new ReportController;
    }

    public function test_day_detail_returns_inertia_response(): void
    {
        $season = Season::factory()->create();
        $day = OperatingDay::factory()->create(['season_id' => $season->id]);

        $response = $this->controller->dayDetail($day);

        $this->assertInstanceOf(Response::class, $response);
    }

    public function test_day_summary_returns_inertia_response(): void
    {
        $season = Season::factory()->create();
        $day = OperatingDay::factory()->create(['season_id' => $season->id]);

        $response = $this->controller->daySummary($day);

        $this->assertInstanceOf(Response::class, $response);
    }

    public function test_day_balance_returns_inertia_response(): void
    {
        $season = Season::factory()->create();
        $day = OperatingDay::factory()->create(['season_id' => $season->id]);

        $response = $this->controller->dayBalance($day);

        $this->assertInstanceOf(Response::class, $response);
    }

    public function test_period_returns_inertia_response(): void
    {
        $request = Request::create('/reports/period', 'GET', [
            'from' => Carbon::now()->subWeek()->toDateString(),
            'to' => Carbon::now()->toDateString(),
            'detail' => 'balance',
        ]);

        $response = $this->controller->period($request);

        $this->assertInstanceOf(Response::class, $response);
    }

    public function test_season_returns_inertia_response(): void
    {
        $season = Season::factory()->create();

        $response = $this->controller->season($season);

        $this->assertInstanceOf(Response::class, $response);
    }

    public function test_season_with_operating_days_returns_inertia_response(): void
    {
        $season = Season::factory()->create();
        OperatingDay::factory()->create(['season_id' => $season->id]);
        OperatingDay::factory()->create(['season_id' => $season->id]);

        $response = $this->controller->season($season);

        $this->assertInstanceOf(Response::class, $response);
    }
}
