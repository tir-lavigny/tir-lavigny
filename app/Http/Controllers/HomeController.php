<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Event;

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
