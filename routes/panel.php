<?php

use App\Http\Controllers\Panel\DashboardController;
use App\Http\Controllers\Panel\SolicitanteController;
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
| Además cada ruta declara QUÉ PERMISO exige, con el middleware 'permiso'.
| Ese alias apunta a spatie/laravel-permission y está registrado en
| bootstrap/app.php. Los permisos salen del enum App\Enums\RolSistema, que es
| la única fuente de verdad: ahí se define qué puede hacer cada rol.
|
|   'permiso:solicitantes.crear'  -> el usuario debe tener ese permiso
|   'rol:administrador'           -> el usuario debe tener ese rol
|
| Todas las URLs de acá cuelgan de /panel: /panel/dashboard, /panel/solicitantes.
| La parte pública vive fuera de ese prefijo (routes/publico.php), así queda
| claro de un vistazo qué es administración y qué ve el ciudadano.
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

    // El controlador es "invocable" (tiene un único método __invoke), por eso
    // se pasa la clase sola en vez del par [Clase::class, 'metodo'].
    Route::get('/dashboard', DashboardController::class)
        ->middleware('permiso:dashboard.ver')
        ->name('dashboard');

    /*
    |--------------------------------------------------------------------------
    | Solicitantes — pescadores, comerciantes y empresas que hacen trámites
    |--------------------------------------------------------------------------
    |
    | OJO CON EL ORDEN DE LAS RUTAS. Laravel las evalúa de arriba hacia abajo y
    | se queda con la primera que coincide. Por eso 'solicitantes/crear' tiene
    | que ir ANTES que 'solicitantes/{solicitante}': si estuviera después,
    | Laravel tomaría la palabra "crear" como si fuera el id del solicitante,
    | no encontraría ningún registro con ese id y respondería 404.
    |
    */

    // --- Lectura: la puede hacer cualquiera que tenga solicitantes.ver
    Route::middleware('permiso:solicitantes.ver')->group(function () {
        Route::get('/solicitantes', [SolicitanteController::class, 'index'])
            ->name('solicitantes.index');
    });

    // --- Alta: solo quien tenga solicitantes.crear
    Route::middleware('permiso:solicitantes.crear')->group(function () {
        Route::get('/solicitantes/crear', [SolicitanteController::class, 'create'])
            ->name('solicitantes.create');

        Route::post('/solicitantes', [SolicitanteController::class, 'store'])
            ->name('solicitantes.store');
    });

    // --- Edición: solo quien tenga solicitantes.editar
    Route::middleware('permiso:solicitantes.editar')->group(function () {
        Route::get('/solicitantes/{solicitante}/editar', [SolicitanteController::class, 'edit'])
            ->name('solicitantes.edit');

        // PUT es el verbo para "reemplazar este registro". Como los formularios
        // HTML solo saben hacer GET y POST, Inertia manda un POST con un campo
        // oculto _method=PUT y Laravel lo interpreta. En React esto se resuelve
        // solo: ver el comentario en pages/panel/solicitantes/editar.tsx.
        Route::put('/solicitantes/{solicitante}', [SolicitanteController::class, 'update'])
            ->name('solicitantes.update');
    });

    // --- Baja: solo quien tenga solicitantes.eliminar (administrador)
    Route::delete('/solicitantes/{solicitante}', [SolicitanteController::class, 'destroy'])
        ->middleware('permiso:solicitantes.eliminar')
        ->name('solicitantes.destroy');

    // --- Ficha individual. Va al final a propósito: '{solicitante}' es un
    //     comodín y se comería a '/solicitantes/crear' si estuviera más arriba.
    Route::get('/solicitantes/{solicitante}', [SolicitanteController::class, 'show'])
        ->middleware('permiso:solicitantes.ver')
        ->name('solicitantes.show');

    /*
    |--------------------------------------------------------------------------
    | Trámites
    |--------------------------------------------------------------------------
    |
    | El circuito completo, en el orden en que ocurre en ventanilla:
    |
    |     recepción  →  revisión  →  aprobación  →  emisión  →  entrega
    |
    | Cada paso tiene SU permiso, y no son los mismos: quien atiende en
    | ventanilla recepciona y entrega, pero no aprueba. Esa separación es la
    | razón de que haya cinco rutas y no un único «cambiar estado» con el
    | destino en el cuerpo de la petición — con eso, un operador podría
    | aprobarse a sí mismo el trámite que acaba de cargar.
    |
    | Qué salto es válido desde cada estado lo decide EstadoTramite, no estas
    | rutas: el permiso dice QUIÉN puede, el enum dice DESDE DÓNDE.
    |
    */

    Route::middleware('permiso:tramites.ver')->group(function () {
        Route::get('/tramites', [TramiteController::class, 'index'])
            ->name('tramites.index');
    });

    Route::middleware('permiso:tramites.crear')->group(function () {
        // 'crear' va antes que cualquier '{tramite}', por lo mismo que en
        // solicitantes: el comodín se comería la palabra.

        // Paso 1: elegir el servicio.
        Route::get('/tramites/crear', [TramiteController::class, 'create'])
            ->name('tramites.create');

        // Paso 2: un formulario por servicio. Cada uno es su propio archivo
        // porque los formularios en papel no se parecen en nada entre sí.
        Route::get('/tramites/crear/permiso-faena', [TramiteController::class, 'crearPermisoFaena'])
            ->name('tramites.crear.permiso-faena');

        Route::get('/tramites/crear/guia-transporte', [TramiteController::class, 'crearGuiaTransporte'])
            ->name('tramites.crear.guia-transporte');

        Route::get('/tramites/crear/cedula-pescador', [TramiteController::class, 'crearCedulaPescador'])
            ->name('tramites.crear.cedula-pescador');

        Route::post('/tramites', [TramiteController::class, 'store'])
            ->name('tramites.store');
    });

    /*
     * La ficha de un trámite.
     *
     * Va DESPUÉS del bloque de arriba y no antes: '/tramites/crear' tiene que
     * declararse primero, o el comodín {tramite} se come la palabra «crear» y
     * la trata como un id. Es la misma trampa que en solicitantes.
     */
    Route::middleware('permiso:tramites.ver')->group(function () {
        Route::get('/tramites/{tramite}', [TramiteController::class, 'show'])
            ->name('tramites.show');
    });

    /*
     * Corregir un expediente en curso.
     *
     * Va acá arriba, antes de los pasos del circuito, porque '/editar' es una
     * palabra fija y el comodín {tramite} de más abajo no la alcanza igual —el
     * de show() ya se declaró—. Solo funciona mientras el trámite está recibido
     * o en revisión; la regla la decide EstadoTramite::permiteEdicion().
     */
    Route::middleware('permiso:tramites.editar')->group(function () {
        Route::get('/tramites/{tramite}/editar', [TramiteController::class, 'edit'])
            ->name('tramites.edit');

        // PUT es el verbo para «reemplazar este registro». Como los formularios
        // HTML solo saben GET y POST, Inertia manda un POST con _method=PUT.
        Route::put('/tramites/{tramite}', [TramiteController::class, 'update'])
            ->name('tramites.update');
    });

    /*
     * Los pasos del circuito. Todos POST: cambian el estado del expediente, y
     * un GET que modifica datos se dispara solo con que el navegador precargue
     * el enlace.
     */
    Route::post('/tramites/{tramite}/aprobar', [TramiteController::class, 'aprobar'])
        ->middleware('permiso:tramites.aprobar')
        ->name('tramites.aprobar');

    Route::post('/tramites/{tramite}/rechazar', [TramiteController::class, 'rechazar'])
        ->middleware('permiso:tramites.rechazar')
        ->name('tramites.rechazar');

    // Emitir y entregar son permisos de DOCUMENTOS y no de trámites: lo que se
    // emite y se entrega es el documento, y quien lo hace no es necesariamente
    // quien aprobó el expediente.
    Route::post('/tramites/{tramite}/emitir', [TramiteController::class, 'emitir'])
        ->middleware('permiso:documentos.emitir')
        ->name('tramites.emitir');

    Route::post('/tramites/{tramite}/entregar', [TramiteController::class, 'entregar'])
        ->middleware('permiso:documentos.entregar')
        ->name('tramites.entregar');

    /*
    |--------------------------------------------------------------------------
    | Módulos pendientes
    |--------------------------------------------------------------------------
    |
    | Documentos, Reportes y Configuración todavía no existen.
    | Aparecen en el menú lateral en gris, porque el layout de React comprueba
    | si la ruta está declarada antes de convertirla en enlace.
    |
    | Para construir cualquiera de ellos: copiar el patrón de Solicitantes.
    | El paso a paso está en docs/GUIA-INERTIA.md y la lista completa de lo
    | que falta en docs/PENDIENTES.md.
    |
    */
});
