<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mapa de rutas de Jichi
|--------------------------------------------------------------------------
|
| Laravel carga automáticamente SOLO este archivo (así está declarado en
| bootstrap/app.php). Desde acá se incluyen los demás, agrupados por área,
| para que no termine todo amontonado en un archivo de 300 líneas.
|
|   routes/publico.php  -> lo que ve cualquier ciudadano, sin iniciar sesión
|   routes/auth.php     -> iniciar y cerrar sesión
|   routes/panel.php    -> el panel de administración (requiere estar logueado)
|
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
