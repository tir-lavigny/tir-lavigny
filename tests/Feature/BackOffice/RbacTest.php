<?php

namespace Tests\Feature\BackOffice;

use App\Models\Content;
use App\Models\Event;
use App\Models\EventChain;
use App\Models\Location;
use App\Models\Post;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RbacTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_content_crud_with_ok_permissions(): void
    {
        $createUser = User::factory()->create();
        $createUser->givePermissionTo('create content');

        $editUser = User::factory()->create();
        $editUser->givePermissionTo('edit content');

        $deleteUser = User::factory()->create();
        $deleteUser->givePermissionTo('delete content');

        // Test create content
        $response = $this->actingAs($createUser)->post(route('contents.store'), [
            'title' => 'Test Content',
            'body' => 'This is a test content body.',
        ]);
        $this->assertDatabaseHas('contents', [
            'title' => 'Test Content',
            'body' => 'This is a test content body.',
        ]);

        // Test edit content
        $content = Content::first();
        $response = $this->actingAs($editUser)->put(route('contents.update', $content), [
            'title' => 'Updated Content',
            'body' => 'This is an updated content body.',
        ]);
        $this->assertDatabaseHas('contents', [
            'title' => 'Updated Content',
            'body' => 'This is an updated content body.',
        ]);
        $this->assertDatabaseMissing('contents', [
            'title' => 'Test Content',
            'body' => 'This is a test content body.',
        ]);

        // Test delete content
        $response = $this->actingAs($deleteUser)->delete(route('contents.destroy', $content));
        $this->assertDatabaseMissing('contents', [
            'title' => 'Updated Content',
            'body' => 'This is an updated content body.',
        ]);
        $this->assertDatabaseMissing('contents', [
            'title' => 'Test Content',
            'body' => 'This is a test content body.',
        ]);
    }

    public function test_content_crud_with_nok_permissions(): void
    {
        $createUser = User::factory()->create();
        $createUser->givePermissionTo('create content');

        $editUser = User::factory()->create();
        $editUser->givePermissionTo('edit content');

        $deleteUser = User::factory()->create();
        $deleteUser->givePermissionTo('delete content');

        // Test create content without permission
        $response = $this->actingAs($editUser)->post(route('contents.store'), [
            'title' => 'Test Content',
            'body' => 'This is a test content body.',
        ]);
        $response->assertStatus(403);
        $this->assertDatabaseMissing('contents', [
            'title' => 'Test Content',
            'body' => 'This is a test content body.',
        ]);

        // Test edit content without permission
        $content = Content::factory()->create();
        $response = $this->actingAs($deleteUser)->put(route('contents.update', $content), [
            'title' => 'Updated Content',
            'body' => 'This is an updated content body.',
        ]);
        $response->assertStatus(403);
        $this->assertDatabaseMissing('contents', [
            'title' => 'Updated Content',
            'body' => 'This is an updated content body.',
        ]);
        $this->assertDatabaseHas('contents', [
            'title' => $content->title,
            'body' => $content->body,
        ]);

        // Test delete content without permission
        $response = $this->actingAs($createUser)->delete(route('contents.destroy', $content));
        $response->assertStatus(403);
        $this->assertDatabaseHas('contents', [
            'title' => $content->title,
            'body' => $content->body,
        ]);
        $this->assertDatabaseMissing('contents', [
            'title' => 'Updated Content',
            'body' => 'This is an updated content body.',
        ]);
    }

    public function test_events_crud_ok_permissions()
    {
        $createUser = User::factory()->create();
        $createUser->givePermissionTo('create event');

        $editUser = User::factory()->create();
        $editUser->givePermissionTo('edit event');

        $deleteUser = User::factory()->create();
        $deleteUser->givePermissionTo('delete event');

        $data = [
            'title' => 'Test Event',
            'start_datetime' => now()->addDays(10)->format('Y-m-d H:i:s'),
            'end_datetime' => now()->addDays(10)->addHours(2)->format('Y-m-d H:i:s'),
            'location_id' => null,
            'template_id' => null,
            'body' => 'Test Event Body',
        ];

        // Test create event
        $response = $this->actingAs($createUser)->post(route('bo.events.store'), $data);
        $this->assertDatabaseHas('events', [
            'title' => $data['title'],
            'start_datetime' => $data['start_datetime'],
            'end_datetime' => $data['end_datetime'],
            'location_id' => $data['location_id'],
            'content_id' => $data['template_id'],
        ]);
        $this->assertDatabaseHas('event_contents', [
            'event_id' => Event::first()->id,
            'body' => $data['body'],
        ]);
        $event = Event::first();

        // Test edit event
        $response = $this->actingAs($editUser)->put(route('bo.events.update', $event), [
            'title' => 'Updated Event',
            'start_datetime' => now()->addDays(20)->format('Y-m-d H:i:s'),
            'end_datetime' => now()->addDays(20)->addHours(2)->format('Y-m-d H:i:s'),
            'location_id' => null,
            'template_id' => null,
            'body' => 'Updated Event Body',
        ]);
        $this->assertDatabaseHas('events', [
            'title' => 'Updated Event',
            'start_datetime' => now()->addDays(20)->format('Y-m-d H:i:s'),
            'end_datetime' => now()->addDays(20)->addHours(2)->format('Y-m-d H:i:s'),
            'location_id' => null,
            'content_id' => null,
        ]);
        $this->assertDatabaseHas('event_contents', [
            'event_id' => $event->id,
            'body' => 'Updated Event Body',
        ]);
        $this->assertDatabaseMissing('event_contents', [
            'event_id' => $event->id,
            'body' => 'Test Event Body',
        ]);
        $this->assertDatabaseMissing('events', [
            'title' => 'Test Event',
            'start_datetime' => $data['start_datetime'],
            'end_datetime' => $data['end_datetime'],
            'location_id' => $data['location_id'],
            'content_id' => $data['template_id'],
        ]);
        $this->assertDatabaseMissing('event_contents', [
            'event_id' => $event->id,
            'body' => 'Test Event Body',
        ]);

        // Test delete event
        $response = $this->actingAs($deleteUser)->delete(route('bo.events.destroy', $event));
        $this->assertDatabaseMissing('events', [
            'title' => 'Updated Event',
            'start_datetime' => now()->addDays(20)->format('Y-m-d H:i:s'),
            'end_datetime' => now()->addDays(20)->addHours(2)->format('Y-m-d H:i:s'),
            'location_id' => null,
            'content_id' => null,
        ]);
        $this->assertDatabaseMissing('event_contents', [
            'event_id' => $event->id,
            'body' => 'Updated Event Body',
        ]);
        $this->assertDatabaseMissing('events', [
            'title' => 'Test Event',
            'start_datetime' => $data['start_datetime'],
            'end_datetime' => $data['end_datetime'],
            'location_id' => $data['location_id'],
            'content_id' => $data['template_id'],
        ]);
        $this->assertDatabaseMissing('event_contents', [
            'event_id' => $event->id,
            'body' => 'Test Event Body',
        ]);
    }

    public function test_events_crud_nok_permissions()
    {
        $createUser = User::factory()->create();
        $createUser->givePermissionTo('create event');

        $editUser = User::factory()->create();
        $editUser->givePermissionTo('edit event');

        $deleteUser = User::factory()->create();
        $deleteUser->givePermissionTo('delete event');

        $data = [
            'title' => 'Test Event',
            'start_datetime' => now()->addDays(10)->format('Y-m-d H:i:s'),
            'end_datetime' => now()->addDays(10)->addHours(2)->format('Y-m-d H:i:s'),
            'location_id' => null,
            'template_id' => null,
            'body' => 'Test Event Body',
        ];

        // Test create event without permission
        $response = $this->actingAs($editUser)->post(route('bo.events.store'), $data);
        $response->assertStatus(403);
        $this->assertDatabaseMissing('events', [
            'title' => $data['title'],
            'start_datetime' => $data['start_datetime'],
            'end_datetime' => $data['end_datetime'],
            'location_id' => $data['location_id'],
            'content_id' => $data['template_id'],
        ]);
        $this->assertDatabaseMissing('event_contents', [
            'body' => $data['body'],
        ]);

        // Test edit event without permission
        $event = Event::factory()->create();
        $response = $this->actingAs($deleteUser)->put(route('bo.events.update', $event), [
            'title' => 'Updated Event',
            'start_datetime' => now()->addDays(20)->format('Y-m-d H:i:s'),
            'end_datetime' => now()->addDays(20)->addHours(2)->format('Y-m-d H:i:s'),
            'location_id' => null,
            'template_id' => null,
            'body' => 'Updated Event Body',
        ]);
        $response->assertStatus(403);
        $this->assertDatabaseMissing('events', [
            'title' => 'Updated Event',
            'start_datetime' => now()->addDays(20)->format('Y-m-d H:i:s'),
            'end_datetime' => now()->addDays(20)->addHours(2)->format('Y-m-d H:i:s'),
            'location_id' => null,
            'content_id' => null,
        ]);
        $this->assertDatabaseHas('events', [
            'title' => $event->title,
            'start_datetime' => $event->start_datetime,
            'end_datetime' => $event->end_datetime,
            'location_id' => $event->location_id,
            'content_id' => $event->content_id,
        ]);

        // Test delete event without permission
        $response = $this->actingAs($createUser)->delete(route('bo.events.destroy', $event));
        $response->assertStatus(403);
        $this->assertDatabaseHas('events', [
            'title' => $event->title,
            'start_datetime' => $event->start_datetime,
            'end_datetime' => $event->end_datetime,
            'location_id' => $event->location_id,
            'content_id' => $event->content_id,
        ]);
        $this->assertDatabaseMissing('events', [
            'title' => 'Updated Event',
            'start_datetime' => now()->addDays(20)->format('Y-m-d H:i:s'),
            'end_datetime' => now()->addDays(20)->addHours(2)->format('Y-m-d H:i:s'),
            'location_id' => null,
            'content_id' => null,
        ]);
    }

    public function test_locations_crud_ok_permissions()
    {
        $createUser = User::factory()->create();
        $createUser->givePermissionTo('create location');

        $editUser = User::factory()->create();
        $editUser->givePermissionTo('edit location');

        $deleteUser = User::factory()->create();
        $deleteUser->givePermissionTo('delete location');

        $data = [
            'name' => 'Test Location',
            'address' => 'Test Address',
            'latitude' => 0,
            'longitude' => 0,
        ];

        // Test create location
        $response = $this->actingAs($createUser)->post(route('locations.store'), $data);
        $this->assertDatabaseHas('locations', [
            'name' => $data['name'],
            'address' => $data['address'],
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
        ]);

        // Test edit location
        $location = Location::first();
        $this->actingAs($editUser)->put(route('locations.update', $location), [
            'name' => 'Updated Location',
            'address' => 'Updated Address',
            'latitude' => 1,
            'longitude' => 1,
        ]);
        $this->assertDatabaseHas('locations', [
            'name' => 'Updated Location',
            'address' => 'Updated Address',
            'latitude' => 1,
            'longitude' => 1,
        ]);
        $this->assertDatabaseMissing('locations', [
            'name' => $data['name'],
            'address' => $data['address'],
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
        ]);

        // Test delete location
        $response = $this->actingAs($deleteUser)->delete(route('locations.destroy', $location));
        $this->assertDatabaseMissing('locations', [
            'name' => 'Updated Location',
            'address' => 'Updated Address',
            'latitude' => 1,
            'longitude' => 1,
        ]);
        $this->assertDatabaseMissing('locations', [
            'name' => $data['name'],
            'address' => $data['address'],
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
        ]);
    }

    public function test_locations_crud_nok_permissions()
    {
        $createUser = User::factory()->create();
        $createUser->givePermissionTo('create location');

        $editUser = User::factory()->create();
        $editUser->givePermissionTo('edit location');

        $deleteUser = User::factory()->create();
        $deleteUser->givePermissionTo('delete location');

        $data = [
            'name' => 'Test Location',
            'address' => 'Test Address',
            'latitude' => 0,
            'longitude' => 0,
        ];

        // Test create location without permission
        $response = $this->actingAs($editUser)->post(route('locations.store'), $data);
        $response->assertStatus(403);
        $this->assertDatabaseMissing('locations', [
            'name' => $data['name'],
            'address' => $data['address'],
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
        ]);

        // Test edit location without permission
        $location = Location::factory()->create();
        $response = $this->actingAs($deleteUser)->put(route('locations.update', $location), [
            'name' => 'Updated Location',
            'address' => 'Updated Address',
            'latitude' => 1,
            'longitude' => 1,
        ]);
        $response->assertStatus(403);
        $this->assertDatabaseMissing('locations', [
            'name' => 'Updated Location',
            'address' => 'Updated Address',
            'latitude' => 1,
            'longitude' => 1,
        ]);
        $this->assertDatabaseHas('locations', [
            'name' => $location->name,
            'address' => $location->address,
            'latitude' => $location->latitude,
            'longitude' => $location->longitude,
        ]);

        // Test delete location without permission
        $response = $this->actingAs($createUser)->delete(route('locations.destroy', $location));
        $response->assertStatus(403);
        $this->assertDatabaseHas('locations', [
            'name' => $location->name,
            'address' => $location->address,
            'latitude' => $location->latitude,
            'longitude' => $location->longitude,
        ]);
        $this->assertDatabaseMissing('locations', [
            'name' => 'Updated Location',
            'address' => 'Updated Address',
            'latitude' => 1,
            'longitude' => 1,
        ]);
        $this->assertDatabaseMissing('locations', [
            'name' => $data['name'],
            'address' => $data['address'],
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
        ]);
    }

    public function test_posts_crud_ok_permissions(): void
    {
        $createUser = User::factory()->create();
        $createUser->givePermissionTo('create post');

        $editUser = User::factory()->create();
        $editUser->givePermissionTo('edit post');

        $deleteUser = User::factory()->create();
        $deleteUser->givePermissionTo('delete post');

        // Test create post
        $response = $this->actingAs($createUser)->post(route('bo.posts.store'), [
            'title' => 'Test Post',
            'content' => 'This is a test post content.',
            'publish_at' => now()->toDateTimeString(),
            'expire_at' => now()->addDays(10)->toDateTimeString(),
        ]);
        $this->assertDatabaseHas('posts', [
            'title' => 'Test Post',
            'content' => 'This is a test post content.',
        ]);
        $post = Post::first();

        // Test edit post
        $response = $this->actingAs($editUser)->put(route('bo.posts.update', $post->public_id), [
            'title' => 'Updated Title',
            'content' => 'Updated content.',
            'publish_at' => now()->toDateTimeString(),
            'expire_at' => now()->addDays(5)->toDateTimeString(),
        ]);
        $this->assertDatabaseHas('posts', [
            'title' => 'Updated Title',
            'content' => 'Updated content.',
        ]);
        $this->assertDatabaseMissing('posts', [
            'title' => 'Test Post',
            'content' => 'This is a test post content.',
        ]);

        // Test delete post
        $response = $this->actingAs($deleteUser)->delete(route('bo.posts.destroy', $post->public_id));
        $this->assertDatabaseMissing('posts', [
            'title' => 'Updated Title',
            'content' => 'Updated content.',
        ]);
        $this->assertDatabaseMissing('posts', [
            'title' => 'Test Post',
            'content' => 'This is a test post content.',
        ]);
    }

    public function test_posts_crud_nok_permissions(): void
    {
        $createUser = User::factory()->create();
        $createUser->givePermissionTo('create post');

        $editUser = User::factory()->create();
        $editUser->givePermissionTo('edit post');

        $deleteUser = User::factory()->create();
        $deleteUser->givePermissionTo('delete post');

        // Test create post without permission
        $response = $this->actingAs($editUser)->post(route('bo.posts.store'), [
            'title' => 'Test Post',
            'content' => 'This is a test post content.',
            'publish_at' => now()->toDateTimeString(),
            'expire_at' => now()->addDays(10)->toDateTimeString(),
        ]);
        $response->assertStatus(403);
        $this->assertDatabaseMissing('posts', [
            'title' => 'Test Post',
            'content' => 'This is a test post content.',
        ]);

        // Test edit post without permission
        $post = Post::factory()->create();
        $response = $this->actingAs($deleteUser)->put(route('bo.posts.update', $post->public_id), [
            'title' => 'Updated Title',
            'content' => 'Updated content.',
            'publish_at' => now()->toDateTimeString(),
            'expire_at' => now()->addDays(5)->toDateTimeString(),
        ]);
        $response->assertStatus(403);
        $this->assertDatabaseMissing('posts', [
            'title' => 'Updated Title',
            'content' => 'Updated content.',
        ]);
        $this->assertDatabaseHas('posts', [
            'title' => $post->title,
            'content' => $post->content,
        ]);

        // Test delete post without permission
        $response = $this->actingAs($createUser)->delete(route('bo.posts.destroy', $post->public_id));
        $response->assertStatus(403);
        $this->assertDatabaseHas('posts', [
            'title' => $post->title,
            'content' => $post->content,
        ]);
        $this->assertDatabaseMissing('posts', [
            'title' => 'Updated Title',
            'content' => 'Updated content.',
        ]);
    }

    public function test_event_chains_crud_ok_permissions(): void
    {
        $createUser = User::factory()->create();
        $createUser->givePermissionTo('create event chain');

        $editUser = User::factory()->create();
        $editUser->givePermissionTo('edit event chain');

        $deleteUser = User::factory()->create();
        $deleteUser->givePermissionTo('delete event chain');

        $data = [
            'title' => 'Daily Training',
            'recurrence_type' => 'daily',
            'interval' => null,
            'interval_unit' => null,
            'day_of_week' => null,
            'day_of_month' => null,
            'start_date' => '2099-01-06',
            'end_date' => '2099-01-07',
            'start_time' => '18:00',
            'end_time' => '20:00',
            'location_id' => null,
            'content_id' => null,
            'custom_body' => null,
        ];

        // Test create event chain
        $response = $this->actingAs($createUser)->post(route('bo.event-chains.store'), $data);
        $response->assertRedirect(route('bo.event-chains.index'));
        $this->assertDatabaseHas('event_chains', [
            'title' => 'Daily Training',
            'recurrence_type' => 'daily',
        ]);
        $chain = EventChain::first();

        // Test edit event chain
        $response = $this->actingAs($editUser)->put(route('bo.event-chains.update', $chain), array_merge($data, [
            'title' => 'Updated Training',
            'update_scope' => 'all',
            'from_date' => null,
        ]));
        $response->assertRedirect(route('bo.event-chains.show', $chain));
        $this->assertDatabaseHas('event_chains', ['title' => 'Updated Training']);
        $this->assertDatabaseMissing('event_chains', ['title' => 'Daily Training']);

        // Test delete event chain
        $response = $this->actingAs($deleteUser)->delete(route('bo.event-chains.destroy', $chain));
        $response->assertRedirect(route('bo.event-chains.index'));
        $this->assertDatabaseMissing('event_chains', ['title' => 'Updated Training']);
    }

    public function test_event_chains_crud_nok_permissions(): void
    {
        $createUser = User::factory()->create();
        $createUser->givePermissionTo('create event chain');

        $editUser = User::factory()->create();
        $editUser->givePermissionTo('edit event chain');

        $deleteUser = User::factory()->create();
        $deleteUser->givePermissionTo('delete event chain');

        $data = [
            'title' => 'Daily Training',
            'recurrence_type' => 'daily',
            'interval' => null,
            'interval_unit' => null,
            'day_of_week' => null,
            'day_of_month' => null,
            'start_date' => '2099-01-06',
            'end_date' => '2099-01-07',
            'start_time' => '18:00',
            'end_time' => '20:00',
            'location_id' => null,
            'content_id' => null,
            'custom_body' => null,
        ];

        // Test create event chain without permission
        $response = $this->actingAs($editUser)->post(route('bo.event-chains.store'), $data);
        $response->assertStatus(403);
        $this->assertDatabaseMissing('event_chains', ['title' => 'Daily Training']);

        // Test edit event chain without permission
        $chain = EventChain::factory()->withoutEvents()->create();
        $response = $this->actingAs($deleteUser)->put(route('bo.event-chains.update', $chain), array_merge($data, [
            'title' => 'Updated Training',
            'update_scope' => 'all',
            'from_date' => null,
        ]));
        $response->assertStatus(403);
        $this->assertDatabaseMissing('event_chains', ['title' => 'Updated Training']);
        $this->assertDatabaseHas('event_chains', ['title' => $chain->title]);

        // Test delete event chain without permission
        $response = $this->actingAs($createUser)->delete(route('bo.event-chains.destroy', $chain));
        $response->assertStatus(403);
        $this->assertDatabaseHas('event_chains', ['title' => $chain->title]);
    }
}
