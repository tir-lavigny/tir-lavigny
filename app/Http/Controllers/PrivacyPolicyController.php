<?php

namespace App\Http\Controllers;

use Inertia\Response;

class PrivacyPolicyController extends Controller
{
    public function __invoke(): Response
    {
        return $this->renderInertia('PrivacyPolicy', [
            'og' => [
                'title' => 'Déclaration de protection des données - Société de tir de Lavigny',
                'description' => 'Déclaration de protection des données de la Société de tir de Lavigny.',
                'image' => asset('/images/hero.webp'),
                'type' => 'website',
            ],
            'breadcrumbs' => [
                ['title' => 'Accueil', 'href' => route('home')],
                ['title' => 'Protection des données', 'href' => route('privacy-policy')],
            ],
        ]);
    }
}
