<?php

namespace Tests\Unit\Http\Requests\BackOffice\Register;

use App\Http\Requests\BackOffice\Register\StoreSeasonRequest;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class StoreSeasonRequestTest extends TestCase
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

    private function makeRequest(array $data): StoreSeasonRequest
    {
        $request = new StoreSeasonRequest;
        $request->setUserResolver(fn () => $this->user);
        $request->merge($data);

        return $request;
    }

    private function passes(array $data): bool
    {
        $request = $this->makeRequest($data);
        $validator = Validator::make($request->all(), $request->rules());

        return $validator->passes();
    }

    private function fails(array $data): bool
    {
        $request = $this->makeRequest($data);
        $validator = Validator::make($request->all(), $request->rules());

        return $validator->fails();
    }

    public function test_authorize_passes_with_permission(): void
    {
        $request = new StoreSeasonRequest;
        $request->setUserResolver(fn () => $this->user);

        $this->assertTrue($request->authorize());
    }

    public function test_authorize_fails_without_permission(): void
    {
        $user = User::factory()->create();
        $request = new StoreSeasonRequest;
        $request->setUserResolver(fn () => $user);

        $this->assertFalse($request->authorize());
    }

    public function test_name_is_required(): void
    {
        $this->assertTrue($this->fails([
            'start_date' => Carbon::now()->startOfYear()->toDateString(),
            'end_date' => Carbon::now()->endOfYear()->toDateString(),
            'initial_balance' => 0,
        ]));
    }

    public function test_start_date_is_required(): void
    {
        $this->assertTrue($this->fails([
            'name' => 'Season 2026',
            'end_date' => Carbon::now()->endOfYear()->toDateString(),
            'initial_balance' => 0,
        ]));
    }

    public function test_end_date_is_required(): void
    {
        $this->assertTrue($this->fails([
            'name' => 'Season 2026',
            'start_date' => Carbon::now()->startOfYear()->toDateString(),
            'initial_balance' => 0,
        ]));
    }

    public function test_end_date_must_be_after_or_equal_start_date(): void
    {
        $this->assertTrue($this->fails([
            'name' => 'Season 2026',
            'start_date' => Carbon::now()->endOfYear()->toDateString(),
            'end_date' => Carbon::now()->startOfYear()->toDateString(),
            'initial_balance' => 0,
        ]));
    }

    public function test_initial_balance_is_required(): void
    {
        $this->assertTrue($this->fails([
            'name' => 'Season 2026',
            'start_date' => Carbon::now()->startOfYear()->toDateString(),
            'end_date' => Carbon::now()->endOfYear()->toDateString(),
        ]));
    }

    public function test_initial_balance_must_be_non_negative(): void
    {
        $this->assertTrue($this->fails([
            'name' => 'Season 2026',
            'start_date' => Carbon::now()->startOfYear()->toDateString(),
            'end_date' => Carbon::now()->endOfYear()->toDateString(),
            'initial_balance' => -1,
        ]));
    }

    public function test_end_balance_must_be_numeric(): void
    {
        $this->assertTrue($this->fails([
            'name' => 'Season 2026',
            'start_date' => Carbon::now()->startOfYear()->toDateString(),
            'end_date' => Carbon::now()->endOfYear()->toDateString(),
            'initial_balance' => 0,
            'end_balance' => 'abc',
        ]));
    }

    public function test_passes_with_valid_data(): void
    {
        $this->assertTrue($this->passes([
            'name' => 'Season 2026',
            'start_date' => Carbon::now()->startOfYear()->toDateString(),
            'end_date' => Carbon::now()->endOfYear()->toDateString(),
            'initial_balance' => 1000.00,
        ]));
    }
}
