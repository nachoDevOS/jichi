<?php

use App\Http\Controllers\Publico\VerificacionController;
use Illuminate\Support\Facades\Route;

/*
| Rutas públicas — sin autenticación
*/

/*
 * throttle:60,1 = máximo 60 peticiones por minuto desde la misma IP.
 */
Route::get('/verificar/{codigo?}', [VerificacionController::class, 'show'])
    ->middleware('throttle:60,1')
    ->name('verificar.show');

/*
 * Formulario para escribir la firma a mano, cuando el QR no se deja escanear. Se
 * limita más fuerte porque es el que permitiría probar combinaciones al azar
 * buscando acertar una.
 */
Route::post('/verificar', [VerificacionController::class, 'buscar'])
    ->middleware('throttle:20,1')
    ->name('verificar.buscar');
