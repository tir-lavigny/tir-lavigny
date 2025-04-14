<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Inertia\Inertia;

class ShootingController extends Controller
{
    public function index()
    {
        $title = 'Tir militaire';
        $events = Event::whereLike('title', '%'.$title.'%', false)
            ->where('start_datetime', '>=', now())
            ->with('location')
            ->orderBy('start_datetime', 'asc')
            ->get();

        return Inertia::render('Shootings', [
            'events' => $events,
        ]);
    }
}
