<?php

use Illuminate\Support\Facades\Route;

/*
| Mapa de rutas de Jichi
*/

/*
 * Puerta de entrada del sistema. No hay página de bienvenida: si hay sesión
 * abierta se va al panel, y si no, al login.
 */
Route::get('/', fn () => auth()->check()
    ? redirect()->route('dashboard')
    : redirect()->route('login'));

require __DIR__.'/publico.php';
require __DIR__.'/auth.php';
require __DIR__.'/panel.php';
