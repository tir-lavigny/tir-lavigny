<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Inertia\Inertia;

class HomeController extends Controller
{
    public function index()
    {
        $events = Event::with('location')->where('start_datetime', '>=', now())->orderBy('start_datetime')->take(3)->get();

        return Inertia::render('Home', [
            'events' => $events,
        ]);
    }
}
