<?php

namespace Tests\Feature\Inertia;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for HandleInertiaRequests::share().
 *
 * These tests verify that every key declared in the SharedData TypeScript
 * interface is present on every Inertia response, has the correct shape,
 * and reflects the server-side state faithfully — regardless of the Inertia
 * adapter version in use.
 */
class SharedPropsTest extends TestCase
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

    // ─── name ────────────────────────────────────────────────────────────────

    public function test_name_prop_is_the_application_name(): void
    {
        $user = $this->backOfficeUser();

        $this->actingAs($user)
            ->get('/back-office/dashboard')
            ->assertInertia(fn ($page) => $page
                ->has('name')
                ->where('name', config('app.name'))
            );
    }

    // ─── quote ───────────────────────────────────────────────────────────────

    public function test_quote_prop_contains_message_and_author(): void
    {
        $user = $this->backOfficeUser();

        $this->actingAs($user)
            ->get('/back-office/dashboard')
            ->assertInertia(fn ($page) => $page
                ->has('quote')
                ->has('quote.message')
                ->has('quote.author')
            );
    }

    public function test_quote_message_and_author_are_non_empty_strings(): void
    {
        $user = $this->backOfficeUser();

        $this->actingAs($user)
            ->get('/back-office/dashboard')
            ->assertInertia(fn ($page) => $page
                ->where('quote.message', fn ($v) => is_string($v) && strlen(trim($v)) > 0)
                ->where('quote.author', fn ($v) => is_string($v) && strlen(trim($v)) > 0)
            );
    }

    // ─── auth ─────────────────────────────────────────────────────────────────

    public function test_auth_user_is_null_for_guests(): void
    {
        // Guests hit a public route — auth.user must be null.
        $this->get('/')
            ->assertInertia(fn ($page) => $page
                ->has('auth')
                ->where('auth.user', null)
            );
    }

    public function test_auth_user_contains_correct_id_and_email_for_authenticated_user(): void
    {
        $user = $this->backOfficeUser();

        $this->actingAs($user)
            ->get('/back-office/dashboard')
            ->assertInertia(fn ($page) => $page
                ->has('auth.user')
                ->where('auth.user.id', $user->id)
                ->where('auth.user.email', $user->email)
                ->where('auth.user.name', $user->name)
            );
    }

    public function test_auth_user_includes_roles_relationship(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $this->actingAs($user)
            ->get('/back-office/dashboard')
            ->assertInertia(fn ($page) => $page
                ->has('auth.user.roles')
                ->where('auth.user.roles.0.name', 'admin')
            );
    }

    public function test_auth_user_password_is_not_exposed(): void
    {
        $user = $this->backOfficeUser();

        $this->actingAs($user)
            ->get('/back-office/dashboard')
            ->assertInertia(fn ($page) => $page
                ->missing('auth.user.password')
            );
    }

    // ─── ziggy ───────────────────────────────────────────────────────────────

    public function test_ziggy_prop_is_present(): void
    {
        $user = $this->backOfficeUser();

        $this->actingAs($user)
            ->get('/back-office/dashboard')
            ->assertInertia(fn ($page) => $page
                ->has('ziggy')
            );
    }

    public function test_ziggy_location_reflects_current_url(): void
    {
        $user = $this->backOfficeUser();

        $this->actingAs($user)
            ->get('/back-office/dashboard')
            ->assertInertia(fn ($page) => $page
                ->where('ziggy.location', fn ($v) => str_contains($v, 'back-office/dashboard'))
            );
    }

    public function test_ziggy_contains_named_routes(): void
    {
        $user = $this->backOfficeUser();

        $this->actingAs($user)
            ->get('/back-office/dashboard')
            ->assertInertia(fn ($page) => $page
                ->has('ziggy.routes')
                ->where('ziggy.routes', fn ($routes) => isset($routes['dashboard']))
            );
    }

    // ─── sidebarOpen ─────────────────────────────────────────────────────────

    public function test_sidebar_is_open_by_default_when_no_cookie(): void
    {
        $user = $this->backOfficeUser();

        $this->actingAs($user)
            ->get('/back-office/dashboard')
            ->assertInertia(fn ($page) => $page
                ->where('sidebarOpen', true)
            );
    }

    public function test_sidebar_is_open_when_cookie_is_true(): void
    {
        $user = $this->backOfficeUser();

        $this->actingAs($user)
            ->withUnencryptedCookie('sidebar_state', 'true')
            ->get('/back-office/dashboard')
            ->assertInertia(fn ($page) => $page
                ->where('sidebarOpen', true)
            );
    }

    public function test_sidebar_is_closed_when_cookie_is_false(): void
    {
        $user = $this->backOfficeUser();

        $this->actingAs($user)
            ->withUnencryptedCookie('sidebar_state', 'false')
            ->get('/back-office/dashboard')
            ->assertInertia(fn ($page) => $page
                ->where('sidebarOpen', false)
            );
    }

    // ─── all shared keys are present on a public route ───────────────────────

    public function test_all_shared_keys_are_present_on_public_inertia_response(): void
    {
        $this->get('/')
            ->assertInertia(fn ($page) => $page
                ->has('name')
                ->has('quote')
                ->has('quote.message')
                ->has('quote.author')
                ->has('auth')
                ->has('ziggy')
                ->has('sidebarOpen')
            );
    }
}
