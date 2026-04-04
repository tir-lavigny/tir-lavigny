<?php

namespace Tests\Feature\Inertia;

use App\Models\ActivityLog;
use App\Models\Content;
use App\Models\Event;
use App\Models\Location;
use App\Models\Post;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests that every controller using Inertia::render() returns the correct
 * component name and expected top-level props.
 *
 * Coverage: public pages, auth pages, back-office pages (CRUD index/create/
 * show/edit), and settings pages.  Each test is version-agnostic — it asserts
 * on the contract (component + props) rather than Inertia internals.
 */
class InertiaRenderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    // ─── helpers ─────────────────────────────────────────────────────────────

    /** Create a user with all back-office permissions and return it. */
    private function adminUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    // ─── public pages ────────────────────────────────────────────────────────

    public function test_home_renders_home_component_with_events_and_posts(): void
    {
        Event::factory()->create(['start_datetime' => now()->addDay()]);
        Post::factory()->create(['publish_at' => now()->subHour()]);

        $this->get('/')
            ->assertInertia(fn ($page) => $page
                ->component('Home')
                ->has('events')
                ->has('posts')
            );
    }

    public function test_home_only_includes_upcoming_events(): void
    {
        Event::factory()->create(['title' => 'Past Event',    'start_datetime' => now()->subDay()]);
        Event::factory()->create(['title' => 'Future Event',  'start_datetime' => now()->addDay()]);

        $this->get('/')
            ->assertInertia(fn ($page) => $page
                ->component('Home')
                ->where('events', fn ($events) => count($events) === 1
                    && $events[0]['title'] === 'Future Event'
                )
            );
    }

    public function test_home_only_includes_published_non_expired_posts(): void
    {
        Post::factory()->create(['title' => 'Future Post',  'publish_at' => now()->addDay()]);
        Post::factory()->create(['title' => 'Expired Post', 'publish_at' => now()->subDay(), 'expire_at' => now()->subHour()]);
        Post::factory()->create(['title' => 'Visible Post', 'publish_at' => now()->subHour()]);

        $this->get('/')
            ->assertInertia(fn ($page) => $page
                ->where('posts', fn ($posts) => count($posts) === 1
                    && $posts[0]['title'] === 'Visible Post'
                )
            );
    }

    // ─── auth pages ──────────────────────────────────────────────────────────

    public function test_login_renders_auth_login_component(): void
    {
        $this->get('/login')
            ->assertInertia(fn ($page) => $page
                ->component('auth/Login')
                ->has('canResetPassword')
                ->has('status')
            );
    }

    public function test_login_can_reset_password_prop_is_true_when_route_exists(): void
    {
        $this->get('/login')
            ->assertInertia(fn ($page) => $page
                ->where('canResetPassword', true)
            );
    }

    public function test_login_status_prop_is_null_by_default(): void
    {
        $this->get('/login')
            ->assertInertia(fn ($page) => $page
                ->where('status', null)
            );
    }

    public function test_password_reset_link_screen_renders_correct_component(): void
    {
        $this->get('/forgot-password')
            ->assertInertia(fn ($page) => $page
                ->component('auth/ForgotPassword')
                ->has('status')
            );
    }

    // ─── back-office dashboard ────────────────────────────────────────────────

    public function test_dashboard_renders_back_office_dashboard_component(): void
    {
        $user = $this->adminUser();

        $this->actingAs($user)
            ->get('/back-office/dashboard')
            ->assertInertia(fn ($page) => $page
                ->component('backOffice/Dashboard')
                ->has('activities')
            );
    }

    public function test_dashboard_activities_are_scoped_to_authenticated_user(): void
    {
        $user = $this->adminUser();
        $other = User::factory()->create();

        // Log activity for each user (direct insert to stay framework-agnostic)
        ActivityLog::create(['user_id' => $user->id,  'model' => 'Event', 'model_id' => 1, 'action' => 'created']);
        ActivityLog::create(['user_id' => $other->id, 'model' => 'Event', 'model_id' => 2, 'action' => 'created']);

        $this->actingAs($user)
            ->get('/back-office/dashboard')
            ->assertInertia(fn ($page) => $page
                ->where('activities', fn ($activities) => count($activities) === 1
                    && $activities[0]['user_id'] === $user->id
                )
            );
    }

    // ─── back-office events ───────────────────────────────────────────────────

    public function test_event_index_renders_correct_component_and_props(): void
    {
        $user = $this->adminUser();

        $this->actingAs($user)
            ->get('/back-office/events')
            ->assertInertia(fn ($page) => $page
                ->component('backOffice/Events/Index')
                ->has('paginatedEvents')
                ->has('filters')
                ->has('filters.search')
                ->has('filters.start_before')
                ->has('filters.end_before')
            );
    }

    public function test_event_index_filters_are_reflected_in_props(): void
    {
        $user = $this->adminUser();

        $this->actingAs($user)
            ->get('/back-office/events?search=concert&start_before=2030-01-01')
            ->assertInertia(fn ($page) => $page
                ->where('filters.search', 'concert')
                ->where('filters.start_before', '2030-01-01')
            );
    }

    public function test_event_create_renders_correct_component_with_templates_and_locations(): void
    {
        $user = $this->adminUser();
        Content::factory()->create();
        Location::factory()->create();

        $this->actingAs($user)
            ->get('/back-office/events/create')
            ->assertInertia(fn ($page) => $page
                ->component('backOffice/Events/Create')
                ->has('templates')
                ->has('locations')
                ->where('templates', fn ($t) => count($t) === 1)
                ->where('locations', fn ($l) => count($l) === 1)
            );
    }

    public function test_event_show_renders_correct_component_with_event_and_content(): void
    {
        $user = $this->adminUser();
        $event = Event::factory()->create();

        $this->actingAs($user)
            ->get("/back-office/events/{$event->id}")
            ->assertInertia(fn ($page) => $page
                ->component('backOffice/Events/Show')
                ->has('event')
                ->has('content')
                ->where('event.id', $event->id)
                ->where('event.title', $event->title)
            );
    }

    public function test_event_edit_renders_correct_component_with_full_props(): void
    {
        $user = $this->adminUser();
        $event = Event::factory()->create();

        $this->actingAs($user)
            ->get("/back-office/events/{$event->id}/edit")
            ->assertInertia(fn ($page) => $page
                ->component('backOffice/Events/Edit')
                ->has('event')
                ->has('content')
                ->has('contentSource')
                ->has('templates')
                ->has('locations')
                ->where('event.id', $event->id)
            );
    }

    // ─── back-office settings ─────────────────────────────────────────────────

    public function test_profile_settings_renders_correct_component(): void
    {
        $user = $this->adminUser();

        $this->actingAs($user)
            ->get('/back-office/settings/profile')
            ->assertInertia(fn ($page) => $page
                ->component('settings/Profile')
                ->has('mustVerifyEmail')
                ->has('status')
            );
    }

    public function test_password_settings_renders_correct_component(): void
    {
        $user = $this->adminUser();

        $this->actingAs($user)
            ->get('/back-office/settings/password')
            ->assertInertia(fn ($page) => $page
                ->component('settings/Password')
            );
    }

    // ─── Inertia protocol ────────────────────────────────────────────────────

    /**
     * Resolve the current Inertia asset version so XHR tests send the right
     * header and avoid a spurious 409 version-mismatch response.
     */
    private function inertiaVersion(): ?string
    {
        $manifest = public_path('build/manifest.json');

        return file_exists($manifest) ? hash_file('xxh128', $manifest) : null;
    }

    /**
     * Inertia partial reload: when the X-Inertia-Partial-Data header is set,
     * only the requested props should be returned.
     * Partial reload responses are JSON (not a view), so we assert the raw JSON.
     */
    public function test_partial_reload_only_returns_requested_props(): void
    {
        $user = $this->adminUser();
        $version = $this->inertiaVersion();

        $headers = array_filter([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version,
            'X-Inertia-Partial-Component' => 'backOffice/Events/Index',
            'X-Inertia-Partial-Data' => 'filters',
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $response = $this->actingAs($user)
            ->withHeaders($headers)
            ->get('/back-office/events');

        $response->assertStatus(200);
        $json = $response->json();

        $this->assertArrayHasKey('filters', $json['props']);
        $this->assertArrayNotHasKey('paginatedEvents', $json['props']);
    }

    /**
     * A plain Inertia XHR request (no partial flag) must return JSON with
     * the standard page object keys.
     */
    public function test_inertia_xhr_returns_json_page_object(): void
    {
        $user = $this->adminUser();
        $version = $this->inertiaVersion();

        $headers = array_filter([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version,
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $response = $this->actingAs($user)
            ->withHeaders($headers)
            ->get('/back-office/dashboard');

        $response->assertStatus(200);
        $response->assertHeader('X-Inertia', 'true');

        $json = $response->json();
        $this->assertArrayHasKey('component', $json);
        $this->assertArrayHasKey('props', $json);
        $this->assertArrayHasKey('url', $json);
        $this->assertArrayHasKey('version', $json);
    }

    /**
     * A stale asset version should trigger a 409 Conflict so the client
     * hard-reloads instead of soft-navigating into a broken state.
     */
    public function test_stale_asset_version_returns_409(): void
    {
        $user = $this->adminUser();

        $this->actingAs($user)
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Inertia-Version' => 'stale-version-abc',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->get('/back-office/dashboard')
            ->assertStatus(409);
    }

    /**
     * PUT/PATCH/DELETE redirects from a controller must return HTTP 303 when
     * initiated by Inertia — Inertia's middleware upgrades 302 → 303 for these
     * methods to prevent browsers from changing the method on the follow-up
     * GET request.  POST redirects remain 302 by spec.
     */
    public function test_inertia_put_redirect_uses_303_status(): void
    {
        $user = $this->adminUser();
        $version = $this->inertiaVersion();
        $event = Event::factory()->create();

        $headers = array_filter([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version,
        ]);

        $this->actingAs($user)
            ->withHeaders($headers)
            ->put("/back-office/events/{$event->id}", [
                'title' => 'Updated Title',
                'start_datetime' => now()->addDay()->toDateTimeString(),
            ])
            ->assertStatus(303);
    }
}
