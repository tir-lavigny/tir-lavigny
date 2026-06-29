<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Inertia\Inertia;

class ShootingController extends Controller
{
    private function getShootingEvents(string $title)
    {
        return Event::whereLike('title', '%'.$title.'%', false)
            ->where('start_datetime', '>=', now())
            ->with('location')
            ->orderBy('start_datetime', 'asc')
            ->get();
    }

    public function indexODS()
    {
        $events = $this->getShootingEvents('Tir militaire');

        return Inertia::render('Shootings', [
            'events' => $events,
        ]);
    }

    public function indexField()
    {
        $events = $this->getShootingEvents('Tir fédéral en campagne');

        return Inertia::render('Shootings', [
            'events' => $events,
        ]);
    }
}
