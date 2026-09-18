<?php

use App\Http\Controllers\Panel\BeneficiarioController;
use App\Http\Controllers\Panel\CarnetController;
use App\Http\Controllers\Panel\CarnetImpresionController;
use App\Http\Controllers\Panel\DashboardController;
use App\Http\Controllers\Panel\FaenaController;
use App\Http\Controllers\Panel\GuiaController;
use App\Http\Controllers\Panel\PagoController;
use App\Http\Controllers\Panel\ReciboController;
use App\Http\Controllers\Panel\RubroController;
use App\Http\Controllers\Panel\TramiteController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Panel de administración — requiere sesión iniciada
|--------------------------------------------------------------------------
|
| Todo lo de este archivo está dentro del middleware 'auth': si no hay sesión,
| Laravel redirige al login antes de ejecutar nada.
|
| Además cada ruta declara QUÉ PERMISO exige, con el middleware 'permiso'. Ese
| alias apunta a spatie/laravel-permission y está registrado en
| bootstrap/app.php. Los permisos salen del enum App\Enums\RolSistema, que es la
| única fuente de verdad.
|
|   'permiso:beneficiarios.crear'  -> el usuario debe tener ese permiso
|
| HOY EL ÚNICO ROL ES `administrador` Y LOS TIENE TODOS. El middleware igual va
| en cada ruta, y no es trabajo de más: el día que exista el rol de ventanilla,
| se agrega su lista al enum y las rutas ya están protegidas. Al revés —quitarlo
| ahora «porque total el admin puede todo» y volver a ponerlo después— es donde
| se olvida uno y queda un agujero.
|
| ESCONDER UN BOTÓN EN REACT NO ES SEGURIDAD. usePermisos() sirve para que la
| pantalla no ofrezca lo que no se puede hacer; quien realmente bloquea es este
| middleware. Van siempre los dos.
|
| Todas las URLs cuelgan de /panel. La parte pública vive fuera de ese prefijo
| (routes/publico.php), así queda claro de un vistazo qué es administración y
| qué ve el ciudadano.
|
| No hay auto-registro de usuarios: las cuentas las crea el administrador.
|
*/

Route::middleware('auth')->prefix('panel')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Panel principal
    |--------------------------------------------------------------------------
    */

    // El controlador es "invocable" (tiene un único método __invoke), por eso se
    // pasa la clase sola en vez del par [Clase::class, 'metodo'].
    Route::get('/dashboard', DashboardController::class)
        ->middleware('permiso:dashboard.ver')
        ->name('dashboard');

    /*
    |--------------------------------------------------------------------------
    | Beneficiarios — las personas que sacan el carnet
    |--------------------------------------------------------------------------
    |
    | OJO CON EL ORDEN DE LAS RUTAS. Laravel las evalúa de arriba hacia abajo y
    | se queda con la primera que coincide. Por eso 'beneficiarios/crear' tiene
    | que ir ANTES que 'beneficiarios/{beneficiario}': si estuviera después,
    | Laravel tomaría la palabra «crear» como si fuera el id, no encontraría
    | ningún registro y respondería 404.
    |
    */

    Route::middleware('permiso:beneficiarios.ver')->group(function () {
        Route::get('/beneficiarios', [BeneficiarioController::class, 'index'])
            ->name('beneficiarios.index');

        // Autocompletado del formulario de trámite. Devuelve JSON, no una
        // pantalla: se consulta mientras el operador escribe y ahí no se quiere
        // navegar a ningún lado. Va antes del comodín por lo mismo que 'crear'.
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

        // PUT es el verbo para «reemplazar este registro». Como los formularios
        // HTML solo saben GET y POST, Inertia manda un POST con un campo oculto
        // _method=PUT y Laravel lo interpreta.
        Route::put('/beneficiarios/{beneficiario}', [BeneficiarioController::class, 'update'])
            ->name('beneficiarios.update');
    });

    Route::delete('/beneficiarios/{beneficiario}', [BeneficiarioController::class, 'destroy'])
        ->middleware('permiso:beneficiarios.eliminar')
        ->name('beneficiarios.destroy');

    // La ficha va AL FINAL a propósito: '{beneficiario}' es un comodín y se
    // comería '/beneficiarios/crear' y '/beneficiarios/buscar' si estuviera
    // más arriba.
    Route::get('/beneficiarios/{beneficiario}', [BeneficiarioController::class, 'show'])
        ->middleware('permiso:beneficiarios.ver')
        ->name('beneficiarios.show');

    /*
    |--------------------------------------------------------------------------
    | Rubros — el catálogo de actividades
    |--------------------------------------------------------------------------
    |
    | No hay DELETE. Un rubro no se borra nunca: los carnets históricos apuntan a
    | él y desaparecerlo haría que un carnet del año pasado dejara de mostrar una
    | actividad que en su momento estuvo autorizada. Se pasa a 'inactivo' desde
    | el formulario de edición.
    |
    */

    Route::get('/rubros', [RubroController::class, 'index'])
        ->middleware('permiso:rubros.ver')
        ->name('rubros.index');

    Route::middleware('permiso:rubros.gestionar')->group(function () {
        Route::get('/rubros/crear', [RubroController::class, 'create'])->name('rubros.create');
        Route::post('/rubros', [RubroController::class, 'store'])->name('rubros.store');
        Route::get('/rubros/{rubro}/editar', [RubroController::class, 'edit'])->name('rubros.edit');
        Route::put('/rubros/{rubro}', [RubroController::class, 'update'])->name('rubros.update');
    });

    /*
    |--------------------------------------------------------------------------
    | Trámites — el circuito del expediente
    |--------------------------------------------------------------------------
    |
    |     PENDIENTE ──▶ EN REVISIÓN ──▶ APROBADO ──▶ (impreso) ──▶ (entregado)
    |         │              │
    |         └──────────────┴─────────▶ RECHAZADO
    |
    */

    Route::get('/tramites', [TramiteController::class, 'index'])
        ->middleware('permiso:tramites.ver')
        ->name('tramites.index');

    Route::middleware('permiso:tramites.crear')->group(function () {
        // 'crear' va antes que cualquier '{tramite}', por lo mismo que en
        // beneficiarios: el comodín se comería la palabra.
        //
        // Un solo formulario para los dos tipos de trámite: el operador carga
        // siempre lo mismo y el sistema decide si es emisión inicial o adición.
        Route::get('/tramites/crear', [TramiteController::class, 'create'])
            ->name('tramites.create');

        Route::post('/tramites', [TramiteController::class, 'store'])
            ->name('tramites.store');
    });

    Route::get('/tramites/{tramite}', [TramiteController::class, 'show'])
        ->middleware('permiso:tramites.ver')
        ->name('tramites.show');

    /*
     * Corregir un expediente en curso: se reemplaza el papel que salió ilegible.
     * Funciona mientras el expediente siga abierto —PENDIENTE o EN REVISIÓN—,
     * que es el caso más frecuente: el revisor abre el trámite, ve que el
     * escaneo salió ilegible y el operador lo vuelve a cargar sin que haya que
     * rechazar y empezar de nuevo. La regla la decide
     * EstadoTramite::permiteEdicion().
     */
    Route::middleware('permiso:tramites.editar')->group(function () {
        Route::get('/tramites/{tramite}/editar', [TramiteController::class, 'edit'])
            ->name('tramites.edit');

        Route::put('/tramites/{tramite}', [TramiteController::class, 'update'])
            ->name('tramites.update');
    });

    /*
     * BORRAR UN EXPEDIENTE — solo pendiente o en revisión.
     *
     * Permiso propio y del grupo de ADMINISTRACIÓN, no del de operación: borrar
     * no es corregir. El operador de ventanilla que se equivocó al cargar puede
     * editar los adjuntos con `tramites.editar`; hacer desaparecer el
     * expediente entero —con sus pagos y sus archivos— es otra cosa.
     *
     * El estado no se comprueba acá: lo decide EstadoTramite::permiteEliminacion()
     * y lo aplica el servicio. El middleware cubre QUIÉN puede; el servicio,
     * CUÁNDO se puede.
     */
    Route::delete('/tramites/{tramite}', [TramiteController::class, 'destroy'])
        ->middleware('permiso:tramites.eliminar')
        ->name('tramites.destroy');

    /*
    |--------------------------------------------------------------------------
    | Los pasos del circuito
    |--------------------------------------------------------------------------
    |
    |     PENDIENTE ──▶ EN REVISIÓN ──▶ APROBADO ──▶ (impreso) ──▶ (entregado)
    |         │              │
    |         └──────────────┴─────────▶ RECHAZADO
    |
    | TODOS SON PATCH. Cambian parcialmente un recurso que ya existe, que es
    | exactamente lo que significa PATCH.
    |
    | Lo que NO pueden ser nunca es GET. Un verbo de lectura que escribe se
    | dispara solo: alcanza con que el navegador precargue el enlace, que un
    | antivirus corporativo lo visite o que alguien comparta la URL por chat y la
    | vista previa la abra. Un trámite aprobado por el prefetch del navegador es
    | un problema que no se puede explicar después.
    |
    | Cada paso tiene SU ruta y SU permiso, en vez de un único «cambiar estado»
    | con el destino en el cuerpo de la petición. Con eso, quien recepciona
    | podría aprobarse a sí mismo el trámite que acaba de cargar.
    |
    | Qué salto vale desde cada estado lo decide App\Enums\EstadoTramite, no
    | estas rutas: el permiso dice QUIÉN puede, el enum dice DESDE DÓNDE.
    |
    */

    /*
     * ENVIAR EL EXPEDIENTE A REVISIÓN — el paso OBLIGATORIO del circuito.
     *
     * Sin pasar por acá no se puede aprobar: `EstadoTramite::siguientes()` ya no
     * permite el salto PENDIENTE ──▶ APROBADO. El motivo es de control, no de
     * comodidad — con ese atajo, quien cargaba la solicitud en ventanilla podía
     * aprobarla sin que nadie más la tocara.
     *
     * Lleva el permiso de ventanilla (`tramites.editar`) y no el de supervisión:
     * enviar es el último acto de quien ARMA el expediente. Aprobar, que es lo
     * que sigue, exige `tramites.aprobar`, y así los dos actos quedan en manos
     * distintas el día que exista el rol de ventanilla.
     */
    Route::patch('/tramites/{tramite}/enviar', [TramiteController::class, 'enviar'])
        ->middleware('permiso:tramites.editar')
        ->name('tramites.enviar');

    /*
     * REABRIR UN EXPEDIENTE RECHAZADO — el camino de vuelta.
     *
     * Lleva el permiso de VENTANILLA (`tramites.editar`) y no uno de
     * supervisión, por el mismo motivo que enviar: reabrir es el primer acto de
     * quien vuelve a ARMAR el expediente, no una decisión sobre él. La decisión
     * —rechazar— ya la tomó otra persona y queda registrada; esto solo devuelve
     * los papeles a la mesa donde se corrigen.
     *
     * Quién puede lo dice este middleware; CUÁNDO se puede lo dice
     * `EstadoTramite::siguientes()`, y que el carnet no tenga ya otro
     * expediente abierto lo comprueba el servicio.
     */
    Route::patch('/tramites/{tramite}/reabrir', [TramiteController::class, 'reabrir'])
        ->middleware('permiso:tramites.editar')
        ->name('tramites.reabrir');

    Route::patch('/tramites/{tramite}/aprobar', [TramiteController::class, 'aprobar'])
        ->middleware('permiso:tramites.aprobar')
        ->name('tramites.aprobar');

    Route::patch('/tramites/{tramite}/rechazar', [TramiteController::class, 'rechazar'])
        ->middleware('permiso:tramites.rechazar')
        ->name('tramites.rechazar');

    // Imprimir y entregar son permisos de CARNETS y no de trámites: lo que se
    // imprime y se entrega es el documento, y quien lo hace no es
    // necesariamente quien aprobó el expediente.
    Route::patch('/tramites/{tramite}/generar', [TramiteController::class, 'generar'])
        ->middleware('permiso:carnets.generar')
        ->name('tramites.generar');

    Route::patch('/tramites/{tramite}/entregar', [TramiteController::class, 'entregar'])
        ->middleware('permiso:carnets.entregar')
        ->name('tramites.entregar');

    /*
     * EL RECIBO OFICIAL — el talonario verde que se lleva el pescador.
     *
     * Es la ÚNICA ruta del circuito que es GET, y no es una excepción a la regla
     * de arriba: esta no escribe nada. El recibo ya se emitió solo, dentro de la
     * transacción que pasó el expediente a EN REVISIÓN (ver
     * SolicitudCarnetService::enviarARevision); acá únicamente se dibuja el
     * PDF. Que el navegador precargue el enlace no cambia ningún dato ni consume
     * ningún número de la serie.
     *
     * Reimprimir sale siempre con el MISMO número: el papel ya está en manos de
     * alguien, y un segundo recibo por el mismo pago dejaría a Contabilidad con
     * dos comprobantes que no puede cuadrar.
     */
    Route::get('/tramites/{tramite}/recibo', [ReciboController::class, 'imprimir'])
        ->middleware('permiso:recibos.imprimir')
        ->name('tramites.recibo');

    /*
    |--------------------------------------------------------------------------
    | Pagos — Regla C
    |--------------------------------------------------------------------------
    |
    | El alta cuelga del trámite porque un pago sin expediente no significa nada:
    | no habría contra qué compararlo ni a quién acreditárselo.
    |
    | El listado general sí es suelto: es el libro de caja, y lo que se quiere
    | ahí es justamente mirarlos todos juntos para cuadrar contra el banco.
    |
    | NO HAY ANULACIÓN DE PAGOS. Una boleta cargada mal se corrige, y el trait
    | Auditable deja el valor anterior registrado. Un pago «anulado» que sigue en
    | la lista solo invita a sumarlo por error.
    |
    */

    Route::get('/pagos', [PagoController::class, 'index'])
        ->middleware('permiso:pagos.ver')
        ->name('pagos.index');

    Route::post('/tramites/{tramite}/pagos', [PagoController::class, 'store'])
        ->middleware('permiso:pagos.registrar')
        ->name('pagos.store');

    /*
     * CORREGIR UN DEPÓSITO — la única salida cuando la boleta se cargó mal.
     *
     * Cuelga del PAGO y no del trámite, al revés que el alta: el alta necesita
     * saber a qué se le carga el depósito, la corrección no, porque el pago ya
     * sabe de qué es —y desde que `pagos` es polimórfica, ese «de qué» puede ser
     * también una faena o una guía—.
     *
     * VA CON `pagos.registrar` Y NO CON UN PERMISO NUEVO. Corregir lo que se
     * tipeó mal es el mismo acto de ventanilla que cargarlo: quien puede
     * escribir la fila puede arreglarla. Lo que NO puede hacer ventanilla es
     * darla por buena, y eso sigue pidiendo `pagos.validar`.
     *
     * Quién puede lo dice este middleware; CUÁNDO se puede lo dice
     * `Pago::admiteCorreccion()` — un depósito ya validado no se toca, porque
     * alguien firmó que cuadraba con el extracto.
     */
    Route::put('/pagos/{pago}', [PagoController::class, 'update'])
        ->middleware('permiso:pagos.registrar')
        ->name('pagos.update');

    /*
     * QUITAR UN DEPÓSITO — la boleta cargada dos veces, o la de otra persona.
     *
     * PERMISO PROPIO Y DE ADMINISTRACIÓN, no el de ventanilla: quitar no es
     * corregir. Corregir deja la fila y su historial; quitar la hace
     * desaparecer del expediente y de la suma cobrada, y lo único que queda es
     * `auditorias`. Es la misma separación que hay entre `tramites.editar` y
     * `tramites.eliminar`.
     *
     * CUÁNDO se puede lo dice `Pago::admiteEliminacion()`, y es más estricto
     * que corregir: solo mientras el expediente sea un BORRADOR. Una vez
     * enviado salió el recibo oficial, y hacer desaparecer un depósito dejaría
     * ese papel cobrando más de lo que el expediente puede mostrar.
     */
    Route::delete('/pagos/{pago}', [PagoController::class, 'destroy'])
        ->middleware('permiso:pagos.eliminar')
        ->name('pagos.destroy');

    /*
     * CONTROLAR UN DEPÓSITO — validarlo u observarlo.
     *
     * Van por PATCH y no por GET, como todos los pasos que escriben: un verbo de
     * lectura que escribe se dispara solo con que el navegador precargue el
     * enlace. Un depósito «validado» por el prefetch es un problema que no se
     * puede explicar después.
     *
     * Permiso de SUPERVISIÓN y no de ventanilla: quien carga la boleta no puede
     * darla por buena. Eso lo impide además `Pago::puedeValidarlo()`, que
     * compara contra `registrado_por` — el middleware dice QUIÉN puede, el
     * servicio dice SOBRE CUÁL.
     *
     * Son DOS rutas y no una con el destino en el cuerpo, por lo mismo que los
     * pasos del trámite: así el día que observar y validar tengan permisos
     * distintos, ya están separadas.
     */
    Route::middleware('permiso:pagos.validar')->group(function () {
        Route::patch('/pagos/{pago}/validar', [PagoController::class, 'validar'])
            ->name('pagos.validar');

        Route::patch('/pagos/{pago}/observar', [PagoController::class, 'observar'])
            ->name('pagos.observar');
    });

    /*
    |--------------------------------------------------------------------------
    | Carnets — consulta y sanciones
    |--------------------------------------------------------------------------
    |
    | NO HAY ALTA. Un carnet nace dentro de SolicitudCarnetService cuando la
    | Regla A determina que la persona no tenía uno de esta gestión. Un botón de
    | «crear carnet» suelto permitiría emitir documentos sin expediente que los
    | respalde, y sin cobrar.
    |
    */

    Route::middleware('permiso:carnets.ver')->group(function () {
        Route::get('/carnets', [CarnetController::class, 'index'])->name('carnets.index');

        /*
         * Autocompletado de los formularios de faena y de guía. Devuelve JSON,
         * no una pantalla.
         *
         * VA ANTES DEL COMODÍN, por lo mismo que 'beneficiarios/buscar': si
         * estuviera después, Laravel tomaría la palabra «buscar» como si fuera
         * el id del carnet y respondera 404.
         */
        Route::get('/carnets/buscar', [CarnetController::class, 'buscar'])
            ->name('carnets.buscar');

        Route::get('/carnets/{carnet}', [CarnetController::class, 'show'])->name('carnets.show');
    });

    /*
     * IMPRIMIR EL CARNET — el plástico que se lleva la persona.
     *
     * Es GET y NO es una excepción a la regla de que los pasos del circuito van
     * por PATCH: esta ruta no escribe nada. Dibuja el PDF a partir de la fila
     * del carnet y lo manda al navegador; que alguien la precargue o comparta el
     * enlace no cambia ningún dato.
     *
     * MARCAR EL TRÁMITE COMO IMPRESO SIGUE SIENDO OTRA COSA
     * —`PATCH /tramites/{tramite}/generar`—. Son dos actos distintos: abrir la
     * vista previa no es haber sacado el plástico en la impresora de
     * credenciales, y si esta ruta marcara, alcanzaría con mirar el documento
     * para que el expediente declarara un carnet que nunca existió.
     *
     * Lleva `carnets.generar` —el permiso de ventanilla, el mismo que marca
     * impreso— y no `carnets.ver`: consultar un carnet en pantalla y sacar el
     * documento con validez no son la misma atribución.
     */
    Route::get('/carnets/{carnet}/imprimir', [CarnetImpresionController::class, 'imprimir'])
        ->middleware('permiso:carnets.generar')
        ->name('carnets.imprimir');

    Route::post('/carnets/{carnet}/anular', [CarnetController::class, 'anular'])
        ->middleware('permiso:carnets.anular')
        ->name('carnets.anular');

    // Suspender o rehabilitar un rubro suelto del carnet. La medida es por
    // actividad y no por documento: a un pescador se le puede cortar el
    // transporte sin quitarle la pesca.
    /*
     * Suspender o levantar un carnet. Reemplaza a la ruta
     * `habilitaciones.alternar` del modelo anterior: con un carnet por rubro no
     * hay habilitaciones que suspender, se suspende el carnet de esa actividad.
     */
    Route::post('/carnets/{carnet}/suspender', [CarnetController::class, 'suspender'])
        ->middleware('permiso:carnets.suspender')
        ->name('carnets.suspender');

    /*
    |--------------------------------------------------------------------------
    | Faenas — el permiso por salida de pesca
    |--------------------------------------------------------------------------
    |
    | Cuelgan de un carnet de Pescador VIGENTE. Se emiten muchas por gestión: una
    | por cada salida.
    |
    | NO HAY EDICIÓN NI BORRADO. El número sale de un talonario de papel que la
    | persona se lleva en el momento: editarlo dejaría el sistema diciendo una
    | cosa y el papel otra, y borrarlo dejaría un hueco en la serie —además de
    | liberar un número que el índice único volvería a aceptar—. Una faena mal
    | emitida se ANULA, con su motivo escrito.
    |
    */

    Route::get('/faenas', [FaenaController::class, 'index'])
        ->middleware('permiso:faenas.ver')
        ->name('faenas.index');

    Route::middleware('permiso:faenas.crear')->group(function () {
        // 'crear' va antes de cualquier '{faena}': el comodín se comería la
        // palabra y buscaría una faena con id «crear».
        Route::get('/faenas/crear', [FaenaController::class, 'create'])->name('faenas.create');
        Route::post('/faenas', [FaenaController::class, 'store'])->name('faenas.store');
    });

    Route::get('/faenas/{faena}', [FaenaController::class, 'show'])
        ->middleware('permiso:faenas.ver')
        ->name('faenas.show');

    /*
     * ANULAR — PATCH y no GET.
     *
     * Un verbo de lectura que escribe se dispara solo: alcanza con que el
     * navegador precargue el enlace o que alguien lo comparta por chat y la
     * vista previa lo abra. Es la misma regla que en los pasos del trámite.
     *
     * Permiso de SUPERVISIÓN y no de ventanilla: quema un número del talonario
     * para siempre.
     */
    Route::patch('/faenas/{faena}/anular', [FaenaController::class, 'anular'])
        ->middleware('permiso:faenas.anular')
        ->name('faenas.anular');

    /*
    |--------------------------------------------------------------------------
    | Guías únicas de transporte
    |--------------------------------------------------------------------------
    |
    | Cuelgan de un carnet de Comercializador VIGENTE, y son muchas por gestión.
    |
    | Misma regla que las faenas: la CABECERA no se edita. Lo único que se
    | corrige es el DETALLE de la carga —el peso real sale de la balanza—, y solo
    | mientras la guía siga valiendo.
    |
    */

    Route::get('/guias', [GuiaController::class, 'index'])
        ->middleware('permiso:guias.ver')
        ->name('guias.index');

    Route::middleware('permiso:guias.crear')->group(function () {
        Route::get('/guias/crear', [GuiaController::class, 'create'])->name('guias.create');
        Route::post('/guias', [GuiaController::class, 'store'])->name('guias.store');

        // Corregir la grilla de carga. Va con el permiso de QUIEN EMITE y no con
        // el de anular: ajustar los kilos contra la balanza es parte de emitir.
        Route::put('/guias/{guia}/detalle', [GuiaController::class, 'actualizarDetalle'])
            ->name('guias.detalle');
    });

    Route::get('/guias/{guia}', [GuiaController::class, 'show'])
        ->middleware('permiso:guias.ver')
        ->name('guias.show');

    Route::patch('/guias/{guia}/anular', [GuiaController::class, 'anular'])
        ->middleware('permiso:guias.anular')
        ->name('guias.anular');

    /*
    |--------------------------------------------------------------------------
    | Módulos pendientes
    |--------------------------------------------------------------------------
    |
    | Reportes y Configuración todavía no existen. Aparecen en el menú lateral en
    | gris, porque el layout de React comprueba si la ruta está declarada antes
    | de convertirla en enlace (ver barra-lateral.tsx).
    |
    | Para construir cualquiera de ellos: copiar el patrón de Beneficiarios.
    |
    */
});
