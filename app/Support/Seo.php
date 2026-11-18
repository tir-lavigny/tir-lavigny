<?php

namespace App\Support;

use App\Models\Event;
use App\Models\Post;
use Illuminate\Support\Str;

class Seo
{
    private const NON_INDEXABLE_PATHS = [
        'back-office',
        'back-office/*',
        'member-registration',
        'member-registration/*',
        'login',
        'register',
        'forgot-password',
        'reset-password',
        'reset-password/*',
        'verify-email',
        'verify-email/*',
        'confirm-password',
        'docs/*',
        '_scramble/*',
        '_boost/*',
    ];

    public static function title(?string $title): string
    {
        $title = trim((string) $title);
        $appName = (string) config('app.name');

        if ($title === '') {
            return $appName;
        }

        $brandNames = array_filter([$appName, 'Société de tir de Lavigny']);

        foreach ($brandNames as $brandName) {
            if (Str::contains(Str::lower($title), Str::lower($brandName))) {
                return $title;
            }
        }

        return "{$title} - {$appName}";
    }

    public static function robots(): string
    {
        if (! app()->isProduction() || request()->is(self::NON_INDEXABLE_PATHS)) {
            return 'noindex,nofollow';
        }

        return 'index,follow';
    }

    public static function canonical(): string
    {
        return url()->current();
    }

    /**
     * @return array<string, mixed>
     */
    public static function organization(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'SportsOrganization',
            '@id' => url('/').'#organization',
            'name' => config('app.name'),
            'url' => url('/'),
            'logo' => asset('favicon-32x32.png'),
            'image' => asset('images/hero.webp'),
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => 'GC2C+J3',
                'postalCode' => '1175',
                'addressLocality' => 'Lavigny',
                'addressCountry' => 'CH',
            ],
            'geo' => [
                '@type' => 'GeoCoordinates',
                'latitude' => 46.501453,
                'longitude' => 6.419919,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function website(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            '@id' => url('/').'#website',
            'name' => config('app.name'),
            'url' => url('/'),
            'inLanguage' => 'fr-CH',
            'publisher' => [
                '@id' => url('/').'#organization',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function event(Event $event, ?string $description = null): array
    {
        $event->loadMissing('location');

        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Event',
            'name' => $event->title,
            'url' => route('events.show', $event),
            'eventStatus' => 'https://schema.org/EventScheduled',
            'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
            'image' => asset('images/hero.webp'),
            'organizer' => [
                '@id' => url('/').'#organization',
            ],
        ];

        $data['startDate'] = $event->start_datetime->toIso8601String();

        if ($event->end_datetime) {
            $data['endDate'] = $event->end_datetime->toIso8601String();
        }

        if ($description) {
            $data['description'] = $description;
        }

        if ($event->location) {
            $data['location'] = [
                '@type' => 'Place',
                'name' => $event->location->name,
                'address' => $event->location->address,
                'geo' => [
                    '@type' => 'GeoCoordinates',
                    'latitude' => $event->location->latitude,
                    'longitude' => $event->location->longitude,
                ],
            ];
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public static function post(Post $post, ?string $description = null): array
    {
        $url = route('posts.show', $post->public_id);

        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            'headline' => $post->title,
            'url' => $url,
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id' => $url,
            ],
            'image' => asset('images/hero.webp'),
            'inLanguage' => 'fr-CH',
            'publisher' => [
                '@id' => url('/').'#organization',
            ],
        ];

        $data['datePublished'] = $post->publish_at->toIso8601String();

        if ($post->updated_at) {
            $data['dateModified'] = $post->updated_at->toIso8601String();
        }

        if ($description) {
            $data['description'] = $description;
        }

        return $data;
    }

    /**
     * @param  array<int, array{title: string, href?: string}>  $breadcrumbs
     * @return array<string, mixed>
     */
    public static function breadcrumbs(array $breadcrumbs): array
    {
        $items = [];

        foreach (array_values($breadcrumbs) as $index => $breadcrumb) {
            $item = [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $breadcrumb['title'],
            ];

            if (! empty($breadcrumb['href'])) {
                $item['item'] = $breadcrumb['href'];
            }

            $items[] = $item;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $items,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function jsonLd(array $data): string
    {
        $json = json_encode(
            $data,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );

        if ($json === false) {
            return '';
        }

        return '<script type="application/ld+json">'.$json.'</script>';
    }
}
