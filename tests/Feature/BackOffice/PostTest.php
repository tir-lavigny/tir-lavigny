<?php

namespace Tests\Feature\BackOffice;

use App\Models\Post;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PostTest extends TestCase
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

    public function test_can_create_post(): void
    {
        $data = [
            'title' => 'Test Post',
            'content' => 'This is a test post content.',
            'publish_at' => now()->toDateTimeString(),
            'expire_at' => now()->addDays(10)->toDateTimeString(),
        ];

        $response = $this->post(route('bo.posts.store'), $data);

        $response->assertStatus(302);
        $this->assertDatabaseHas('posts', [
            'title' => $data['title'],
            'content' => $data['content'],
            'publish_at' => $this->toUtc($data['publish_at']),
            'expire_at' => $this->toUtc($data['expire_at']),
        ]);
    }

    public function test_title_is_required(): void
    {
        $data = [
            'title' => '',
            'content' => 'This is a test post content.',
            'publish_at' => now()->toDateTimeString(),
            'expire_at' => now()->addDays(10)->toDateTimeString(),
        ];

        $response = $this->post(route('bo.posts.store'), $data);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['title']);
        $this->assertDatabaseMissing('posts', [
            'content' => $data['content'],
        ]);
    }

    public function test_content_is_required(): void
    {
        $data = [
            'title' => 'Test Post',
            'content' => '',
            'publish_at' => now()->toDateTimeString(),
            'expire_at' => now()->addDays(10)->toDateTimeString(),
        ];

        $response = $this->post(route('bo.posts.store'), $data);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['content']);
        $this->assertDatabaseMissing('posts', [
            'title' => $data['title'],
        ]);
    }

    public function test_publish_at_is_required(): void
    {
        $data = [
            'title' => 'Test Post',
            'content' => 'This is a test post content.',
            'publish_at' => '',
            'expire_at' => now()->addDays(10)->toDateTimeString(),
        ];

        $response = $this->post(route('bo.posts.store'), $data);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['publish_at']);
        $this->assertDatabaseMissing('posts', [
            'title' => $data['title'],
        ]);
    }

    public function test_can_create_post_without_expire_at(): void
    {
        $data = [
            'title' => 'Test Post Without Expiry',
            'content' => 'This is a test post content without expiry.',
            'publish_at' => now()->toDateTimeString(),
            'expire_at' => null,
        ];

        $response = $this->post(route('bo.posts.store'), $data);

        $response->assertStatus(302);
        $this->assertDatabaseHas('posts', [
            'title' => $data['title'],
            'content' => $data['content'],
            'publish_at' => $this->toUtc($data['publish_at']),
            'expire_at' => null,
        ]);
    }

    public function test_can_not_create_post_with_invalid_data(): void
    {
        $data = [
            'title' => '', // Title is required
            'content' => 'This is a test post content.',
            'publish_at' => 'invalid-date', // Invalid date format
            'expire_at' => now()->addDays(10)->toDateTimeString(),
        ];

        $response = $this->post(route('bo.posts.store'), $data);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['title', 'publish_at']);
        $this->assertDatabaseMissing('posts', [
            'content' => $data['content'],
        ]);
    }

    public function test_can_not_create_post_with_date_logic_error(): void
    {
        $data = [
            'title' => 'Test Post with Date Logic Error',
            'content' => 'This is a test post content.',
            'publish_at' => now()->addDays(10)->toDateTimeString(),
            'expire_at' => now()->toDateTimeString(), // Expire date is before publish date
        ];

        $response = $this->post(route('bo.posts.store'), $data);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['expire_at']);
        $this->assertDatabaseMissing('posts', [
            'title' => $data['title'],
        ]);
    }

    public function test_can_view_post_list(): void
    {
        $response = $this->get(route('bo.posts.index'));

        $response->assertStatus(200);
    }

    public function test_can_view_specific_post(): void
    {
        $post = Post::factory()->create();

        $response = $this->get(route('bo.posts.edit', $post->public_id));

        $response->assertStatus(200);
        $response->assertSee($post->title);
    }

    public function test_can_not_view_post_using__internal_id(): void
    {
        $post = Post::factory()->create();

        $response = $this->get(route('bo.posts.edit', $post->id));

        $response->assertStatus(404);
    }

    public function test_can_not_view_post_non_existing_public_id(): void
    {
        $response = $this->get(route('bo.posts.edit', 9999));

        $response->assertStatus(404);
    }

    public function test_guest_is_redirected_to_login_on_post_list(): void
    {
        $this->post('/logout');

        $response = $this->get(route('bo.posts.index'));
        $response->assertRedirect('/login');
    }

    public function test_guest_is_redirected_to_login_on_post_show(): void
    {
        $this->post('/logout');

        $post = Post::factory()->create();

        $response = $this->get(route('bo.posts.show', $post->public_id));
        $response->assertRedirect('/login');
    }

    public function test_guest_is_redirected_to_login_on_post_create(): void
    {
        $this->post('/logout');

        $response = $this->get(route('bo.posts.create'));
        $response->assertRedirect('/login');
    }

    public function test_guest_is_redirected_to_login_on_post_edit(): void
    {
        $this->post('/logout');

        $post = Post::factory()->create();

        $response = $this->get(route('bo.posts.edit', $post->id));
        $response->assertRedirect('/login');
    }

    public function test_guest_is_redirected_to_login_on_post_store(): void
    {
        $this->post('/logout');

        $data = [
            'title' => 'Test Post',
            'content' => 'This is a test post content.',
            'publish_at' => now()->toDateTimeString(),
            'expire_at' => now()->addDays(10)->toDateTimeString(),
        ];

        $response = $this->post(route('bo.posts.store'), $data);
        $response->assertRedirect('/login');
    }

    public function test_guest_is_redirected_to_login_on_post_update(): void
    {
        $this->post('/logout');

        $post = Post::factory()->create();

        $data = [
            'title' => 'Updated Title',
            'content' => 'Updated content.',
            'publish_at' => now()->toDateTimeString(),
            'expire_at' => now()->addDays(5)->toDateTimeString(),
        ];

        $response = $this->put(route('bo.posts.update', $post->id), $data);
        $response->assertRedirect('/login');
    }

    public function test_can_update_post(): void
    {
        $post = Post::factory()->create();

        $data = [
            'title' => 'Updated Title',
            'content' => 'Updated content.',
            'publish_at' => now()->toDateTimeString(),
            'expire_at' => now()->addDays(5)->toDateTimeString(),
        ];

        $response = $this->put(route('bo.posts.update', $post->public_id), $data);

        $response->assertStatus(302);
        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'title' => 'Updated Title',
            'content' => 'Updated content.',
            'publish_at' => $this->toUtc($data['publish_at']),
            'expire_at' => $this->toUtc($data['expire_at']),
        ]);
    }

    public function test_can_not_update_post_with_invalid_data(): void
    {
        $post = Post::factory()->create();

        $data = [
            'title' => '', // Title is required
            'content' => 'Updated content.',
            'publish_at' => 'invalid-date', // Invalid date format
            'expire_at' => now()->addDays(5)->toDateTimeString(),
        ];

        $response = $this->put(route('bo.posts.update', $post->public_id), $data);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['title', 'publish_at']);
        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'title' => $post->title, // Original title remains unchanged
        ]);
    }

    public function test_can_delete_post(): void
    {
        $post = Post::factory()->create();

        $response = $this->delete(route('bo.posts.destroy', $post->public_id));

        $response->assertStatus(302);
        $this->assertDatabaseMissing('posts', [
            'id' => $post->id,
        ]);
    }

    public function test_index_filters_posts_by_search(): void
    {
        Post::factory()->create(['title' => 'Only Matching Event']);
        Post::factory()->create(['title' => 'Non-Matching Event']);

        $response = $this->get(route('bo.posts.index', ['search' => 'Only']));

        $response->assertStatus(200);
        $response->assertSee('Only Matching Event');
        $response->assertDontSee('Non-Matching Event');
    }

    public function test_index_searches_posts_without_accents(): void
    {
        Post::factory()->create(['title' => 'Fête annuelle']);
        Post::factory()->create(['title' => 'Tir sportif']);

        $response = $this->get(route('bo.posts.index', ['search' => 'fete']));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('paginatedPosts.data', 1)
            ->where('paginatedPosts.data.0.title', 'Fête annuelle')
        );
    }

    public function test_index_pagination_works(): void
    {
        Post::factory()->count(15)->create();

        $response = $this->get(route('bo.posts.index', ['page' => 2]));

        $response->assertStatus(200);
        $response->assertSee('Posts');
    }

    public function test_store_creates_post_with_minimal_data(): void
    {
        $data = [
            'title' => 'Minimal Post',
            'content' => 'Content with minimal data.',
            'publish_at' => now()->toDateTimeString(),
        ];

        $response = $this->post(route('bo.posts.store'), $data);

        $response->assertStatus(302);
        $this->assertDatabaseHas('posts', [
            'title' => $data['title'],
            'content' => $data['content'],
            'publish_at' => $this->toUtc($data['publish_at']),
            'expire_at' => null,
        ]);
    }

    public function test_show_displays_post_details(): void
    {
        $post = Post::factory()->create();

        $response = $this->get(route('bo.posts.show', $post->public_id));

        $response->assertStatus(200);
        $response->assertSee($post->title);
    }

    public function test_update_modifies_post_with_valid_data(): void
    {
        $post = Post::factory()->create();

        $data = [
            'title' => 'Modified Title',
            'content' => 'Modified content.',
            'publish_at' => now()->toDateTimeString(),
            'expire_at' => now()->addDays(7)->toDateTimeString(),
        ];

        $response = $this->put(route('bo.posts.update', $post->public_id), $data);

        $response->assertStatus(302);
        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'title' => 'Modified Title',
            'content' => 'Modified content.',
            'publish_at' => $this->toUtc($data['publish_at']),
            'expire_at' => $this->toUtc($data['expire_at']),
        ]);
    }

    public function test_destroy_deletes_post(): void
    {
        $post = Post::factory()->create();

        $response = $this->delete(route('bo.posts.destroy', $post->public_id));

        $response->assertStatus(302);
        $this->assertDatabaseMissing('posts', [
            'id' => $post->id,
        ]);
    }

    public function test_cannot_delete_non_existing_post(): void
    {
        $response = $this->delete(route('bo.posts.destroy', 9999));

        $response->assertStatus(404);
    }
}
