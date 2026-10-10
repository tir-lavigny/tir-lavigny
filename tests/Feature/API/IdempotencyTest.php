<?php

namespace Tests\Feature\API;

use App\Http\Middleware\EnsureIdempotentRequest;
use App\Mail\OperatingDayClosed;
use App\Models\Category;
use App\Models\IdempotencyKey;
use App\Models\OperatingDay;
use App\Models\Product;
use App\Models\Season;
use App\Models\StockLocation;
use App\Models\Ticket;
use App\Models\TicketItem;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class IdempotencyTest extends TestCase
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
    }

    protected function tearDown(): void
    {
        AuthTestHelper::clearUser($this->user);
        parent::tearDown();
    }

    /** @return array<string, string> */
    private function authHeaders(string $key): array
    {
        return [
            'Authorization' => 'Bearer '.$this->tokens['accessToken'],
            'Idempotency-Key' => $key,
        ];
    }

    private function createOpenDay(): OperatingDay
    {
        $season = Season::factory()->create([
            'start_date' => Carbon::now()->startOfYear(),
            'end_date' => Carbon::now()->endOfYear(),
        ]);

        return OperatingDay::factory()->create([
            'season_id' => $season->id,
            'closed_at' => null,
            'date' => Carbon::today()->toDateString(),
        ]);
    }

    private function createProduct(): Product
    {
        $category = Category::factory()->create(['is_restricted' => false]);

        return Product::factory()->create([
            'category_id' => $category->id,
            'unit_price' => 5.00,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function ticketPayload(OperatingDay $day, Product $product, int $quantity = 1): array
    {
        return [
            'payment_method' => 'cash',
            'operating_day_id' => $day->id,
            'sold_at' => now()->toDateTimeString(),
            'stock_location_id' => StockLocation::factory()->create()->id,
            'items' => [['product_id' => $product->id, 'quantity' => $quantity]],
        ];
    }

    private function seedProcessingRecord(User $user, string $key, string $body, int $lockSeconds = 30): IdempotencyKey
    {
        $request = Request::create('api/register/tickets', 'POST', content: $body);

        return IdempotencyKey::create([
            'user_id' => $user->id,
            'key' => $key,
            'request_hash' => EnsureIdempotentRequest::requestHash($request),
            'status' => IdempotencyKey::STATUS_PROCESSING,
            'locked_until' => now()->addSeconds($lockSeconds),
        ]);
    }

    public function test_retried_request_with_same_key_replays_response_without_side_effect(): void
    {
        $day = $this->createOpenDay();
        $product = $this->createProduct();
        $payload = $this->ticketPayload($day, $product);
        $key = (string) Str::uuid();

        $first = $this->postJson(route('api.register.tickets.store'), $payload, $this->authHeaders($key));
        $second = $this->postJson(route('api.register.tickets.store'), $payload, $this->authHeaders($key));

        $first->assertCreated();
        $second->assertCreated()->assertHeader('Idempotency-Replayed', 'true');
        $this->assertSame($first->getContent(), $second->getContent());
        $this->assertDatabaseCount('tickets', 1);
    }

    public function test_same_key_with_different_body_is_rejected(): void
    {
        $day = $this->createOpenDay();
        $product = $this->createProduct();
        $key = (string) Str::uuid();

        $this->postJson(
            route('api.register.tickets.store'),
            $this->ticketPayload($day, $product),
            $this->authHeaders($key),
        )->assertCreated();

        $this->postJson(
            route('api.register.tickets.store'),
            $this->ticketPayload($day, $product, quantity: 2),
            $this->authHeaders($key),
        )->assertStatus(422);

        $this->assertDatabaseCount('tickets', 1);
    }

    public function test_same_key_on_different_endpoint_is_rejected(): void
    {
        $day = $this->createOpenDay();
        $product = $this->createProduct();
        $season = Season::factory()->create();
        $key = (string) Str::uuid();

        $this->postJson(
            route('api.register.tickets.store'),
            $this->ticketPayload($day, $product),
            $this->authHeaders($key),
        )->assertCreated();

        $this->postJson(
            route('api.register.operating-days.store'),
            ['season_id' => $season->id],
            $this->authHeaders($key),
        )->assertStatus(422);
    }

    public function test_different_keys_execute_both_requests(): void
    {
        $day = $this->createOpenDay();
        $product = $this->createProduct();

        $this->postJson(
            route('api.register.tickets.store'),
            $this->ticketPayload($day, $product),
            $this->authHeaders((string) Str::uuid()),
        )->assertCreated();

        $this->postJson(
            route('api.register.tickets.store'),
            $this->ticketPayload($day, $product),
            $this->authHeaders((string) Str::uuid()),
        )->assertCreated();

        $this->assertDatabaseCount('tickets', 2);
    }

    public function test_missing_idempotency_key_is_rejected(): void
    {
        $day = $this->createOpenDay();
        $product = $this->createProduct();

        $response = $this->postJson(
            route('api.register.tickets.store'),
            $this->ticketPayload($day, $product),
            ['Authorization' => 'Bearer '.$this->tokens['accessToken']],
        );

        $response->assertStatus(400);
        $response->assertJsonPath('message', 'Idempotency-Key header is required for this endpoint.');
        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_concurrent_duplicate_returns_conflict_while_original_is_processing(): void
    {
        $day = $this->createOpenDay();
        $product = $this->createProduct();
        $payload = $this->ticketPayload($day, $product);
        $key = (string) Str::uuid();

        $this->seedProcessingRecord($this->user, $key, json_encode($payload));

        $this->postJson(route('api.register.tickets.store'), $payload, $this->authHeaders($key))
            ->assertStatus(409);

        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_stale_processing_key_is_taken_over_and_request_executes(): void
    {
        $day = $this->createOpenDay();
        $product = $this->createProduct();
        $payload = $this->ticketPayload($day, $product);
        $key = (string) Str::uuid();

        $this->seedProcessingRecord($this->user, $key, json_encode($payload), lockSeconds: -1);

        $this->postJson(route('api.register.tickets.store'), $payload, $this->authHeaders($key))
            ->assertCreated();

        $this->assertDatabaseCount('tickets', 1);
    }

    public function test_failed_attempt_releases_key_so_retry_executes_again(): void
    {
        $day = $this->createOpenDay();
        $product = $this->createProduct();
        $key = (string) Str::uuid();

        $this->postJson(
            route('api.register.tickets.store'),
            ['operating_day_id' => $day->id],
            $this->authHeaders($key),
        )->assertUnprocessable();

        $this->assertDatabaseCount('idempotency_keys', 0);

        $this->postJson(
            route('api.register.tickets.store'),
            $this->ticketPayload($day, $product),
            $this->authHeaders($key),
        )->assertCreated();

        $this->assertDatabaseCount('tickets', 1);
    }

    public function test_refund_with_same_key_is_replayed(): void
    {
        $day = $this->createOpenDay();
        $product = $this->createProduct();
        $ticket = Ticket::factory()->cash()->create([
            'operating_day_id' => $day->id,
            'total' => 5.00,
        ]);
        TicketItem::factory()->create([
            'ticket_id' => $ticket->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 5.00,
            'subtotal' => 5.00,
        ]);
        $this->user->givePermissionTo('refund ticket');
        $key = (string) Str::uuid();

        $first = $this->postJson(route('api.register.tickets.refund', $ticket), [], $this->authHeaders($key));
        $second = $this->postJson(route('api.register.tickets.refund', $ticket), [], $this->authHeaders($key));

        $first->assertOk();
        $second->assertOk()->assertHeader('Idempotency-Replayed', 'true');
        $this->assertSoftDeleted('tickets', ['id' => $ticket->id]);
        $this->assertEquals('0.00', $day->fresh()->total_cash);

        $this->postJson(
            route('api.register.tickets.refund', $ticket),
            [],
            $this->authHeaders((string) Str::uuid()),
        )->assertStatus(422);
    }

    public function test_closing_day_with_same_key_replays_and_sends_single_email(): void
    {
        Mail::fake();

        $recipient = User::factory()->create();
        $recipient->assignRole('get register close operating day remark');

        $day = $this->createOpenDay();
        $key = (string) Str::uuid();
        $payload = [
            'status' => 'closed',
            'cash_remark' => 'Short 5 CHF',
        ];

        $this->patchJson(route('api.register.operating-days.update', $day), $payload, $this->authHeaders($key))
            ->assertOk();
        $this->patchJson(route('api.register.operating-days.update', $day), $payload, $this->authHeaders($key))
            ->assertOk()
            ->assertHeader('Idempotency-Replayed', 'true');

        Mail::assertSent(OperatingDayClosed::class, 1);
    }

    public function test_key_is_scoped_per_user(): void
    {
        $otherUser = User::factory()->create();
        $otherUser->assignRole('cashier');

        $day = $this->createOpenDay();
        $product = $this->createProduct();
        $payload = $this->ticketPayload($day, $product);
        $key = (string) Str::uuid();

        $this->postJson(route('api.register.tickets.store'), $payload, $this->authHeaders($key))
            ->assertCreated();

        Auth::forgetGuards();
        $this->actingAs($otherUser, 'sanctum');

        $otherPayload = $this->ticketPayload($day, $product);
        $this->postJson(route('api.register.tickets.store'), $otherPayload, [
            'Idempotency-Key' => $key,
        ])->assertCreated();

        $this->assertDatabaseCount('tickets', 2);

        AuthTestHelper::clearUser($otherUser);
    }

    public function test_prune_command_deletes_keys_older_than_a_day(): void
    {
        $old = IdempotencyKey::create([
            'user_id' => $this->user->id,
            'key' => 'old-key',
            'request_hash' => 'hash',
            'status' => IdempotencyKey::STATUS_COMPLETED,
        ]);
        $old->created_at = now()->subDays(2);
        $old->save();
        $recent = IdempotencyKey::create([
            'user_id' => $this->user->id,
            'key' => 'recent-key',
            'request_hash' => 'hash',
            'status' => IdempotencyKey::STATUS_COMPLETED,
        ]);

        $this->artisan('idempotency:prune')->assertSuccessful();

        $this->assertDatabaseMissing('idempotency_keys', ['id' => $old->id]);
        $this->assertDatabaseHas('idempotency_keys', ['id' => $recent->id]);
    }
}
