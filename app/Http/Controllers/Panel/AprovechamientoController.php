<?php

namespace App\Http\Controllers\Panel;

use App\Enums\ConceptoArancel;
use App\Enums\EstadoAprovechamiento;
use App\Enums\EstadoCarnet;
use App\Exceptions\CupoInvalidoException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\CargarPagoRequest;
use App\Http\Requests\Panel\EditarCupoRequest;
use App\Http\Requests\Panel\MotivoRequest;
use App\Http\Requests\Panel\OtorgarCupoRequest;
use App\Models\AprovechamientoPesq;
use App\Models\Beneficiario;
use App\Models\Carnet;
use App\Models\PermisoFaena;
use App\Services\CargarPagoService;
use App\Services\ConfirmarPagoService;
use App\Services\OtorgarCupoService;
use App\Services\RenovarLiquidacionService;
use App\Services\RevisarCupoService;
use App\Sireb\SirebException;
use App\Sireb\TarifarioSireb;
use App\Support\Paginacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 *  APROVECHAMIENTOS — la BOLSA MADRE del pescador (paso 2 del flujo)
 */
class AprovechamientoController extends Controller
{
    private const AVISO_SIN_SIREB = 'Se guardó la autorización, pero Recaudaciones no respondió y falta registrar el cobro allá. '.
        'Use «Verificar pago» en esta ficha más tarde.';

    public function __construct(private readonly OtorgarCupoService $servicio) {}

    /**
     * Listado — GET /panel/aprovechamientos
     */
    public function index(Request $request): Response
    {
        $filtros = [
            'buscar' => $request->string('buscar')->trim()->value() ?: null,
            'estado' => $request->string('estado')->trim()->value() ?: null,
            'por_pagina' => Paginacion::filas($request),
        ];

        $cupos = AprovechamientoPesq::query()
            // Ojo con pedir columnas sueltas: van las CINCO partes del nombre
            // porque `nombreCompleto` las lee todas. La que falte vuelve null y
            // el accesor contesta cualquier cosa, sin error.
            ->with([
                'beneficiario:id,ci,complemento,departamento_id,primerNombre,segundoNombre,apellidoPaterno,apellidoMaterno,apellidoCasado,foto',
            ])
            ->with('recibo:id,recibible_type,recibible_id,numero_recibo')
            ->when($filtros['buscar'], fn ($q, $termino) => $q->whereHas(
                'beneficiario',
                fn ($b) => $b->buscar($termino),
            ))
            ->when($filtros['estado'], fn ($q, $estado) => $q->where(
                'aprovechamientos_pesq.estado',
                $estado,
            ))
            // Por `created_at` y no por la fecha de solicitud, que el operador
            // declara y puede ser pasada. El `id` desempata, o el paginado
            // repite filas entre páginas.
            ->orderByDesc('aprovechamientos_pesq.created_at')
            ->orderByDesc('aprovechamientos_pesq.id')
            ->paginate($filtros['por_pagina'])
            ->withQueryString()
            ->through($this->resumir(...));

        return Inertia::render('panel/aprovechamientos/index', [
            'cupos' => $cupos,
            'filtros' => $filtros,
            'estados' => EstadoAprovechamiento::opciones(),
            'opcionesPorPagina' => Paginacion::OPCIONES,

        ]);
    }

    /**
     * Formulario — GET /panel/aprovechamientos/crear
     */
    public function create(Request $request, TarifarioSireb $tarifario): Response
    {
        $beneficiario = $request->integer('beneficiario')
            ? Beneficiario::query()->find($request->integer('beneficiario'))
            : null;

        return Inertia::render('panel/aprovechamientos/crear', [
            'beneficiario' => $beneficiario ? [
                'id' => $beneficiario->id,
                'nombreCompleto' => $beneficiario->nombreCompleto,
                'documento_identidad' => $beneficiario->documento_identidad,
                'foto_url' => $beneficiario->foto_url,
            ] : null,

            // Las tarifas del servicio de la escala: null si SIREB no responde.
            'escala' => $tarifario->tarifas(ConceptoArancel::Autorizacion),
            'servicioConfigurado' => $tarifario->servicioId(ConceptoArancel::Autorizacion) !== null,
        ]);
    }

    /**
     * Otorgar — POST /panel/aprovechamientos
     */
    public function store(OtorgarCupoRequest $request): RedirectResponse
    {
        $datos = $request->validated();

        try {
            $cupo = $this->servicio->otorgar(
                Beneficiario::query()->findOrFail($datos['beneficiario_id']),
                $datos['tarifa_sireb'],
                (float) $datos['volumen_total_kg'],
                $datos['tipo_embarcacion'],
                now()->parse($datos['fecha_solicitud']),
            );
        } catch (CupoInvalidoException $e) {
            if ($e->avisoGeneral) {
                return $this->avisoDeError($e, 'No se registró la autorización. ');
            }

            // Error DEL CAMPO y no un cartel arriba: la regla que falló es sobre
            // la persona elegida, y el texto tiene que salir al lado de ese campo.
            return back()
                ->withInput()
                ->withErrors(['beneficiario_id' => $e->getMessage()])
                ->with('error', 'No se registró el aprovechamiento. Revise el motivo en el formulario.');
        }

        if (! $cupo->registradoEnSireb()) {
            return redirect()->route('aprovechamientos.show', $cupo)->with('aviso', self::AVISO_SIN_SIREB);
        }

        /*
         *  Otorgar termina en la ficha del cupo
         */
        return redirect()
            ->route('aprovechamientos.show', $cupo)
            ->with('exito', sprintf(
                'Aprovechamiento registrado, PENDIENTE de cobro: %s kg por %s Bs. '.
                'Al pagarse en Recaudaciones queda aprobado solo.',
                number_format((float) $cupo->volumen_total_kg, 2, ',', '.'),
                number_format($cupo->montoACobrar(), 2, ',', '.'),
            ));
    }

    /** Escala o SIREB: el motivo va en el aviso rojo de arriba, no bajo un campo. */
    private function avisoDeError(CupoInvalidoException $e, string $prefijo): RedirectResponse
    {
        return back()->withInput()->with('error', $prefijo.$e->getMessage());
    }

    /**
     * Verificar pago — POST /panel/aprovechamientos/{id}/verificar-pago
     *
     * Pregunta a SIREB; si está pagada, la aprueba y emite el recibo.
     */
    public function verificarPago(AprovechamientoPesq $aprovechamiento, ConfirmarPagoService $pagos): RedirectResponse
    {
        $resultado = $pagos->verificar($aprovechamiento);

        return back()->with($resultado['aprobado'] ? 'exito' : 'aviso', $resultado['mensaje']);
    }

    /**
     * Generar nueva liquidación — POST /panel/aprovechamientos/{aprovechamiento}/renovar-liquidacion
     *
     * La vencida sin pago pasa al historial y se pide otra con la tarifa vigente.
     */
    public function renovarLiquidacion(AprovechamientoPesq $aprovechamiento, RenovarLiquidacionService $renovacion): RedirectResponse
    {
        $resultado = $renovacion->renovar($aprovechamiento);

        return back()->with($resultado['renovada'] ? 'exito' : 'aviso', $resultado['mensaje']);
    }

    /**
     * Cargar pago — POST /panel/aprovechamientos/{aprovechamiento}/cargar-pago
     *
     * Lo carga en SIREB, que lo valida allá.
     */
    public function cargarPago(CargarPagoRequest $request, AprovechamientoPesq $aprovechamiento, CargarPagoService $carga): RedirectResponse
    {
        $datos = $request->validated();
        $resultado = $carga->cargar($aprovechamiento, $datos['numero_transaccion'], $datos['banco'], $request->file('comprobante'));

        return back()->with($resultado['cargado'] ? 'exito' : 'aviso', $resultado['mensaje']);
    }

    /** Antes de mostrar el QR: pregunta a SIREB si el cobro sigue pendiente y sin pago. */
    public function consultarQr(AprovechamientoPesq $aprovechamiento, ConfirmarPagoService $pagos): JsonResponse
    {
        return response()->json($pagos->puedePagarPorQr($aprovechamiento, desdeElPanel: true));
    }

    /**
     * FICHA — GET /panel/aprovechamientos/{aprovechamiento}
     */
    public function show(AprovechamientoPesq $aprovechamiento): Response
    {
        $aprovechamiento->load([
            'beneficiario:id,ci,complemento,departamento_id,primerNombre,segundoNombre,apellidoPaterno,apellidoMaterno,apellidoCasado,foto',
            'codigo',
        ]);

        // El recibo: uno, emitido cuando SIREB confirmó el pago. NULL mientras está pendiente.
        $recibo = $aprovechamiento->recibo;

        return Inertia::render('panel/aprovechamientos/ver', [
            // Solo al abrir «Generar nueva liquidación» (partial reload): consulta a SIREB.
            'cotizacion_renovacion' => Inertia::optional(fn () => app(RenovarLiquidacionService::class)->cotizar($aprovechamiento)),
            'cupo' => [
                ...$this->resumir($aprovechamiento),

                // La llave del QR de la autorización impresa.
                'codigo' => $aprovechamiento->codigo_legible,

                // Firmado, en fecha y sin cédula viva: una vigente o en trámite ya lo cubre.
                // Revocada o vencida no cuenta, así se puede emitir la que la reemplaza.
                'puede_emitir_carnet' => $aprovechamiento->estado->habilita()
                    && $aprovechamiento->estaEnFecha()
                    && ! $aprovechamiento->carnets()
                        ->where(fn ($q) => $q->vigentes()
                            ->orWhere('estado', EstadoCarnet::Pendiente))
                        ->exists(),
            ],

            'recibo' => $recibo ? [
                'id' => $recibo->id,
                'numero_recibo' => $recibo->numero_recibo,
                'monto_total' => (float) $recibo->monto_total,
                'numero_boleta' => $recibo->numero_boleta,
                'entidad_bancaria' => $recibo->entidad_bancaria,
                'fecha_pago' => $recibo->fecha_pago?->toDateString(),
                'emitido_en' => $recibo->created_at?->toIso8601String(),
            ] : null,

            // Las cédulas que se apoyan en este cupo. Son varias en teoría: el
            // carnet se renueva a mitad de un cupo vigente, o se repone uno perdido.
            'carnets' => $aprovechamiento->carnets()
                ->latest('created_at')
                ->get()
                // El padre ya está en memoria: sin esto, «sin efecto» consulta por fila.
                ->each(fn (Carnet $c) => $c->setRelation('aprovechamiento', $aprovechamiento))
                ->map(fn (Carnet $c): array => [
                    'id' => $c->id,
                    'codigo' => $c->codigo_legible,
                    'registro' => $c->registro_legible,
                    'tipo' => $c->tipo_actor->nombreCarnet(),
                    'tipo_actor_etiqueta' => $c->tipo_actor->etiqueta(),
                    'tipo_actor_color' => $c->tipo_actor->color(),
                    'estado_etiqueta' => $c->etiquetaEstado(),
                    'estado_color' => $c->colorEstado(),
                    'ya_fue_aprobado' => $c->yaFueAprobado(),
                    // Mismo corte que CarnetImpresionController: firmado, no revocado y con la autorización viva.
                    'puede_imprimirse' => $c->puedeImprimirse(),
                    // Reponer usa ESTA autorización: revocada, no hay con qué emitir el nuevo.
                    'puede_reponerse' => $c->estado->permiteRevocacion() && ! $c->autorizacionRevocada(),
                    'fecha_solicitud' => $c->fecha_solicitud?->toDateString(),
                    'fecha_emision' => $c->fecha_emision?->toDateString(),
                    'fecha_vencimiento' => $c->fecha_vencimiento?->toDateString(),
                ])
                ->all(),

            // El carnet con el que se emite la próxima faena: aprobado y vigente.
            'carnetParaFaena' => $aprovechamiento->carnets()
                ->vigentes()
                ->with('aprovechamiento')
                ->latest('fecha_emision')
                ->get()
                ->first(fn (Carnet $c): bool => $c->puedeEmitirFaenas())?->id,

            // Las faenas de este cupo, cada una con sus kilos fijos.
            'faenas' => $aprovechamiento->faenas()
                // Con `nro` y `fecha_emision`: de esas dos columnas
                // salen los accesores del número del carnet, y sin ellas
                // devuelven null sin ningún error.
                ->with(['carnet:id,nro,fecha_emision,beneficiario_id,tipo_actor,aprovechamiento_id,estado,fecha_vencimiento', 'carnet.codigo'])
                ->orderByDesc('permisos_faena.nro')
                ->get()
                ->each(fn (PermisoFaena $f) => $f->carnet?->setRelation('aprovechamiento', $aprovechamiento))
                ->map(fn (PermisoFaena $f): array => [
                    'id' => $f->id,
                    'nro' => $f->nro,
                    // Con los seis ceros del talonario: lo arma el modelo, no
                    // la pantalla, o cada tabla elige su propio relleno.
                    'numero_legible' => $f->numero_legible,

                    // De qué carnet cuelga: una autorización puede respaldar más de una credencial.
                    'carnet_id' => $f->carnet_id,
                    'carnet_registro' => $f->carnet?->registro_legible,
                    'kilos_extraidos' => (float) $f->kilos_extraidos,
                    'estado' => $f->estado->value,
                    'estado_etiqueta' => $f->etiquetaEstado(),
                    'estado_color' => $f->colorEstado(),
                    'puede_imprimirse' => $f->puedeImprimirse(),
                    'fecha_salida' => $f->fecha_salida?->toDateString(),
                    'fecha_desembarque' => $f->fecha_desembarque?->toDateString(),
                ])
                ->all(),
        ]);
    }

    /**
     * Formulario de corrección — GET /panel/aprovechamientos/{id}/editar
     */
    public function edit(AprovechamientoPesq $aprovechamiento): Response|RedirectResponse
    {
        if (! $aprovechamiento->puedeEditarse()) {
            return redirect()
                ->route('aprovechamientos.show', $aprovechamiento)
                ->withErrors(['general' => CupoInvalidoException::noSePuedeEditar(
                    $aprovechamiento->estado->etiqueta(),
                )->getMessage()]);
        }

        $aprovechamiento->load(
            'beneficiario:id,ci,complemento,departamento_id,primerNombre,segundoNombre,apellidoPaterno,apellidoMaterno,apellidoCasado,foto',
        );

        // Titular, tramo, kilos, monto y fechas llegan FIJOS: solo se corrige la embarcación.
        return Inertia::render('panel/aprovechamientos/editar', [
            'cupo' => [
                'id' => $aprovechamiento->id,
                'beneficiario' => $aprovechamiento->beneficiario?->nombreCompleto,
                'documento' => $aprovechamiento->beneficiario?->documento_identidad,
                'foto_url' => $aprovechamiento->beneficiario?->foto_url,
                'tramo' => $aprovechamiento->tramo,
                'volumen_total_kg' => (float) $aprovechamiento->volumen_total_kg,
                'monto' => $aprovechamiento->montoACobrar(),
                'tipo_embarcacion' => $aprovechamiento->tipo_embarcacion,
                'fecha_solicitud' => $aprovechamiento->fecha_solicitud?->toDateString(),
                'fecha_vencimiento' => $aprovechamiento->fecha_vencimiento?->toDateString(),
            ],
        ]);
    }

    /**
     * Guardar la corrección — PUT /panel/aprovechamientos/{id}
     */
    public function update(EditarCupoRequest $request, AprovechamientoPesq $aprovechamiento): RedirectResponse
    {
        try {
            $this->servicio->editar($aprovechamiento, $request->validated()['tipo_embarcacion']);
        } catch (CupoInvalidoException $e) {
            return $this->avisoDeError($e, 'No se corrigió la autorización. ');
        }

        return redirect()
            ->route('aprovechamientos.show', $aprovechamiento)
            ->with('exito', 'Aprovechamiento corregido.');
    }

    /**
     * Eliminar — DELETE /panel/aprovechamientos/{id}
     */
    public function destroy(MotivoRequest $request, AprovechamientoPesq $aprovechamiento, ConfirmarPagoService $pagos): RedirectResponse
    {
        $persona = $aprovechamiento->beneficiario?->nombreCompleto ?? 'el pescador';

        try {
            $this->servicio->eliminar($aprovechamiento, $request->validated()['motivo']);
        } catch (CupoInvalidoException $e) {
            return back()->withErrors(['motivo' => $e->getMessage()]);
        } catch (SirebException $e) {
            if (! $e->frenaPorPago()) {
                throw $e;
            }

            $resultado = $pagos->alNoPoderEliminar($aprovechamiento);

            return redirect()->route('aprovechamientos.show', $aprovechamiento)->with($resultado['aprobado'] ? 'exito' : 'aviso', $resultado['mensaje']);
        }

        return redirect()
            ->route('aprovechamientos.index')
            ->with('exito', "Aprovechamiento de {$persona} eliminado. El motivo quedó en la auditoría.");
    }

    /**
     * Revocar — PATCH /panel/aprovechamientos/{id}/revocar
     */
    public function revocar(MotivoRequest $request, AprovechamientoPesq $aprovechamiento, RevisarCupoService $revision): RedirectResponse
    {
        try {
            $revision->revocar($aprovechamiento, $request->validated()['motivo']);
        } catch (CupoInvalidoException $e) {
            return back()->withErrors(['motivo' => $e->getMessage()]);
        }

        return redirect()
            ->route('aprovechamientos.show', $aprovechamiento)
            ->with('exito', 'Autorización revocada, junto con sus carnets y faenas vigentes. La persona puede tramitar otra.');
    }

    //  Auxiliares

    /**
     * Los datos de un cupo que pintan el listado y la ficha.
     *
     * @return array<string, mixed>
     */
    private function resumir(AprovechamientoPesq $cupo): array
    {
        $recibo = $cupo->recibo;

        return [
            'id' => $cupo->id,
            // El mismo N° que lleva la autorización impresa.
            'numero' => $cupo->numeroLegible(),
            'beneficiario_id' => $cupo->beneficiario_id,
            'beneficiario' => $cupo->beneficiario?->nombreCompleto,
            'documento' => $cupo->beneficiario?->documento_identidad,
            // La foto va con el nombre y la cédula en la misma celda del
            // listado. Sale de un accesor que lee la columna `foto`, así que esa
            // columna TIENE que estar en el select de arriba.
            'foto_url' => $cupo->beneficiario?->foto_url,

            // La etiqueta de la tarifa de SIREB, copiada al otorgar.
            'descripcion' => $cupo->tramo,

            'volumen_total_kg' => (float) $cupo->volumen_total_kg,
            // NULL y no cadena vacía: así la pantalla dice «no declarada» en vez
            // de imprimir un renglón en blanco.
            'tipo_embarcacion' => $cupo->tipo_embarcacion,
            // Lo que sale fijo en cada faena: las faenas no descuentan de acá.
            'kilos_disponibles' => (float) $cupo->kilos_disponibles,

            // Cuándo se cargó, que no es la fecha de solicitud. Es un MOMENTO:
            // va con toIso8601String().
            'registrado_en' => $cupo->created_at?->toIso8601String(),

            'estado' => $cupo->estado->value,
            'estado_etiqueta' => $cupo->estado->etiqueta(),
            'estado_color' => $cupo->estado->color(),
            'vigente' => $cupo->estaVigente(),

            // Se puede colgar una faena HOY: aprobada y en fecha.
            'puede_emitir_faena' => $cupo->puedeEmitirFaena(),
            // Y si no se puede, POR QUÉ: un cupo pendiente no es uno vencido.
            'motivo_sin_faena' => $cupo->motivoSinFaena(),
            /*
             * Editar y eliminar llegan resueltas, y no se deducen de `estado`
             * en React.
             */
            'puede_editarse' => $cupo->puedeEditarse(),
            'puede_eliminarse' => $cupo->puedeEliminarse() && $cupo->sinPagoInformado(),

            // El cobro está en SIREB: la ficha muestra su estado y ofrece verificar el pago.
            'sireb' => $cupo->resumenSireb(),
            'puede_verificar_pago' => $cupo->puedeVerificarPago(),
            'puede_cargar_pago' => $cupo->puedeCargarPago(),
            // La autorización en papel sale recién con el cupo aprobado.
            'ya_fue_aprobado' => $cupo->yaFueAprobado(),
            // Revocada: firmada, pero sin papel y sin poder revocarse de nuevo.
            'puede_imprimirse' => $cupo->puedeImprimirse(),
            'puede_revocarse' => $cupo->puedeRevocarse(),

            // EL RECIBO, para poder imprimirlo sin entrar a la ficha. Existe desde la aprobación.
            'recibo_id' => $recibo?->id,
            'recibo_numero' => $recibo?->numero_recibo,

            'monto' => $cupo->montoACobrar(),

            // Son DÍAS, no instantes: van con toDateString(). Mandados como
            // instante, en UTC-4 la pantalla mostraría el día anterior.
            'fecha_solicitud' => $cupo->fecha_solicitud?->toDateString(),
            // NULL hasta la firma: recién ahí hay algo otorgado.
            'fecha_emision' => $cupo->fecha_emision?->toDateString(),
            'fecha_vencimiento' => $cupo->fecha_vencimiento?->toDateString(),
        ];
    }
}
