<?php

namespace Tests\Feature\BackOffice\Register;

use App\Models\Member;
use App\Models\User;
use App\Services\InfomaniakDavService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
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

        $davMock = Mockery::mock(InfomaniakDavService::class);
        $davMock->shouldReceive('updateMember')->andReturn(true);
        $davMock->shouldReceive('softDeleteMember')->andReturn(true);
        $this->app->instance(InfomaniakDavService::class, $davMock);
    }

    public function test_index_returns_members(): void
    {
        Member::factory()->create(['last_name' => 'Dupont']);

        $response = $this->get(route('bo.register.members.index'));

        $response->assertOk();
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
}
