<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class RobotsController extends Controller
{
    public function __invoke(): Response
    {
        $lines = app()->isProduction()
            ? [
                'User-agent: *',
                'Allow: /',
                'Disallow: /back-office/',
                'Disallow: /member-registration/',
                'Disallow: /privacy-policy',
                'Disallow: /login',
                'Disallow: /register',
                'Disallow: /forgot-password',
                'Disallow: /reset-password',
                'Disallow: /verify-email',
                'Disallow: /confirm-password',
                'Disallow: /api/',
                'Disallow: /docs/',
                'Disallow: /_scramble/',
                'Disallow: /_boost/',
                '',
                'Sitemap: '.route('sitemap'),
            ]
            : [
                'User-agent: *',
                'Disallow: /',
            ];

        return response(implode("\n", $lines)."\n", 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
