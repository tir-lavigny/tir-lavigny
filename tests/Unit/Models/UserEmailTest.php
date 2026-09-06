<?php

namespace Tests\Unit\Models;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_get_member_registration_emails_returns_role_users(): void
    {
        $user = User::factory()->create(['email' => 'role-user@example.com']);
        $user->assignRole('get member registration mails');

        $emails = User::getMemberRegistrationEmails();

        $this->assertCount(1, $emails);
        $this->assertContains('role-user@example.com', $emails);
    }

    public function test_get_member_registration_emails_falls_back_to_config(): void
    {
        User::factory()->create(['email' => 'fallback@example.com']);
        config(['app.user.email' => 'fallback@example.com']);

        $emails = User::getMemberRegistrationEmails();

        $this->assertCount(1, $emails);
        $this->assertContains('fallback@example.com', $emails);
    }

    public function test_get_member_registration_emails_returns_empty_when_no_role_and_no_config(): void
    {
        config(['app.user.email' => null]);

        $emails = User::getMemberRegistrationEmails();

        $this->assertCount(0, $emails);
    }

    public function test_get_register_close_operating_day_remark_emails_returns_role_users(): void
    {
        $user = User::factory()->create(['email' => 'remark-user@example.com']);
        $user->assignRole('get register close operating day remark');

        $emails = User::getRegisterCloseOperatingDayRemarkEmails();

        $this->assertCount(1, $emails);
        $this->assertContains('remark-user@example.com', $emails);
    }

    public function test_get_register_close_operating_day_remark_emails_falls_back_to_config(): void
    {
        User::factory()->create(['email' => 'fallback-remark@example.com']);
        config(['app.user.email' => 'fallback-remark@example.com']);

        $emails = User::getRegisterCloseOperatingDayRemarkEmails();

        $this->assertCount(1, $emails);
        $this->assertContains('fallback-remark@example.com', $emails);
    }

    public function test_get_register_close_operating_day_remark_emails_returns_empty_when_no_role_and_no_config(): void
    {
        config(['app.user.email' => null]);

        $emails = User::getRegisterCloseOperatingDayRemarkEmails();

        $this->assertCount(0, $emails);
    }
}
