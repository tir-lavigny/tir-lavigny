<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class BecomeMemberController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('BecomeMember', [
            'og' => [
                'title' => 'Devenir membre - Société de tir de Lavigny',
                'description' => 'Rejoignez la Société de tir de Lavigny : le plaisir du tir, sans contraintes.',
                'image' => asset('/images/hero.jpg'),
                'type' => 'website',
            ],
        ]);
    }
}
