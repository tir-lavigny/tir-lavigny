<?php

namespace App\Http\Controllers;

use Inertia\Response;

class BecomeMemberController extends Controller
{
    public function __invoke(): Response
    {
        return $this->renderInertia('BecomeMember', [
            'og' => [
                'title' => 'Devenir membre - Société de tir de Lavigny',
                'description' => 'Rejoignez la Société de tir de Lavigny : le plaisir du tir, sans contraintes.',
                'image' => asset('/images/hero.webp'),
                'type' => 'website',
            ],
            'breadcrumbs' => [
                ['title' => 'Accueil', 'href' => route('home')],
                ['title' => 'Devenir membre', 'href' => route('become-member')],
            ],
        ]);
    }
}
