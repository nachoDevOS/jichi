<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Inicio y cierre de sesión
|--------------------------------------------------------------------------
|
| No hay registro público ni verificación por correo: las cuentas del sistema
| las crea el administrador desde el panel. Por eso este archivo solo tiene
| login y logout, y no las rutas de "recuperar contraseña" que trae Laravel.
|
| Cada intento (exitoso, fallido o bloqueado) queda registrado en la tabla
| `accesos`. Ver App\Http\Requests\Auth\LoginRequest.
|
*/

/*
 * 'guest' = solo para quien NO tiene sesión. Si un usuario logueado entra a
 * /login, Laravel lo devuelve al panel en vez de mostrar el formulario.
 */
Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    // Doble protección contra fuerza bruta:
    //   - throttle:10,1 corta por IP a nivel de ruta
    //   - LoginRequest corta por combinación correo + IP (5 intentos)
    Route::post('login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:10,1');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
