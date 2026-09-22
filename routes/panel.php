<?php

use App\Http\Controllers\Panel\AprovechamientoController;
use App\Http\Controllers\Panel\AsociacionController;
use App\Http\Controllers\Panel\AutorizacionPescaController;
use App\Http\Controllers\Panel\BeneficiarioController;
use App\Http\Controllers\Panel\CajaController;
use App\Http\Controllers\Panel\CarnetController;
use App\Http\Controllers\Panel\CarnetImpresionController;
use App\Http\Controllers\Panel\CategoriaAprovechamientoController;
use App\Http\Controllers\Panel\DashboardController;
use App\Http\Controllers\Panel\FaenaController;
use App\Http\Controllers\Panel\GuiaController;
use App\Http\Controllers\Panel\GuiaImpresionController;
use App\Http\Controllers\Panel\PagoController;
use App\Http\Controllers\Panel\PermisoFaenaImpresionController;
use App\Http\Controllers\Panel\ReciboController;
use App\Http\Controllers\Panel\TipoCarnetController;
use Illuminate\Support\Facades\Route;

/*
| Panel de administración — requiere sesión iniciada
*/

Route::middleware('auth')->prefix('panel')->group(function () {

    /*
    | Panel principal
    */

    // El controlador es "invocable" (tiene un único método __invoke), por eso se
    // pasa la clase sola en vez del par [Clase::class, 'metodo'].
    Route::get('/dashboard', DashboardController::class)
        ->middleware('permiso:dashboard.ver')
        ->name('dashboard');

    /*
    | 1. Beneficiarios — la persona, UNA SOLA VEZ
    */

    Route::middleware('permiso:beneficiarios.ver')->group(function () {
        Route::get('/beneficiarios', [BeneficiarioController::class, 'index'])
            ->name('beneficiarios.index');

        /*
         * El autocompletado del mostrador. Devuelve JSON, no una pantalla de
         * Inertia, y va ANTES de la ruta con {beneficiario} por lo dicho arriba.
         */
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

    // La ficha va ÚLTIMA de su bloque: {beneficiario} coincide con cualquier
    // palabra, así que puesta antes se tragaría 'crear' y 'buscar'.
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

    /*
     * CORREGIR EL BORRADOR. El permiso es de ventanilla, pero el estado manda:
     * el servicio rechaza cualquier cupo que ya no esté pendiente, con la fila
     * bloqueada, porque entre abrir el formulario y guardar alguien pudo
     * cobrarlo.
     */
    Route::middleware('permiso:aprovechamientos.editar')->group(function () {
        Route::get('/aprovechamientos/{aprovechamiento}/editar', [AprovechamientoController::class, 'edit'])
            ->name('aprovechamientos.edit');

        Route::put('/aprovechamientos/{aprovechamiento}', [AprovechamientoController::class, 'update'])
            ->name('aprovechamientos.update');
    });

    /*
     * CARGAR LOS DEPÓSITOS DESDE LA FICHA DEL CUPO.
     */
    Route::post('/aprovechamientos/{aprovechamiento}/pagos', [AprovechamientoController::class, 'pagar'])
        ->middleware('permiso:caja.cobrar')
        ->name('aprovechamientos.pagar');

    /*
     *  EL CIRCUITO DE REVISIÓN
     */
    Route::post('/aprovechamientos/{aprovechamiento}/enviar', [AprovechamientoController::class, 'enviar'])
        ->middleware('permiso:aprovechamientos.enviar')
        ->name('aprovechamientos.enviar');

    Route::middleware('permiso:aprovechamientos.aprobar')->group(function () {
        Route::patch('/aprovechamientos/{aprovechamiento}/aprobar', [AprovechamientoController::class, 'aprobar'])
            ->name('aprovechamientos.aprobar');

        Route::patch('/aprovechamientos/{aprovechamiento}/rechazar', [AprovechamientoController::class, 'rechazar'])
            ->name('aprovechamientos.rechazar');
    });

    /*
     * ELIMINAR es de SUPERVISIÓN: borrar la fila la hace desaparecer, y lo único
     * que queda es la línea de `auditorias` con el motivo. Ver el bloque de
     * arriba.
     */
    Route::delete('/aprovechamientos/{aprovechamiento}', [AprovechamientoController::class, 'destroy'])
        ->middleware('permiso:aprovechamientos.eliminar')
        ->name('aprovechamientos.destroy');

    /*
     * LA AUTORIZACIÓN DE PESCA, en PDF. Sale recién con el cupo aprobado.
     *
     * Es GET y devuelve bytes, no una pantalla: el navegador la abre en su visor
     * de PDF, que es desde donde se imprime. Permiso propio —entregar el papel
     * es un acto distinto de consultar la ficha—, igual que `carnets.imprimir`.
     */
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

    /*
     * REVOCAR es de SUPERVISIÓN: es una sanción, no se revierte, y la
     * verificación pública empieza a informarla al instante.
     */
    /*
     * CORREGIR Y ELIMINAR, SOLO SOBRE EL BORRADOR. Lo comprueba el servicio con
     * la fila bloqueada: entre abrir el formulario y guardar, otra ventanilla
     * pudo cobrarlo.
     */
    Route::middleware('permiso:carnets.editar')->group(function () {
        Route::get('/carnets/{carnet}/editar', [CarnetController::class, 'edit'])
            ->name('carnets.edit');

        Route::put('/carnets/{carnet}', [CarnetController::class, 'update'])
            ->name('carnets.update');
    });

    Route::delete('/carnets/{carnet}', [CarnetController::class, 'destroy'])
        ->middleware('permiso:carnets.eliminar')
        ->name('carnets.destroy');

    /*
     * CARGAR LOS DEPÓSITOS DESDE LA FICHA DEL CARNET. Mismo circuito que el
     * aprovechamiento, y el mismo permiso: es un cobro de mostrador.
     */
    Route::post('/carnets/{carnet}/pagos', [CarnetController::class, 'pagar'])
        ->middleware('permiso:caja.cobrar')
        ->name('carnets.pagar');

    Route::post('/carnets/{carnet}/enviar', [CarnetController::class, 'enviar'])
        ->middleware('permiso:carnets.enviar')
        ->name('carnets.enviar');

    Route::middleware('permiso:carnets.aprobar')->group(function () {
        Route::patch('/carnets/{carnet}/aprobar', [CarnetController::class, 'aprobar'])
            ->name('carnets.aprobar');

        Route::patch('/carnets/{carnet}/rechazar', [CarnetController::class, 'rechazar'])
            ->name('carnets.rechazar');
    });

    Route::patch('/carnets/{carnet}/revocar', [CarnetController::class, 'revocar'])
        ->middleware('permiso:carnets.revocar')
        ->name('carnets.revocar');

    /*
     * EL PLÁSTICO. Va ANTES de '{carnet}' aunque la URL sea más larga: el
     * orden solo importa entre rutas que puedan coincidir con el mismo camino,
     * y estas dos no —'/carnets/7/imprimir' no coincide con '/carnets/{carnet}'—
     * pero se agrupa acá para que el bloque se lea de corrido.
     */
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

    /*
     * EL CIRCUITO DE COBRO Y FIRMA, igual que el del carnet y el del cupo:
     * los depósitos se cargan desde la ficha, presentar es de ventanilla y
     * firmar es de supervisión.
     */
    Route::post('/faenas/{faena}/pagos', [FaenaController::class, 'pagar'])
        ->middleware('permiso:caja.cobrar')
        ->name('faenas.pagar');

    Route::post('/faenas/{faena}/enviar', [FaenaController::class, 'enviar'])
        ->middleware('permiso:faenas.enviar')
        ->name('faenas.enviar');

    Route::middleware('permiso:faenas.aprobar')->group(function () {
        Route::patch('/faenas/{faena}/aprobar', [FaenaController::class, 'aprobar'])
            ->name('faenas.aprobar');

        Route::patch('/faenas/{faena}/rechazar', [FaenaController::class, 'rechazar'])
            ->name('faenas.rechazar');
    });

    /*
     * COMPLETAR es de VENTANILLA y no de supervisión: registrar que el pescador
     * volvió y descargó es un hecho del mostrador, no una decisión que alguien
     * firme. Recién ahí los kilos quedan firmes contra el cupo.
     */
    Route::patch('/faenas/{faena}/completar', [FaenaController::class, 'completar'])
        ->middleware('permiso:faenas.completar')
        ->name('faenas.completar');

    /*
     * CORREGIR Y ELIMINAR EL BORRADOR. Corregir es de ventanilla —es el mismo
     * mostrador que lo está armando— y eliminar es de supervisión, igual que
     * en el carnet y en el cupo. Las dos solo valen en PENDIENTE y sin un peso
     * cargado: lo decide `PermisoFaena::puedeEditarse()`.
     */
    Route::get('/faenas/{faena}/editar', [FaenaController::class, 'edit'])
        ->middleware('permiso:faenas.editar')
        ->name('faenas.edit');

    Route::patch('/faenas/{faena}', [FaenaController::class, 'update'])
        ->middleware('permiso:faenas.editar')
        ->name('faenas.update');

    Route::delete('/faenas/{faena}', [FaenaController::class, 'destroy'])
        ->middleware('permiso:faenas.eliminar')
        ->name('faenas.destroy');

    // ANTES de '/faenas/{faena}': con la ficha primero, «imprimir» se toma
    // como id. Permiso propio, como `carnets.imprimir`.
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

    /*
     * EL CIRCUITO DE COBRO Y FIRMA, igual que el de la faena: los depósitos se
     * cargan desde la ficha, presentar es de ventanilla y firmar es de
     * supervisión.
     */
    Route::post('/guias/{guia}/pagos', [GuiaController::class, 'pagar'])
        ->middleware('permiso:caja.cobrar')
        ->name('guias.pagar');

    Route::post('/guias/{guia}/enviar', [GuiaController::class, 'enviar'])
        ->middleware('permiso:guias.enviar')
        ->name('guias.enviar');

    Route::middleware('permiso:guias.aprobar')->group(function () {
        Route::patch('/guias/{guia}/aprobar', [GuiaController::class, 'aprobar'])
            ->name('guias.aprobar');

        Route::patch('/guias/{guia}/rechazar', [GuiaController::class, 'rechazar'])
            ->name('guias.rechazar');
    });

    /*
     * CORREGIR Y ELIMINAR EL BORRADOR. Corregir es de ventanilla —es el mismo
     * mostrador que la está armando— y eliminar es de supervisión, igual que en
     * la faena. Las dos solo valen en PENDIENTE y sin un depósito cargado: lo
     * decide `GuiaMovimiento::puedeEditarse()`.
     */
    Route::get('/guias/{guia}/editar', [GuiaController::class, 'edit'])
        ->middleware('permiso:guias.editar')
        ->name('guias.edit');

    Route::patch('/guias/{guia}', [GuiaController::class, 'update'])
        ->middleware('permiso:guias.editar')
        ->name('guias.update');

    Route::delete('/guias/{guia}', [GuiaController::class, 'destroy'])
        ->middleware('permiso:guias.eliminar')
        ->name('guias.destroy');

    // Cerrar es de VENTANILLA: registrar que la carga llegó es un hecho del
    // mostrador, no una decisión que alguien firme.
    Route::patch('/guias/{guia}/cerrar', [GuiaController::class, 'cerrar'])
        ->middleware('permiso:guias.cerrar')
        ->name('guias.cerrar');

    /*
     * ANULAR es de SUPERVISIÓN: quema un número del talonario para siempre —no
     * se desanula— y deja un hueco en la serie que hay que poder explicar. Por
     * eso no lo tiene quien emite.
     */
    Route::patch('/guias/{guia}/anular', [GuiaController::class, 'anular'])
        ->middleware('permiso:guias.anular')
        ->name('guias.anular');

    // ANTES de '/guias/{guia}': con la ficha primero, «imprimir» se toma como
    // id. Permiso propio, como `faenas.imprimir`.
    Route::get('/guias/{guia}/imprimir', [GuiaImpresionController::class, 'imprimir'])
        ->middleware('permiso:guias.imprimir')
        ->name('guias.imprimir');

    Route::get('/guias/{guia}', [GuiaController::class, 'show'])
        ->middleware('permiso:guias.ver')
        ->name('guias.show');

    /*
    | 5. Caja — el circuito del dinero
    */

    Route::middleware('permiso:caja.ver')->group(function () {
        Route::get('/caja', [CajaController::class, 'index'])->name('caja.index');
        Route::get('/recibos', [ReciboController::class, 'index'])->name('recibos.index');
    });

    Route::middleware('permiso:caja.cobrar')->group(function () {
        // 'cobrar' va ANTES de cualquier ruta con parámetro del mismo prefijo,
        // por lo de siempre: esa palabra se tomaría como si fuera un id.
        Route::get('/caja/cobrar', [CajaController::class, 'create'])->name('caja.create');
        Route::post('/caja', [CajaController::class, 'store'])->name('caja.store');
    });

    /*
     * IMPRIMIR va ANTES de '/recibos/{recibo}'… no: van los dos con parámetro,
     * así que el orden entre ellos no importa. Lo que sí importa es el PERMISO:
     * imprimir tiene el suyo —`recibos.imprimir`— y no el de ver.
     */
    Route::get('/recibos/{recibo}/imprimir', [ReciboController::class, 'imprimir'])
        ->middleware('permiso:recibos.imprimir')
        ->name('recibos.imprimir');

    Route::get('/recibos/{recibo}', [ReciboController::class, 'show'])
        ->middleware('permiso:caja.ver')
        ->name('recibos.show');

    /*
     * EL CONTROL DE LAS BOLETAS — la segunda mitad de la revisión.
     */
    Route::middleware('permiso:pagos.controlar')->group(function () {
        Route::patch('/pagos/{pago}/validar', [PagoController::class, 'validar'])
            ->name('pagos.validar');

        Route::patch('/pagos/{pago}/observar', [PagoController::class, 'observar'])
            ->name('pagos.observar');
    });

    /*
     * CORREGIR va por POST porque puede traer un ARCHIVO: un multipart no viaja
     * en un PATCH —PHP no puebla `$_FILES`— y el truco del `_method` hay que
     * recordarlo en cada llamada.
     */
    Route::post('/pagos/{pago}/corregir', [PagoController::class, 'corregir'])
        ->middleware('permiso:pagos.corregir')
        ->name('pagos.corregir');

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

        /*
         * NO HAY ALTA DE TIPOS: la lista sale de la resolución, así que el
         * catálogo se corrige pero no se le agregan filas. Sacar el botón no
         * alcanzaba —la ruta seguía aceptando un POST armado a mano—.
         */
        Route::put('/tipos-carnet/{tipo_carnet}', [TipoCarnetController::class, 'update'])
            ->middleware('permiso:catalogos.gestionar')
            ->name('tipos-carnet.update');
    });

    /*
    | Módulos por construir
    */
});
