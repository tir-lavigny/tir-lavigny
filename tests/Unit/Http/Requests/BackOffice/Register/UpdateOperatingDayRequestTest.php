<?php

namespace Tests\Unit\Http\Requests\BackOffice\Register;

use App\Http\Requests\BackOffice\Register\UpdateOperatingDayRequest;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class UpdateOperatingDayRequestTest extends TestCase
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

    private function makeRequest(array $data): UpdateOperatingDayRequest
    {
        $request = new UpdateOperatingDayRequest;
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
        $request = new UpdateOperatingDayRequest;
        $request->setUserResolver(fn () => $this->user);

        $this->assertTrue($request->authorize());
    }

    public function test_authorize_fails_without_permission(): void
    {
        $user = User::factory()->create();
        $request = new UpdateOperatingDayRequest;
        $request->setUserResolver(fn () => $user);

        $this->assertFalse($request->authorize());
    }

    public function test_cash_remark_must_be_string(): void
    {
        $this->assertTrue($this->fails(['cash_remark' => 123]));
    }

    public function test_cash_remark_max_length(): void
    {
        $this->assertTrue($this->fails(['cash_remark' => str_repeat('a', 5001)]));
    }

    public function test_cash_remark_is_nullable(): void
    {
        $this->assertTrue($this->passes([]));
    }

    public function test_closing_balance_must_be_numeric(): void
    {
        $this->assertTrue($this->fails(['closing_balance' => 'abc']));
    }

    public function test_closing_balance_must_be_non_negative(): void
    {
        $this->assertTrue($this->fails(['closing_balance' => -1]));
    }

    public function test_closing_balance_accepts_zero(): void
    {
        $this->assertTrue($this->passes(['closing_balance' => 0]));
    }

    public function test_closing_balance_is_nullable(): void
    {
        $this->assertTrue($this->passes([]));
    }

    public function test_passes_with_valid_data(): void
    {
        $this->assertTrue($this->passes([
            'cash_remark' => 'All good',
            'closing_balance' => 500.00,
        ]));
    }
}
