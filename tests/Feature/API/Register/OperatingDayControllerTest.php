<?php

namespace Tests\Feature\API\Register;

use App\Mail\OperatingDayClosed;
use App\Models\OperatingDay;
use App\Models\Season;
use App\Models\Ticket;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\Feature\API\AuthTestHelper;
use Tests\TestCase;

class OperatingDayControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private array $tokens;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->user = User::factory()->create();
        $this->user->assignRole('cashier');
        $this->tokens = AuthTestHelper::generateTokens($this->user);

        $remarkRecipient = User::factory()->create();
        $remarkRecipient->assignRole('get register close operating day remark');
    }

    protected function tearDown(): void
    {
        AuthTestHelper::clearUser($this->user);
        parent::tearDown();
    }

    private function authHeaders(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->tokens['accessToken'],
            'Idempotency-Key' => (string) Str::uuid(),
        ];
    }

    // --- store() ---

    public function test_store_creates_operating_day(): void
    {
        $season = Season::factory()->create([
            'start_date' => Carbon::now()->startOfYear(),
            'end_date' => Carbon::now()->endOfYear(),
        ]);

        $response = $this->postJson(route('api.register.operating-days.store'), [
            'season_id' => $season->id,
        ], $this->authHeaders());

        $response->assertCreated();
        $response->assertJsonPath('data.season_id', $season->id);
        $this->assertDatabaseHas('operating_days', [
            'season_id' => $season->id,
            'closed_at' => null,
        ]);
    }

    public function test_store_fails_when_open_day_already_exists(): void
    {
        $season = Season::factory()->create([
            'start_date' => Carbon::now()->startOfYear(),
            'end_date' => Carbon::now()->endOfYear(),
        ]);
        OperatingDay::factory()->create([
            'season_id' => $season->id,
            'closed_at' => null,
        ]);

        $response = $this->postJson(route('api.register.operating-days.store'), [
            'season_id' => $season->id,
        ], $this->authHeaders());

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['season_id']);
    }

    public function test_store_fails_with_duplicate_date(): void
    {
        $season = Season::factory()->create([
            'start_date' => Carbon::now()->startOfYear(),
            'end_date' => Carbon::now()->endOfYear(),
        ]);
        OperatingDay::factory()->create([
            'season_id' => $season->id,
            'date' => Carbon::today('Europe/Zurich')->toDateString(),
        ]);

        $response = $this->postJson(route('api.register.operating-days.store'), [
            'season_id' => $season->id,
        ], $this->authHeaders());

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['season_id']);
    }

    public function test_store_requires_permission(): void
    {
        $user = User::factory()->create();
        $tokens = AuthTestHelper::generateTokens($user);
        $season = Season::factory()->create();

        $response = $this->postJson(route('api.register.operating-days.store'), [
            'season_id' => $season->id,
        ], ['Authorization' => 'Bearer '.$tokens['accessToken']]);

        $response->assertForbidden();
        AuthTestHelper::clearUser($user);
    }

    // --- update() ---

    public function test_update_closes_open_day(): void
    {
        $season = Season::factory()->create([
            'start_date' => Carbon::now()->startOfYear(),
            'end_date' => Carbon::now()->endOfYear(),
        ]);
        $day = OperatingDay::factory()->create([
            'season_id' => $season->id,
            'closed_at' => null,
        ]);

        $response = $this->patchJson(route('api.register.operating-days.update', $day), [
            'status' => 'closed',
        ], $this->authHeaders());

        $response->assertOk();
        $this->assertNotNull($day->fresh()->closed_at);
        $this->assertNotNull($day->fresh()->closing_balance);
    }

    public function test_update_reopens_closed_day(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        $tokens = AuthTestHelper::generateTokens($user);

        $season = Season::factory()->create([
            'start_date' => Carbon::now()->startOfYear(),
            'end_date' => Carbon::now()->endOfYear(),
        ]);
        $day = OperatingDay::factory()->closed()->create([
            'season_id' => $season->id,
        ]);

        $response = $this->patchJson(route('api.register.operating-days.update', $day), [
            'status' => 'open',
        ], ['Authorization' => 'Bearer '.$tokens['accessToken'], 'Idempotency-Key' => (string) Str::uuid()]);

        $response->assertOk();
        $this->assertNull($day->fresh()->closed_at);
        AuthTestHelper::clearUser($user);
    }

    public function test_update_reopen_requires_permission(): void
    {
        $season = Season::factory()->create([
            'start_date' => Carbon::now()->startOfYear(),
            'end_date' => Carbon::now()->endOfYear(),
        ]);
        $day = OperatingDay::factory()->closed()->create([
            'season_id' => $season->id,
        ]);

        $response = $this->patchJson(route('api.register.operating-days.update', $day), [
            'status' => 'open',
        ], $this->authHeaders());

        $response->assertForbidden();
    }

    public function test_update_updates_cash_remark(): void
    {
        $season = Season::factory()->create([
            'start_date' => Carbon::now()->startOfYear(),
            'end_date' => Carbon::now()->endOfYear(),
        ]);
        $day = OperatingDay::factory()->create([
            'season_id' => $season->id,
            'cash_remark' => null,
        ]);

        $response = $this->patchJson(route('api.register.operating-days.update', $day), [
            'cash_remark' => 'Short 2.50 CHF',
        ], $this->authHeaders());

        $response->assertOk();
        $this->assertEquals('Short 2.50 CHF', $day->fresh()->cash_remark);
    }

    public function test_update_fails_to_close_already_closed_day(): void
    {
        $season = Season::factory()->create([
            'start_date' => Carbon::now()->startOfYear(),
            'end_date' => Carbon::now()->endOfYear(),
        ]);
        $day = OperatingDay::factory()->closed()->create([
            'season_id' => $season->id,
        ]);

        $response = $this->patchJson(route('api.register.operating-days.update', $day), [
            'status' => 'closed',
        ], $this->authHeaders());

        $response->assertUnprocessable();
    }

    // --- current() ---

    public function test_current_returns_open_day_for_today(): void
    {
        $season = Season::factory()->create([
            'start_date' => Carbon::now()->startOfYear(),
            'end_date' => Carbon::now()->endOfYear(),
        ]);
        $day = OperatingDay::create([
            'date' => Carbon::today('Europe/Zurich'),
            'started_at' => Carbon::now(),
            'season_id' => $season->id,
            'closed_at' => null,
            'total_cash' => 0,
            'total_twint' => 0,
        ]);

        $response = $this->getJson(route('api.register.operating-days.current'), $this->authHeaders());

        $response->assertOk();
        $response->assertJsonPath('data.id', $day->id);
    }

    public function test_current_returns_404_when_no_open_day(): void
    {
        $response = $this->getJson(route('api.register.operating-days.current'), $this->authHeaders());

        $response->assertNotFound();
    }

    public function test_current_eager_loads_tickets(): void
    {
        $season = Season::factory()->create([
            'start_date' => Carbon::now()->startOfYear(),
            'end_date' => Carbon::now()->endOfYear(),
        ]);
        $day = OperatingDay::create([
            'date' => Carbon::today('Europe/Zurich'),
            'started_at' => Carbon::now(),
            'season_id' => $season->id,
            'closed_at' => null,
            'total_cash' => 0,
            'total_twint' => 0,
        ]);
        Ticket::factory()->cash()->create(['operating_day_id' => $day->id]);

        $response = $this->getJson(route('api.register.operating-days.current'), $this->authHeaders());

        $response->assertOk();
        $response->assertJsonCount(1, 'data.tickets');
    }

    // --- previousUnclosed() ---

    public function test_previous_unclosed_returns_unclosed_day_from_past(): void
    {
        $season = Season::factory()->create([
            'start_date' => Carbon::now()->startOfYear(),
            'end_date' => Carbon::now()->endOfYear(),
        ]);
        $day = OperatingDay::create([
            'date' => Carbon::yesterday(),
            'started_at' => Carbon::yesterday()->subHours(8),
            'season_id' => $season->id,
            'closed_at' => null,
            'total_cash' => 0,
            'total_twint' => 0,
        ]);

        $response = $this->getJson(route('api.register.operating-days.previous-unclosed'), $this->authHeaders());

        $response->assertOk();
        $response->assertJsonPath('data.id', $day->id);
    }

    public function test_previous_unclosed_returns_404_when_none_found(): void
    {
        $response = $this->getJson(route('api.register.operating-days.previous-unclosed'), $this->authHeaders());

        $response->assertNotFound();
    }

    public function test_previous_unclosed_excludes_today(): void
    {
        $season = Season::factory()->create([
            'start_date' => Carbon::now()->startOfYear(),
            'end_date' => Carbon::now()->endOfYear(),
        ]);
        OperatingDay::create([
            'date' => Carbon::today('Europe/Zurich'),
            'started_at' => Carbon::now(),
            'season_id' => $season->id,
            'closed_at' => null,
            'total_cash' => 0,
            'total_twint' => 0,
        ]);

        $response = $this->getJson(route('api.register.operating-days.previous-unclosed'), $this->authHeaders());

        $response->assertNotFound();
    }

    public function test_previous_unclosed_excludes_closed_days(): void
    {
        $season = Season::factory()->create([
            'start_date' => Carbon::now()->startOfYear(),
            'end_date' => Carbon::now()->endOfYear(),
        ]);
        OperatingDay::create([
            'date' => Carbon::yesterday(),
            'started_at' => Carbon::yesterday()->subHours(8),
            'closed_at' => Carbon::yesterday(),
            'season_id' => $season->id,
            'total_cash' => 0,
            'total_twint' => 0,
        ]);

        $response = $this->getJson(route('api.register.operating-days.previous-unclosed'), $this->authHeaders());

        $response->assertNotFound();
    }

    public function test_previous_unclosed_returns_most_recent(): void
    {
        $season = Season::factory()->create([
            'start_date' => Carbon::now()->startOfYear(),
            'end_date' => Carbon::now()->endOfYear(),
        ]);
        $oldest = OperatingDay::create([
            'date' => Carbon::now()->subDays(3),
            'started_at' => Carbon::now()->subDays(3)->subHours(8),
            'season_id' => $season->id,
            'closed_at' => null,
            'total_cash' => 0,
            'total_twint' => 0,
        ]);
        $recent = OperatingDay::create([
            'date' => Carbon::yesterday(),
            'started_at' => Carbon::yesterday()->subHours(8),
            'season_id' => $season->id,
            'closed_at' => null,
            'total_cash' => 0,
            'total_twint' => 0,
        ]);

        $response = $this->getJson(route('api.register.operating-days.previous-unclosed'), $this->authHeaders());

        $response->assertOk();
        $response->assertJsonPath('data.id', $recent->id);
    }

    public function test_previous_unclosed_requires_authentication(): void
    {
        $response = $this->getJson(route('api.register.operating-days.previous-unclosed'));

        $response->assertUnauthorized();
    }

    // --- show() ---

    public function test_show_returns_operating_day_with_tickets(): void
    {
        $season = Season::factory()->create([
            'start_date' => Carbon::now()->startOfYear(),
            'end_date' => Carbon::now()->endOfYear(),
        ]);
        $day = OperatingDay::factory()->create(['season_id' => $season->id]);
        Ticket::factory()->cash()->create(['operating_day_id' => $day->id]);

        $response = $this->getJson(route('api.register.operating-days.show', $day), $this->authHeaders());

        $response->assertOk();
        $response->assertJsonPath('data.id', $day->id);
        $response->assertJsonCount(1, 'data.tickets');
    }

    public function test_show_includes_refunded_tickets(): void
    {
        $season = Season::factory()->create([
            'start_date' => Carbon::now()->startOfYear(),
            'end_date' => Carbon::now()->endOfYear(),
        ]);
        $day = OperatingDay::factory()->create(['season_id' => $season->id]);
        $ticket = Ticket::factory()->cash()->create(['operating_day_id' => $day->id]);
        $ticket->delete();

        $response = $this->getJson(route('api.register.operating-days.show', $day), $this->authHeaders());

        $response->assertOk();
        $response->assertJsonCount(1, 'data.tickets');
    }

    public function test_show_returns_404_for_nonexistent_day(): void
    {
        $response = $this->getJson(route('api.register.operating-days.show', 9999), $this->authHeaders());

        $response->assertNotFound();
    }

    public function test_close_sends_email_when_remark_present(): void
    {
        Mail::fake();

        $season = Season::factory()->create();
        $day = OperatingDay::factory()->create([
            'season_id' => $season->id,
        ]);

        $this->patchJson(route('api.register.operating-days.update', $day), [
            'status' => 'closed',
            'cash_remark' => 'Short 5 CHF',
        ], $this->authHeaders());

        Mail::assertSent(OperatingDayClosed::class);
    }
}
