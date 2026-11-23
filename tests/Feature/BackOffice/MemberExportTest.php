<?php

namespace Tests\Feature\BackOffice;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class MemberExportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
    }

    public function test_export_route_is_registered_before_the_show_route(): void
    {
        $this->assertTrue(Route::has('bo.members.export'));
        $this->assertSame('/back-office/members/export', route('bo.members.export', absolute: false));
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('bo.members.export'))->assertRedirect(route('login'));
    }

    public function test_users_without_permission_cannot_export(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('bo.members.export'))
            ->assertForbidden();
    }

    public function test_unknown_format_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->from(route('bo.members.index'))
            ->get(route('bo.members.export', ['format' => 'pdf']))
            ->assertRedirect(route('bo.members.index'))
            ->assertSessionHasErrors('format');
    }

    public function test_unknown_columns_are_rejected(): void
    {
        $this->actingAs($this->admin)
            ->from(route('bo.members.index'))
            ->get(route('bo.members.export', ['columns' => ['password']]))
            ->assertRedirect(route('bo.members.index'))
            ->assertSessionHasErrors('columns.0');
    }

    public function test_extra_columns_require_a_header(): void
    {
        $this->actingAs($this->admin)
            ->from(route('bo.members.index'))
            ->get(route('bo.members.export', ['extra_columns' => [['header' => '', 'width' => 20]]]))
            ->assertRedirect(route('bo.members.index'))
            ->assertSessionHasErrors('extra_columns.0.header');
    }
}
