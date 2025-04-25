<?php

namespace App\Providers;

use App\View\Components\Livewire\FourXxCard;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Livewire\LivewireManager;

class LaravelPulse4xxServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->callAfterResolving('livewire', function (LivewireManager $livewire, Application $app) {
            $livewire->component('4xx', FourXxCard::class);
        });
    }
}
