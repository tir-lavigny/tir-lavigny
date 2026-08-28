<?php

namespace Tests\Unit\Http\Controllers\BackOffice\Register;

use App\Http\Controllers\BackOffice\Register\CategoryController;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Response;
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

    public function test_index_returns_inertia_response(): void
    {
        Category::factory()->count(3)->create();

        $response = $this->controller->index();

        $this->assertInstanceOf(Response::class, $response);
    }

    public function test_store_persists_new_category(): void
    {
        $this->assertDatabaseEmpty('categories');

        $category = new Category;
        $category->fill(['name' => 'Nourriture', 'is_restricted' => false]);
        $category->save();

        $this->assertDatabaseHas('categories', ['name' => 'Nourriture', 'is_restricted' => false]);
    }

    public function test_update_modifies_category_attributes(): void
    {
        $category = Category::factory()->create(['name' => 'Old Name']);

        $category->update(['name' => 'New Name']);

        $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => 'New Name']);
    }

    public function test_destroy_soft_deletes_category(): void
    {
        $category = Category::factory()->create();

        $category->delete();

        $this->assertSoftDeleted('categories', ['id' => $category->id]);
    }
}
