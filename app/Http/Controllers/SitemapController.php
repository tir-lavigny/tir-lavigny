<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Post;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $eventsUpdatedAt = Event::max('updated_at');

        $urls = [
            $this->url(route('home')),
            $this->url(route('events'), $eventsUpdatedAt),
            $this->url(route('shootings'), $eventsUpdatedAt),
            $this->url(route('shootings.field'), $eventsUpdatedAt),
            $this->url(route('become-member')),
            $this->url(route('privacy-policy')),
        ];

        Event::query()
            ->where('start_datetime', '>=', now()->startOfYear())
            ->orderBy('start_datetime')
            ->get(['id', 'updated_at'])
            ->each(function (Event $event) use (&$urls) {
                $urls[] = $this->url(route('events.show', $event), $event->updated_at);
            });

        Post::published()
            ->orderBy('publish_at')
            ->get(['public_id', 'updated_at'])
            ->each(function (Post $post) use (&$urls) {
                $urls[] = $this->url(route('posts.show', $post->public_id), $post->updated_at);
            });

        return response()
            ->view('sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml; charset=UTF-8')
            ->header('Cache-Control', 'public, max-age=3600');
    }

    /**
     * @return array{loc: string, lastmod: string|null}
     */
    private function url(string $loc, mixed $lastmod = null): array
    {
        return [
            'loc' => $loc,
            'lastmod' => $lastmod === null ? null : Carbon::parse($lastmod)->toAtomString(),
        ];
    }
}
