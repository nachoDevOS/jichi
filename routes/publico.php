<?php

use App\Http\Controllers\Publico\VerificacionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas públicas — sin autenticación
|--------------------------------------------------------------------------
|
| Acá va lo único que el sistema expone al ciudadano: la verificación de
| autenticidad de un documento. Es la URL que está codificada dentro del
| código QR impreso en cada permiso, carnet y certificación.
|
| Un pescador muestra su permiso, el inspector escanea el QR con su teléfono
| y cae en esta pantalla, que le dice si el documento es real y si está
| vigente. Por eso NO puede pedir login.
|
*/

/*
 * throttle:60,1 = máximo 60 peticiones por minuto desde la misma IP.
 *
 * Es imprescindible en una ruta pública y sin sesión: cada visita escribe en
 * la base de datos (sube el contador de escaneos del documento). Sin límite,
 * cualquiera con un script podría martillar esta URL y saturar la base.
 */
Route::get('/verificar/{codigo?}', [VerificacionController::class, 'show'])
    ->middleware('throttle:60,1')
    ->name('verificar.show');

/*
 * Formulario para escribir el código a mano, cuando el QR no se puede
 * escanear. Se limita más fuerte porque es el que permitiría probar códigos
 * al azar buscando acertar uno.
 */
Route::post('/verificar', [VerificacionController::class, 'buscar'])
    ->middleware('throttle:20,1')
    ->name('verificar.buscar');
