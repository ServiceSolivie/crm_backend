<?php

namespace App\Providers;

use App\Enums\PermissionEnum;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Abilities that are not tied to a model (no policy to hang them on).
        Gate::define('manage-ringover', fn (User $user) => $user->can(PermissionEnum::RINGOVER_MANAGE->value));
    }
}
