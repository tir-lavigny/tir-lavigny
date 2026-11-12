<?php

namespace Tests\Feature\BackOffice\Register;

use App\Models\OperatingDay;
use App\Models\Season;
use App\Models\Ticket;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportControllerTest extends TestCase
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

    public function test_index_returns_report_page(): void
    {
        Season::factory()->create();
        Season::factory()->create();

        $response = $this->get(route('bo.register.reports.index'));

        $response->assertOk();
    }

    public function test_day_detail_returns_report(): void
    {
        $season = Season::factory()->create();
        $day = OperatingDay::factory()->create(['season_id' => $season->id]);
        Ticket::factory()->cash()->create(['operating_day_id' => $day->id, 'total' => 25.00]);

        $response = $this->get(route('bo.register.reports.day-detail', $day));

        $response->assertOk();
    }

    public function test_day_summary_returns_report(): void
    {
        $season = Season::factory()->create();
        $day = OperatingDay::factory()->create(['season_id' => $season->id]);
        Ticket::factory()->cash()->create(['operating_day_id' => $day->id, 'total' => 30.00]);

        $response = $this->get(route('bo.register.reports.day-summary', $day));

        $response->assertOk();
    }

    public function test_period_returns_report(): void
    {
        $season = Season::factory()->create();
        OperatingDay::factory()->create([
            'season_id' => $season->id,
            'date' => Carbon::now()->subDays(5)->toDateString(),
        ]);
        OperatingDay::factory()->create([
            'season_id' => $season->id,
            'date' => Carbon::now()->subDays(3)->toDateString(),
        ]);

        $response = $this->get(route('bo.register.reports.period'), [
            'from' => Carbon::now()->subWeek()->toDateString(),
            'to' => Carbon::now()->toDateString(),
            'detail' => 'balance',
        ]);

        $response->assertOk();
    }

    public function test_season_returns_report(): void
    {
        $season = Season::factory()->create();
        OperatingDay::factory()->create(['season_id' => $season->id]);
        OperatingDay::factory()->create(['season_id' => $season->id]);

        $response = $this->get(route('bo.register.reports.season', $season));

        $response->assertOk();
    }

    public function test_reports_require_permission(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['access backoffice', 'view register']);
        $this->actingAs($user);

        $season = Season::factory()->create();
        $day = OperatingDay::factory()->create(['season_id' => $season->id]);

        $response = $this->get(route('bo.register.reports.day-detail', $day));

        $response->assertForbidden();
    }
}
