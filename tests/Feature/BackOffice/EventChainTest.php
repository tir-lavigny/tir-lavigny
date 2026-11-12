<?php

namespace Tests\Feature\BackOffice;

use App\Models\Event;
use App\Models\EventChain;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EventChainTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);
    }

    // ─── Helper ───────────────────────────────────────────────────────────────

    private function validChainData(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Weekly Training',
            'recurrence_type' => 'weekly',
            'interval' => null,
            'interval_unit' => null,
            'day_of_week' => 3, // Thursday (0=Mon)
            'day_of_month' => null,
            'start_date' => '2099-01-06', // A Monday
            'end_date' => '2099-01-27', // 3 weeks later (4 Thursdays)
            'start_time' => '18:00',
            'end_time' => '20:00',
            'location_id' => null,
            'content_id' => null,
            'custom_body' => null,
        ], $overrides);
    }

    // ─── Index ────────────────────────────────────────────────────────────────

    public function test_can_view_event_chain_index(): void
    {
        $response = $this->get(route('bo.event-chains.index'));

        $response->assertStatus(200);
    }

    public function test_index_searches_event_chains_without_accents(): void
    {
        EventChain::factory()->create(['title' => 'Fête annuelle']);
        EventChain::factory()->create(['title' => 'Tir sportif']);

        $response = $this->get(route('bo.event-chains.index', ['search' => 'fete']));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('paginatedChains.data', 1)
            ->where('paginatedChains.data.0.title', 'Fête annuelle')
        );
    }

    public function test_guest_is_redirected_on_event_chain_index(): void
    {
        $this->post('/logout');
        $this->get(route('bo.event-chains.index'))->assertRedirect('/login');
    }

    // ─── Create ───────────────────────────────────────────────────────────────

    public function test_can_view_event_chain_create_form(): void
    {
        $response = $this->get(route('bo.event-chains.create'));

        $response->assertStatus(200);
    }

    public function test_guest_is_redirected_on_event_chain_create(): void
    {
        $this->post('/logout');
        $this->get(route('bo.event-chains.create'))->assertRedirect('/login');
    }

    // ─── Store ────────────────────────────────────────────────────────────────

    public function test_can_create_event_chain(): void
    {
        $data = $this->validChainData();

        $response = $this->post(route('bo.event-chains.store'), $data);

        $response->assertStatus(302);
        $this->assertDatabaseHas('event_chains', [
            'title' => $data['title'],
            'recurrence_type' => $data['recurrence_type'],
            'day_of_week' => $data['day_of_week'],
        ]);
    }

    public function test_creating_a_chain_generates_events(): void
    {
        $data = $this->validChainData([
            'start_date' => '2099-01-06', // Monday
            'end_date' => '2099-01-27', // 4 weeks span — Thursdays: 9, 16, 23
            'day_of_week' => 3,            // Thursday
        ]);

        $this->post(route('bo.event-chains.store'), $data);

        $chain = EventChain::first();
        $this->assertNotNull($chain);
        // Thursdays in range: Jan 9, Jan 16, Jan 23
        $this->assertEquals(3, $chain->events()->count());
    }

    public function test_generated_events_inherit_chain_title(): void
    {
        $this->post(route('bo.event-chains.store'), $this->validChainData());

        $chain = EventChain::first();
        $chain->events()->each(function (Event $event) use ($chain) {
            $this->assertEquals($chain->title, $event->title);
        });
    }

    public function test_generated_events_have_correct_start_time(): void
    {
        $this->post(route('bo.event-chains.store'), $this->validChainData());

        $chain = EventChain::first();
        $chain->events()->each(function (Event $event) {
            // Chain time '18:00' is Zurich local, stored as UTC.
            $expected = Carbon::parse($event->start_datetime->toDateString().' 18:00', 'Europe/Zurich')->utc()->format('H:i:s');
            $this->assertEquals($expected, $event->start_datetime->format('H:i:s'));
        });
    }

    public function test_generated_events_inherit_custom_body(): void
    {
        $this->post(route('bo.event-chains.store'), $this->validChainData([
            'custom_body' => 'Training session body',
        ]));

        $chain = EventChain::first();
        $chain->events()->with('customContent')->each(function (Event $event) {
            $this->assertNotNull($event->customContent);
            $this->assertEquals('Training session body', $event->customContent->body);
        });
    }

    public function test_title_is_required_when_creating_chain(): void
    {
        $response = $this->post(route('bo.event-chains.store'), $this->validChainData(['title' => '']));

        $response->assertSessionHasErrors(['title']);
    }

    public function test_recurrence_type_is_required(): void
    {
        $response = $this->post(route('bo.event-chains.store'), $this->validChainData(['recurrence_type' => '']));

        $response->assertSessionHasErrors(['recurrence_type']);
    }

    public function test_recurrence_type_must_be_valid(): void
    {
        $response = $this->post(route('bo.event-chains.store'), $this->validChainData(['recurrence_type' => 'hourly']));

        $response->assertSessionHasErrors(['recurrence_type']);
    }

    public function test_start_date_is_required(): void
    {
        $response = $this->post(route('bo.event-chains.store'), $this->validChainData(['start_date' => '']));

        $response->assertSessionHasErrors(['start_date']);
    }

    public function test_end_date_must_be_after_or_equal_start_date(): void
    {
        $response = $this->post(route('bo.event-chains.store'), $this->validChainData([
            'start_date' => '2099-02-01',
            'end_date' => '2099-01-01',
        ]));

        $response->assertSessionHasErrors(['end_date']);
    }

    public function test_start_time_is_required(): void
    {
        $response = $this->post(route('bo.event-chains.store'), $this->validChainData(['start_time' => '']));

        $response->assertSessionHasErrors(['start_time']);
    }

    public function test_location_id_must_exist(): void
    {
        $response = $this->post(route('bo.event-chains.store'), $this->validChainData(['location_id' => 9999]));

        $response->assertSessionHasErrors(['location_id']);
    }

    public function test_location_id_is_nullable(): void
    {
        $response = $this->post(route('bo.event-chains.store'), $this->validChainData(['location_id' => null]));

        $response->assertStatus(302);
        $response->assertSessionHasNoErrors();
    }

    public function test_guest_is_redirected_on_event_chain_store(): void
    {
        $this->post('/logout');
        $this->post(route('bo.event-chains.store'), $this->validChainData())->assertRedirect('/login');
    }

    // ─── Show ─────────────────────────────────────────────────────────────────

    public function test_can_view_event_chain(): void
    {
        $chain = EventChain::factory()->withoutEvents()->create();

        $response = $this->get(route('bo.event-chains.show', $chain));

        $response->assertStatus(200);
    }

    public function test_show_nonexistent_chain_returns_404(): void
    {
        $this->get(route('bo.event-chains.show', 9999))->assertStatus(404);
    }

    public function test_guest_is_redirected_on_event_chain_show(): void
    {
        $this->post('/logout');
        $chain = EventChain::factory()->withoutEvents()->create();
        $this->get(route('bo.event-chains.show', $chain))->assertRedirect('/login');
    }

    // ─── Edit ─────────────────────────────────────────────────────────────────

    public function test_can_view_event_chain_edit_form(): void
    {
        $chain = EventChain::factory()->withoutEvents()->create();

        $response = $this->get(route('bo.event-chains.edit', $chain));

        $response->assertStatus(200);
    }

    public function test_guest_is_redirected_on_event_chain_edit(): void
    {
        $this->post('/logout');
        $chain = EventChain::factory()->withoutEvents()->create();
        $this->get(route('bo.event-chains.edit', $chain))->assertRedirect('/login');
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    public function test_can_update_chain_title_for_all_events(): void
    {
        $chain = EventChain::factory()->withoutEvents()->create(['title' => 'Old Title']);
        // Manually create two events linked to the chain
        $e1 = Event::factory()->create(['event_chain_id' => $chain->id, 'title' => 'Old Title']);
        $e2 = Event::factory()->create(['event_chain_id' => $chain->id, 'title' => 'Old Title']);

        $payload = array_merge($this->validChainData(['title' => 'New Title']), [
            'update_scope' => 'all',
            'from_date' => null,
        ]);

        $response = $this->put(route('bo.event-chains.update', $chain), $payload);

        $response->assertStatus(302);
        $this->assertDatabaseHas('events', ['id' => $e1->id, 'title' => 'New Title']);
        $this->assertDatabaseHas('events', ['id' => $e2->id, 'title' => 'New Title']);
    }

    public function test_can_update_chain_for_future_events_only(): void
    {
        $chain = EventChain::factory()->withoutEvents()->create(['title' => 'Old Title']);
        $past = Event::factory()->create([
            'event_chain_id' => $chain->id,
            'title' => 'Old Title',
            'start_datetime' => now()->subWeek(),
            'end_datetime' => now()->subWeek()->addHours(2),
        ]);
        $future = Event::factory()->create([
            'event_chain_id' => $chain->id,
            'title' => 'Old Title',
            'start_datetime' => now()->addWeek(),
            'end_datetime' => now()->addWeek()->addHours(2),
        ]);

        $payload = array_merge($this->validChainData(['title' => 'New Title']), [
            'update_scope' => 'from_date',
            'from_date' => now()->format('Y-m-d'),
        ]);

        $this->put(route('bo.event-chains.update', $chain), $payload);

        $this->assertDatabaseHas('events', ['id' => $past->id, 'title' => 'Old Title']);
        $this->assertDatabaseHas('events', ['id' => $future->id, 'title' => 'New Title']);
    }

    public function test_update_flags_conflict_on_overridden_field(): void
    {
        $chain = EventChain::factory()->withoutEvents()->create(['title' => 'Chain Title']);
        $event = Event::factory()->create([
            'event_chain_id' => $chain->id,
            'title' => 'Custom Title',
            'overridden_fields' => ['title'],
        ]);

        $payload = array_merge($this->validChainData(['title' => 'New Chain Title']), [
            'update_scope' => 'all',
            'from_date' => null,
        ]);

        $this->put(route('bo.event-chains.update', $chain), $payload);

        $this->assertDatabaseHas('events', ['id' => $event->id, 'has_chain_conflict' => true]);
        // The event title should NOT have been overwritten
        $this->assertDatabaseHas('events', ['id' => $event->id, 'title' => 'Custom Title']);
    }

    public function test_update_scope_is_required(): void
    {
        $chain = EventChain::factory()->withoutEvents()->create();

        $response = $this->put(route('bo.event-chains.update', $chain), $this->validChainData());

        $response->assertSessionHasErrors(['update_scope']);
    }

    public function test_guest_is_redirected_on_event_chain_update(): void
    {
        $this->post('/logout');
        $chain = EventChain::factory()->withoutEvents()->create();
        $this->put(route('bo.event-chains.update', $chain), [])->assertRedirect('/login');
    }

    // ─── Destroy ──────────────────────────────────────────────────────────────

    public function test_can_delete_event_chain(): void
    {
        $chain = EventChain::factory()->withoutEvents()->create();

        $response = $this->delete(route('bo.event-chains.destroy', $chain));

        $response->assertStatus(302);
        $this->assertDatabaseMissing('event_chains', ['id' => $chain->id]);
    }

    public function test_deleting_chain_deletes_its_events(): void
    {
        $chain = EventChain::factory()->withoutEvents()->create();
        $event = Event::factory()->create(['event_chain_id' => $chain->id]);

        $this->delete(route('bo.event-chains.destroy', $chain));

        $this->assertDatabaseMissing('events', ['id' => $event->id]);
    }

    public function test_delete_nonexistent_chain_returns_404(): void
    {
        $this->delete(route('bo.event-chains.destroy', 9999))->assertStatus(404);
    }

    public function test_guest_is_redirected_on_event_chain_destroy(): void
    {
        $this->post('/logout');
        $chain = EventChain::factory()->withoutEvents()->create();
        $this->delete(route('bo.event-chains.destroy', $chain))->assertRedirect('/login');
    }

    // ─── Conflict resolution ──────────────────────────────────────────────────

    public function test_can_resolve_conflict_by_accepting_chain_value(): void
    {
        $chain = EventChain::factory()->withoutEvents()->create(['title' => 'Chain Title']);
        $event = Event::factory()->create([
            'event_chain_id' => $chain->id,
            'title' => 'Custom Title',
            'overridden_fields' => ['title'],
            'has_chain_conflict' => true,
        ]);

        $this->post(route('bo.event-chains.resolve-conflict', [$chain, $event]), [
            'field' => 'title',
            'resolution' => 'accept_chain',
        ]);

        $this->assertDatabaseHas('events', ['id' => $event->id, 'title' => 'Chain Title', 'has_chain_conflict' => false]);
    }

    public function test_can_resolve_conflict_by_keeping_override(): void
    {
        $chain = EventChain::factory()->withoutEvents()->create(['title' => 'Chain Title']);
        $event = Event::factory()->create([
            'event_chain_id' => $chain->id,
            'title' => 'Custom Title',
            'overridden_fields' => ['title'],
            'has_chain_conflict' => true,
        ]);

        $this->post(route('bo.event-chains.resolve-conflict', [$chain, $event]), [
            'field' => 'title',
            'resolution' => 'keep_override',
        ]);

        // Title is kept; has_chain_conflict stays true because the event intentionally diverges from the chain
        $this->assertDatabaseHas('events', ['id' => $event->id, 'title' => 'Custom Title', 'has_chain_conflict' => true]);
    }

    public function test_cannot_resolve_conflict_for_event_not_in_chain(): void
    {
        $chain = EventChain::factory()->withoutEvents()->create();
        $otherChain = EventChain::factory()->withoutEvents()->create();
        $event = Event::factory()->create(['event_chain_id' => $otherChain->id]);

        $this->post(route('bo.event-chains.resolve-conflict', [$chain, $event]), [
            'field' => 'title',
            'resolution' => 'accept_chain',
        ])->assertStatus(404);
    }

    // ─── Detach ───────────────────────────────────────────────────────────────

    public function test_can_reset_event_to_chain_values(): void
    {
        $chain = EventChain::factory()->withoutEvents()->create(['title' => 'Chain Title']);
        $event = Event::factory()->create([
            'event_chain_id' => $chain->id,
            'title' => 'Custom Title',
            'overridden_fields' => ['title'],
            'has_chain_conflict' => true,
            'pending_conflict_fields' => [],
        ]);

        $this->post(route('bo.event-chains.reset-to-chain', [$chain, $event]))
            ->assertStatus(302);

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'title' => 'Chain Title',
            'has_chain_conflict' => false,
        ]);
    }

    public function test_reset_to_chain_returns_404_for_event_not_in_chain(): void
    {
        $chain = EventChain::factory()->withoutEvents()->create();
        $otherChain = EventChain::factory()->withoutEvents()->create();
        $event = Event::factory()->create(['event_chain_id' => $otherChain->id]);

        $this->post(route('bo.event-chains.reset-to-chain', [$chain, $event]))
            ->assertStatus(404);
    }

    public function test_guest_is_redirected_on_reset_to_chain(): void
    {
        $this->post('/logout');
        $chain = EventChain::factory()->withoutEvents()->create();
        $event = Event::factory()->create(['event_chain_id' => $chain->id]);
        $this->post(route('bo.event-chains.reset-to-chain', [$chain, $event]))
            ->assertRedirect('/login');
    }

    public function test_can_detach_event_from_chain(): void
    {
        $chain = EventChain::factory()->withoutEvents()->create();
        $event = Event::factory()->create(['event_chain_id' => $chain->id]);

        $this->post(route('bo.events.detach', $event));

        $this->assertDatabaseHas('events', ['id' => $event->id, 'event_chain_id' => null]);
    }

    public function test_guest_is_redirected_on_event_detach(): void
    {
        $this->post('/logout');
        $chain = EventChain::factory()->withoutEvents()->create();
        $event = Event::factory()->create(['event_chain_id' => $chain->id]);
        $this->post(route('bo.events.detach', $event))->assertRedirect('/login');
    }
}
