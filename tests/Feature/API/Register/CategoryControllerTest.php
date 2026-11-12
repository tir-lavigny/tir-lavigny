<?php

namespace Tests\Feature\API\Register;

use App\Models\Category;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\API\AuthTestHelper;
use Tests\TestCase;

class CategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private array $tokens;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->user = User::factory()->create();
        $this->user->assignRole('cashier');
        $this->tokens = AuthTestHelper::generateTokens($this->user);
    }

    protected function tearDown(): void
    {
        AuthTestHelper::clearUser($this->user);
        parent::tearDown();
    }

    private function authHeaders(): array
    {
        return ['Authorization' => 'Bearer '.$this->tokens['accessToken']];
    }

    public function test_index_returns_non_restricted_categories(): void
    {
        Category::factory()->create(['name' => 'Tir', 'is_restricted' => false]);
        Category::factory()->create(['name' => 'Bar', 'is_restricted' => false]);
        Category::factory()->create(['name' => 'Internal', 'is_restricted' => true]);

        $response = $this->getJson(route('api.register.categories.index'), $this->authHeaders());

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    public function test_index_excludes_soft_deleted_categories(): void
    {
        Category::factory()->create(['name' => 'Active', 'is_restricted' => false]);
        Category::factory()->create(['name' => 'Deleted', 'is_restricted' => false])->delete();

        $response = $this->getJson(route('api.register.categories.index'), $this->authHeaders());

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson(route('api.register.categories.index'));

        $response->assertUnauthorized();
    }

    public function test_index_requires_register_access(): void
    {
        $user = User::factory()->create();
        $user->assignRole('editor');
        $tokens = AuthTestHelper::generateTokens($user);

        $response = $this->getJson(route('api.register.categories.index'), [
            'Authorization' => 'Bearer '.$tokens['accessToken'],
        ]);

        $response->assertForbidden();

        AuthTestHelper::clearUser($user);
    }

    public function test_cashier_manager_can_access_register_routes(): void
    {
        $user = User::factory()->create();
        $user->assignRole('cashier manager');
        $tokens = AuthTestHelper::generateTokens($user);

        $response = $this->getJson(route('api.register.categories.index'), [
            'Authorization' => 'Bearer '.$tokens['accessToken'],
        ]);

        $response->assertOk();

        AuthTestHelper::clearUser($user);
    }

    public function test_accountant_can_access_register_routes(): void
    {
        $user = User::factory()->create();
        $user->assignRole('accountant');
        $tokens = AuthTestHelper::generateTokens($user);

        $response = $this->getJson(route('api.register.categories.index'), [
            'Authorization' => 'Bearer '.$tokens['accessToken'],
        ]);

        $response->assertOk();

        AuthTestHelper::clearUser($user);
    }
}
