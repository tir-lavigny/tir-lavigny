<?php

namespace App\Http\Controllers;

use App\Support\Seo;
use Inertia\Inertia;
use Inertia\Response;

abstract class Controller
{
    /**
     * @param  array<string, mixed>  $props
     */
    protected function renderInertia(string $component, array $props): Response
    {
        $props['head'] = Seo::head(
            og: is_array($props['og'] ?? null) ? $props['og'] : [],
            jsonLd: is_array($props['json_ld'] ?? null) ? $props['json_ld'] : [],
            breadcrumbs: is_array($props['breadcrumbs'] ?? null) ? $props['breadcrumbs'] : [],
        );

        return Inertia::render($component, $props);
    }
}
