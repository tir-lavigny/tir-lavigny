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
            'og' => [
                'title' => 'Tirs militaires - '.config('app.name'),
                'description' => 'Venez effectuer vos tirs obligatoires au stand de Lavigny.',
                'image' => asset('/images/tirMilitaire.jpg'),
                'type' => 'website',
            ],
        ]);
    }

    public function indexField()
    {
        $events = $this->getShootingEvents('Tir fédéral en campagne');

        return Inertia::render('ShootingsField', [
            'events' => $events,
            'og' => [
                'title' => 'Tir fédéral en campagne - '.config('app.name'),
                'description' => 'Le tir fédéral est la plus grande fête de tir au monde. Participez sous les couleurs de la Société de tir de Lavigny.',
                'image' => asset('/images/tirCampagne.webp'),
                'type' => 'website',
            ],
        ]);
    }
}
