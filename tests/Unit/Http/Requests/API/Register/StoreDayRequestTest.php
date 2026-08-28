<?php

namespace Tests\Unit\Http\Requests\API\Register;

use App\Http\Requests\API\Register\StoreDayRequest;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class StoreDayRequestTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->user = User::factory()->create();
        $this->user->assignRole('admin');
    }

    private function makeRequest(array $data): StoreDayRequest
    {
        $request = new StoreDayRequest;
        $request->setUserResolver(fn () => $this->user);
        $request->merge($data);

        return $request;
    }

    private function passes(array $data): bool
    {
        request()->merge($data);
        $request = $this->makeRequest($data);
        $validator = Validator::make($request->all(), $request->rules());

        return $validator->passes();
    }

    private function fails(array $data): bool
    {
        request()->merge($data);
        $request = $this->makeRequest($data);
        $validator = Validator::make($request->all(), $request->rules());

        return $validator->fails();
    }

    private function createSeason(): int
    {
        return DB::table('seasons')->insertGetId([
            'name' => 'Test Season',
            'start_date' => Carbon::now()->startOfYear()->toDateString(),
            'end_date' => Carbon::now()->endOfYear()->toDateString(),
            'initial_balance' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_authorize_passes_with_permission(): void
    {
        $request = new StoreDayRequest;
        $request->setUserResolver(fn () => $this->user);

        $this->assertTrue($request->authorize());
    }

    public function test_authorize_fails_without_permission(): void
    {
        $user = User::factory()->create();
        $request = new StoreDayRequest;
        $request->setUserResolver(fn () => $user);

        $this->assertFalse($request->authorize());
    }

    public function test_season_id_is_required(): void
    {
        $this->assertTrue($this->fails([]));
    }

    public function test_season_id_must_exist(): void
    {
        $this->assertTrue($this->fails(['season_id' => 9999]));
    }

    public function test_passes_with_valid_season_id(): void
    {
        $seasonId = $this->createSeason();

        $this->assertTrue($this->passes(['season_id' => $seasonId]));
    }

    public function test_fails_when_open_day_already_exists(): void
    {
        $seasonId = $this->createSeason();

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

        $this->assertTrue($this->fails(['season_id' => $seasonId]));
    }

    public function test_fails_when_date_is_duplicate(): void
    {
        $seasonId = $this->createSeason();

        DB::table('operating_days')->insert([
            'date' => Carbon::now()->toDateString(),
            'started_at' => Carbon::now(),
            'season_id' => $seasonId,
            'total_cash' => 0,
            'total_twint' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertTrue($this->fails(['season_id' => $seasonId]));
    }
}
