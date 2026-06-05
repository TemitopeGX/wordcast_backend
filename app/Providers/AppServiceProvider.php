<?php

namespace App\Providers;

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
        // Fix for cPanel older MySQL "Specified key was too long" error
        \Illuminate\Support\Facades\Schema::defaultStringLength(191);

        // 'admin' Gate — used by ProContent admin endpoints
        // A user is an admin when their `role` column is set to 'admin'.
        Gate::define('admin', function ($user) {
            return $user->role === 'admin';
        });
    }
}
