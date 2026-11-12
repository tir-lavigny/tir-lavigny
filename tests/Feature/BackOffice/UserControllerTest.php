<?php

namespace Tests\Feature\BackOffice;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);
    }

    public function test_index_searches_users_without_accents(): void
    {
        User::factory()->create(['name' => 'Élodie Martin']);
        User::factory()->create(['name' => 'Zoe Bernard']);

        $response = $this->get(route('bo.users.index', ['search' => 'elodie']));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('paginatedUsers.data', 1)
            ->where('paginatedUsers.data.0.name', 'Élodie Martin')
            ->where('filters.search', 'elodie')
        );
    }
}
