<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Support\MarkdownText;
use App\Support\Seo;
use Inertia\Response;
use Spatie\IcalendarGenerator\Components\Calendar as IcalCalendar;
use Spatie\IcalendarGenerator\Components\Event as IcalEvent;

class EventController extends Controller
{
    public function index(): Response
    {
        $events = Event::with('location')->where('start_datetime', '>=', now()->startOfYear())
            ->orderBy('start_datetime')
            ->get();

        return $this->renderInertia('Events', [
            'events' => $events,
            'og' => [
                'title' => 'Événements - '.config('app.name'),
                'description' => 'Consultez le calendrier des événements de la Société de tir de Lavigny: tirs obligatoires, compétitions et activités.',
                'image' => asset('/images/hero.webp'),
                'type' => 'website',
            ],
            'breadcrumbs' => [
                ['title' => 'Accueil', 'href' => route('home')],
                ['title' => 'Événements', 'href' => route('events')],
            ],
        ]);
    }

    public function show(Event $event): Response
    {
        $event->load('location');
        $content = $event->getEffectiveContent();
        $description = MarkdownText::toPlainText($content) ?: sprintf(
            '%s le %s%s',
            $event->title,
            $event->start_datetime->format('d.m.Y'),
            $event->location ? ' - '.$event->location->name : ''
        );

        return $this->renderInertia('Event', [
            'event' => $event,
            'content' => $content,
            'og' => [
                'title' => $event->title,
                'description' => $description,
                'image' => asset('/images/hero.webp'),
                'type' => 'event',
            ],
            'json_ld' => [
                Seo::event($event, $description),
            ],
            'breadcrumbs' => [
                ['title' => 'Accueil', 'href' => route('home')],
                ['title' => 'Événements', 'href' => route('events')],
                ['title' => $event->title, 'href' => route('events.show', $event)],
            ],
        ]);
    }

    public function feed_calendar(): \Illuminate\Http\Response
    {
        $startDate = now()->subMonths(3); // 3 months ago
        $endDate = now()->addMonths(12);  // 1 year from now

        $calendar = IcalCalendar::create(config('app.name', 'Tir Lavigny').' événements')
            ->refreshInterval(60); // Suggests to Google/Apple to check for updates every 60 mins

        $events = Event::whereBetween('start_datetime', [$startDate, $endDate])
            ->orderBy('start_datetime')
            ->with('location')
            ->get();

        foreach ($events as $item) {
            $start = $item->start_datetime;

            $end = $item->end_datetime
                   ? $item->end_datetime
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
