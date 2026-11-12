<?php

namespace Tests\Feature\BackOffice\Register;

use App\Models\Member;
use App\Models\User;
use App\Services\DavServiceInterface;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
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

        $davMock = Mockery::mock(DavServiceInterface::class);
        $davMock->shouldReceive('updateMember')->andReturn(true);
        $davMock->shouldReceive('softDeleteMember')->andReturn(true);
        $this->app->instance(DavServiceInterface::class, $davMock);
    }

    public function test_index_returns_members(): void
    {
        Member::factory()->create(['last_name' => 'Dupont']);

        $response = $this->get(route('bo.register.members.index'));

        $response->assertOk();
    }

    public function test_index_sorts_members_by_first_name_then_last_name(): void
    {
        Member::factory()->create(['first_name' => 'Zoe', 'last_name' => 'Bernard']);
        Member::factory()->create(['first_name' => 'Alice', 'last_name' => 'Zurcher']);
        Member::factory()->create(['first_name' => 'Alice', 'last_name' => 'Andre']);

        $response = $this->get(route('bo.register.members.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('members.data.0.first_name', 'Alice')
            ->where('members.data.0.last_name', 'Andre')
            ->where('members.data.1.first_name', 'Alice')
            ->where('members.data.1.last_name', 'Zurcher')
            ->where('members.data.2.first_name', 'Zoe')
        );
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
