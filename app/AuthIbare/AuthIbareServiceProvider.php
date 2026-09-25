<?php

namespace App\AuthIbare;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Punto de entrada del login con Ibare: todo el módulo vive en esta carpeta.
 * Sacar el provider de bootstrap/providers.php lo desconecta entero.
 */
class AuthIbareServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware(['web', 'guest'])->group(__DIR__.'/rutas.php');

        if ($this->app->runningInConsole()) {
            $this->commands([VincularIbareCommand::class]);
        }
    }
}
