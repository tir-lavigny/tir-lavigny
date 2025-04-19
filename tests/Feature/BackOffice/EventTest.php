<?php

namespace Tests\Feature\BackOffice;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create();
        $this->actingAs($user);
    }

    public function test_can_create_event(): void
    {
        $data = [
            'title' => 'Test Event',
            'start_datetime' => now()->addDays(10)->format('Y-m-d H:i:s'),
            'end_datetime' => now()->addDays(10)->addHours(2)->format('Y-m-d H:i:s'),
            'location_id' => null,
            'template_id' => null,
            'body' => 'Test Event Body',
        ];

        $response = $this->post(route('bo.events.store'), $data);

        $response->assertStatus(302);
        $this->assertDatabaseHas('events', [
            'title' => $data['title'],
            'start_datetime' => $data['start_datetime'],
            'end_datetime' => $data['end_datetime'],
        ]);
        $this->assertDatabaseHas('event_contents', [
            'event_id' => 1,
            'body' => $data['body'],
        ]);
    }

    public function test_can_view_event_list(): void
    {
        $response = $this->get(route('events'));

        $response->assertStatus(200);
        $response->assertSee('Events'); // Vérifie que la vue contient "Events"
    }

    public function test_can_view_specific_event(): void
    {
        $event = Event::factory()->create();

        $response = $this->get(route('events.show', $event->id));

        $response->assertStatus(200);
        $response->assertSee($event->title); // Vérifie que le titre de l'événement est visible
    }

    public function test_view_nonexistent_event_returns_404(): void
    {
        $response = $this->get(route('events.show', 9999)); // ID inexistant

        $response->assertStatus(404);
    }

    public function test_guest_is_redirected_to_login_on_event_list(): void
    {
        $this->post('/logout');

        $response = $this->get(route('bo.events.index'));
        $response->assertRedirect('/login');
    }

    public function test_guest_is_redirected_to_login_on_event_show(): void
    {
        $this->post('/logout');

        $event = Event::factory()->create();

        $response = $this->get(route('bo.events.show', $event->id));
        $response->assertRedirect('/login');
    }

    public function test_guest_is_redirected_to_login_on_event_create(): void
    {
        $this->post('/logout');

        $response = $this->get(route('bo.events.create'));
        $response->assertRedirect('/login');
    }

    public function test_guest_is_redirected_to_login_on_event_edit(): void
    {
        $this->post('/logout');

        $event = Event::factory()->create();

        $response = $this->get(route('bo.events.edit', $event->id));
        $response->assertRedirect('/login');
    }

    public function test_guest_is_redirected_to_login_on_event_store(): void
    {
        $this->post('/logout');

        $data = [
            'title' => 'Test Event',
            'start_datetime' => now()->addDays(10)->format('Y-m-d H:i:s'),
            'end_datetime' => now()->addDays(10)->addHours(2)->format('Y-m-d H:i:s'),
            'location_id' => null,
            'template_id' => null,
            'body' => 'Test Event Body',
        ];

        $response = $this->post(route('bo.events.store'), $data);
        $response->assertRedirect('/login');
    }

    public function test_guest_is_redirected_to_login_on_event_update(): void
    {
        $this->post('/logout');

        $event = Event::factory()->create();

        $data = [
            'title' => 'Updated Event',
            'start_datetime' => now()->addDays(10)->format('Y-m-d H:i:s'),
            'end_datetime' => now()->addDays(10)->addHours(2)->format('Y-m-d H:i:s'),
            'location_id' => null,
            'template_id' => null,
            'body' => 'Updated Event Body',
        ];

        $response = $this->put(route('bo.events.update', $event->id), $data);
        $response->assertRedirect('/login');
    }

    public function test_can_update_event(): void
    {
        $event = Event::factory()->create();

        $data = [
            'title' => 'Updated Event',
            'start_datetime' => now()->addDays(10)->format('Y-m-d H:i:s'),
            'end_datetime' => now()->addDays(10)->addHours(2)->format('Y-m-d H:i:s'),
            'location_id' => null,
            'template_id' => null,
            'body' => 'Updated Event Body',
        ];

        $response = $this->put(route('bo.events.update', $event->id), $data);

        $response->assertStatus(302);
        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'title' => $data['title'],
            'start_datetime' => $data['start_datetime'],
            'end_datetime' => $data['end_datetime'],
        ]);
        $this->assertDatabaseHas('event_contents', [
            'event_id' => $event->id,
            'body' => $data['body'],
        ]);
    }

    public function test_can_delete_event(): void
    {
        $event = Event::factory()->create();

        $response = $this->delete(route('bo.events.destroy', $event->id));

        $response->assertStatus(302);
        $this->assertDatabaseMissing('events', [
            'id' => $event->id,
        ]);
    }

    public function test_cannot_delete_nonexistent_event(): void
    {
        $response = $this->delete(route('bo.events.destroy', 9999));

        $response->assertStatus(404);
    }

    public function test_title_is_required(): void
    {
        $data = [
            'title' => '',
            'start_datetime' => now()->addDays(10)->format('Y-m-d H:i:s'),
            'end_datetime' => now()->addDays(10)->addHours(2)->format('Y-m-d H:i:s'),
            'location_id' => null,
            'template_id' => null,
            'body' => 'Test Event Body',
        ];

        $response = $this->post(route('bo.events.store'), $data);

        $response->assertSessionHasErrors(['title']);
    }

    public function test_start_datetime_is_required(): void
    {
        $data = [
            'title' => 'Test Event',
            'start_datetime' => '',
            'end_datetime' => now()->addDays(10)->addHours(2)->format('Y-m-d H:i:s'),
            'location_id' => null,
            'template_id' => null,
            'body' => 'Test Event Body',
        ];

        $response = $this->post(route('bo.events.store'), $data);

        $response->assertSessionHasErrors(['start_datetime']);
    }

    public function test_end_datetime_is_after_start_datetime(): void
    {
        $data = [
            'title' => 'Test Event',
            'start_datetime' => now()->addDays(10)->format('Y-m-d H:i:s'),
            'end_datetime' => now()->addDays(9)->format('Y-m-d H:i:s'), // Before start
            'location_id' => null,
            'template_id' => null,
            'body' => 'Test Event Body',
        ];

        $response = $this->post(route('bo.events.store'), $data);

        $response->assertSessionHasErrors(['end_datetime']);
    }

    public function test_location_id_is_nullable(): void
    {
        $data = [
            'title' => 'Test Event',
            'start_datetime' => now()->addDays(10)->format('Y-m-d H:i:s'),
            'end_datetime' => now()->addDays(10)->addHours(2)->format('Y-m-d H:i:s'),
            'location_id' => null,
            'template_id' => null,
            'body' => 'Test Event Body',
        ];

        $response = $this->post(route('bo.events.store'), $data);

        $response->assertStatus(302);
    }

    public function test_template_id_is_nullable(): void
    {
        $data = [
            'title' => 'Test Event',
            'start_datetime' => now()->addDays(10)->format('Y-m-d H:i:s'),
            'end_datetime' => now()->addDays(10)->addHours(2)->format('Y-m-d H:i:s'),
            'location_id' => null,
            'template_id' => null,
            'body' => 'Test Event Body',
        ];

        $response = $this->post(route('bo.events.store'), $data);

        $response->assertStatus(302);
    }

    public function test_body_is_nullable(): void
    {
        $data = [
            'title' => 'Test Event',
            'start_datetime' => now()->addDays(10)->format('Y-m-d H:i:s'),
            'end_datetime' => now()->addDays(10)->addHours(2)->format('Y-m-d H:i:s'),
            'location_id' => null,
            'template_id' => null,
            'body' => null,
        ];

        $response = $this->post(route('bo.events.store'), $data);

        $response->assertStatus(302);
    }

    public function test_start_datetime_is_valid_datetime(): void
    {
        $data = [
            'title' => 'Test Event',
            'start_datetime' => 'invalid-datetime',
            'end_datetime' => now()->addDays(10)->addHours(2)->format('Y-m-d H:i:s'),
            'location_id' => null,
            'template_id' => null,
            'body' => 'Test Event Body',
        ];

        $response = $this->post(route('bo.events.store'), $data);

        $response->assertSessionHasErrors(['start_datetime']);
    }

    public function test_end_datetime_is_valid_datetime(): void
    {
        $data = [
            'title' => 'Test Event',
            'start_datetime' => now()->addDays(10)->format('Y-m-d H:i:s'),
            'end_datetime' => 'invalid-datetime',
            'location_id' => null,
            'template_id' => null,
            'body' => 'Test Event Body',
        ];

        $response = $this->post(route('bo.events.store'), $data);

        $response->assertSessionHasErrors(['end_datetime']);
    }

    public function test_event_title_is_not_unique(): void
    {
        $existingEvent = Event::factory()->create(['title' => 'Unique Event Title']);

        $data = [
            'title' => 'Unique Event Title',
            'start_datetime' => now()->addDays(10)->format('Y-m-d H:i:s'),
            'end_datetime' => now()->addDays(10)->addHours(2)->format('Y-m-d H:i:s'),
            'location_id' => null,
            'template_id' => null,
            'body' => 'Test Event Body',
        ];

        $response = $this->post(route('bo.events.store'), $data);

        $response->assertStatus(302);
        $this->assertDatabaseHas('events', [
            'title' => $data['title'],
        ]);
    }

    public function test_cannot_update_event_with_invalid_data(): void
    {
        $event = Event::factory()->create();

        $data = [
            'title' => '', // Invalid title
            'start_datetime' => 'invalid-datetime', // Invalid start_datetime
            'end_datetime' => 'invalid-datetime', // Invalid end_datetime
        ];

        $response = $this->put(route('bo.events.update', $event->id), $data);

        $response->assertSessionHasErrors(['title', 'start_datetime', 'end_datetime']);
    }

    public function test_index_filters_events_by_search(): void
    {
        Event::factory()->create(['title' => 'Only Matching Event']);
        Event::factory()->create(['title' => 'Non-Matching Event']);

        $response = $this->get(route('bo.events.index', ['search' => 'Only']));

        $response->assertStatus(200);
        $response->assertSee('Matching Event');
        $response->assertDontSee('Non-Matching Event');
    }

    public function test_index_filters_events_by_start_and_end_before(): void
    {
        Event::factory()->create(['start_datetime' => now()->addDays(5), 'end_datetime' => now()->addDays(6)]);
        Event::factory()->create(['start_datetime' => now()->addDays(10), 'end_datetime' => now()->addDays(11)]);

        $response = $this->get(route('bo.events.index', ['start_before' => now()->addDays(7)->format('Y-m-d'), 'end_before' => now()->addDays(7)->format('Y-m-d')]));

        $response->assertStatus(200);
        $response->assertSee('5');
        $response->assertDontSee('10');
    }

    public function test_index_pagination_works(): void
    {
        Event::factory()->count(15)->create();

        $response = $this->get(route('bo.events.index', ['page' => 2]));

        $response->assertStatus(200);
        $response->assertSee('Events');
    }

    public function test_store_creates_event_with_valid_data(): void
    {
        $data = [
            'title' => 'New Event',
            'start_datetime' => now()->addDays(10)->format('Y-m-d H:i:s'),
            'end_datetime' => now()->addDays(10)->addHours(2)->format('Y-m-d H:i:s'),
            'location_id' => null,
            'template_id' => null,
            'body' => 'Event Body',
        ];

        $response = $this->post(route('bo.events.store'), $data);

        $response->assertStatus(302);
        $this->assertDatabaseHas('events', ['title' => 'New Event']);
    }

    public function test_show_displays_event_details(): void
    {
        $event = Event::factory()->create();

        $response = $this->get(route('bo.events.show', $event->id));

        $response->assertStatus(200);
        $response->assertSee($event->title);
    }

    public function test_update_modifies_event_with_valid_data(): void
    {
        $event = Event::factory()->create();

        $data = [
            'title' => 'Updated Event',
            'start_datetime' => now()->addDays(10)->format('Y-m-d H:i:s'),
            'end_datetime' => now()->addDays(10)->addHours(2)->format('Y-m-d H:i:s'),
            'location_id' => null,
            'template_id' => null,
            'body' => 'Updated Body',
        ];

        $response = $this->put(route('bo.events.update', $event->id), $data);

        $response->assertStatus(302);
        $this->assertDatabaseHas('events', ['id' => $event->id, 'title' => 'Updated Event']);
    }

    public function test_destroy_deletes_event(): void
    {
        $event = Event::factory()->create();

        $response = $this->delete(route('bo.events.destroy', $event->id));

        $response->assertStatus(302);
        $this->assertDatabaseMissing('events', ['id' => $event->id]);
    }

    public function test_destroy_fails_for_nonexistent_event(): void
    {
        $response = $this->delete(route('bo.events.destroy', 9999));

        $response->assertStatus(404);
    }
}
