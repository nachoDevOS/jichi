<?php

use App\Http\Controllers\Publico\VerificacionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas públicas — sin autenticación
|--------------------------------------------------------------------------
|
| Acá va lo único que el sistema expone al ciudadano: la verificación de
| autenticidad de un carnet. Es la URL codificada dentro del código QR impreso
| en cada documento.
|
| Un pescador muestra su carnet, el inspector escanea el QR con su teléfono y
| cae en esta pantalla, que le dice si el documento es real, si está vigente y
| para qué rubros habilita. Por eso NO puede pedir login.
|
| HACE FALTA UN SOLO DATO: la firma de validación. El carnet no tiene número —se
| retiró la columna `codigo`— y se identifica por esos dieciséis caracteres, que
| están impresos en el plástico y dentro del QR. Ver VerificacionController.
|
*/

/*
 * throttle:60,1 = máximo 60 peticiones por minuto desde la misma IP.
 *
 * Es imprescindible en una ruta pública y sin sesión. Acá el motivo no es el
 * costo de la consulta sino el ataque por fuerza bruta: cada visita es un
 * intento de adivinar una firma. Con 16 caracteres alfanuméricos —unas 8 · 10^24
 * combinaciones— y 60 intentos por minuto, acertar deja de ser una posibilidad
 * práctica.
 */
Route::get('/verificar/{firma?}', [VerificacionController::class, 'show'])
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
