<?php

namespace Tests\Unit\Http\Requests\BackOffice;

use App\Http\Requests\BackOffice\StoreMemberRequest;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class StoreMemberRequestTest extends TestCase
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

    private function passes(array $data): bool
    {
        $request = new StoreMemberRequest;
        $request->setUserResolver(fn () => $this->user);

        return Validator::make($data, $request->rules())->passes();
    }

    private function fails(array $data): bool
    {
        $request = new StoreMemberRequest;
        $request->setUserResolver(fn () => $this->user);

        return Validator::make($data, $request->rules())->fails();
    }

    private function failsFor(User $user, array $data): bool
    {
        $request = new StoreMemberRequest;
        $request->setUserResolver(fn () => $user);

        return Validator::make($data, $request->rules())->fails();
    }

    private function memberManagerWithoutSensitivePermission(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo('manage members');

        return $user;
    }

    private function validData(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'email' => 'jean.dupont@example.com',
            'is_swiss_citizen' => false,
            'tel' => '+41 21 123 45 67',
            'street' => 'Rue du Marché 1',
            'npa' => '1003',
            'city' => 'Lausanne',
            'canton' => 'VD',
            'country' => 'Suisse',
            'status' => 'active',
        ], $overrides);
    }

    public function test_authorize_passes_with_permission(): void
    {
        $request = new StoreMemberRequest;
        $request->setUserResolver(fn () => $this->user);

        $this->assertTrue($request->authorize());
    }

    public function test_authorize_fails_without_permission(): void
    {
        $request = new StoreMemberRequest;
        $request->setUserResolver(fn () => User::factory()->create());

        $this->assertFalse($request->authorize());
    }

    public function test_passes_with_valid_data(): void
    {
        $this->assertTrue($this->passes($this->validData()));
    }

    public function test_passes_with_valid_avs(): void
    {
        $this->assertTrue($this->passes($this->validData([
            'is_swiss_citizen' => true,
            'avs' => '756.1234.5678.90',
        ])));
    }

    public function test_fails_with_invalid_avs(): void
    {
        $this->assertTrue($this->fails($this->validData([
            'avs' => '1234',
        ])));
    }

    public function test_fails_with_missing_avs_for_swiss_citizen(): void
    {
        $this->assertTrue($this->fails($this->validData([
            'is_swiss_citizen' => true,
        ])));
    }

    public function test_fails_without_avs_for_swiss_citizen_without_sensitive_permission(): void
    {
        $this->assertTrue($this->failsFor($this->memberManagerWithoutSensitivePermission(), $this->validData([
            'is_swiss_citizen' => true,
        ])));
    }

    public function test_accepts_avs_without_sensitive_permission(): void
    {
        $this->assertFalse($this->failsFor($this->memberManagerWithoutSensitivePermission(), $this->validData([
            'is_swiss_citizen' => true,
            'avs' => '756.1234.5678.90',
        ])));
    }

    public function test_fails_without_required_contact_fields(): void
    {
        $this->assertTrue($this->fails([
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'status' => 'active',
        ]));
    }

    public function test_passes_without_avs_when_not_swiss_citizen(): void
    {
        $this->assertTrue($this->passes($this->validData([
            'is_swiss_citizen' => false,
        ])));
    }

    public function test_fails_with_too_long_tel(): void
    {
        $this->assertTrue($this->fails($this->validData([
            'tel' => str_repeat('1', 21),
        ])));
    }

    public function test_fails_with_too_long_npa(): void
    {
        $this->assertTrue($this->fails($this->validData([
            'npa' => str_repeat('1', 11),
        ])));
    }

    public function test_fails_with_too_long_country(): void
    {
        $this->assertTrue($this->fails($this->validData([
            'country' => str_repeat('a', 101),
        ])));
    }
}
