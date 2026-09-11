<?php

namespace Tests\Unit\Http\Requests\BackOffice;

use App\Http\Requests\BackOffice\StoreEventRequest;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class StoreEventRequestTest extends TestCase
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

    private function makeRequest(array $data): StoreEventRequest
    {
        $request = new StoreEventRequest;
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

    public function test_title_is_required(): void
    {
        $this->assertTrue($this->fails([]));
    }

    public function test_start_datetime_is_required(): void
    {
        $this->assertTrue($this->fails(['title' => 'Test Event']));
    }

    public function test_valid_data_passes(): void
    {
        $this->assertTrue($this->passes([
            'title' => 'Test Event',
            'start_datetime' => '2099-01-01 09:00:00',
        ]));
    }

    public function test_end_datetime_must_be_after_start_datetime(): void
    {
        $this->assertTrue($this->fails([
            'title' => 'Test Event',
            'start_datetime' => '2099-01-01 09:00:00',
            'end_datetime' => '2099-01-01 08:00:00',
        ]));
    }

    public function test_end_datetime_can_be_null(): void
    {
        $this->assertTrue($this->passes([
            'title' => 'Test Event',
            'start_datetime' => '2099-01-01 09:00:00',
            'end_datetime' => null,
        ]));
    }

    public function test_authorize_requires_create_event_permission(): void
    {
        $request = new StoreEventRequest;
        $request->setUserResolver(fn () => $this->user);

        $this->assertTrue($request->authorize());
    }

    public function test_authorize_rejects_user_without_permission(): void
    {
        $user = User::factory()->create();
        $request = new StoreEventRequest;
        $request->setUserResolver(fn () => $user);

        $this->assertFalse($request->authorize());
    }
}
