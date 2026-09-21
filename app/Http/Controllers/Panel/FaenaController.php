<?php

namespace App\Http\Controllers\Panel;

use App\Enums\EstadoFaena;
use App\Exceptions\CobroInvalidoException;
use App\Exceptions\PermisoOperativoException;
use App\Http\Controllers\Controller;
use App\Http\Controllers\StorageController;
use App\Http\Requests\Panel\CompletarFaenaRequest;
use App\Http\Requests\Panel\EmitirFaenaRequest;
use App\Http\Requests\Panel\RechazarFaenaRequest;
use App\Http\Requests\Panel\RegistrarDepositosRequest;
use App\Models\AprovechamientoPesq;
use App\Models\Beneficiario;
use App\Models\Carnet;
use App\Models\Pago;
use App\Models\PermisoFaena;
use App\Models\Recibo;
use App\Services\CobrarService;
use App\Services\EmitirFaenaService;
use App\Services\RevisarFaenaService;
use App\Support\Archivos;
use App\Support\Paginacion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 *  PERMISOS DE FAENA — una salida de pesca (paso 4 del flujo)
 */
class FaenaController extends Controller
{
    public function __construct(private readonly EmitirFaenaService $servicio) {}

    /**
     * LISTADO — GET /panel/faenas
     */
    public function index(Request $request): Response
    {
        $filtros = [
            'buscar' => $request->string('buscar')->trim()->value() ?: null,
            'estado' => $request->string('estado')->trim()->value() ?: null,
            'por_pagina' => Paginacion::filas($request),
        ];

        $faenas = PermisoFaena::query()
            /*
             * OJO CON PEDIR COLUMNAS SUELTAS: el beneficiario va con las CINCO
             * partes del nombre porque `nombreCompleto` las lee todas. Una
             * columna que un método consulta y no está en el select vuelve null
             * y el método contesta cualquier cosa, sin ningún error.
             */
            ->with([
                'carnet:id,beneficiario_id,codigo_carnet,tipo_actor',
                'carnet.beneficiario:id,ci,complemento,departamento_id,primerNombre,segundoNombre,apellidoPaterno,apellidoMaterno,apellidoCasado',
            ])
            ->when($filtros['buscar'], fn ($q, $termino) => $q->where(
                fn ($s) => $s
                    ->whereHas('carnet.beneficiario', fn ($b) => $b->buscar($termino))
                    ->orWhere('numero_faena', 'like', '%'.preg_replace('/\D/', '', $termino).'%'),
            ))
            // Evita una consulta agregada POR FILA al calcular el saldo, y
            // resuelve el recibo sin ir a buscarlo de a uno.
            ->withSum('pagos', 'monto_parcial')
            ->with(['pagos:id,pagable_type,pagable_id,recibo_id', 'pagos.recibo:id,numero_recibo'])
            ->when($filtros['estado'], fn ($q, $estado) => $q->where('permisos_faena.estado', $estado))
            // Lo último cargado, arriba: ver AprovechamientoController::index().
            ->orderByDesc('permisos_faena.created_at')
            ->orderByDesc('permisos_faena.id')
            ->paginate($filtros['por_pagina'])
            ->withQueryString()
            ->through($this->resumir(...));

        return Inertia::render('panel/faenas/index', [
            'faenas' => $faenas,
            'filtros' => $filtros,
            'estados' => EstadoFaena::opciones(),
            'opcionesPorPagina' => Paginacion::OPCIONES,
        ]);
    }

    /**
     * FORMULARIO — GET /panel/faenas/crear
     *
     * Acepta `?beneficiario=7` para llegar desde la ficha de la persona con el
     * buscador ya resuelto.
     */
    public function create(Request $request): Response
    {
        $beneficiario = $request->integer('beneficiario')
            ? Beneficiario::query()->find($request->integer('beneficiario'))
            : null;

        return Inertia::render('panel/faenas/crear', [
            'beneficiario' => $beneficiario ? [
                'id' => $beneficiario->id,
                'nombreCompleto' => $beneficiario->nombreCompleto,
                'documento_identidad' => $beneficiario->documento_identidad,
                'foto_url' => $beneficiario->foto_url,

                /*
                 * Los carnets llegan con la MISMA forma que devuelve el
                 * autocompletado, para que la pantalla trate igual a la persona
                 * preseleccionada y a la que se busca a mano. Sin eso habría dos
                 * caminos en el componente, y uno de los dos se queda viejo.
                 */
                'carnets_vigentes' => $beneficiario->carnets()
                    ->vigentes()
                    ->with([
                        'tipoCarnet:id,nombre',
                        'aprovechamiento' => fn ($a) => $a
                            ->withSum('faenasQueConsumen', 'kilos_extraidos'),
                    ])
                    ->get()
                    ->map($this->resumirCarnetParaEmitir(...))
                    ->values()
                    ->all(),
            ] : null,

            'diasVigencia' => PermisoFaena::DIAS_VIGENCIA,

            'tarifa' => PermisoFaena::tarifaVigente(),

            /*
             * En modo FLEXIBLE el formulario no puede frenar por exceder el
             * cupo, así que tiene que dejar de decir que lo va a hacer: el aviso
             * pasa de «no entra en el cupo» a «va a quedar por encima». Sin este
             * dato la pantalla mentiría en la mitad de los despliegues.
             */
            'modoEstricto' => AprovechamientoPesq::modoEstricto(),
        ]);
    }

    /**
     * EMITIR — POST /panel/faenas
     */
    public function store(EmitirFaenaRequest $request): RedirectResponse
    {
        $datos = $request->validated();

        try {
            $faena = $this->servicio->emitir(
                Carnet::query()->findOrFail($datos['carnet_id']),
                (float) $datos['kilos_extraidos'],
                now()->parse($datos['fecha_salida']),
                now()->parse($datos['fecha_desembarque']),
                // `validated()` devuelve SOLO lo que vino: un nullable que el
                // formulario no mandó no existe en el arreglo.
                $datos,
            );
        } catch (PermisoOperativoException $e) {
            /*
             * El mensaje vuelve como error del campo que el operador puede
             * corregir. Las reglas del carnet no tienen arreglo desde este
             * formulario —hay que ir a emitir o renovar el carnet— así que se
             * cuelgan de `carnet_id`; el exceso de cupo sí se corrige acá.
             */
            $campo = str_contains($e->getMessage(), 'quedan') ? 'kilos_extraidos' : 'carnet_id';

            return back()->withInput()->withErrors([$campo => $e->getMessage()]);
        }

        return redirect()
            ->route('faenas.show', $faena)
            ->with('exito', sprintf(
                'Faena N° %s registrada, PENDIENTE de pago. Cargue los depósitos —%s Bs— y envíela '.
                'a revisión; autoriza la salida recién cuando la aprueben.',
                $faena->numero_legible,
                number_format($faena->montoACobrar(), 2, ',', '.'),
            ));
    }

    /**
     * FICHA — GET /panel/faenas/{faena}
     */
    public function show(PermisoFaena $faena): Response
    {
        $faena->load([
            'carnet:id,beneficiario_id,codigo_carnet,tipo_actor,asociacion_id',
            'carnet.beneficiario:id,ci,complemento,departamento_id,primerNombre,segundoNombre,apellidoPaterno,apellidoMaterno,apellidoCasado',
            'carnet.asociacion:id,nombre,sigla',
            // El cupo cuelga del CARNET: la faena ya no guarda su id.
            'carnet.aprovechamiento.categoria',
        ]);

        // El recibo del trámite —uno solo— y cuántas boletas faltan controlar.
        $recibo = $faena->recibos()->first();
        $sinValidar = $faena->pagos()->sinValidar()->count();

        return Inertia::render('panel/faenas/ver', [
            'faena' => [
                ...$this->resumir($faena),
                'asociacion' => $faena->carnet?->asociacion?->sigla ?? $faena->carnet?->asociacion?->nombre,

                /*
                 * LAS DEL CIRCUITO, resueltas en el servidor. React no vuelve a
                 * evaluar el estado: pregunta por estas.
                 */
                'puede_enviarse' => $faena->puedeEnviarseARevision(),
                'puede_revisarse' => $faena->puedeRevisarse(),
                'puede_aprobarse' => $faena->puedeRevisarse() && $sinValidar === 0,
                'pagos_sin_validar' => $sinValidar,

                /*
                 * EL CUPO DEL QUE SALIERON LOS KILOS.
                 */
                'cupo' => $faena->cupo() ? [
                    'id' => $faena->cupo()->id,
                    'escala' => $faena->cupo()->categoria?->nro_escala,
                    'volumen_total_kg' => (float) $faena->cupo()->volumen_total_kg,
                    'saldo_kg' => $faena->cupo()->saldoKg(),
                    'porcentaje_usado' => $faena->cupo()->porcentajeUsado(),
                ] : null,
            ],

            /*
             * EL DETALLE DE LO COBRADO, igual que en la ficha del carnet y la
             * del cupo: una fila por boleta, con su control.
             */
            'pagos' => $faena->pagos()
                // Precargados: si no, cinco depósitos son diez consultas.
                ->with(['registradoPor:id,name', 'validadoPor:id,name'])
                ->latest('created_at')
                ->get()
                // El trámite se le pone a mano: `admiteControl()` le pregunta
                // al `pagable`, y sin esto cada fila lo va a buscar a la base.
                ->each(fn (Pago $p) => $p->setRelation('pagable', $faena))
                ->map(fn (Pago $p): array => [
                    'id' => $p->id,
                    'monto_parcial' => (float) $p->monto_parcial,
                    'nro_transaccion' => $p->nro_transaccion,
                    'fecha_deposito' => $p->fecha_deposito?->toDateString(),
                    'comprobante_url' => $p->comprobante_url,
                    'cobrado_en' => $p->created_at?->toIso8601String(),
                    'estado_validacion' => $p->estado_validacion->value,
                    'estado_validacion_etiqueta' => $p->estado_validacion->etiqueta(),
                    'estado_validacion_color' => $p->estado_validacion->color(),
                    'observacion' => $p->observacion,
                    'registrado_por' => $p->registradoPor?->name,
                    'validado_por' => $p->validadoPor?->name,
                    'validado_en' => $p->validado_en?->toIso8601String(),
                    'puede_validarse' => $p->admiteControl(),
                    'puede_corregirse' => $p->admiteCorreccion(),
                ])
                ->all(),

            // El recibo del trámite: uno solo, emitido al enviar a revisión.
            'recibo' => $recibo ? [
                'id' => $recibo->id,
                'numero_recibo' => $recibo->numero_recibo,
                'monto_total' => (float) $recibo->monto_total,
                'emitido_en' => $recibo->created_at?->toIso8601String(),
                'pagos_count' => $recibo->pagos()->count(),
            ] : null,
        ]);
    }

    /**
     * COMPLETAR — PATCH /panel/faenas/{faena}/completar
     */
    public function completar(CompletarFaenaRequest $request, PermisoFaena $faena): RedirectResponse
    {
        $kilos = $request->validated()['kilos_extraidos'] ?? null;

        try {
            $this->servicio->completar($faena, $kilos !== null ? (float) $kilos : null);
        } catch (PermisoOperativoException $e) {
            return back()->withErrors(['kilos_extraidos' => $e->getMessage()]);
        }

        return redirect()
            ->route('faenas.show', $faena)
            ->with('exito', 'Faena completada. El volumen quedó firme contra el cupo.');
    }

    /**
     *  CARGAR LOS DEPÓSITOS — POST /panel/faenas/{faena}/pagos
     *
     * Mismo circuito que el carnet y el cupo: las boletas entran juntas, tienen
     * que cubrir el arancel entero y, si el operador lo pide, la faena queda
     * presentada en el mismo acto.
     */
    public function pagar(
        RegistrarDepositosRequest $request,
        PermisoFaena $faena,
        CobrarService $caja,
        RevisarFaenaService $revision,
    ): RedirectResponse {
        $datos = $request->validated();

        /*
         * EL ESTADO Y EL MONTO SE MIRAN ANTES DE SUBIR NADA: descubrirlo
         * adentro obligaría a borrar los archivos ya escritos, porque una
         * transacción no deshace lo que se escribió en disco.
         */
        if (! $faena->admitePagos()) {
            return back()->withErrors([
                'pagos' => CobroInvalidoException::noAdmiteDepositos(
                    'La '.mb_strtolower($faena->etiqueta),
                    $faena->estado->etiqueta(),
                )->getMessage(),
            ]);
        }

        $suma = round(array_sum(array_map(
            static fn (array $p): float => round((float) $p['monto'], 2),
            $datos['pagos'],
        )), 2);

        $saldo = $faena->saldoPendiente();

        if ($suma < $saldo) {
            return back()->withInput()->withErrors([
                'pagos' => CobroInvalidoException::noCubreElMonto('esta faena', $suma, $saldo)->getMessage(),
            ]);
        }

        $subidos = [];

        try {
            foreach ($datos['pagos'] as $i => $pago) {
                $subidos[$i] = app(StorageController::class)
                    ->file($request->file("pagos.{$i}.comprobante"), 'comprobantes');
            }

            $depositos = [];

            foreach ($datos['pagos'] as $i => $pago) {
                $depositos[] = [
                    'monto' => (float) $pago['monto'],
                    'nro_transaccion' => $pago['nro_transaccion'],
                    'fecha_deposito' => $pago['fecha_deposito'],
                    'comprobante' => $subidos[$i],
                ];
            }

            $caja->registrarDepositos($faena, $depositos);
        } catch (CobroInvalidoException $e) {
            foreach ($subidos as $ruta) {
                Archivos::borrar($ruta);
            }

            return back()->withInput()->withErrors(['pagos' => $e->getMessage()]);
        } catch (\Throwable $e) {
            foreach ($subidos as $ruta) {
                Archivos::borrar($ruta);
            }

            throw $e;
        }

        $faena->refresh();
        $cuantos = count($datos['pagos']);

        //  REGISTRAR Y ENVIAR SON UN SOLO ACTO CUANDO EL ARANCEL QUEDA CUBIERTO
        $enviada = false;

        if (($datos['enviar'] ?? false) && $faena->puedeEnviarseARevision()) {
            $revision->enviar($faena);
            $faena->refresh();
            $enviada = true;
        }

        return redirect()
            ->route('faenas.show', $faena)
            ->with('exito', match (true) {
                $enviada => sprintf(
                    '%d depósito(s) registrado(s) y enviada a revisión. Se emitió el recibo con el '.
                    'total; queda esperando la firma de quien la aprueba.',
                    $cuantos,
                ),
                $faena->saldoPendiente() <= 0.0 => sprintf(
                    '%d depósito(s) registrado(s). El arancel quedó cubierto: ya se puede enviar a revisión.',
                    $cuantos,
                ),
                default => sprintf(
                    '%d depósito(s) registrado(s). Quedan %s Bs por cobrar.',
                    $cuantos,
                    number_format($faena->saldoPendiente(), 2, ',', '.'),
                ),
            });
    }

    /**
     * ENVIAR A REVISIÓN — POST /panel/faenas/{faena}/enviar
     */
    public function enviar(PermisoFaena $faena, RevisarFaenaService $revision): RedirectResponse
    {
        try {
            $revision->enviar($faena);
        } catch (PermisoOperativoException $e) {
            return back()->withErrors(['general' => $e->getMessage()]);
        }

        return redirect()
            ->route('faenas.show', $faena)
            ->with('exito', 'Enviada a revisión. Se emitió el recibo con el total de los depósitos; '.
                'queda esperando la firma de quien la aprueba.');
    }

    /**
     * APROBAR — PATCH /panel/faenas/{faena}/aprobar
     *
     * Recién acá el permiso autoriza a salir a pescar.
     */
    public function aprobar(PermisoFaena $faena, RevisarFaenaService $revision): RedirectResponse
    {
        try {
            $revision->aprobar($faena);
        } catch (PermisoOperativoException $e) {
            return back()->withErrors(['general' => $e->getMessage()]);
        }

        return redirect()
            ->route('faenas.show', $faena)
            ->with('exito', "Faena N° {$faena->numero_legible} aprobada. Ya autoriza la salida.");
    }

    /**
     * RECHAZAR — PATCH /panel/faenas/{faena}/rechazar
     */
    public function rechazar(
        RechazarFaenaRequest $request,
        PermisoFaena $faena,
        RevisarFaenaService $revision,
    ): RedirectResponse {
        try {
            $revision->rechazar($faena, $request->validated()['motivo']);
        } catch (PermisoOperativoException $e) {
            return back()->withErrors(['motivo' => $e->getMessage()]);
        }

        return redirect()
            ->route('faenas.show', $faena)
            ->with('exito', 'Faena devuelta a ventanilla. Los depósitos quedan intactos: se corrige '.
                'lo observado y se vuelve a presentar.');
    }

    //  Auxiliares

    /**
     * El recibo del trámite, sin disparar una consulta por fila.
     *
     * `Pagable::recibos()` es una CONSULTA y no una relación, así que en un
     * listado hay que resolverlo desde los pagos ya precargados.
     */
    private function reciboDe(PermisoFaena $faena): ?Recibo
    {
        if ($faena->relationLoaded('pagos')) {
            return $faena->pagos->firstWhere('recibo_id', '!=', null)?->recibo;
        }

        return $faena->recibos()->first();
    }

    /**
     * Un carnet como lo necesita el formulario de emisión.
     *
     * @return array<string, mixed>
     */
    private function resumirCarnetParaEmitir(Carnet $carnet): array
    {
        return [
            'id' => $carnet->id,
            'codigo' => $carnet->codigo_legible,
            'tipo' => $carnet->tipoCarnet?->nombre,
            'tipo_actor' => $carnet->tipo_actor->value,
            'tipo_actor_etiqueta' => $carnet->tipo_actor->etiqueta(),
            'puede_emitir_faenas' => $carnet->puedeEmitirFaenas(),
            'puede_emitir_guias' => $carnet->puedeEmitirGuias(),
            'saldo_kg' => $carnet->aprovechamiento?->saldoKg(),
        ];
    }

    /**
     * Los datos de una faena que pintan el listado y la ficha.
     *
     * @return array<string, mixed>
     */
    private function resumir(PermisoFaena $faena): array
    {
        $recibo = $this->reciboDe($faena);

        return [
            'id' => $faena->id,
            'numero_faena' => $faena->numero_faena,
            // Con los seis ceros del talonario: «002190».
            'numero_legible' => $faena->numero_legible,
            'etiqueta' => $faena->etiqueta,

            'carnet_id' => $faena->carnet_id,
            'carnet_codigo' => $faena->carnet?->codigo_legible,
            'beneficiario_id' => $faena->carnet?->beneficiario_id,
            'beneficiario' => $faena->carnet?->beneficiario?->nombreCompleto,

            'kilos_extraidos' => (float) $faena->kilos_extraidos,

            // LOS RENGLONES DEL TALONARIO. Nullable: el papel llega incompleto.
            'embarcacion' => $faena->embarcacion,
            'propietario' => $faena->propietario,
            'comandante_barco' => $faena->comandante_barco,
            'matricula_naval' => $faena->matricula_naval,
            'nro_kardex' => $faena->nro_kardex,
            'region_desde' => $faena->region_desde,
            'region_hasta' => $faena->region_hasta,

            'estado' => $faena->estado->value,
            'estado_etiqueta' => $faena->estado->etiqueta(),
            'estado_color' => $faena->estado->color(),
            'vigente' => $faena->estaVigente(),
            // Una faena vencida LIBERA su volumen: la salida no ocurrió.
            'consume_cupo' => $faena->consumeCupo(),
            'caducada' => $faena->estaCaducada(),
            'puede_completarse' => $faena->estado === EstadoFaena::Activo,
            // Por qué todavía no autoriza. Se resuelve en el servidor: React
            // no vuelve a evaluar el estado.
            'motivo_sin_autorizar' => $faena->motivoSinAutorizar(),
            'ya_fue_aprobada' => $faena->yaFueAprobada(),
            'admite_pagos' => $faena->admitePagos(),

            // El arancel de la salida y cómo va cobrado.
            'monto' => $faena->montoACobrar(),
            'saldo_pendiente' => $faena->saldoPendiente(),
            'pagado' => $faena->estaPagado(),

            // El recibo existe desde el ENVÍO; null mientras es borrador.
            'recibo_id' => $recibo?->id,
            'recibo_numero' => $recibo?->numero_recibo,

            // Cuándo se cargó la fila. Un MOMENTO: con toIso8601String().
            'registrado_en' => $faena->created_at?->toIso8601String(),

            // Son DÍAS, no instantes: con toDateString().
            'fecha_solicitud' => $faena->fecha_solicitud?->toDateString(),
            'fecha_salida' => $faena->fecha_salida?->toDateString(),
            'fecha_desembarque' => $faena->fecha_desembarque?->toDateString(),
            'fecha_limite' => $faena->fecha_limite?->toDateString(),
            'fecha_emision' => $faena->fecha_emision?->toDateString(),
        ];
    }
}
