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
        // `composer run dev` no traía el programador: sin él nadie verifica pagos solo
        // y había que apretar «Verificar pago». En producción lo hace el cron de schedule:run.
        DevCommands::artisan('schedule:work', 'scheduler');
    }
}
