<?php

namespace App\Http\Controllers\Panel;

use App\Enums\EstadoTramite;
use App\Enums\TipoTramite;
use App\Exceptions\SolicitudInvalidaException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\RegistrarSolicitudRequest;
use App\Models\Beneficiario;
use App\Models\Carnet;
use App\Models\Rubro;
use App\Models\Tramite;
use App\Services\ReciboTramiteService;
use App\Services\SolicitudCarnetService;
use App\Support\ControlDePago;
use App\Support\Paginacion;
use App\Support\SituacionCarnet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * ============================================================================
 *  MÓDULO TRÁMITES
 * ============================================================================
 *
 * EL CONTROLADOR NO DECIDE NADA. Esa es la idea central de este archivo.
 *
 * Todo lo que es regla de negocio —si corresponde emisión inicial o adición de
 * rubro, si el carnet admite más rubros, si el monto está cubierto, en qué
 * orden se suben los archivos respecto de la transacción— vive en
 * SolicitudCarnetService. Acá solo se hacen tres cosas:
 *
 *     1. traducir la petición HTTP a objetos del dominio,
 *     2. llamar al servicio,
 *     3. convertir lo que devuelva (o la excepción que lance) en un redirect.
 *
 * ¿Por qué tanto cuidado? Porque el mismo caso de uso lo van a necesitar un
 * comando de consola para migrar el padrón en papel y las pruebas automáticas.
 * Escrito acá adentro, esos dos tendrían que copiarlo, y las copias se quedan
 * viejas.
 */
class TramiteController extends Controller
{
    public function __construct(
        private readonly SolicitudCarnetService $solicitudes,
        private readonly ReciboTramiteService $recibos,
    ) {}

    /**
     * LISTADO — GET /panel/tramites
     */
    public function index(Request $request): Response
    {
        $filtros = [
            'buscar' => $request->string('buscar')->trim()->value() ?: null,
            'estado' => $request->string('estado')->trim()->value() ?: null,
            'tipo' => $request->string('tipo')->trim()->value() ?: null,
            'gestion' => $request->integer('gestion') ?: null,
            'por_pagina' => Paginacion::filas($request),
        ];

        $tramites = Tramite::query()
            // with() y no consultas dentro del bucle: sin esto, pintar 15 filas
            // serían 46 consultas a la base (el problema "N+1").
            /*
             * OJO CON LAS COLUMNAS QUE SE PIDEN DE `beneficiarios`.
             *
             * Traer solo algunas hace la consulta más liviana, pero los ACCESORES
             * del modelo siguen necesitando las suyas. `documento_identidad` se
             * arma con ci_nit + complemento + expedido: si esas tres no están en
             * la lista, el accesor devuelve una cadena vacía —no un error— y la
             * tabla muestra la columna en blanco sin que nada lo explique.
             *
             * Lo mismo vale para `nombreCompleto` y sus cinco partes, y para
             * `foto_url`, que necesita la columna `foto`.
             */
            ->with([
                'rubro:id,nombre',
                'carnet:id,gestion,beneficiario_id',
                'carnet.beneficiario:id,foto,ci_nit,complemento,expedido,primerNombre,segundoNombre,apellidoPaterno,apellidoMaterno,apellidoCasado',
            ])

            // withSum trae lo cobrado de cada trámite en la MISMA consulta. Sin
            // él, calcular el saldo de cada fila sería una consulta agregada por
            // fila. Ver Tramite::montoPagado(), que usa este atributo si está.
            ->withSum('pagos', 'monto')

            ->when($filtros['estado'], fn ($q, $estado) => $q->where('tramites.estado', $estado))
            ->when($filtros['tipo'], fn ($q, $tipo) => $q->where('tipo_tramite', $tipo))
            ->when($filtros['gestion'], fn ($q, $gestion) => $q->deGestion($gestion))

            // La búsqueda salta al titular a través del carnet. whereHas genera
            // un EXISTS, que es lo que se quiere: filtrar sin multiplicar filas
            // como haría un join.
            /*
             * La búsqueda salta al titular a través del carnet, y acepta también
             * el número de registro impreso. whereHas genera un EXISTS, que es lo
             * que se quiere: filtrar sin multiplicar filas como haría un join.
             */
            ->when($filtros['buscar'], fn ($q, $termino) => $q->whereHas(
                'carnet',
                function ($c) use ($termino) {
                    $c->whereHas('beneficiario', fn ($b) => $b->buscar($termino));

                    // «000013» y «13» son el mismo carnet.
                    if (ctype_digit(ltrim($termino, '0')) && ltrim($termino, '0') !== '') {
                        $c->orWhere('id', (int) ltrim($termino, '0'));
                    }
                },
            ))

            /*
             * Lo más nuevo primero, ordenado por ID y NO por `fecha_solicitud`.
             *
             * La fecha empata: en una mañana de ventanilla entran varios
             * trámites en el mismo segundo, y con la fecha sola el orden entre
             * ellos lo decide el motor —o sea, cambia de una consulta a otra y
             * una fila puede aparecer dos veces al pasar de página—. El id es
             * estrictamente creciente y nunca empata, así que la paginación
             * queda estable.
             */
            ->orderByDesc('tramites.id')
            ->paginate($filtros['por_pagina'])
            // Sin esto, al pasar de página se pierden los filtros.
            ->withQueryString()
            ->through(fn (Tramite $t): array => $this->resumir($t));

        return Inertia::render('panel/tramites/index', [
            'tramites' => $tramites,
            'filtros' => $filtros,
            // Los catálogos salen del servidor y no escritos en React: los
            // valores de estado y tipo son los del enum, y tenerlos repetidos en
            // el frontend garantiza que algún día digan cosas distintas.
            'estados' => EstadoTramite::opciones(),
            'tipos' => TipoTramite::opciones(),
            'gestiones' => $this->gestionesDisponibles(),
            'opcionesPorPagina' => Paginacion::OPCIONES,
        ]);
    }

    /**
     * FORMULARIO DE ALTA — GET /panel/tramites/crear
     *
     * Un solo formulario para los dos tipos de trámite. No hay una pantalla de
     * «emisión inicial» y otra de «adición»: el operador carga siempre lo mismo
     * —persona, rubro, dos papeles, los pagos que traiga— y el sistema decide el
     * tipo al guardar. Ver App\Enums\TipoTramite.
     */
    public function create(Request $request): Response
    {
        // La pantalla puede venir precargada desde la ficha de un beneficiario
        // ("nuevo trámite" desde ahí), y en ese caso el buscador arranca resuelto.
        $beneficiario = $request->integer('beneficiario')
            ? Beneficiario::find($request->integer('beneficiario'))
            : null;

        $gestion = (int) now()->format('Y');

        return Inertia::render('panel/tramites/crear', [
            'gestion' => $gestion,

            // La MISMA forma que devuelve el autocompletado, a propósito: la
            // pantalla no tiene que saber de dónde vino el beneficiario.
            'beneficiarioPreseleccionado' => $beneficiario === null ? null : [
                'id' => $beneficiario->id,
                'nombreCompleto' => $beneficiario->nombreCompleto,
                'documento_identidad' => $beneficiario->documento_identidad,
                'foto_url' => $beneficiario->foto_url,
                'ciudad' => $beneficiario->ciudad,
                'provincia' => $beneficiario->provincia,
                'direccion' => $beneficiario->direccion,
                'situacion' => SituacionCarnet::para($beneficiario, $gestion),
            ],

            // Solo los rubros que se pueden pedir hoy. Los inactivos siguen
            // existiendo y se ven en los carnets que ya los tienen, pero no se
            // ofrecen acá. El servidor lo vuelve a comprobar al guardar: el
            // rubro_id llega por el cuerpo de la petición y cualquiera puede
            // escribir otro.
            'rubros' => Rubro::query()
                ->activos()
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'descripcion', 'costo', 'requiere_capacidad'])
                ->map(fn (Rubro $r): array => [
                    'id' => $r->id,
                    'nombre' => $r->nombre,
                    'descripcion' => $r->descripcion,
                    'costo' => (float) $r->costo,
                    // De esto depende que el formulario muestre el campo del
                    // cupo. La validación lo vuelve a comprobar contra el
                    // catálogo: esconder el campo es comodidad, no regla.
                    'requiere_capacidad' => $r->requiereCapacidad(),
                ])
                ->all(),
        ]);
    }

    /**
     * GUARDAR LA SOLICITUD — POST /panel/tramites
     *
     * Acá se ve lo dicho arriba: el método es corto porque no decide nada.
     * Reglas A, B y C las resuelve el servicio, en una sola transacción.
     */
    public function store(RegistrarSolicitudRequest $request): RedirectResponse
    {
        $beneficiario = Beneficiario::findOrFail($request->integer('beneficiario_id'));
        $rubro = Rubro::findOrFail($request->integer('rubro_id'));

        try {
            $tramite = $this->solicitudes->registrar(
                beneficiario: $beneficiario,
                rubro: $rubro,
                adjuntos: [
                    'ciFile' => $request->file('ciFile'),
                    'certAsociacionFile' => $request->file('certAsociacionFile'),
                ],
                // El request sabe armar esta lista porque conoce la forma del
                // formulario; el controlador no tiene por qué.
                pagosIniciales: $request->pagosIniciales(),
                observaciones: $request->input('observaciones'),
                asociacion: $request->input('asociacion'),

                // El formulario lo exige, así que acá siempre llega con valor.
                // El servicio igual lo acepta nulo: lo necesita el día que se
                // cargue el padrón en papel por consola, donde el dato puede no
                // estar. Ver RegistrarSolicitudRequest.
                capacidadKg: $request->input('capacidad_kg'),
            );
        } catch (SolicitudInvalidaException $e) {
            // El mensaje de la excepción está escrito en castellano de mostrador
            // justamente para poder mostrarlo tal cual. Se vuelve al formulario
            // con lo que el operador había cargado (withInput) para que no tenga
            // que escribirlo todo de nuevo.
            //
            // OJO: los ARCHIVOS no vuelven. Los navegadores no permiten
            // rellenar un input de tipo file por seguridad, así que el operador
            // tiene que volver a adjuntarlos. No hay forma de evitarlo.
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('tramites.show', $tramite)
            ->with('exito', sprintf(
                'Trámite registrado como %s sobre el carnet de la gestión %s.',
                $tramite->tipo_tramite->etiqueta(),
                $tramite->carnet->gestion,
            ));
    }

    /**
     * FICHA — GET /panel/tramites/{tramite}
     */
    public function show(Request $request, Tramite $tramite): Response
    {
        $tramite->load([
            'rubro',
            'carnet.beneficiario',
            'carnet.rubro',
            // Con `validadoPor` y `registradoPor`: la ficha muestra quién cargó
            // y quién controló cada boleta, y sin esto son dos consultas por
            // depósito.
            'pagos.registradoPor:id,name',
            'pagos.validadoPor:id,name',
        ]);

        // Quién está mirando, para saber si puede validar cada depósito: quien
        // cargó la boleta no puede darla por buena.
        $usuario = $request->user();

        return Inertia::render('panel/tramites/ver', [
            'tramite' => [
                ...$this->resumir($tramite),
                'observaciones' => $tramite->observaciones,
                'motivo_rechazo' => $tramite->motivo_rechazo,
                // Los adjuntos van como URL ya armada y no como ruta cruda: la
                // columna guarda a veces una ruta y a veces una dirección
                // completa, y decidir eso en React sería repetir la lógica de
                // App\Support\Archivos en otro lenguaje.
                'ci_file_url' => $tramite->ci_file_url,
                'cert_asociacion_file_url' => $tramite->cert_asociacion_file_url,
                'fecha_revision' => $tramite->fecha_revision?->toIso8601String(),
                'fecha_aprobacion' => $tramite->fecha_aprobacion?->toIso8601String(),
                'fecha_generacion' => $tramite->fecha_generacion?->toIso8601String(),
                'fecha_entrega' => $tramite->fecha_entrega?->toIso8601String(),

                // Qué le toca hacer al operador con este expediente. Sale del
                // enum: la pantalla no tiene por qué saber qué significa cada
                // estado.
                'que_sigue' => $tramite->estado->queSigue(),

                /*
                 * QUÉ BOTONES DIBUJAR — lo decide el SERVIDOR, no la pantalla.
                 *
                 * La regla de qué salto vale desde cada estado vive en
                 * App\Enums\EstadoTramite. Escribirla otra vez en React
                 * garantiza que algún día las dos versiones digan cosas
                 * distintas, y el operador vería un botón que el servidor
                 * rechaza —o peor, no vería uno que sí puede usar—.
                 */
                'puede_enviar' => $tramite->puedeEnviarse(),

                /*
                 * QUÉ LE FALTA AL EXPEDIENTE PARA PODER ENVIARSE.
                 *
                 * Viaja la lista y no solo un booleano: con `puede_enviar` en
                 * false la pantalla sabría que no se puede, pero no qué ir a
                 * buscar, y el operador tendría que abrir la ficha pieza por
                 * pieza para descubrirlo.
                 *
                 * Se manda SIEMPRE, también cuando está vacía, porque el aviso
                 * solo tiene sentido mientras el expediente sigue abierto: un
                 * trámite ya aprobado no necesita que le recuerden nada.
                 */
                'faltantes' => $tramite->estado->permiteEdicion()
                    ? $tramite->faltantesParaRevision()
                    : [],
                'puede_aprobar' => $tramite->puedeAprobarse(),
                'puede_rechazar' => $tramite->puedeRechazarse(),
                // El camino de vuelta de un rechazo. Ver
                // SolicitudCarnetService::reabrir().
                'puede_reabrir' => $tramite->puedeReabrirse(),
                'puede_editar' => $tramite->estado->permiteEdicion(),
                'puede_generar' => $tramite->puedeGenerarse(),
                'puede_entregar' => $tramite->puedeEntregarse(),
                'admite_pagos' => $tramite->estado->permitePagos(),
            ],

            'carnet' => [
                'id' => $tramite->carnet->id,
                'registro' => $tramite->carnet->registro(),
                'gestion' => $tramite->carnet->gestion,
                'estado_etiqueta' => $tramite->carnet->estado->etiqueta(),
                'estado_color' => $tramite->carnet->estado->color(),
                'vigente' => $tramite->carnet->estaVigente(),
                'fecha_vencimiento' => $tramite->carnet->fecha_vencimiento?->toDateString(),

                // Si ya hay plástico que sacar. Lo decide el modelo, igual que
                // los `puede_*` del trámite: el carnet existe desde PENDIENTE,
                // pero no habilita a nada hasta que se aprueba.
                'puede_imprimirse' => $tramite->carnet->puedeImprimirse(),

                /*
                 * LA ACTIVIDAD DEL CARNET Y SU CUPO.
                 *
                 * Antes acá viajaba la lista de rubros del carnet, para que el
                 * supervisor viera en qué contexto entraba el que estaba por
                 * aprobar. Con un carnet por rubro esa lista no existe: el
                 * contexto ES el carnet, y siempre coincide con el rubro del
                 * trámite. Se manda igual porque la ficha lo muestra al lado del
                 * registro, y porque el cupo del carnet puede diferir del que
                 * trae el expediente —el trámite propone, la aprobación
                 * consolida—.
                 */
                'rubro' => $tramite->carnet->rubro?->nombre,
                'capacidad' => $tramite->carnet->capacidadLegible(),
            ],

            /*
             * EL `pagable` SE LE PONE A MANO A CADA DEPÓSITO.
             *
             * `admiteCorreccion()` y `admiteEliminacion()` preguntan por el
             * estado de lo que se paga, y `pagable` es polimórfica: sin esto,
             * cada pago vuelve a consultar el MISMO trámite que ya está en
             * memoria. Acá se sabe de qué es cada uno: son todos de este
             * expediente.
             */
            'pagos' => $tramite->pagos->map(fn ($p): array => [
                'id' => $p->id,
                'nro_transaccion' => $p->nro_transaccion,
                'monto' => (float) $p->monto,
                'comprobante_url' => $p->comprobante_url,
                // Fecha SUELTA y no instante: lo que guarda la columna es el día
                // que dice la boleta, y mandado como instante el navegador lo
                // corría un día hacia atrás en Bolivia. El porqué largo está en
                // PagoController::index().
                'fecha_pago' => $p->fecha_pago?->toDateString(),
                'observaciones' => $p->observaciones,
                // El control de cada boleta. Es ACÁ donde quien revisa trabaja:
                // abre el expediente, mira las boletas una por una y recién
                // entonces aprueba.
                ...ControlDePago::resumen($p, $usuario),

                /*
                 * Y QUÉ SE PUEDE HACER CON LA FILA, que en esta pantalla hace
                 * falta desde que un depósito OBSERVADO ya no se puede validar.
                 *
                 * La salida de un observado es corregirlo, y observar solo pasa
                 * EN REVISIÓN —el control es parte de la revisión—, así que si
                 * el botón de corregir viviera solo en «Editar trámite» el
                 * circuito no se podría cerrar en ninguna pantalla: ahí está el
                 * expediente que quedó trabado.
                 *
                 * `puede_eliminarse` llega en false mientras el expediente no
                 * sea un borrador, así que el botón de quitar se esconde solo.
                 */
                'puede_corregirse' => $p->setRelation('pagable', $tramite)->admiteCorreccion(),
                'motivo_sin_correccion' => $p->motivoSinCorreccion(),
                'puede_eliminarse' => $p->admiteEliminacion(),
                'motivo_sin_eliminacion' => $p->motivoSinEliminacion(),
            ])->all(),

            /*
             * EL RECIBO OFICIAL, si este expediente ya tiene uno.
             *
             * Nace solo al pasar a EN REVISIÓN, así que un trámite PENDIENTE
             * todavía no lo tiene y la ficha no dibuja el botón. Viaja el número
             * —no un booleano— porque la pantalla lo muestra: en ventanilla se
             * pregunta por el recibo por su número, igual que con el talonario
             * de papel.
             */
            'recibo' => $this->resumirRecibo($tramite),
        ]);
    }

    /**
     * El recibo del expediente, para la ficha.
     *
     * NULL cuando el expediente todavía no llegó a revisión: ahí no hay nada
     * cobrado que respaldar y la pantalla no ofrece el botón.
     *
     * En cualquier otro caso devuelve el recibo armado. Ya no existe el estado
     * intermedio de «le corresponde pero la fila no está escrita»: no hay fila
     * que escribir, el comprobante se reconstruye cada vez. Ver
     * App\Support\ReciboArmado.
     *
     * @return array<string, mixed>|null
     */
    private function resumirRecibo(Tramite $tramite): ?array
    {
        $recibo = $this->recibos->armar($tramite);

        if ($recibo === null) {
            return null;
        }

        return [
            'numero' => $recibo->numeroImpreso(),
            'fecha_emision' => $recibo->fecha_emision?->toDateString(),
            'monto' => $recibo->monto,
        ];
    }

    /**
     * FORMULARIO DE CORRECCIÓN — GET /panel/tramites/{tramite}/editar
     *
     * Solo sirve para reemplazar los papeles que salieron ilegibles y corregir
     * las observaciones. Ni el rubro ni el beneficiario se pueden cambiar: eso
     * sería otro trámite, no una corrección de este.
     */
    public function edit(Request $request, Tramite $tramite): Response|RedirectResponse
    {
        /*
         * SE VUELVE A LA FICHA, NO SE ABORTA CON UN 403.
         *
         * Acá no hay nada que defender: quien entra tiene permiso de editar
         * trámites, lo que pasa es que ESTE trámite ya salió del borrador. Un
         * 403 le tira al operador una pantalla de error en la cara por haber
         * clickeado un botón viejo —la ficha abierta en otra pestaña desde
         * antes de que alguien lo enviara—.
         *
         * Devolverlo a la ficha con el motivo escrito lo deja donde puede
         * seguir trabajando. El texto sale de la excepción para que sea el
         * mismo que daría el servicio si se saltara esta comprobación.
         */
        if (! $tramite->estado->permiteEdicion()) {
            return redirect()
                ->route('tramites.show', $tramite)
                ->with('error', SolicitudInvalidaException::tramiteNoSePuedeEditar($tramite->estado)->getMessage());
        }

        // Los dos usuarios de cada pago van en el load: `ControlDePago::resumen()`
        // muestra los NOMBRES de quien cargó y quien controló, y sin esto son
        // dos consultas por depósito.
        $tramite->load([
            'rubro:id,nombre,requiere_capacidad',
            'carnet:id,gestion,beneficiario_id',
            'carnet.beneficiario',
            'pagos',
            'pagos.registradoPor:id,name',
            'pagos.validadoPor:id,name',
        ]);

        return Inertia::render('panel/tramites/editar', [
            'tramite' => [
                'id' => $tramite->id,
                'rubro' => $tramite->rubro->nombre,
                'carnet_gestion' => $tramite->carnet->gestion,
                'beneficiario' => $tramite->carnet->beneficiario->nombreCompleto,
                'observaciones' => $tramite->observaciones,
                'asociacion' => $tramite->asociacion,
                'capacidad_kg' => $tramite->capacidad_kg !== null ? (float) $tramite->capacidad_kg : null,
                // Si el rubro del expediente se autoriza por volumen. De esto
                // depende que el campo del cupo se muestre; la validación lo
                // vuelve a comprobar contra el catálogo.
                'requiere_capacidad' => $tramite->rubro?->requiereCapacidad() ?? false,
                'ci_file_url' => $tramite->ci_file_url,
                'cert_asociacion_file_url' => $tramite->cert_asociacion_file_url,

                /*
                 * EL DINERO VIAJA ACÁ PORQUE ACÁ SE CARGAN LOS DEPÓSITOS.
                 *
                 * La ficha del trámite los MUESTRA —resumen, lista, ver boleta—
                 * pero no deja cargarlos: corregir un expediente es una sola
                 * pantalla, y tener el formulario en los dos lados hacía que no
                 * quedara claro cuál era el lugar.
                 */
                'monto_requerido' => (float) $tramite->monto_requerido,
                'monto_pagado' => $tramite->montoPagado(),
                'saldo_pendiente' => $tramite->saldoPendiente(),
                'admite_pagos' => $tramite->estado->permitePagos(),
            ],

            /*
             * EL `pagable` SE LE PONE A MANO A CADA DEPÓSITO.
             *
             * `admiteCorreccion()` pregunta por el estado de lo que se paga, y
             * `pagable` es polimórfica: sin esto, cada pago vuelve a consultar
             * el MISMO trámite que ya está en memoria —un N+1 silencioso, que
             * es la forma en que este problema aparece siempre—. Acá se sabe
             * de qué es cada uno: son todos de este expediente.
             */
            'pagos' => $tramite->pagos->map(fn ($p): array => [
                'id' => $p->id,
                'nro_transaccion' => $p->nro_transaccion,
                'monto' => (float) $p->monto,
                'comprobante_url' => $p->comprobante_url,
                // Fecha SUELTA y no instante: lo que guarda la columna es el día
                // que dice la boleta, y mandado como instante el navegador lo
                // corría un día hacia atrás en Bolivia. El porqué largo está en
                // PagoController::index().
                'fecha_pago' => $p->fecha_pago?->toDateString(),
                'observaciones' => $p->observaciones,

                /*
                 * EL CONTROL VIAJA TAMBIÉN ACÁ, aunque esta pantalla no
                 * controla nada.
                 *
                 * No es para dibujar los botones de validar —esos son de la
                 * ficha, porque quien carga la boleta no puede darla por
                 * buena—: es porque el estado del control CONDICIONA la
                 * corrección. Un depósito validado no se toca, y uno observado
                 * vuelve a quedar sin controlar al corregirlo; las dos cosas
                 * las dice la pantalla antes de que el operador escriba.
                 */
                ...ControlDePago::resumen($p, $request->user()),

                /*
                 * SI ESTE DEPÓSITO TODAVÍA SE PUEDE CORREGIR.
                 *
                 * Lo decide el servidor —`Pago::admiteCorreccion()`— y no React,
                 * por lo mismo que los `puede_*` del circuito: la regla es una
                 * sola y vive en el modelo. Un depósito ya validado no se toca,
                 * porque alguien firmó con su nombre que cuadraba contra el
                 * extracto del banco.
                 *
                 * Esconder el botón es comodidad; quien impide de verdad es
                 * PagoTramiteService::corregir().
                 */
                'puede_corregirse' => $p->setRelation('pagable', $tramite)->admiteCorreccion(),
                'motivo_sin_correccion' => $p->motivoSinCorreccion(),

                /*
                 * Y SI SE PUEDE QUITAR, que es OTRA pregunta.
                 *
                 * Quitar es más estricto que corregir: solo mientras el
                 * expediente sea un borrador. Ver `Pago::admiteEliminacion()`.
                 */
                'puede_eliminarse' => $p->admiteEliminacion(),
                'motivo_sin_eliminacion' => $p->motivoSinEliminacion(),
            ])->all(),
        ]);
    }

    /**
     * GUARDAR LA EDICIÓN — PUT /panel/tramites/{tramite}
     *
     * Los adjuntos son OPCIONALES acá, a diferencia del alta: se reemplaza el
     * que salió ilegible y el otro se deja como está.
     *
     * El RUBRO y el BENEFICIARIO no se validan porque no se editan — ni siquiera
     * llegan del formulario. El porqué está en
     * SolicitudCarnetService::actualizar().
     */
    public function update(Request $request, Tramite $tramite): RedirectResponse
    {
        $extensiones = implode(',', config('jichi.archivos.extensiones'));
        $maxKb = config('jichi.archivos.max_kb');

        // El rubro decide si el cupo se puede editar. loadMissing y no load:
        // si el modelo ya vino con la relación, no se vuelve a consultar.
        $tramite->loadMissing('rubro');

        $datos = $request->validate([
            'ciFile' => ['nullable', 'file', 'mimes:'.$extensiones, 'max:'.$maxKb],
            'certAsociacionFile' => ['nullable', 'file', 'mimes:'.$extensiones, 'max:'.$maxKb],
            'asociacion' => ['nullable', 'string', 'max:150'],
            /*
             * Mismas reglas que el alta: el cupo se contrasta después contra
             * guías de transporte, así que un cero o un negativo no sirven.
             *
             * Y solo se puede editar si el rubro del trámite lo lleva.
             * Se mira el rubro del EXPEDIENTE y no el del formulario porque el
             * rubro no se puede cambiar al editar —ver el docblock de
             * SolicitudCarnetService::actualizar()—.
             */
            'capacidad_kg' => $tramite->rubro?->requiereCapacidad()
                ? ['nullable', 'numeric', 'min:0.01', 'max:99999999']
                : ['nullable', 'prohibited'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ], [
            'capacidad_kg.min' => 'El cupo tiene que ser mayor que cero.',
            'capacidad_kg.prohibited' => 'Este rubro no se autoriza por volumen: no lleva cupo en kilos.',
        ]);

        try {
            $this->solicitudes->actualizar(
                $tramite,
                adjuntos: [
                    'ciFile' => $request->file('ciFile'),
                    'certAsociacionFile' => $request->file('certAsociacionFile'),
                ],
                datos: [
                    'asociacion' => $datos['asociacion'] ?? null,
                    'capacidad_kg' => $datos['capacidad_kg'] ?? null,
                    'observaciones' => $datos['observaciones'] ?? null,
                ],
            );
        } catch (SolicitudInvalidaException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('tramites.show', $tramite)
            ->with('exito', 'El expediente fue actualizado.');
    }

    /**
     * ENVIAR A REVISIÓN — PATCH /panel/tramites/{tramite}/enviar
     *
     * Es el paso OBLIGATORIO del circuito: sin pasar por acá el expediente no se
     * puede aprobar. Ver EstadoTramite::siguientes().
     *
     * Significa un cambio de manos: mientras está PENDIENTE ventanilla lo está
     * armando; al enviarlo declara que está completo y pasa a quien lo verifica
     * y lo firma. Acá además nace el RECIBO OFICIAL que se lleva el pescador.
     *
     * Es PATCH y no POST porque modifica parcialmente un recurso que ya existe.
     * Lo que NO puede ser nunca es GET: un verbo de lectura que escribe se
     * dispara solo con que el navegador precargue el enlace o un antivirus lo
     * visite.
     */
    public function enviar(Tramite $tramite): RedirectResponse
    {
        try {
            $this->solicitudes->enviarARevision($tramite);
        } catch (SolicitudInvalidaException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('exito', 'Expediente enviado a revisión. Ya se puede imprimir el recibo para el beneficiario.');
    }

    /**
     * APROBAR — PATCH /panel/tramites/{tramite}/aprobar
     *
     * Es el paso que HABILITA el rubro en el carnet. Todo lo que hay que
     * comprobar antes —que el salto de estado valga, que el monto esté cubierto,
     * que el rubro no esté ya habilitado— lo hace el servicio dentro de una
     * transacción y con la fila bloqueada.
     */
    /**
     * REABRIR — PATCH /panel/tramites/{tramite}/reabrir
     *
     * El pescador volvió al mostrador con lo que le faltaba. El expediente
     * rechazado vuelve a ser BORRADOR, con sus depósitos y su historial, en vez
     * de obligar a presentar uno nuevo — que nacería con cero cobrado mientras
     * el dinero se queda colgado del rechazado.
     *
     * NO PIDE MOTIVO, al revés que rechazar y eliminar. Esos dos cierran algo y
     * el motivo es lo único que queda para explicarlo; esto no decide nada:
     * devuelve el expediente a la mesa de quien lo arma, y el porqué ya está
     * escrito en el rechazo que se está atendiendo.
     */
    public function reabrir(Tramite $tramite): RedirectResponse
    {
        try {
            $this->solicitudes->reabrir($tramite);
        } catch (SolicitudInvalidaException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('exito', 'Expediente reabierto. Ya se puede corregir y volver a enviar a revisión.');
    }

    public function aprobar(Tramite $tramite): RedirectResponse
    {
        try {
            $this->solicitudes->aprobar($tramite);
        } catch (SolicitudInvalidaException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('exito', sprintf(
            'Trámite aprobado. El rubro «%s» quedó habilitado en el carnet de la gestión %s.',
            $tramite->rubro->nombre,
            $tramite->carnet->gestion,
        ));
    }

    /**
     * RECHAZAR — PATCH /panel/tramites/{tramite}/rechazar
     *
     * El motivo es obligatorio y se valida acá además de en el servicio: la
     * validación de Laravel pinta el error bajo el campo del formulario, que es
     * lo que el operador necesita ver; la del servicio es la que garantiza que
     * nadie rechace sin motivo entrando por otra puerta.
     */
    public function rechazar(Request $request, Tramite $tramite): RedirectResponse
    {
        $datos = $request->validate([
            'motivo_rechazo' => ['required', 'string', 'min:10', 'max:1000'],
        ], [
            'motivo_rechazo.required' => 'Escriba el motivo del rechazo.',
            'motivo_rechazo.min' => 'El motivo tiene que explicar qué faltó: escriba al menos 10 caracteres.',
        ]);

        try {
            $this->solicitudes->rechazar($tramite, $datos['motivo_rechazo']);
        } catch (SolicitudInvalidaException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('exito', 'Trámite rechazado. El motivo quedó registrado en el expediente.');
    }

    /**
     * MARCAR COMO IMPRESO — PATCH /panel/tramites/{tramite}/generar
     */
    public function generar(Tramite $tramite): RedirectResponse
    {
        try {
            $this->solicitudes->generar($tramite);
        } catch (SolicitudInvalidaException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('exito', 'Carnet marcado como impreso.');
    }

    /**
     * MARCAR COMO ENTREGADO — PATCH /panel/tramites/{tramite}/entregar
     */
    public function entregar(Tramite $tramite): RedirectResponse
    {
        try {
            $this->solicitudes->entregar($tramite);
        } catch (SolicitudInvalidaException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('exito', 'Carnet entregado al beneficiario.');
    }

    /**
     * BORRAR EL EXPEDIENTE — DELETE /panel/tramites/{tramite}
     *
     * Se redirige al LISTADO y no con back(): la ficha del trámite que se
     * acaba de borrar ya no existe, y volver ahí daría un 404 justo después de
     * una operación que salió bien.
     *
     * En qué estados se puede lo decide EstadoTramite::permiteEliminacion(),
     * y la comprobación real la hace el servicio. Acá no hay ningún
     * `if ($tramite->estado === ...)`: sería la regla escrita por segunda vez.
     *
     * PIDE UN MOTIVO. Es la única operación del sistema que no deja la fila —el
     * expediente, sus pagos y sus archivos desaparecen—, así que lo único que
     * queda es la línea de `auditorias`. Sin el porqué ahí, nada explica después
     * por qué ese trámite ya no está.
     */
    public function destroy(Request $request, Tramite $tramite): RedirectResponse
    {
        /*
         * EL MOTIVO ES OBLIGATORIO, igual que al rechazar.
         *
         * Se valida acá además de en el servicio: la validación de Laravel
         * pinta el error bajo el campo del formulario, que es lo que el
         * operador necesita ver; la del servicio es la que garantiza que nadie
         * borre sin motivo entrando por otra puerta.
         */
        $datos = $request->validate([
            'motivo' => ['required', 'string', 'min:10', 'max:1000'],
        ], [
            'motivo.required' => 'Escriba por qué se elimina este trámite.',
            'motivo.min' => 'El motivo tiene que explicar qué pasó: escriba al menos 10 caracteres.',
        ]);

        try {
            $this->solicitudes->eliminar($tramite, $datos['motivo']);
        } catch (SolicitudInvalidaException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('tramites.index')
            ->with('exito', "Trámite {$tramite->id} eliminado.");
    }

    // ------------------------------------------------------------------
    //  Auxiliares
    // ------------------------------------------------------------------

    /**
     * Los datos de un trámite que pintan tanto la tabla como la ficha.
     *
     * Está en un método y no repetido en los dos lugares porque el día que se
     * agregue una columna hay que tocarla una sola vez; repetido, la tabla y la
     * ficha terminan mostrando cosas distintas del mismo expediente.
     *
     * @return array<string, mixed>
     */
    private function resumir(Tramite $tramite): array
    {
        $beneficiario = $tramite->carnet?->beneficiario;

        return [
            'id' => $tramite->id,
            'carnet_id' => $tramite->carnet_id,
            'carnet_registro' => $tramite->carnet?->registro(),
            'gestion' => $tramite->carnet?->gestion,
            'beneficiario_id' => $beneficiario?->id,
            'beneficiario' => $beneficiario?->nombreCompleto,
            // Se muestran junto al nombre: con dos personas del mismo apellido
            // —que en el padrón son muchas— son lo que las distingue.
            'documento_identidad' => $beneficiario?->documento_identidad,
            'foto_url' => $beneficiario?->foto_url,
            'rubro' => $tramite->rubro?->nombre,

            // El cupo autorizado. Va como number y no como cadena formateada
            // porque la pantalla decide cómo mostrarlo —«600 Kg», «sin definir»—
            // y con un texto ya armado no podría distinguir el null del cero.
            'capacidad_kg' => $tramite->capacidad_kg !== null
                ? (float) $tramite->capacidad_kg
                : null,

            'tipo' => $tramite->tipo_tramite->value,
            'tipo_etiqueta' => $tramite->tipo_tramite->etiqueta(),
            'tipo_color' => $tramite->tipo_tramite->color(),

            'estado' => $tramite->estado->value,
            'estado_etiqueta' => $tramite->estado->etiqueta(),
            'estado_color' => $tramite->estado->color(),

            // El dinero se manda como número y no como texto formateado: el
            // símbolo de moneda sale de Configuración y lo pone React, que es
            // quien sabe en qué idioma está el navegador.
            'monto_requerido' => (float) $tramite->monto_requerido,
            'monto_pagado' => $tramite->montoPagado(),
            'saldo_pendiente' => $tramite->saldoPendiente(),
            'pagado' => $tramite->estaPagado(),

            /*
             * CUÁNTOS DEPÓSITOS SIGUEN FRENANDO LA APROBACIÓN.
             *
             * Que el botón «Aprobar» desaparezca no alcanza: el supervisor tiene
             * que poder saber POR QUÉ sin apretar nada. Van separados pendientes
             * de observados porque las dos salidas son distintas —uno se valida,
             * el otro hay que corregirlo primero—.
             */
            'pagos_por_controlar' => $tramite->pagosPorControlar(),

            'fecha_solicitud' => $tramite->fecha_solicitud?->toIso8601String(),

            /*
             * CUÁNDO SE CARGÓ EN EL SISTEMA — `created_at`, no `fecha_solicitud`.
             *
             * Son dos cosas distintas aunque casi siempre coincidan:
             *
             *   fecha_solicitud  cuándo la persona PRESENTÓ los papeles. Es un
             *                    dato del expediente y se puede escribir con
             *                    fecha anterior al cargar un trámite atrasado.
             *   created_at       cuándo ALGUIEN LO TIPEÓ acá. Lo pone Eloquent y
             *                    nadie lo elige.
             *
             * El listado muestra este y no aquel porque la pregunta que responde
             * la tabla es «qué entró al sistema y en qué orden», y para eso el
             * dato que no se puede retocar es el único que sirve.
             */
            'created_at' => $tramite->created_at?->toIso8601String(),

            /*
             * ¿Se puede borrar? La respuesta viene YA CALCULADA del servidor.
             *
             * React no vuelve a preguntar qué estados admiten borrado: si lo
             * hiciera, la regla quedaría escrita en dos idiomas y algún día
             * dirían cosas distintas —y el operador vería un botón que el
             * servidor rechaza—. Ver EstadoTramite::permiteEliminacion().
             */
            'puede_eliminarse' => $tramite->estado->permiteEliminacion(),
        ];
    }

    /**
     * Las gestiones que tienen carnets, para el selector de filtro.
     *
     * Salen de la base y no de un rango fijo: un `range(2020, año actual)`
     * ofrecería años vacíos, y al empezar una gestión nueva tampoco la
     * mostraría hasta que alguien se acuerde de cambiar el número.
     *
     * @return array<int, int>
     */
    private function gestionesDisponibles(): array
    {
        return Carnet::query()
            ->distinct()
            ->orderByDesc('gestion')
            ->pluck('gestion')
            ->map(fn ($g): int => (int) $g)
            ->all();
    }
}
