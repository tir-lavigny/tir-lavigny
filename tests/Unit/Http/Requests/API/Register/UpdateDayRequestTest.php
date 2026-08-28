<?php

namespace Tests\Unit\Http\Requests\API\Register;

use App\Http\Requests\API\Register\UpdateDayRequest;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class UpdateDayRequestTest extends TestCase
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

    private function makeRequest(array $data): UpdateDayRequest
    {
        $request = new UpdateDayRequest;
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

    public function test_authorize_passes_with_close_day_permission(): void
    {
        $this->user->givePermissionTo('close day');
        $request = new UpdateDayRequest;
        $request->setUserResolver(fn () => $this->user);
        $request->merge(['status' => 'closed']);

        $this->assertTrue($request->authorize());
    }

    public function test_authorize_passes_with_reopen_day_permission(): void
    {
        $this->user->givePermissionTo('reopen day');
        $request = new UpdateDayRequest;
        $request->setUserResolver(fn () => $this->user);
        $request->merge(['status' => 'open']);

        $this->assertTrue($request->authorize());
    }

    public function test_authorize_passes_with_add_cash_remark_permission(): void
    {
        $this->user->givePermissionTo('add cash remark');
        $request = new UpdateDayRequest;
        $request->setUserResolver(fn () => $this->user);
        $request->merge(['cash_remark' => 'Some remark']);

        $this->assertTrue($request->authorize());
    }

    public function test_status_must_be_valid(): void
    {
        $this->user->givePermissionTo('close day');
        $this->assertTrue($this->fails(['status' => 'invalid']));
    }

    public function test_status_accepts_open(): void
    {
        $this->user->givePermissionTo('reopen day');
        $this->assertTrue($this->passes(['status' => 'open']));
    }

    public function test_status_accepts_closed(): void
    {
        $this->user->givePermissionTo('close day');
        $this->assertTrue($this->passes(['status' => 'closed']));
    }

    public function test_closing_balance_must_be_numeric(): void
    {
        $this->user->givePermissionTo('add cash remark');
        $this->assertTrue($this->fails(['closing_balance' => 'abc']));
    }

    public function test_closing_balance_must_be_non_negative(): void
    {
        $this->user->givePermissionTo('add cash remark');
        $this->assertTrue($this->fails(['closing_balance' => -1]));
    }

    public function test_closing_balance_accepts_zero(): void
    {
        $this->user->givePermissionTo('add cash remark');
        $this->assertTrue($this->passes(['closing_balance' => 0]));
    }

    public function test_closing_balance_is_nullable(): void
    {
        $this->user->givePermissionTo('add cash remark');
        $this->assertTrue($this->passes([]));
    }

    public function test_cash_remark_max_length(): void
    {
        $this->user->givePermissionTo('add cash remark');
        $this->assertTrue($this->fails(['cash_remark' => str_repeat('a', 5001)]));
    }

    public function test_cash_remark_accepts_5000_chars(): void
    {
        $this->user->givePermissionTo('add cash remark');
        $this->assertTrue($this->passes(['cash_remark' => str_repeat('a', 5000)]));
    }
}
