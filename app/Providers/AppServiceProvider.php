<?php

namespace App\Providers;

use App\Domain\Events\RecurrenceExpander;
use App\Domain\Events\RruleRecurrenceExpander;
use App\Events\LodgeModuleStateChanged;
use App\Listeners\InvalidateLodgeModuleProjections;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(RecurrenceExpander::class, RruleRecurrenceExpander::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(LodgeModuleStateChanged::class, InvalidateLodgeModuleProjections::class);
    }
}
