<?php

use App\Http\Controllers\Portal\AccesoController;
use App\Http\Controllers\Portal\DescargarController;
use App\Http\Controllers\Portal\DescargarReciboController;
use App\Http\Controllers\Portal\EnCursoController;
use App\Http\Controllers\Portal\FaenaController;
use App\Http\Controllers\Portal\GuiaController;
use App\Http\Controllers\Portal\InicioController;
use App\Http\Controllers\Portal\PagoQrController;
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

        // Trámites en línea (19/11/2026): faena y guía; lo demás, en ventanilla.
        Route::get('/faena/pedir', [FaenaController::class, 'create'])->name('faenas.create');
        Route::post('/faena', [FaenaController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('faenas.store');

        Route::get('/guia/pedir', [GuiaController::class, 'create'])->name('guias.create');
        // El código es una llave de 16 caracteres: throttle para que no se pruebe al azar.
        Route::get('/guia/autorizacion/{codigo}', [GuiaController::class, 'autorizacion'])
            ->middleware('throttle:20,1')
            ->name('guias.autorizacion');
        Route::post('/guia', [GuiaController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('guias.store');

        // El PDF del panel, solo de lo vigente hoy, y solo si es suyo.
        Route::get('/descargar/{codigo}', DescargarController::class)->name('descargar');
        Route::get('/recibos/{codigo}/descargar', DescargarReciboController::class)->name('recibos.descargar');

        // Lo abierto se VE con «NO VÁLIDO», no se descarga.
        Route::get('/vista-previa/{codigo}', VistaPreviaController::class)->name('vista-previa');

        // Consulta SIREB en el momento: throttle para que no se use de martillo contra Recaudaciones.
        Route::get('/pagar-qr/{codigo}', PagoQrController::class)
            ->middleware('throttle:20,1')
            ->name('pagar-qr');

        // Con el QR abierto: ¿ya pagó? Pregunta a SIREB como mucho cada 15 s (CobroQrService).
        Route::get('/pagar-qr/{codigo}/estado', [PagoQrController::class, 'estado'])
            ->middleware('throttle:30,1')
            ->name('pagar-qr.estado');

        // Solo test: SIREB paga el QR de prueba (en producción responde 404). Como en el panel.
        Route::post('/pagar-qr/{codigo}/simular', [PagoQrController::class, 'simular'])
            ->middleware('throttle:10,1')
            ->name('pagar-qr.simular');
    });
});
