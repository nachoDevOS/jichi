<?php

use App\Http\Controllers\ApiApp\CuentaController;
use App\Http\Controllers\ApiApp\EnCursoController;
use App\Http\Controllers\ApiApp\EstadoTramiteController;
use App\Http\Controllers\ApiApp\FaenaController;
use App\Http\Controllers\ApiApp\GuiaController;
use App\Http\Controllers\ApiApp\InicioController;
use App\Http\Controllers\ApiApp\PagosController;
use App\Http\Controllers\ApiApp\PerfilController;
use App\Http\Controllers\ApiApp\ReciboPdfController;
use App\Http\Controllers\ApiApp\SesionController;
use Illuminate\Support\Facades\Route;

/*
| API de la app BeniDigital — /api/v1/portal. La incluye routes/api.php.
|
| La misma cuenta del portal /mi-cuenta (C.I. + clave de ventanilla), con token de
| Sanctum. Alcance (25/11/2026): el inicio (como el de la web), pedir faena y guía,
| saber cuándo quedaron aprobadas, ver «Mi perfil» y «Mis pagos» (sus recibos).
| El QR lo paga la app directo en SIREB (API pública); acá no se cobra. Contrato en
| docs/api/portal.md; todo lo de la app vive en carpetas ApiApp/ (docs/api/README.md).
|
| Cada throttle lleva su prefijo: sin él, todos los de una cuenta comparten el contador.
*/

Route::prefix('v1/portal')->name('api.portal.')->group(function () {
    // La C.I. se adivina: throttle por IP acá y por C.I. + IP en IngresarAppRequest.
    Route::post('/sesion', [SesionController::class, 'store'])
        ->middleware('throttle:10,1,sesion')
        ->name('sesion.store');

    // Tope general por cuenta, además del propio de cada ruta (docs/api/seguridad.md § 3, B).
    Route::middleware(['auth:sanctum', 'beneficiario.app', 'throttle:60,1,app'])->group(function () {
        Route::delete('/sesion', [SesionController::class, 'destroy'])->name('sesion.destroy');
        Route::get('/yo', [CuentaController::class, 'show'])->name('yo');
        Route::put('/clave', [CuentaController::class, 'actualizarClave'])
            ->middleware('throttle:10,1,clave')
            ->name('clave.update');

        // «Mi perfil» (vuelve el 25/11/2026): sus datos, sus actividades y la foto del carnet.
        Route::get('/perfil', [PerfilController::class, 'show'])->name('perfil');
        Route::get('/perfil/foto', [PerfilController::class, 'foto'])->name('perfil.foto');

        // El inicio, como el del portal web: vigente hoy, qué hacer ahora y la deuda.
        Route::get('/inicio', InicioController::class)->name('inicio');

        // «Mis pagos» (25/11/2026): sus recibos de una gestión, como en la web.
        Route::get('/pagos', PagosController::class)->name('pagos');
        // El PDF de un recibo suyo (26/11/2026): un enlace firmado de 5 minutos, no los bytes.
        Route::get('/recibos/{codigo}/pdf', ReciboPdfController::class)
            ->middleware('throttle:20,1,recibos')
            ->name('recibos.pdf');
        // Lo pendiente de pago, con su codigo_pago para pagar el QR en SIREB.
        Route::get('/en-curso', EnCursoController::class)->name('en-curso');

        // Después de pagar en SIREB: consulta el pago en el momento y, si está, aprueba.
        Route::get('/tramites/{codigo}/estado', EstadoTramiteController::class)
            ->middleware('throttle:30,1,estado')
            ->name('tramites.estado');

        // Trámites en línea (19/11/2026): las reglas del panel, ver PedirFaenaService.
        // Cobran en SIREB: Idempotency-Key obligatoria, para no registrar dos veces lo mismo.
        Route::get('/faenas/nueva', [FaenaController::class, 'nueva'])->name('faenas.nueva');
        Route::post('/faenas', [FaenaController::class, 'store'])
            ->middleware(['throttle:10,1,faenas', 'idempotente'])
            ->name('faenas.store');

        Route::get('/guias/nueva', [GuiaController::class, 'nueva'])->name('guias.nueva');
        // El código es una llave de 16 caracteres: throttle para que no se pruebe al azar.
        Route::get('/autorizaciones/{codigo}', [GuiaController::class, 'autorizacion'])
            ->middleware('throttle:20,1,autorizaciones')
            ->name('autorizaciones.show');
        Route::post('/guias', [GuiaController::class, 'store'])
            ->middleware(['throttle:10,1,guias', 'idempotente'])
            ->name('guias.store');
    });
});
