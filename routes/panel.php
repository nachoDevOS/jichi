<?php

use App\Http\Controllers\Panel\AprovechamientoController;
use App\Http\Controllers\Panel\AsociacionController;
use App\Http\Controllers\Panel\BeneficiarioController;
use App\Http\Controllers\Panel\CajaController;
use App\Http\Controllers\Panel\CarnetController;
use App\Http\Controllers\Panel\CarnetImpresionController;
use App\Http\Controllers\Panel\CategoriaAprovechamientoController;
use App\Http\Controllers\Panel\DashboardController;
use App\Http\Controllers\Panel\FaenaController;
use App\Http\Controllers\Panel\GuiaController;
use App\Http\Controllers\Panel\ReciboController;
use App\Http\Controllers\Panel\TipoCarnetController;
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
|--------------------------------------------------------------------------
| EL ORDEN DE LOS BLOQUES ES EL DEL FLUJO DE TRABAJO
|--------------------------------------------------------------------------
|
|     1. Beneficiarios      la persona, una sola vez
|     2. Aprovechamientos   la bolsa madre: el cupo en kilos
|     3. Carnets            la credencial anual, y su impresión
|     4. Faenas / Guías     los permisos operativos que cuelgan del carnet
|     5. Caja               recibos y pagos
|
| Los módulos que todavía no existen NO tienen rutas declaradas, y eso es
| deliberado: una ruta declarada convierte el renglón del menú en un enlace
| pinchable (ver barra-lateral.tsx, que pregunta por la ruta antes de enlazar).
| Sin ella, el renglón se dibuja en gris y se lee como «todavía no» en vez de
| llevar a un 500.
|
| Del modelo anterior no quedó nada: Trámites y Rubros no tienen equivalente y
| se retiraron enteros. Están en git, en el commit `8d48422`.
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
    | 1. Beneficiarios — la persona, UNA SOLA VEZ
    |--------------------------------------------------------------------------
    |
    | Es el primer paso del flujo, y el único módulo que sobrevivió al cambio de
    | núcleo casi intacto: la persona no cambió, lo que cambió es lo que le
    | cuelga.
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
    |--------------------------------------------------------------------------
    | 2. Aprovechamientos — la BOLSA MADRE del pescador
    |--------------------------------------------------------------------------
    |
    | El cupo anual en kilos. Va ANTES del carnet en el flujo porque el plástico
    | necesita saber qué cupo imprimir: al revés habría que emitir el carnet y
    | corregirlo después, y en el medio existiría una credencial impresa sin
    | cupo.
    |
    | `edit`, `update` y `destroy` EXISTEN, PERO SOLO SOBRE EL BORRADOR.
    |
    | Un cupo nace PENDIENTE DE PAGO, y mientras nadie pagó nada es eso: un
    | borrador que el operador acaba de cargar contra el talonario, con el
    | pescador enfrente. Equivocarse de tramo se arregla corrigiendo la fila, y
    | uno cargado por error se borra con el motivo escrito.
    |
    | En cuanto entra el primer boliviano las tres se cierran solas —lo decide
    | `EstadoAprovechamiento::permiteEdicion()`, no el middleware—: hay un recibo
    | numerado con el detalle impreso, y cambiar lo que ese papel dice por detrás
    | no es una corrección. De ahí en adelante el único camino es AMPLIARLO, que
    | suma kilos, pide su propio permiso y deja el motivo en `auditorias`.
    |
    | Un cupo editable SIEMPRE dejaría de ser un límite: alcanzaría con subirle
    | el tramo para saltear la escala, sin que quedara constancia de quién lo
    | decidió.
    |
    | Mismo cuidado con el orden que en beneficiarios: 'crear' va ANTES de
    | '{aprovechamiento}' o Laravel toma esa palabra como si fuera el id.
    |
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
     * ELIMINAR es de SUPERVISIÓN: borrar la fila la hace desaparecer, y lo único
     * que queda es la línea de `auditorias` con el motivo. Ver el bloque de
     * arriba.
     */
    Route::delete('/aprovechamientos/{aprovechamiento}', [AprovechamientoController::class, 'destroy'])
        ->middleware('permiso:aprovechamientos.eliminar')
        ->name('aprovechamientos.destroy');

    /*
     * AMPLIAR es de SUPERVISIÓN y no de ventanilla: otorgar es aplicar la
     * escala que corresponde, ampliar es dar más kilos de los que esa escala
     * daba — que es justamente lo que el cupo viene a limitar.
     */
    Route::patch('/aprovechamientos/{aprovechamiento}/ampliar', [AprovechamientoController::class, 'ampliar'])
        ->middleware('permiso:aprovechamientos.ampliar')
        ->name('aprovechamientos.ampliar');

    Route::get('/aprovechamientos/{aprovechamiento}', [AprovechamientoController::class, 'show'])
        ->middleware('permiso:aprovechamientos.ver')
        ->name('aprovechamientos.show');

    /*
    |--------------------------------------------------------------------------
    | 3. Carnets — la credencial anual
    |--------------------------------------------------------------------------
    |
    | La LLAVE del año. De ella cuelgan los permisos operativos: faenas si es de
    | pescador, guías si es de comercializador.
    |
    | NO HAY `edit` NI `update`. Un carnet emitido no se corrige: el plástico ya
    | salió de la impresora y está en manos de la persona, así que editarlo
    | dejaría al documento diciendo una cosa y al sistema otra —y la
    | verificación pública respondería por el dato nuevo, que el inspector NO
    | tiene delante—. Lo que hay es REVOCAR, con motivo, y emitir uno nuevo.
    |
    | Mismo cuidado con el orden: 'crear' va ANTES de '{carnet}'.
    |
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
    Route::patch('/carnets/{carnet}/revocar', [CarnetController::class, 'revocar'])
        ->middleware('permiso:carnets.revocar')
        ->name('carnets.revocar');

    /*
     * EL PLÁSTICO. Va ANTES de '{carnet}' aunque la URL sea más larga: el
     * orden solo importa entre rutas que puedan coincidir con el mismo camino,
     * y estas dos no —'/carnets/7/imprimir' no coincide con '/carnets/{carnet}'—
     * pero se agrupa acá para que el bloque se lea de corrido.
     *
     * Es GET y devuelve bytes, no una pantalla de Inertia: el navegador lo abre
     * en su visor de PDF, que es desde donde el operador aprieta imprimir.
     */
    Route::get('/carnets/{carnet}/imprimir', [CarnetImpresionController::class, 'imprimir'])
        ->middleware('permiso:carnets.imprimir')
        ->name('carnets.imprimir');

    Route::get('/carnets/{carnet}', [CarnetController::class, 'show'])
        ->middleware('permiso:carnets.ver')
        ->name('carnets.show');

    /*
    |--------------------------------------------------------------------------
    | 4a. Permisos de faena — una salida de pesca
    |--------------------------------------------------------------------------
    |
    | Cuelgan del carnet de PESCADOR y descuentan kilos de la bolsa madre. El
    | carnet es la llave anual; con él solo no se sale a trabajar.
    |
    | NO HAY `edit`, NI `update`, NI `destroy`, NI `anular`. El número sale de un
    | talonario de papel que el pescador se llevó: borrar la fila deja un hueco
    | en la serie que nadie puede explicar y libera un número que el índice único
    | volvería a aceptar.
    |
    | Y `EstadoFaena` no tiene un estado anulado: una faena emitida de más se
    | deja VENCER, y al vencer libera su volumen sola. Lo único que se escribe
    | después de emitir es COMPLETAR, que registra la vuelta.
    |
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
     * COMPLETAR es de VENTANILLA y no de supervisión: registrar que el pescador
     * volvió y descargó es un hecho del mostrador, no una decisión que alguien
     * firme. Recién ahí los kilos quedan firmes contra el cupo.
     */
    Route::patch('/faenas/{faena}/completar', [FaenaController::class, 'completar'])
        ->middleware('permiso:faenas.completar')
        ->name('faenas.completar');

    Route::get('/faenas/{faena}', [FaenaController::class, 'show'])
        ->middleware('permiso:faenas.ver')
        ->name('faenas.show');

    /*
    |--------------------------------------------------------------------------
    | 4b. Guías de movimiento — un traslado de producto
    |--------------------------------------------------------------------------
    |
    | La rama del COMERCIALIZADOR. Lo que la faena es para el pescador, la guía
    | es para él: el carnet habilita el año, la guía habilita el viaje.
    |
    | TRES DIFERENCIAS CON LAS FAENAS:
    |
    |   - No toca ningún cupo: la comercialización no se autoriza por volumen.
    |   - Lleva descuento: piscicultura paga el 50% del arancel.
    |   - SÍ SE ANULA. `EstadoGuia` tiene ese estado y `EstadoFaena` no, porque
    |     una guía emitida mal ampara un camión que puede estar en la ruta.
    |
    | Igual que las faenas, NO hay `edit` ni `destroy`: el código sale de un
    | talonario de papel que viaja dentro del camión.
    |
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

    Route::get('/guias/{guia}', [GuiaController::class, 'show'])
        ->middleware('permiso:guias.ver')
        ->name('guias.show');

    /*
    |--------------------------------------------------------------------------
    | 5. Caja — el circuito del dinero
    |--------------------------------------------------------------------------
    |
    | Atraviesa a todos los anteriores: se cobran la credencial, el cupo y la
    | guía, y los tres se pagan igual. NO es un paso del flujo —es algo que
    | puede pasar en cualquiera de ellos y varias veces— y por eso va aparte y
    | no intercalado.
    |
    | DOS LISTADOS QUE NO SE REEMPLAZAN:
    |
    |   /caja     los ABONOS, uno por entrega de dinero. Es lo que se cuadra
    |             contra el efectivo del cajón al cerrar el día.
    |   /recibos  los PAPELES entregados, con su correlativo. Es lo que audita
    |             Contabilidad.
    |
    | Un recibo agrupa varios abonos, así que las dos listas nunca tienen la
    | misma cantidad de filas.
    |
    | LOS RECIBOS NO TIENEN `store`: nacen del cobro, en la misma transacción.
    | Un endpoint para crear uno suelto permitiría un comprobante numerado sin
    | ningún pago detrás — un papel oficial que dice que entró plata que no
    | entró.
    |
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

    Route::get('/recibos/{recibo}', [ReciboController::class, 'show'])
        ->middleware('permiso:caja.ver')
        ->name('recibos.show');

    /*
    |--------------------------------------------------------------------------
    | Catálogos — lo que sale de una resolución y casi no se toca
    |--------------------------------------------------------------------------
    |
    | Son tres listas chicas: los gremios, la escala oficial de kilos y precios,
    | y los tipos de credencial con su arancel. De ellas dependen los dos pasos
    | siguientes del flujo —el cupo y el carnet— así que sin cargarlas no se
    | puede emitir nada.
    |
    | CUELGAN DE /panel/catalogos/ Y NO DE LA RAÍZ del panel a propósito: son
    | mantenimiento, no trabajo de mostrador, y la URL lo dice sin que haga
    | falta explicarlo.
    |
    | LOS TRES TIENEN index + store + update, Y NINGUNO TIENE destroy. No es un
    | olvido: los carnets, las guías y los aprovechamientos ya emitidos apuntan
    | a estas filas. Una entrada que se deja de usar se pone inactiva, y así los
    | documentos históricos la siguen mostrando —que es lo correcto: la persona
    | pertenecía a esa asociación cuando se le emitió el carnet—.
    |
    | Tampoco tienen pantalla de alta ni de edición aparte: el formulario vive
    | al lado de la tabla, porque son listas de pocas filas que se comparan
    | entre sí mientras se cargan. Ver AsociacionController.
    |
    | VER es de lectura y GESTIONAR es de administración, y por eso son dos
    | permisos: cualquiera que emita un carnet necesita LEER el catálogo —el
    | desplegable sale de acá— pero tocar una tarifa es otra cosa.
    |
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

        Route::middleware('permiso:catalogos.gestionar')->group(function () {
            Route::post('/tipos-carnet', [TipoCarnetController::class, 'store'])
                ->name('tipos-carnet.store');

            Route::put('/tipos-carnet/{tipo_carnet}', [TipoCarnetController::class, 'update'])
                ->name('tipos-carnet.update');
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Módulos por construir
    |--------------------------------------------------------------------------
    |
    | Aprovechamientos, Carnets, Faenas, Guías, Caja, Reportes y Configuración.
    | Todos aparecen en el menú lateral en gris, porque barra-lateral.tsx
    | comprueba si la ruta está declarada antes de convertir el renglón en
    | enlace.
    |
    | Para construir cualquiera: copiar el patrón de Beneficiarios —controlador,
    | Request, tipos de TypeScript y pantallas—, que es la plantilla del sistema
    | y está comentado paso a paso a propósito.
    |
    */
});
