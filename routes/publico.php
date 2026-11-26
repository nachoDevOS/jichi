<?php

use App\Http\Controllers\Publico\EnlaceDocumentoController;
use App\Http\Controllers\Publico\InicioController;
use App\Http\Controllers\Publico\VerificacionController;
use Illuminate\Support\Facades\Route;

/*
| Rutas públicas — sin autenticación
*/

/*
 * La portada institucional. Es la raíz del sitio: quien escribe el dominio ve
 * los servicios, no el login. El acceso al panel queda en /login.
 */
Route::get('/', [InicioController::class, 'index'])->name('inicio');

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
Route::post('/verificar', [VerificacionController::class, 'buscarPorFormulario'])
    ->middleware('throttle:20,1')
    ->name('verificar.buscar');

/*
 * El PDF guardado en el disco LOCAL, por un enlace firmado que vence a los 5 minutos
 * (DocumentoEmitidoService::enlaceDescarga, 26/11/2026). Sin firma válida, 403. En s3 no se
 * usa: ahí el enlace firmado es el del bucket.
 */
Route::get('/documentos/enlace/{emitido}', EnlaceDocumentoController::class)
    ->middleware(['signed', 'throttle:30,1'])
    ->name('documentos.enlace');
