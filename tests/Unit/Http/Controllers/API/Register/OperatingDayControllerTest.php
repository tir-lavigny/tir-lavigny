<?php

namespace Tests\Unit\Http\Controllers\API\Register;

use App\Http\Controllers\API\Register\OperatingDayController;
use App\Http\Requests\API\Register\StoreDayRequest;
use App\Http\Requests\API\Register\UpdateDayRequest;
use App\Models\OperatingDay;
use App\Models\Season;
use App\Models\Ticket;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class OperatingDayControllerTest extends TestCase
{
    use RefreshDatabase;

    private OperatingDayController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->controller = new OperatingDayController;
    }

    private function storeRequest(array $data = []): StoreDayRequest
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        $request = new StoreDayRequest;
        $request->merge($data);
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    private function updateRequest(array $data = []): UpdateDayRequest
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        $request = new UpdateDayRequest;
        $request->merge($data);
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    // --- store() ---

    public function test_store_creates_operating_day(): void
    {
        $season = Season::factory()->create([
            'start_date' => Carbon::now()->startOfYear(),
            'end_date' => Carbon::now()->endOfYear(),
        ]);

        $response = $this->controller->store($this->storeRequest([
            'season_id' => $season->id,
        ]));

        $this->assertEquals(201, $response->status());
        $this->assertDatabaseHas('operating_days', [
            'season_id' => $season->id,
            'closed_at' => null,
        ]);

        $day = OperatingDay::where('season_id', $season->id)->first();
        $this->assertEquals(Carbon::today()->format('Y-m-d'), $day->date->format('Y-m-d'));
    }

    public function test_store_sets_started_at_to_now(): void
    {
        $season = Season::factory()->create([
            'start_date' => Carbon::now()->startOfYear(),
            'end_date' => Carbon::now()->endOfYear(),
        ]);

        $before = Carbon::now();
        $this->controller->store($this->storeRequest([
            'season_id' => $season->id,
        ]));
        $after = Carbon::now();

        $day = OperatingDay::where('season_id', $season->id)->first();
        $startedAt = Carbon::parse($day->started_at);
        $this->assertTrue($startedAt->gte($before->subSecond()));
        $this->assertTrue($startedAt->lte($after->addSecond()));
    }

    // --- update() ---

    public function test_update_closes_open_day(): void
    {
        $season = Season::factory()->create([
            'start_date' => Carbon::now()->startOfYear(),
            'end_date' => Carbon::now()->endOfYear(),
        ]);
        $day = OperatingDay::factory()->create([
            'season_id' => $season->id,
            'closed_at' => null,
        ]);

        $response = $this->controller->update($this->updateRequest([
            'status' => 'closed',
            'closing_balance' => 150.00,
        ]), $day);

        $this->assertEquals(200, $response->status());
        $this->assertNotNull($day->fresh()->closed_at);
        $this->assertEquals('150.00', $day->fresh()->closing_balance);
    }

    public function test_update_closes_day_without_closing_balance(): void
    {
        $season = Season::factory()->create([
            'start_date' => Carbon::now()->startOfYear(),
            'end_date' => Carbon::now()->endOfYear(),
        ]);
        $day = OperatingDay::factory()->create([
            'season_id' => $season->id,
            'closed_at' => null,
        ]);

        $response = $this->controller->update($this->updateRequest([
            'status' => 'closed',
        ]), $day);

        $this->assertEquals(200, $response->status());
        $this->assertNotNull($day->fresh()->closed_at);
        $this->assertNull($day->fresh()->closing_balance);
    }

    public function test_update_reopens_closed_day(): void
    {
        $season = Season::factory()->create([
            'start_date' => Carbon::now()->startOfYear(),
            'end_date' => Carbon::now()->endOfYear(),
        ]);
        $day = OperatingDay::factory()->closed()->create([
            'season_id' => $season->id,
        ]);

        $response = $this->controller->update($this->updateRequest([
            'status' => 'open',
        ]), $day);

        $this->assertEquals(200, $response->status());
        $this->assertNull($day->fresh()->closed_at);
    }

    public function test_update_updates_cash_remark(): void
    {
        $season = Season::factory()->create([
            'start_date' => Carbon::now()->startOfYear(),
            'end_date' => Carbon::now()->endOfYear(),
        ]);
        $day = OperatingDay::factory()->create([
            'season_id' => $season->id,
            'cash_remark' => null,
        ]);

        $response = $this->controller->update($this->updateRequest([
            'cash_remark' => 'Short 2.50 CHF',
        ]), $day);

        $this->assertEquals(200, $response->status());
        $this->assertEquals('Short 2.50 CHF', $day->fresh()->cash_remark);
    }

    public function test_update_fails_to_close_already_closed_day(): void
    {
        $season = Season::factory()->create([
            'start_date' => Carbon::now()->startOfYear(),
            'end_date' => Carbon::now()->endOfYear(),
        ]);
        $day = OperatingDay::factory()->closed()->create([
            'season_id' => $season->id,
        ]);

        $response = $this->controller->update($this->updateRequest([
            'status' => 'closed',
        ]), $day);

        $this->assertEquals(422, $response->status());
    }

    // --- current() ---

    public function test_current_returns_open_day_for_today(): void
    {
        $season = Season::factory()->create([
            'start_date' => Carbon::now()->startOfYear(),
            'end_date' => Carbon::now()->endOfYear(),
        ]);
        $day = OperatingDay::create([
            'date' => Carbon::today(),
            'started_at' => Carbon::now(),
            'season_id' => $season->id,
            'closed_at' => null,
            'total_cash' => 0,
            'total_twint' => 0,
        ]);

        $request = Request::create('/api/register/operating-days/current', 'GET');
        $response = $this->controller->current($request);

        $this->assertEquals(200, $response->status());
    }

    public function test_current_returns_404_when_no_open_day(): void
    {
        $request = Request::create('/api/register/operating-days/current', 'GET');
        $response = $this->controller->current($request);

        $this->assertEquals(404, $response->status());
    }

    public function test_current_returns_404_when_today_is_closed(): void
    {
        $season = Season::factory()->create([
            'start_date' => Carbon::now()->startOfYear(),
            'end_date' => Carbon::now()->endOfYear(),
        ]);
        OperatingDay::create([
            'date' => Carbon::today(),
            'started_at' => Carbon::now()->subHours(8),
            'closed_at' => Carbon::now(),
            'season_id' => $season->id,
            'total_cash' => 0,
            'total_twint' => 0,
        ]);

        $request = Request::create('/api/register/operating-days/current', 'GET');
        $response = $this->controller->current($request);

        $this->assertEquals(404, $response->status());
    }

    public function test_current_eager_loads_tickets(): void
    {
        $season = Season::factory()->create([
            'start_date' => Carbon::now()->startOfYear(),
            'end_date' => Carbon::now()->endOfYear(),
        ]);
        $day = OperatingDay::create([
            'date' => Carbon::today(),
            'started_at' => Carbon::now(),
            'season_id' => $season->id,
            'closed_at' => null,
            'total_cash' => 0,
            'total_twint' => 0,
        ]);
        Ticket::factory()->cash()->create(['operating_day_id' => $day->id]);

        $request = Request::create('/api/register/operating-days/current', 'GET');
        $response = $this->controller->current($request);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->status());
        $this->assertCount(1, $data['data']['tickets']);
    }

    // --- show() ---

    public function test_show_returns_operating_day_with_tickets(): void
    {
        $season = Season::factory()->create([
            'start_date' => Carbon::now()->startOfYear(),
            'end_date' => Carbon::now()->endOfYear(),
        ]);
        $day = OperatingDay::factory()->create(['season_id' => $season->id]);
        Ticket::factory()->cash()->create(['operating_day_id' => $day->id]);

        $response = $this->controller->show($day);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->status());
        $this->assertCount(1, $data['data']['tickets']);
    }

    public function test_show_includes_refunded_tickets(): void
    {
        $season = Season::factory()->create([
            'start_date' => Carbon::now()->startOfYear(),
            'end_date' => Carbon::now()->endOfYear(),
        ]);
        $day = OperatingDay::factory()->create(['season_id' => $season->id]);
        $ticket = Ticket::factory()->cash()->create(['operating_day_id' => $day->id]);
        $ticket->delete();

        $response = $this->controller->show($day);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->status());
        $this->assertCount(1, $data['data']['tickets']);
    }
}
