<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Post;
use Inertia\Inertia;

class HomeController extends Controller
{
    public function index()
    {
        $events = Event::with('location')->where('start_datetime', '>=', now())->orderBy('start_datetime')->take(3)->get();
        $posts = Post::where('publish_at', '<=', now())
            ->where(function ($query) {
                $query->whereNull('expire_at')->orWhere('expire_at', '>', now());
            })
            ->orderBy('publish_at', 'desc')
            ->get();

        return Inertia::render('Home', [
            'events' => $events,
            'posts' => $posts,
            'og' => [
                'title' => config('app.name'),
                'description' => 'Société de tir de Lavigny - Tirs militaire, campagne et sportif',
                'image' => asset('/images/hero.jpg'),
                'type' => 'website',
            ],
        ]);
    }
}
