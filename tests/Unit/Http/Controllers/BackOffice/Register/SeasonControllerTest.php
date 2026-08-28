<?php

namespace Tests\Unit\Http\Controllers\BackOffice\Register;

use App\Http\Controllers\BackOffice\Register\SeasonController;
use App\Models\Season;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Response;
use Tests\TestCase;

class SeasonControllerTest extends TestCase
{
    use RefreshDatabase;

    private SeasonController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new SeasonController;
    }

    public function test_index_returns_inertia_response(): void
    {
        Season::factory()->count(3)->create();

        $response = $this->controller->index();

        $this->assertInstanceOf(Response::class, $response);
    }

    public function test_create_returns_inertia_response(): void
    {
        $response = $this->controller->create();

        $this->assertInstanceOf(Response::class, $response);
    }

    public function test_show_returns_inertia_response(): void
    {
        $season = Season::factory()->create();

        $response = $this->controller->show($season);

        $this->assertInstanceOf(Response::class, $response);
    }

    public function test_season_model_can_be_created(): void
    {
        $season = Season::create([
            'name' => '2026-2027',
            'start_date' => Carbon::now()->startOfYear(),
            'end_date' => Carbon::now()->endOfYear(),
            'initial_balance' => 500.00,
        ]);

        $this->assertDatabaseHas('seasons', ['name' => '2026-2027']);
    }

    public function test_season_model_can_be_updated(): void
    {
        $season = Season::factory()->create(['name' => 'Old']);

        $season->update(['name' => 'New']);

        $this->assertDatabaseHas('seasons', ['id' => $season->id, 'name' => 'New']);
    }

    public function test_season_model_soft_deletes(): void
    {
        $season = Season::factory()->create();

        $season->delete();

        $this->assertSoftDeleted('seasons', ['id' => $season->id]);
    }
}
