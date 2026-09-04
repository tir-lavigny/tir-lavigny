<?php

namespace Tests\Feature\BackOffice\Register;

use App\Models\Member;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberControllerTest extends TestCase
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

    public function test_index_returns_members(): void
    {
        Member::factory()->create(['last_name' => 'Dupont']);

        $response = $this->get(route('bo.register.members.index'));

        $response->assertOk();
    }

    public function test_store_creates_member(): void
    {
        $response = $this->post(route('bo.register.members.store'), [
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'email' => 'jean@example.com',
            'is_swiss' => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('members', [
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'email' => 'jean@example.com',
            'is_swiss' => true,
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->post(route('bo.register.members.store'), []);

        $response->assertSessionHasErrors(['first_name', 'last_name', 'email', 'is_swiss']);
    }

    public function test_edit_returns_form(): void
    {
        $member = Member::factory()->create();

        $response = $this->get(route('bo.register.members.edit', $member));

        $response->assertOk();
    }

    public function test_update_modifies_member(): void
    {
        $member = Member::factory()->create(['first_name' => 'Jean']);

        $response = $this->put(route('bo.register.members.update', $member), [
            'first_name' => 'Pierre',
            'last_name' => $member->last_name,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('members', ['id' => $member->id, 'first_name' => 'Pierre']);
    }

    public function test_destroy_soft_deletes_member(): void
    {
        $member = Member::factory()->create();

        $response = $this->delete(route('bo.register.members.destroy', $member));

        $response->assertRedirect();
        $this->assertSoftDeleted('members', ['id' => $member->id]);
    }

    public function test_store_requires_permission(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view register');
        $this->actingAs($user);

        $response = $this->post(route('bo.register.members.store'), [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'is_swiss' => false,
        ]);

        $response->assertForbidden();
    }
}
