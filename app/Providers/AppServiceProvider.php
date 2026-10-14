<?php

namespace App\Providers;

use App\Services\HyperswitchClient;
use App\Support\LeadStatusCatalog;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(HyperswitchClient::class, fn () => HyperswitchClient::fromConfig());
        // One catalog per request: the custom statuses are read once
        $this->app->scoped(LeadStatusCatalog::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
