<?php

namespace Tests\Unit\Http\Controllers\API\Register;

use App\Http\Controllers\API\Register\CategoryController;
use App\Models\Category;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class CategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    private CategoryController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new CategoryController;
    }

    private function indexRequest(User $user): Request
    {
        $request = Request::create('/api/register/categories', 'GET');
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    public function test_index_returns_non_restricted_categories(): void
    {
        Category::factory()->create(['name' => 'Tir', 'is_restricted' => false]);
        Category::factory()->create(['name' => 'Bar', 'is_restricted' => false]);
        Category::factory()->create(['name' => 'Internal', 'is_restricted' => true]);

        $user = User::factory()->create();
        $response = $this->controller->index($this->indexRequest($user));
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->status());
        $this->assertCount(2, $data['data']);
    }

    public function test_index_includes_restricted_for_admin(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        Category::factory()->create(['name' => 'Tir', 'is_restricted' => false]);
        Category::factory()->create(['name' => 'Internal', 'is_restricted' => true]);

        $user = User::factory()->create();
        $user->assignRole('admin');
        $response = $this->controller->index($this->indexRequest($user));
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->status());
        $this->assertCount(2, $data['data']);
    }

    public function test_index_excludes_soft_deleted_categories(): void
    {
        Category::factory()->create(['name' => 'Active', 'is_restricted' => false]);
        Category::factory()->create(['name' => 'Deleted', 'is_restricted' => false])->delete();

        $user = User::factory()->create();
        $response = $this->controller->index($this->indexRequest($user));
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->status());
        $this->assertCount(1, $data['data']);
        $this->assertEquals('Active', $data['data'][0]['name']);
    }
}
