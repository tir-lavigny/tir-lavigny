<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Inertia\Inertia;

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
}
