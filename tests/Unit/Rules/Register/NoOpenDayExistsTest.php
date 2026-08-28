<?php

namespace Tests\Unit\Rules\Register;

use App\Rules\Register\NoOpenDayExists;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NoOpenDayExistsTest extends TestCase
{
    use RefreshDatabase;

    private NoOpenDayExists $rule;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rule = new NoOpenDayExists;
    }

    private function createSeason(string $start, string $end): int
    {
        return DB::table('seasons')->insertGetId([
            'name' => 'Test Season',
            'start_date' => $start,
            'end_date' => $end,
            'initial_balance' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_passes_when_no_operating_days_exist_for_season(): void
    {
        $seasonId = $this->createSeason(
            Carbon::now()->startOfYear()->toDateString(),
            Carbon::now()->endOfYear()->toDateString(),
        );

        $fail = fn ($msg) => $this->fail('Validation should pass but got: '.$msg);
        $this->rule->validate('season_id', $seasonId, $fail);
        $this->assertTrue(true);
    }

    public function test_passes_when_all_days_are_closed(): void
    {
        $seasonId = $this->createSeason(
            Carbon::now()->startOfYear()->toDateString(),
            Carbon::now()->endOfYear()->toDateString(),
        );

        DB::table('operating_days')->insert([
            'date' => Carbon::now()->toDateString(),
            'started_at' => Carbon::now(),
            'closed_at' => Carbon::now(),
            'season_id' => $seasonId,
            'total_cash' => 0,
            'total_twint' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $fail = fn ($msg) => $this->fail('Validation should pass but got: '.$msg);
        $this->rule->validate('season_id', $seasonId, $fail);
        $this->assertTrue(true);
    }

    public function test_fails_when_open_day_exists_for_season(): void
    {
        $seasonId = $this->createSeason(
            Carbon::now()->startOfYear()->toDateString(),
            Carbon::now()->endOfYear()->toDateString(),
        );

        DB::table('operating_days')->insert([
            'date' => Carbon::now()->toDateString(),
            'started_at' => Carbon::now(),
            'closed_at' => null,
            'season_id' => $seasonId,
            'total_cash' => 0,
            'total_twint' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $failed = false;
        $fail = function ($msg) use (&$failed) {
            $failed = true;
            $this->assertStringContainsString('already open', $msg);
        };

        $this->rule->validate('season_id', $seasonId, $fail);
        $this->assertTrue($failed, 'Validation should fail when open day exists');
    }

    public function test_fails_only_for_same_season(): void
    {
        $season1 = $this->createSeason(
            Carbon::now()->startOfYear()->toDateString(),
            Carbon::now()->addMonths(6)->toDateString(),
        );

        $season2 = $this->createSeason(
            Carbon::now()->addMonths(7)->toDateString(),
            Carbon::now()->endOfYear()->toDateString(),
        );

        DB::table('operating_days')->insert([
            'date' => Carbon::now()->addMonths(6)->toDateString(),
            'started_at' => Carbon::now(),
            'closed_at' => null,
            'season_id' => $season1,
            'total_cash' => 0,
            'total_twint' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $fail = fn ($msg) => $this->fail('Validation should pass for season 2 but got: '.$msg);
        $this->rule->validate('season_id', $season2, $fail);
        $this->assertTrue(true);
    }
}
