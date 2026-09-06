<?php

namespace Tests\Feature\BackOffice;

use App\Models\Content;
use App\Models\Event;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentTest extends TestCase
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

    public function test_can_create_content(): void
    {
        $data = [
            'title' => 'Test Content',
            'body' => '# Test Content Body',
        ];

        $response = $this->post(route('contents.store'), $data);

        $response->assertStatus(302);
        $this->assertDatabaseHas('contents', $data);
    }

    public function test_can_not_create_content_with_invalid_data(): void
    {
        $data = [
            'title' => '',
            'body' => '',
        ];

        $response = $this->post(route('contents.store'), $data);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['title', 'body']);
    }

    public function test_can_not_create_content_with_duplicate_title(): void
    {
        $existingContent = Content::factory()->create(['title' => 'Unique Title']);

        $data = [
            'title' => 'Unique Title',
            'body' => '# Test Content Body',
        ];

        $response = $this->post(route('contents.store'), $data);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['title']);
    }

    public function test_unauthorized_user_cannot_access_contents(): void
    {
        $this->post('/logout');

        $response = $this->get(route('contents.index'));
        $response->assertRedirect('/login');
    }

    public function test_unauthorized_user_cannot_create_content(): void
    {
        $this->post('/logout');

        $data = [
            'title' => 'Test Content',
            'body' => '# Test Content Body',
        ];

        $response = $this->post(route('contents.store'), $data);
        $response->assertRedirect('/login');

        // Check that the content was not created in the database
        $this->assertDatabaseMissing('contents', $data);
    }

    public function test_unauthorized_user_cannot_update_content(): void
    {
        $this->post('/logout');

        $content = Content::factory()->create();

        $data = [
            'title' => 'Updated Title',
            'body' => '# Updated Content Body',
        ];
        $response = $this->put(route('contents.update', $content), $data);
        $response->assertRedirect('/login');

        // Check that the content was not updated in the database
        $this->assertDatabaseMissing('contents', [
            'id' => $content->id,
            'title' => 'Updated Title',
            'body' => '# Updated Content Body',
        ]);
    }

    public function test_can_read_contents(): void
    {
        $content = Content::factory()->create();

        $response = $this->get(route('contents.index'));

        $response->assertStatus(200);

        $this->assertDatabaseHas('contents', [
            'id' => $content->id,
            'title' => $content->title,
            'body' => $content->body,
        ]);
    }

    public function test_can_update_content(): void
    {
        $content = Content::factory()->create();

        $data = [
            'title' => 'Updated Title',
            'body' => '# Updated Content Body',
        ];

        $response = $this->put(route('contents.update', $content), $data);

        $response->assertStatus(302);
        $this->assertDatabaseHas('contents', $data);
    }

    public function test_cannot_update_non_existent_content(): void
    {
        $data = [
            'title' => 'Updated Title',
            'body' => '# Updated Content Body',
        ];

        $response = $this->put(route('contents.update', 999), $data);

        $response->assertStatus(404);
    }

    public function test_can_delete_content(): void
    {
        $content = Content::factory()->create();

        $response = $this->delete(route('contents.destroy', $content));

        $response->assertStatus(302);
        $this->assertDatabaseMissing('contents', [
            'id' => $content->id,
            'title' => $content->title,
            'body' => $content->body,
        ]);
    }

    public function test_cannot_delete_non_existent_content(): void
    {
        $response = $this->delete(route('contents.destroy', 999));

        $response->assertStatus(404);
    }

    public function test_title_is_required(): void
    {
        $data = [
            'title' => '',
            'body' => '# Test Content Body',
        ];

        $response = $this->post(route('contents.store'), $data);

        $response->assertSessionHasErrors(['title']);
    }

    public function test_body_is_required(): void
    {
        $data = [
            'title' => 'Test Content',
            'body' => '',
        ];

        $response = $this->post(route('contents.store'), $data);

        $response->assertSessionHasErrors(['body']);
    }

    public function test_title_must_be_unique(): void
    {
        $existingContent = Content::factory()->create(['title' => 'Unique Title']);

        $data = [
            'title' => 'Unique Title',
            'body' => '# Test Content Body',
        ];

        $response = $this->post(route('contents.store'), $data);

        $response->assertSessionHasErrors(['title']);
    }

    public function test_title_and_body_must_be_string(): void
    {
        $data = [
            'title' => 123,
            'body' => 456,
        ];

        $response = $this->post(route('contents.store'), $data);

        $response->assertSessionHasErrors(['title', 'body']);
    }

    public function test_title_and_body_must_not_exceed_max_length(): void
    {
        $data = [
            'title' => str_repeat('a', 256),
            'body' => str_repeat('a', 65536),
        ];

        $response = $this->post(route('contents.store'), $data);

        $response->assertSessionHasErrors(['title', 'body']);
    }

    public function test_title_and_body_must_not_exceed_max_length_on_update(): void
    {
        $content = Content::factory()->create();

        $data = [
            'title' => str_repeat('a', 256),
            'body' => str_repeat('a', 65536),
        ];

        $response = $this->put(route('contents.update', $content), $data);

        $response->assertSessionHasErrors(['title', 'body']);
    }

    public function test_cannot_update_content_with_duplicate_title(): void
    {
        $existingContent = Content::factory()->create(['title' => 'Unique Title']);
        $contentToUpdate = Content::factory()->create(['title' => 'Another Title']);

        $data = [
            'title' => 'Unique Title',
            'body' => '# Updated Content Body',
        ];

        $response = $this->put(route('contents.update', $contentToUpdate), $data);

        $response->assertSessionHasErrors(['title']);
    }

    public function test_cannot_delete_content_with_associated_events(): void
    {
        $content = Content::factory()->create();
        $event = Event::factory()->create();
        $event->content()->associate($content);
        $event->save();

        $content->refresh();
        $this->assertNotNull($content->events);

        $response = $this->delete(route('contents.destroy', $content->id));

        $response->assertStatus(302);
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('contents', [
            'id' => $content->id,
            'title' => $content->title,
            'body' => $content->body,
        ]);
    }
}
