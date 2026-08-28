<?php

namespace Tests\Unit\Rules\Register;

use App\Rules\Register\DateNotDuplicate;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DateNotDuplicateTest extends TestCase
{
    use RefreshDatabase;

    private DateNotDuplicate $rule;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rule = new DateNotDuplicate;
    }

    private function createSeason(string $name, string $start, string $end): int
    {
        return DB::table('seasons')->insertGetId([
            'name' => $name,
            'start_date' => $start,
            'end_date' => $end,
            'initial_balance' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_passes_when_date_is_unique_for_season(): void
    {
        $seasonId = $this->createSeason(
            'Test Season',
            Carbon::now()->startOfYear()->toDateString(),
            Carbon::now()->endOfYear()->toDateString(),
        );

        request()->merge(['season_id' => $seasonId]);

        $fail = fn ($msg) => $this->fail('Validation should pass but got: '.$msg);
        $this->rule->validate('date', Carbon::now()->toDateString(), $fail);
        $this->assertTrue(true);
    }

    public function test_passes_when_date_exists_in_different_season(): void
    {
        $season1 = $this->createSeason(
            'Season 1',
            Carbon::now()->startOfYear()->toDateString(),
            Carbon::now()->addMonths(6)->toDateString(),
        );

        $season2 = $this->createSeason(
            'Season 2',
            Carbon::now()->addMonths(7)->toDateString(),
            Carbon::now()->endOfYear()->toDateString(),
        );

        $testDate = Carbon::now()->addMonths(6)->toDateString();

        DB::table('operating_days')->insert([
            'date' => $testDate,
            'started_at' => now(),
            'season_id' => $season1,
            'total_cash' => 0,
            'total_twint' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        request()->merge(['season_id' => $season2]);

        $fail = fn ($msg) => $this->fail('Validation should pass but got: '.$msg);
        $this->rule->validate('date', $testDate, $fail);
        $this->assertTrue(true);
    }

    public function test_fails_when_date_is_duplicate_for_season(): void
    {
        $seasonId = $this->createSeason(
            'Test Season',
            Carbon::now()->startOfYear()->toDateString(),
            Carbon::now()->endOfYear()->toDateString(),
        );

        $testDate = Carbon::now()->toDateString();

        DB::table('operating_days')->insert([
            'date' => $testDate,
            'started_at' => now(),
            'season_id' => $seasonId,
            'total_cash' => 0,
            'total_twint' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        request()->merge(['season_id' => $seasonId]);

        $failed = false;
        $fail = function ($msg) use (&$failed) {
            $failed = true;
            $this->assertStringContainsString('already exists', $msg);
        };

        $this->rule->validate('date', $testDate, $fail);
        $this->assertTrue($failed, 'Validation should fail for duplicate date');
    }

    public function test_skips_validation_when_no_season_id_provided(): void
    {
        request()->merge([]);

        $fail = fn ($msg) => $this->fail('Validation should be skipped when no season_id: '.$msg);
        $this->rule->validate('date', Carbon::now()->toDateString(), $fail);
        $this->assertTrue(true);
    }
}
