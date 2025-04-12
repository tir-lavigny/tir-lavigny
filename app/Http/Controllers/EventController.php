<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Request;
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
        $contnent = $event->getEffectiveContent();

        return Inertia::render('Events', [
            'event' => $event,
            'content' => $contnent,
        ]);
    }
}
