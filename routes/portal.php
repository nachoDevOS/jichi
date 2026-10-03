<?php

use App\Http\Controllers\Portal\AccesoController;
use App\Http\Controllers\Portal\DescargarController;
use App\Http\Controllers\Portal\DescargarReciboController;
use App\Http\Controllers\Portal\EnCursoController;
use App\Http\Controllers\Portal\InicioController;
use App\Http\Controllers\Portal\PagosController;
use App\Http\Controllers\Portal\PapelesController;
use App\Http\Controllers\Portal\PerfilController;
use App\Http\Controllers\Portal\VistaPreviaController;
use Illuminate\Support\Facades\Route;

/*
| Portal del beneficiario — /mi-cuenta
|
| Solo lectura. Ninguna ruta recibe un id: todo sale de la cuenta con sesión,
| así que no hay número que cambiar en la URL para ver lo de otro.
*/

Route::prefix('mi-cuenta')->name('portal.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/ingresar', [AccesoController::class, 'create'])->name('ingresar');

        // La C.I. se adivina: throttle por IP acá y por C.I. + IP en IngresarRequest.
        Route::post('/ingresar', [AccesoController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('ingresar.store');
    });

    Route::middleware(['auth', 'beneficiario'])->group(function () {
        Route::post('/salir', [AccesoController::class, 'destroy'])->name('salir');

        Route::get('/clave', [PerfilController::class, 'editarClave'])->name('clave.edit');
        Route::put('/clave', [PerfilController::class, 'actualizarClave'])->name('clave.update');

        Route::get('/', InicioController::class)->name('inicio');
        Route::get('/en-curso', EnCursoController::class)->name('en-curso');
        Route::get('/papeles', PapelesController::class)->name('papeles');
        Route::get('/pagos', PagosController::class)->name('pagos');
        Route::get('/mis-datos', [PerfilController::class, 'show'])->name('perfil');

        // El PDF del panel, solo de lo vigente hoy, y solo si es suyo.
        Route::get('/descargar/{codigo}', DescargarController::class)->name('descargar');
        Route::get('/recibos/{codigo}/descargar', DescargarReciboController::class)->name('recibos.descargar');

        // Lo abierto se VE con «NO VÁLIDO», no se descarga.
        Route::get('/vista-previa/{codigo}', VistaPreviaController::class)->name('vista-previa');
    });
});
