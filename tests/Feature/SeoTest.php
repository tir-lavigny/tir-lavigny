<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_renders_server_side_seo_tags(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('<html lang="fr"', false)
            ->assertSee('<title data-inertia="server-head-0">'.config('app.name').'</title>', false)
            ->assertSee('name="description"', false)
            ->assertSee('name="robots" content="noindex,nofollow"', false)
            ->assertSee('rel="canonical" href="'.url('/').'"', false)
            ->assertSee('property="og:locale" content="fr_CH"', false)
            ->assertSee('"@type":"SportsOrganization"', false)
            ->assertSee('"@type":"WebSite"', false)
            ->assertSee('"inLanguage":"fr-CH"', false);
    }

    public function test_public_pages_share_a_server_head_prop(): void
    {
        $this->get('/events')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('head')
                ->where('head.0', fn ($tag) => str_contains($tag, '<title'))
            );
    }

    public function test_event_page_uses_event_title_and_event_structured_data(): void
    {
        $event = Event::factory()->create([
            'title' => 'Tir obligatoire de printemps',
            'start_datetime' => now()->addDay(),
        ]);

        $this->get(route('events.show', $event))
            ->assertOk()
            ->assertSee('<title data-inertia="server-head-0">Tir obligatoire de printemps - '.config('app.name').'</title>', false)
            ->assertSee('"@type":"Event"', false)
            ->assertSee('"@type":"BreadcrumbList"', false)
            ->assertSee('"name":"Tir obligatoire de printemps"', false);
    }

    public function test_post_page_uses_post_title_and_post_structured_data(): void
    {
        $post = Post::factory()->create([
            'title' => 'Ouverture de la saison',
            'publish_at' => now()->subHour(),
        ]);

        $this->get(route('posts.show', $post->public_id))
            ->assertOk()
            ->assertSee('<title data-inertia="server-head-0">Ouverture de la saison - '.config('app.name').'</title>', false)
            ->assertSee('"@type":"BlogPosting"', false)
            ->assertSee('"headline":"Ouverture de la saison"', false)
            ->assertSee('"author":{"@type":"Organization"', false);
    }

    public function test_sitemap_lists_public_pages_and_published_posts(): void
    {
        $event = Event::factory()->create(['start_datetime' => now()->addDay()]);
        $post = Post::factory()->create(['publish_at' => now()->subHour()]);
        $expiredPost = Post::factory()->create([
            'publish_at' => now()->subHour(),
            'expire_at' => now()->subHour(),
        ]);
        $futurePost = Post::factory()->create(['publish_at' => now()->addDay()]);

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertHeaderMissing('Set-Cookie')
            ->assertSee(route('home'), false)
            ->assertSee(route('events'), false)
            ->assertSee(route('events.show', $event), false)
            ->assertSee(route('shootings'), false)
            ->assertSee(route('shootings.field'), false)
            ->assertSee(route('posts.show', $post->public_id), false)
            ->assertDontSee(route('posts.show', $expiredPost->public_id), false)
            ->assertDontSee(route('posts.show', $futurePost->public_id), false)
            ->assertDontSee('/back-office', false)
            ->assertDontSee('/member-registration', false);
    }

    public function test_robots_references_sitemap_and_blocks_private_paths_in_production(): void
    {
        app()->detectEnvironment(fn () => 'production');

        $this->get('/robots.txt')
            ->assertOk()
            ->assertHeaderMissing('Set-Cookie')
            ->assertSee('Sitemap: '.route('sitemap'))
            ->assertSee('Disallow: /back-office/')
            ->assertSee('Disallow: /member-registration/')
            ->assertSee('Disallow: /api/')
            ->assertSee('Disallow: /docs/')
            ->assertSee('Disallow: /_scramble/')
            ->assertSee('Disallow: /_boost/');
    }

    public function test_robots_disallows_all_outside_production(): void
    {
        $this->get('/robots.txt')
            ->assertOk()
            ->assertSee("User-agent: *\nDisallow: /");
    }

    public function test_private_pages_are_noindex_in_production(): void
    {
        app()->detectEnvironment(fn () => 'production');

        $this->get('/login')
            ->assertOk()
            ->assertSee('name="robots" content="noindex,nofollow"', false);
    }

    public function test_error_pages_are_noindex_in_production(): void
    {
        app()->detectEnvironment(fn () => 'production');

        $this->get('/missing-page')
            ->assertNotFound()
            ->assertSee('name="robots" content="noindex,nofollow"', false)
            ->assertSee('<title data-inertia="server-head-0">Erreur 404 - '.config('app.name').'</title>', false);
    }

    public function test_public_page_titles_include_the_brand_once(): void
    {
        $this->get('/become-member')
            ->assertSee('<title data-inertia="server-head-0">Devenir membre - Société de tir de Lavigny</title>', false);

        $this->get('/privacy-policy')
            ->assertSee('<title data-inertia="server-head-0">Déclaration de protection des données - Société de tir de Lavigny</title>', false);
    }

    public function test_public_pages_share_breadcrumbs(): void
    {
        $this->get('/events')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('breadcrumbs', 2)
                ->where('breadcrumbs.0.title', 'Accueil')
                ->where('breadcrumbs.1.title', 'Événements')
            );
    }
}
