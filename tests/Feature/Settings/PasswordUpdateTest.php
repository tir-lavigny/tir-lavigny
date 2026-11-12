<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function backOfficeUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('editor');

        return $user;
    }

    public function test_password_can_be_updated()
    {
        $user = $this->backOfficeUser();

        $response = $this
            ->actingAs($user)
            ->from('/back-office/settings/password')
            ->put('/back-office/settings/password', [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/back-office/settings/password');

        $this->assertTrue(Hash::check('new-password', $user->refresh()->password));
    }

    public function test_correct_password_must_be_provided_to_update_password()
    {
        $user = $this->backOfficeUser();

        $response = $this
            ->actingAs($user)
            ->from('/back-office/settings/password')
            ->put('/back-office/settings/password', [
                'current_password' => 'wrong-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response
            ->assertSessionHasErrors('current_password')
            ->assertRedirect('/back-office/settings/password');
    }
}
