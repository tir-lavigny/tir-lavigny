<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Post;
use Inertia\Response;

class HomeController extends Controller
{
    public function index(): Response
    {
        $events = Event::with('location')->where('start_datetime', '>=', now())->orderBy('start_datetime')->take(3)->get();
        $posts = Post::published()
            ->orderBy('publish_at', 'desc')
            ->get();

        return $this->renderInertia('Home', [
            'events' => $events,
            'posts' => $posts,
            'og' => [
                'title' => config('app.name'),
                'description' => 'Société de tir de Lavigny - Tirs militaire, campagne et sportif',
                'image' => asset('/images/hero.webp'),
                'type' => 'website',
            ],
        ]);
    }
}
