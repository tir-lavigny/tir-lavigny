<?php

namespace Tests\Feature\BackOffice\Register;

use App\Models\Season;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeasonControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
        $this->actingAs($this->admin);
    }

    public function test_index_returns_seasons(): void
    {
        Season::factory()->create(['name' => '2025-2026']);

        $response = $this->get(route('bo.register.seasons.index'));

        $response->assertOk();
    }

    public function test_store_creates_season(): void
    {
        $response = $this->post(route('bo.register.seasons.store'), [
            'name' => '2026-2027',
            'start_date' => Carbon::now()->startOfYear()->toDateString(),
            'end_date' => Carbon::now()->endOfYear()->toDateString(),
            'initial_balance' => 500.00,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('seasons', ['name' => '2026-2027']);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->post(route('bo.register.seasons.store'), []);

        $response->assertSessionHasErrors(['name', 'start_date', 'end_date', 'initial_balance']);
    }

    public function test_store_validates_end_date_after_start_date(): void
    {
        $response = $this->post(route('bo.register.seasons.store'), [
            'name' => 'Bad Season',
            'start_date' => Carbon::now()->toDateString(),
            'end_date' => Carbon::now()->subMonth()->toDateString(),
            'initial_balance' => 0,
        ]);

        $response->assertSessionHasErrors(['end_date']);
    }

    public function test_show_returns_season(): void
    {
        $season = Season::factory()->create();

        $response = $this->get(route('bo.register.seasons.show', $season));

        $response->assertOk();
    }

    public function test_edit_returns_form(): void
    {
        $season = Season::factory()->create();

        $response = $this->get(route('bo.register.seasons.edit', $season));

        $response->assertOk();
    }

    public function test_update_modifies_season(): void
    {
        $season = Season::factory()->create(['name' => 'Old']);

        $response = $this->put(route('bo.register.seasons.update', $season), [
            'name' => 'New',
            'start_date' => $season->start_date->toDateString(),
            'end_date' => $season->end_date->toDateString(),
            'initial_balance' => $season->initial_balance,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('seasons', ['id' => $season->id, 'name' => 'New']);
    }

    public function test_destroy_soft_deletes_season(): void
    {
        $season = Season::factory()->create();

        $response = $this->delete(route('bo.register.seasons.destroy', $season));

        $response->assertRedirect();
        $this->assertSoftDeleted('seasons', ['id' => $season->id]);
    }

    public function test_store_requires_permission(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view register');
        $this->actingAs($user);

        $response = $this->post(route('bo.register.seasons.store'), [
            'name' => 'Test',
            'start_date' => Carbon::now()->toDateString(),
            'end_date' => Carbon::now()->addYear()->toDateString(),
            'initial_balance' => 0,
        ]);

        $response->assertForbidden();
    }
}
