<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class PrivacyPolicyController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('PrivacyPolicy', [
            'og' => [
                'title' => 'Déclaration de protection des données - Société de tir de Lavigny',
                'description' => 'Déclaration de protection des données de la Société de tir de Lavigny.',
                'image' => asset('/images/hero.jpg'),
                'type' => 'website',
            ],
        ]);
    }
}
