<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Spatie\IcalendarGenerator\Components\Calendar as IcalCalendar;
use Spatie\IcalendarGenerator\Components\Event as IcalEvent;

class EventController extends Controller
{
    public function index()
    {
        $events = Event::with('location')->where('start_datetime', '>=', now()->startOfYear())
            ->orderBy('start_datetime')
            ->get();

        return Inertia::render('Events', [
            'events' => $events,
            'og' => [
                'title' => 'Événements - '.config('app.name'),
                'description' => 'Consultez le calendrier des événements de la Société de tir de Lavigny: tirs obligatoires, compétitions et activités.',
                'image' => asset('/images/hero.jpg'),
                'type' => 'website',
            ],
        ]);
    }

    public function show($id)
    {
        $event = Event::with('location')->findOrFail($id);
        $content = $event->getEffectiveContent();

        return Inertia::render('Event', [
            'event' => $event,
            'content' => $content,
            'og' => [
                'title' => $event->title,
                'description' => $this->cleanOgDescription($content),
                'image' => asset('/images/hero.jpg'),
                'type' => 'event',
            ],
        ]);
    }

    private function cleanOgDescription(?string $html): string
    {
        $text = strip_tags($html ?? '');
        $text = preg_replace('/#{1,6}\s/', '', $text);
        $text = preg_replace('/\*\*|__/', '', $text);
        $text = preg_replace('/\*|_/', '', $text);
        $text = preg_replace('/\[(.+?)\]\(.+?\)/', '$1', $text);
        $text = preg_replace('/!\[.*?\]\(.+?\)/', '', $text);
        $text = preg_replace('/`{1,3}[^`]*`{1,3}/', '', $text);
        $text = preg_replace('/\s+/', ' ', $text);

        return Str::limit(trim($text), 160, '...');
    }

    public function feed_calendar()
    {
        $startDate = now()->subMonths(3); // 3 months ago
        $endDate = now()->addMonths(12);  // 1 year from now

        $calendar = IcalCalendar::create(env('APP_NAME', 'Tir Lavigny').' événements')
            ->refreshInterval(60); // Suggests to Google/Apple to check for updates every 60 mins

        $events = Event::whereBetween('start_datetime', [$startDate, $endDate])
            ->orderBy('start_datetime')
            ->with('location')
            ->get();

        foreach ($events as $item) {
            $start = Carbon::parse($item->start_datetime->format('Y-m-d H:i:s'), 'Europe/Zurich')->utc();

            $end = $item->end_datetime
                   ? Carbon::parse($item->end_datetime->format('Y-m-d H:i:s'), 'Europe/Zurich')->utc()
                   : $start->copy()->addHour();

            $icalEvent = IcalEvent::create($item->title)
                ->uniqueIdentifier('event-'.$item->id)
                ->startsAt($start)
                ->endsAt($end)
                ->url(route('events.show', $item->id));

            if ($item->location?->address) {
                $icalEvent->address($item->location->address);
            }

            $calendar->event($icalEvent);
        }

        return response($calendar->get())
            ->header('Content-Type', 'text/calendar; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="calendar.ics"');
    }
}
