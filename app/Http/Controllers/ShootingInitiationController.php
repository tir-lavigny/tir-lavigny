<?php

namespace App\Http\Controllers;

use Inertia\Response;

class ShootingInitiationController extends Controller
{
    public function __invoke(): Response
    {
        return $this->renderInertia('ShootingInitiation', [
            'og' => [
                'title' => 'Initiation au tir - Société de tir de Lavigny',
                'description' => 'Découvrez le tir sportif à Lavigny : initiation à 300 m au Fass 90 ou à 10 m avec une carabine à air comprimé, encadrée par un moniteur.',
                'image' => asset('/images/hero.webp'),
                'type' => 'website',
            ],
            'breadcrumbs' => [
                ['title' => 'Accueil', 'href' => route('home')],
                ['title' => 'Initiation au tir', 'href' => route('shooting-initiation')],
            ],
        ]);
    }
}
