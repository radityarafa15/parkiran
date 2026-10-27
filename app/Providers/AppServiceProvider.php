<?php

namespace App\Providers;

use Illuminate\Foundation\DevCommands;
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
        // "composer dev": web, Reverb (didaftarkan paketnya sendiri), scheduler, dan
        // simulator sensor. Aplikasi ini tidak memakai antrean atau Vite.
        DevCommands::artisan('schedule:work', 'scheduler');
        DevCommands::artisan('parkir:simulate', 'sensors');
        DevCommands::except('queue', 'vite');
    }
}
