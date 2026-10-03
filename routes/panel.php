<?php

use App\Http\Controllers\Panel\AprovechamientoController;
use App\Http\Controllers\Panel\ArancelSirebController;
use App\Http\Controllers\Panel\AsociacionController;
use App\Http\Controllers\Panel\AutorizacionPescaController;
use App\Http\Controllers\Panel\BeneficiarioController;
use App\Http\Controllers\Panel\CarnetController;
use App\Http\Controllers\Panel\CarnetImpresionController;
use App\Http\Controllers\Panel\CategoriaAprovechamientoController;
use App\Http\Controllers\Panel\CuentaPortalController;
use App\Http\Controllers\Panel\DashboardController;
use App\Http\Controllers\Panel\FaenaController;
use App\Http\Controllers\Panel\GuiaController;
use App\Http\Controllers\Panel\GuiaImpresionController;
use App\Http\Controllers\Panel\PermisoFaenaImpresionController;
use App\Http\Controllers\Panel\ProductoHidrobiologicoController;
use App\Http\Controllers\Panel\ReciboController;
use App\Http\Controllers\Panel\TipoCarnetController;
use Illuminate\Support\Facades\Route;

/*
| Panel de administración — requiere sesión iniciada
*/

// `funcionario`: una cuenta del portal nunca entra acá, tenga el rol que tenga.
Route::middleware(['auth', 'funcionario'])->prefix('panel')->group(function () {

    /*
    | Panel principal
    */

    Route::get('/dashboard', DashboardController::class)
        ->middleware('permiso:dashboard.ver')
        ->name('dashboard');

    /*
    | 1. Beneficiarios — la persona, UNA SOLA VEZ
    */

    Route::middleware('permiso:beneficiarios.ver')->group(function () {
        Route::get('/beneficiarios', [BeneficiarioController::class, 'index'])
            ->name('beneficiarios.index');

        // Devuelve JSON, no una pantalla. Va ANTES de {beneficiario}.
        Route::get('/beneficiarios/buscar', [BeneficiarioController::class, 'buscar'])
            ->name('beneficiarios.buscar');
    });

    Route::middleware('permiso:beneficiarios.crear')->group(function () {
        Route::get('/beneficiarios/crear', [BeneficiarioController::class, 'create'])
            ->name('beneficiarios.create');

        Route::post('/beneficiarios', [BeneficiarioController::class, 'store'])
            ->name('beneficiarios.store');
    });

    Route::middleware('permiso:beneficiarios.editar')->group(function () {
        Route::get('/beneficiarios/{beneficiario}/editar', [BeneficiarioController::class, 'edit'])
            ->name('beneficiarios.edit');

        Route::put('/beneficiarios/{beneficiario}', [BeneficiarioController::class, 'update'])
            ->name('beneficiarios.update');
    });

    Route::delete('/beneficiarios/{beneficiario}', [BeneficiarioController::class, 'destroy'])
        ->middleware('permiso:beneficiarios.eliminar')
        ->name('beneficiarios.destroy');

    // Su cuenta del portal /mi-cuenta. Los tres devuelven a la ficha.
    Route::middleware('permiso:beneficiarios.portal')->group(function () {
        Route::post('/beneficiarios/{beneficiario}/portal', [CuentaPortalController::class, 'store'])
            ->name('beneficiarios.portal.store');

        Route::patch('/beneficiarios/{beneficiario}/portal/resetear', [CuentaPortalController::class, 'resetear'])
            ->name('beneficiarios.portal.resetear');

        Route::patch('/beneficiarios/{beneficiario}/portal/desactivar', [CuentaPortalController::class, 'desactivar'])
            ->name('beneficiarios.portal.desactivar');
    });

    // ÚLTIMA del bloque: {beneficiario} se tragaría 'crear' y 'buscar'.
    Route::get('/beneficiarios/{beneficiario}', [BeneficiarioController::class, 'show'])
        ->middleware('permiso:beneficiarios.ver')
        ->name('beneficiarios.show');

    /*
    | 2. Aprovechamientos — la BOLSA MADRE del pescador
    */

    Route::get('/aprovechamientos', [AprovechamientoController::class, 'index'])
        ->middleware('permiso:aprovechamientos.ver')
        ->name('aprovechamientos.index');

    Route::middleware('permiso:aprovechamientos.crear')->group(function () {
        Route::get('/aprovechamientos/crear', [AprovechamientoController::class, 'create'])
            ->name('aprovechamientos.create');

        Route::post('/aprovechamientos', [AprovechamientoController::class, 'store'])
            ->name('aprovechamientos.store');
    });

    // Corregir el borrador. El estado manda: el servicio lo comprueba con la
    // fila bloqueada, porque entre abrir el formulario y guardar pudo aprobarse.
    Route::middleware('permiso:aprovechamientos.editar')->group(function () {
        Route::get('/aprovechamientos/{aprovechamiento}/editar', [AprovechamientoController::class, 'edit'])
            ->name('aprovechamientos.edit');

        Route::put('/aprovechamientos/{aprovechamiento}', [AprovechamientoController::class, 'update'])
            ->name('aprovechamientos.update');
    });

    // El pago se hace en SIREB: esto pregunta y, si está pagado, aprueba.
    Route::post('/aprovechamientos/{aprovechamiento}/verificar-pago', [AprovechamientoController::class, 'verificarPago'])
        ->middleware('permiso:aprovechamientos.crear')
        ->name('aprovechamientos.verificar-pago');

    // Revocar es una sanción, como en el carnet: permiso propio y motivo obligatorio.
    Route::patch('/aprovechamientos/{aprovechamiento}/revocar', [AprovechamientoController::class, 'revocar'])
        ->middleware('permiso:aprovechamientos.revocar')
        ->name('aprovechamientos.revocar');

    // Eliminar es de SUPERVISIÓN: lo único que queda es la auditoría.
    Route::delete('/aprovechamientos/{aprovechamiento}', [AprovechamientoController::class, 'destroy'])
        ->middleware('permiso:aprovechamientos.eliminar')
        ->name('aprovechamientos.destroy');

    // El PDF, recién con el cupo aprobado. Permiso propio: entregar el papel
    // es un acto distinto de consultar la ficha.
    Route::get('/aprovechamientos/{aprovechamiento}/autorizacion', [AutorizacionPescaController::class, 'imprimir'])
        ->middleware('permiso:aprovechamientos.imprimir')
        ->name('aprovechamientos.autorizacion');

    Route::get('/aprovechamientos/{aprovechamiento}', [AprovechamientoController::class, 'show'])
        ->middleware('permiso:aprovechamientos.ver')
        ->name('aprovechamientos.show');

    /*
    | 3. Carnets — la credencial anual
    */

    Route::get('/carnets', [CarnetController::class, 'index'])
        ->middleware('permiso:carnets.ver')
        ->name('carnets.index');

    Route::middleware('permiso:carnets.crear')->group(function () {
        Route::get('/carnets/crear', [CarnetController::class, 'create'])
            ->name('carnets.create');

        Route::post('/carnets', [CarnetController::class, 'store'])
            ->name('carnets.store');
    });

    // Corregir y eliminar, SOLO sobre el borrador. Lo comprueba el servicio
    // con la fila bloqueada.
    Route::middleware('permiso:carnets.editar')->group(function () {
        Route::get('/carnets/{carnet}/editar', [CarnetController::class, 'edit'])
            ->name('carnets.edit');

        Route::put('/carnets/{carnet}', [CarnetController::class, 'update'])
            ->name('carnets.update');
    });

    Route::delete('/carnets/{carnet}', [CarnetController::class, 'destroy'])
        ->middleware('permiso:carnets.eliminar')
        ->name('carnets.destroy');

    // El pago se hace en SIREB: esto pregunta y, si está pagado, aprueba.
    Route::post('/carnets/{carnet}/verificar-pago', [CarnetController::class, 'verificarPago'])
        ->middleware('permiso:carnets.crear')
        ->name('carnets.verificar-pago');

    Route::patch('/carnets/{carnet}/revocar', [CarnetController::class, 'revocar'])
        ->middleware('permiso:carnets.revocar')
        ->name('carnets.revocar');

    // Reponer = revocar + abrir el formulario del nuevo: pide los dos permisos.
    Route::patch('/carnets/{carnet}/reponer', [CarnetController::class, 'reponer'])
        ->middleware(['permiso:carnets.revocar', 'permiso:carnets.crear'])
        ->name('carnets.reponer');

    // El plástico. Permiso propio, como el resto de las impresiones.
    Route::get('/carnets/{carnet}/imprimir', [CarnetImpresionController::class, 'imprimir'])
        ->middleware('permiso:carnets.imprimir')
        ->name('carnets.imprimir');

    Route::get('/carnets/{carnet}', [CarnetController::class, 'show'])
        ->middleware('permiso:carnets.ver')
        ->name('carnets.show');

    /*
    | 4a. Permisos de faena — una salida de pesca
    */

    Route::get('/faenas', [FaenaController::class, 'index'])
        ->middleware('permiso:faenas.ver')
        ->name('faenas.index');

    Route::middleware('permiso:faenas.crear')->group(function () {
        Route::get('/faenas/crear', [FaenaController::class, 'create'])
            ->name('faenas.create');

        Route::post('/faenas', [FaenaController::class, 'store'])
            ->name('faenas.store');
    });

    // El pago se hace en SIREB: esto pregunta y, si está pagado, aprueba.
    Route::post('/faenas/{faena}/verificar-pago', [FaenaController::class, 'verificarPago'])
        ->middleware('permiso:faenas.crear')
        ->name('faenas.verificar-pago');

    // Corregir es de ventanilla y eliminar de supervisión. Las dos solo en
    // PENDIENTE: lo decide PermisoFaena::puedeEditarse().
    Route::get('/faenas/{faena}/editar', [FaenaController::class, 'edit'])
        ->middleware('permiso:faenas.editar')
        ->name('faenas.edit');

    Route::patch('/faenas/{faena}', [FaenaController::class, 'update'])
        ->middleware('permiso:faenas.editar')
        ->name('faenas.update');

    Route::delete('/faenas/{faena}', [FaenaController::class, 'destroy'])
        ->middleware('permiso:faenas.eliminar')
        ->name('faenas.destroy');

    // ANTES de '{faena}': con la ficha primero, «imprimir» se toma como id.
    Route::get('/faenas/{faena}/imprimir', [PermisoFaenaImpresionController::class, 'imprimir'])
        ->middleware('permiso:faenas.imprimir')
        ->name('faenas.imprimir');

    Route::get('/faenas/{faena}', [FaenaController::class, 'show'])
        ->middleware('permiso:faenas.ver')
        ->name('faenas.show');

    /*
    | 4b. Guías de movimiento — un traslado de producto
    */

    Route::get('/guias', [GuiaController::class, 'index'])
        ->middleware('permiso:guias.ver')
        ->name('guias.index');

    Route::middleware('permiso:guias.crear')->group(function () {
        Route::get('/guias/crear', [GuiaController::class, 'create'])
            ->name('guias.create');

        Route::post('/guias', [GuiaController::class, 'store'])
            ->name('guias.store');
    });

    // El pago se hace en SIREB: esto pregunta y, si está pagado, aprueba.
    Route::post('/guias/{guia}/verificar-pago', [GuiaController::class, 'verificarPago'])
        ->middleware('permiso:guias.crear')
        ->name('guias.verificar-pago');

    // Corregir es de ventanilla y eliminar de supervisión. Las dos solo en
    // PENDIENTE: lo dice GuiaMovimiento::puedeEditarse().
    Route::get('/guias/{guia}/editar', [GuiaController::class, 'edit'])
        ->middleware('permiso:guias.editar')
        ->name('guias.edit');

    Route::patch('/guias/{guia}', [GuiaController::class, 'update'])
        ->middleware('permiso:guias.editar')
        ->name('guias.update');

    Route::delete('/guias/{guia}', [GuiaController::class, 'destroy'])
        ->middleware('permiso:guias.eliminar')
        ->name('guias.destroy');

    // Anular es de SUPERVISIÓN: quema un número del talonario para siempre.
    Route::patch('/guias/{guia}/anular', [GuiaController::class, 'anular'])
        ->middleware('permiso:guias.anular')
        ->name('guias.anular');

    // ANTES de '{guia}': con la ficha primero, «imprimir» se toma como id.
    Route::get('/guias/{guia}/imprimir', [GuiaImpresionController::class, 'imprimir'])
        ->middleware('permiso:guias.imprimir')
        ->name('guias.imprimir');

    Route::get('/guias/{guia}', [GuiaController::class, 'show'])
        ->middleware('permiso:guias.ver')
        ->name('guias.show');

    /*
    | 5. Recibos — el comprobante de lo pagado en SIREB
    */

    Route::get('/recibos', [ReciboController::class, 'index'])
        ->middleware('permiso:recibos.ver')
        ->name('recibos.index');

    // Imprimir tiene su propio permiso, no el de ver.
    Route::get('/recibos/{recibo}/imprimir', [ReciboController::class, 'imprimir'])
        ->middleware('permiso:recibos.imprimir')
        ->name('recibos.imprimir');

    Route::get('/recibos/{recibo}', [ReciboController::class, 'show'])
        ->middleware('permiso:recibos.ver')
        ->name('recibos.show');

    /*
    | Catálogos — lo que sale de una resolución y casi no se toca
    */

    Route::prefix('catalogos')->group(function () {

        // --- Asociaciones
        Route::get('/asociaciones', [AsociacionController::class, 'index'])
            ->middleware('permiso:catalogos.ver')
            ->name('asociaciones.index');

        Route::middleware('permiso:catalogos.gestionar')->group(function () {
            Route::post('/asociaciones', [AsociacionController::class, 'store'])
                ->name('asociaciones.store');

            Route::put('/asociaciones/{asociacion}', [AsociacionController::class, 'update'])
                ->name('asociaciones.update');
        });

        // --- Escala de aprovechamiento
        Route::get('/categorias-aprovechamiento', [CategoriaAprovechamientoController::class, 'index'])
            ->middleware('permiso:catalogos.ver')
            ->name('categorias-aprovechamiento.index');

        // ANTES de /{categoria}: si no, «crear» se toma como id.
        Route::get('/categorias-aprovechamiento/crear', [CategoriaAprovechamientoController::class, 'create'])
            ->middleware('permiso:catalogos.gestionar')
            ->name('categorias-aprovechamiento.create');

        Route::get('/categorias-aprovechamiento/{categoria}/editar', [CategoriaAprovechamientoController::class, 'edit'])
            ->middleware('permiso:catalogos.gestionar')
            ->name('categorias-aprovechamiento.edit');

        Route::get('/categorias-aprovechamiento/{categoria}', [CategoriaAprovechamientoController::class, 'show'])
            ->middleware('permiso:catalogos.ver')
            ->name('categorias-aprovechamiento.show');

        Route::middleware('permiso:catalogos.gestionar')->group(function () {
            Route::post('/categorias-aprovechamiento', [CategoriaAprovechamientoController::class, 'store'])
                ->name('categorias-aprovechamiento.store');

            Route::put('/categorias-aprovechamiento/{categoria}', [CategoriaAprovechamientoController::class, 'update'])
                ->name('categorias-aprovechamiento.update');
        });

        // --- Tipos de carnet
        Route::get('/tipos-carnet', [TipoCarnetController::class, 'index'])
            ->middleware('permiso:catalogos.ver')
            ->name('tipos-carnet.index');

        Route::get('/tipos-carnet/{tipo_carnet}', [TipoCarnetController::class, 'show'])
            ->middleware('permiso:catalogos.ver')
            ->name('tipos-carnet.show');

        Route::get('/tipos-carnet/{tipo_carnet}/editar', [TipoCarnetController::class, 'edit'])
            ->middleware('permiso:catalogos.gestionar')
            ->name('tipos-carnet.edit');

        // SIN alta: la lista sale de la resolución. Sacar el botón no alcanza,
        // la ruta seguía aceptando un POST armado a mano.
        Route::put('/tipos-carnet/{tipo_carnet}', [TipoCarnetController::class, 'update'])
            ->middleware('permiso:catalogos.gestionar')
            ->name('tipos-carnet.update');

        // --- Productos hidrobiológicos (cuadro D de la guía)
        Route::get('/productos', [ProductoHidrobiologicoController::class, 'index'])
            ->middleware('permiso:catalogos.ver')
            ->name('productos.index');

        // ANTES de /{producto}: si no, «crear» se toma como id.
        Route::get('/productos/crear', [ProductoHidrobiologicoController::class, 'create'])
            ->middleware('permiso:catalogos.gestionar')
            ->name('productos.create');

        Route::get('/productos/{producto}/editar', [ProductoHidrobiologicoController::class, 'edit'])
            ->middleware('permiso:catalogos.gestionar')
            ->name('productos.edit');

        Route::get('/productos/{producto}', [ProductoHidrobiologicoController::class, 'show'])
            ->middleware('permiso:catalogos.ver')
            ->name('productos.show');

        Route::middleware('permiso:catalogos.gestionar')->group(function () {
            Route::post('/productos', [ProductoHidrobiologicoController::class, 'store'])
                ->name('productos.store');

            Route::put('/productos/{producto}', [ProductoHidrobiologicoController::class, 'update'])
                ->name('productos.update');
        });

        // --- Aranceles de SIREB (hoy, la faena). SIN alta ni baja: una fila por concepto.
        Route::get('/aranceles', [ArancelSirebController::class, 'index'])
            ->middleware('permiso:catalogos.ver')
            ->name('aranceles.index');

        Route::get('/aranceles/{arancel}', [ArancelSirebController::class, 'show'])
            ->middleware('permiso:catalogos.ver')
            ->name('aranceles.show');

        Route::middleware('permiso:catalogos.gestionar')->group(function () {
            Route::get('/aranceles/{arancel}/editar', [ArancelSirebController::class, 'edit'])
                ->name('aranceles.edit');

            Route::put('/aranceles/{arancel}', [ArancelSirebController::class, 'update'])
                ->name('aranceles.update');
        });
    });

    /*
    | Módulos por construir
    */
});
