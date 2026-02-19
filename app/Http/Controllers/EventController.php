<?php

namespace App\Http\Controllers;

use App\Models\Event;
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
        ]);
    }

    public function show($id)
    {
        $event = Event::with('location')->findOrFail($id);
        $content = $event->getEffectiveContent();

        return Inertia::render('Event', [
            'event' => $event,
            'content' => $content,
        ]);
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
            $icalEvent = IcalEvent::create($item->title)
                ->uniqueIdentifier('event-'.$item->id)
                ->startsAt($item->start_datetime)
                ->url(route('events.show', $item->id));

            if ($item->end_datetime) {
                $icalEvent->endsAt($item->end_datetime);
            }

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
