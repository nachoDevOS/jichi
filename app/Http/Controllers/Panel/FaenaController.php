<?php

namespace App\Http\Controllers\Panel;

use App\Enums\ConceptoArancel;
use App\Enums\EstadoFaena;
use App\Exceptions\PermisoOperativoException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\ActualizarFaenaRequest;
use App\Http\Requests\Panel\CargarPagoRequest;
use App\Http\Requests\Panel\EliminarFaenaRequest;
use App\Http\Requests\Panel\EmitirFaenaRequest;
use App\Models\AprovechamientoPesq;
use App\Models\ArancelSireb;
use App\Models\Beneficiario;
use App\Models\Carnet;
use App\Models\PermisoFaena;
use App\Services\CargarPagoService;
use App\Services\ConfirmarPagoService;
use App\Services\EmitirFaenaService;
use App\Sireb\SirebException;
use App\Sireb\VistaSireb;
use App\Support\Paginacion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 *  Permisos de faena — una salida de pesca (paso 4 del flujo)
 */
class FaenaController extends Controller
{
    public function __construct(private readonly EmitirFaenaService $servicio) {}

    /**
     * Listado — GET /panel/faenas
     */
    public function index(Request $request): Response
    {
        $filtros = [
            'buscar' => $request->string('buscar')->trim()->value() ?: null,
            'estado' => $request->string('estado')->trim()->value() ?: null,
            'por_pagina' => Paginacion::filas($request),
        ];

        $faenas = PermisoFaena::query()
            // Ojo con pedir columnas sueltas: van las CINCO partes del nombre
            // porque `nombreCompleto` las lee todas. La que falte vuelve null y
            // el método contesta cualquier cosa, sin error.
            ->with([
                'codigo',
                // Con `aprovechamiento_id`: «sin efecto» mira la autorización del carnet.
                'carnet:id,beneficiario_id,tipo_actor,aprovechamiento_id,nro,fecha_emision,estado,fecha_vencimiento',
                'carnet.aprovechamiento:id,estado',
                'carnet.codigo',
                'carnet.beneficiario:id,ci,complemento,departamento_id,primerNombre,segundoNombre,apellidoPaterno,apellidoMaterno,apellidoCasado,foto',
            ])
            ->when($filtros['buscar'], fn ($q, $termino) => $q->where(
                fn ($s) => $s
                    ->whereHas('carnet.beneficiario', fn ($b) => $b->buscar($termino))
                    // Sin los ceros del talonario: la columna es un ENTERO, y
                    // «000042» tecleado tal cual no encuentra al 42.
                    ->orWhere('permisos_faena.nro', 'like', '%'.ltrim(preg_replace('/\D/', '', $termino), '0').'%'),
            ))
            ->with('recibo:id,recibible_type,recibible_id,numero_recibo')
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
     * Formulario — GET /panel/faenas/crear
     *
     * Acepta `?beneficiario=7` para llegar desde la ficha de la persona con el
     * buscador ya resuelto.
     */
    public function create(Request $request): Response
    {
        // `?carnet=` llega desde la ficha del cupo: resuelve también a la persona.
        $carnetElegido = $request->integer('carnet')
            ? Carnet::query()->find($request->integer('carnet'))
            : null;

        $beneficiarioId = $carnetElegido?->beneficiario_id ?? $request->integer('beneficiario');

        $beneficiario = $beneficiarioId
            ? Beneficiario::query()->find($beneficiarioId)
            : null;

        return Inertia::render('panel/faenas/crear', [
            'beneficiario' => $beneficiario ? [
                'id' => $beneficiario->id,
                'nombreCompleto' => $beneficiario->nombreCompleto,
                'documento_identidad' => $beneficiario->documento_identidad,
                'foto_url' => $beneficiario->foto_url,

                // Con la MISMA forma que devuelve el autocompletado: si no, el
                // componente tendría dos caminos y uno se queda viejo.
                'carnets_vigentes' => $beneficiario->carnets()
                    ->vigentes()
                    ->with([
                        'codigo',
                        'tipoCarnet:id,nombre',
                        'aprovechamiento' => fn ($a) => $a
                            ->with('categoria')
                            ->withSum('faenasQueConsumen', 'kilos_extraidos')
                            ->withSum('faenasQueReservan', 'kilos_extraidos'),
                    ])
                    ->get()
                    ->map(fn (Carnet $c): array => $c->resumenParaEmitir())
                    ->values()
                    ->all(),
            ] : null,

            // Se preelige solo si figura entre los vigentes: el formulario no lo valida.
            'carnetElegido' => $carnetElegido?->id,

            'diasVigencia' => PermisoFaena::DIAS_VIGENCIA,

            // De referencia: el que vale lo congela la emisión. null sin tarifa o sin SIREB.
            'tarifa' => $this->tarifaDeReferencia(),

            // En modo FLEXIBLE el formulario no frena, así que tampoco puede decir
            // que va a frenar: el aviso pasa a «va a quedar por encima».
            'modoEstricto' => AprovechamientoPesq::modoEstricto(),
        ]);
    }

    /**
     * Emitir — POST /panel/faenas
     */
    public function store(EmitirFaenaRequest $request): RedirectResponse
    {
        $datos = $request->validated();

        try {
            $faena = $this->servicio->emitir(
                Carnet::query()->findOrFail($datos['carnet_id']),
                (float) $datos['kilos_extraidos'],
                // `validated()` devuelve SOLO lo que vino: un nullable que el
                // formulario no mandó no existe en el arreglo.
                $datos,
            );
        } catch (PermisoOperativoException $e) {
            // Al campo que el operador PUEDE corregir: lo del carnet no tiene
            // arreglo desde acá, el exceso de cupo sí.
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
     * Corregir el borrador — GET /panel/faenas/{faena}/editar
     */
    public function edit(PermisoFaena $faena): Response|RedirectResponse
    {
        if (! $faena->puedeEditarse()) {
            return redirect()
                ->route('faenas.show', $faena)
                ->withErrors(['general' => PermisoOperativoException::faenaNoSePuedeEditar(
                    mb_strtolower($faena->estado->etiqueta()),
                )->getMessage()]);
        }

        $faena->load([
            'carnet:id,beneficiario_id,tipo_actor,aprovechamiento_id,nro,fecha_emision,estado,fecha_vencimiento',
            'carnet.codigo',
            'carnet.beneficiario:id,ci,complemento,departamento_id,primerNombre,segundoNombre,apellidoPaterno,apellidoMaterno,apellidoCasado,foto',
            'carnet.aprovechamiento' => fn ($a) => $a->withSum('faenasQueConsumen', 'kilos_extraidos')
                ->withSum('faenasQueReservan', 'kilos_extraidos'),
        ]);

        $cupo = $faena->carnet?->aprovechamiento;

        return Inertia::render('panel/faenas/editar', [
            // La persona y el carnet llegan FIJOS: cambiar de titular no es
            // corregir, es emitir otro.
            'faena' => [
                'id' => $faena->id,
                'numero_legible' => $faena->numero_legible,
                'beneficiario' => $faena->carnet?->beneficiario?->nombreCompleto,
                'documento' => $faena->carnet?->beneficiario?->documento_identidad,
                'foto_url' => $faena->carnet?->beneficiario?->foto_url,
                'carnet_codigo' => $faena->carnet?->codigo_legible,
                /* El número del libro: «000001». Ver resumir(). */
                'carnet_registro' => $faena->carnet?->registro_legible,

                // Los kilos propios se suman SOLO si descontaban: la pendiente ya
                // no resta, así que devolvérselos mostraría el doble de cupo.
                'saldo_kg' => $cupo !== null
                    ? $cupo->saldoKg() + ($faena->consumeCupo() ? (float) $faena->kilos_extraidos : 0.0)
                    : null,
                // Lo libre más lo que ella misma reserva: es lo que puede pedir al corregirse.
                'libre_kg' => $cupo !== null
                    ? $cupo->libreKg() + ($faena->estado->reservaCupo() ? (float) $faena->kilos_extraidos : 0.0)
                    : null,

                'kilos_extraidos' => (float) $faena->kilos_extraidos,
                'embarcacion' => $faena->embarcacion,
                'propietario' => $faena->propietario,
                'comandante_barco' => $faena->comandante_barco,
                'matricula_naval' => $faena->matricula_naval,
                'nro_kardex' => $faena->nro_kardex,
                'region_desde' => $faena->region_desde,
                'region_hasta' => $faena->region_hasta,
            ],

            'diasVigencia' => PermisoFaena::DIAS_VIGENCIA,
            'modoEstricto' => AprovechamientoPesq::modoEstricto(),
        ]);
    }

    /**
     * Guardar la corrección — PATCH /panel/faenas/{faena}
     */
    public function update(ActualizarFaenaRequest $request, PermisoFaena $faena): RedirectResponse
    {
        $datos = $request->validated();

        try {
            $this->servicio->editar(
                $faena,
                (float) $datos['kilos_extraidos'],
                // `validated()` devuelve SOLO lo que vino: ver store().
                $datos,
            );
        } catch (PermisoOperativoException $e) {
            // El único campo con el que el operador puede reaccionar desde acá
            // son los kilos: el titular y el carnet no se editan.
            return back()->withInput()->withErrors(['kilos_extraidos' => $e->getMessage()]);
        }

        return redirect()
            ->route('faenas.show', $faena)
            ->with('exito', "Faena N° {$faena->numero_legible} corregida.");
    }

    /**
     * Eliminar — DELETE /panel/faenas/{faena}
     */
    public function destroy(EliminarFaenaRequest $request, PermisoFaena $faena, ConfirmarPagoService $pagos): RedirectResponse
    {
        $numero = $faena->numero_legible;

        try {
            $this->servicio->eliminar($faena, $request->validated()['motivo']);
        } catch (PermisoOperativoException $e) {
            return back()->withErrors(['motivo' => $e->getMessage()]);
        } catch (SirebException $e) {
            if (! $e->frenaPorPago()) {
                throw $e;
            }

            $resultado = $pagos->alNoPoderEliminar($faena);

            return redirect()->route('faenas.show', $faena)->with($resultado['aprobado'] ? 'exito' : 'aviso', $resultado['mensaje']);
        }

        return redirect()
            ->route('faenas.index')
            ->with('exito', "Faena N° {$numero} eliminada. El motivo quedó en la auditoría, y el ".
                'número del talonario queda con un hueco.');
    }

    /**
     * FICHA — GET /panel/faenas/{faena}
     */
    public function show(PermisoFaena $faena): Response
    {
        $faena->load([
            'codigo',
            'carnet:id,beneficiario_id,tipo_actor,asociacion_id,aprovechamiento_id,nro,fecha_emision,estado,fecha_vencimiento',
            'carnet.codigo',
            'carnet.beneficiario:id,ci,complemento,departamento_id,primerNombre,segundoNombre,apellidoPaterno,apellidoMaterno,apellidoCasado,foto',
            'carnet.asociacion:id,nombre,sigla',
            // El cupo cuelga del CARNET: la faena ya no guarda su id.
            'carnet.aprovechamiento.categoria',
        ]);

        // El recibo del trámite —uno solo— y cuántas boletas faltan controlar.
        $recibo = $faena->recibo;

        return Inertia::render('panel/faenas/ver', [
            'faena' => [
                ...$this->resumir($faena),
                'asociacion' => $faena->carnet?->asociacion?->sigla ?? $faena->carnet?->asociacion?->nombre,

                /*
                 * El cupo del que salieron los kilos.
                 */
                'cupo' => $faena->cupo() ? [
                    'id' => $faena->cupo()->id,
                    'escala' => $faena->cupo()->categoria?->nro_escala,
                    'volumen_total_kg' => (float) $faena->cupo()->volumen_total_kg,
                    'saldo_kg' => $faena->cupo()->saldoKg(),
                    'porcentaje_usado' => $faena->cupo()->porcentajeUsado(),
                ] : null,
            ],

            // El recibo del trámite: uno solo, emitido al enviar a revisión.
            'recibo' => $recibo ? [
                'id' => $recibo->id,
                'numero_recibo' => $recibo->numero_recibo,
                'monto_total' => (float) $recibo->monto_total,
                'emitido_en' => $recibo->created_at?->toIso8601String(),
                'numero_boleta' => $recibo->numero_boleta,
                'entidad_bancaria' => $recibo->entidad_bancaria,
                'fecha_pago' => $recibo->fecha_pago?->toDateString(),
            ] : null,

        ]);
    }

    //  Auxiliares

    /** El precio de hoy de la fila `faena` de Aranceles, o null sin tarifa o sin SIREB. */
    /**
     * Verificar pago — POST /panel/faenas/{faena}/verificar-pago
     *
     * Pregunta a SIREB; si está pagado, lo aprueba y emite el recibo.
     */
    public function verificarPago(PermisoFaena $faena, ConfirmarPagoService $pagos): RedirectResponse
    {
        $resultado = $pagos->verificar($faena);

        return back()->with($resultado['aprobado'] ? 'exito' : 'aviso', $resultado['mensaje']);
    }

    /**
     * Cargar pago — POST /panel/faenas/{faena}/cargar-pago
     *
     * Lo carga en SIREB, que lo valida allá.
     */
    public function cargarPago(CargarPagoRequest $request, PermisoFaena $faena, CargarPagoService $carga): RedirectResponse
    {
        $datos = $request->validated();
        $resultado = $carga->cargar($faena, $datos['numero_transaccion'], $datos['banco']);

        return back()->with($resultado['cargado'] ? 'exito' : 'aviso', $resultado['mensaje']);
    }

    private function tarifaDeReferencia(): ?float
    {
        $tarifa = ArancelSireb::de(ConceptoArancel::Faena)?->tarifa_sireb;

        return $tarifa === null ? null : (app(VistaSireb::class)->preciosPorTarifa()[$tarifa] ?? null);
    }

    /**
     * Los datos de una faena que pintan el listado y la ficha.
     *
     * @return array<string, mixed>
     */
    private function resumir(PermisoFaena $faena): array
    {
        $recibo = $faena->recibo;

        return [
            'id' => $faena->id,
            'nro' => $faena->nro,
            // Con los seis ceros del talonario: «002190».
            'numero_legible' => $faena->numero_legible,
            'etiqueta' => $faena->etiqueta,
            // La llave del QR del permiso impreso. Necesita `with('codigo')`.
            'codigo' => $faena->codigo_legible,

            'carnet_id' => $faena->carnet_id,
            'carnet_codigo' => $faena->carnet?->codigo_legible,
            // El número de registro: «000001», que es como se nombra un carnet en
            // el mostrador. El código de 16 sirve para verificar, no para nombrar.
            'carnet_registro' => $faena->carnet?->registro_legible,
            'beneficiario_id' => $faena->carnet?->beneficiario_id,
            'beneficiario' => $faena->carnet?->beneficiario?->nombreCompleto,
            'documento' => $faena->carnet?->beneficiario?->documento_identidad,
            'foto_url' => $faena->carnet?->beneficiario?->foto_url,

            'kilos_extraidos' => (float) $faena->kilos_extraidos,

            // Los renglones del talonario. Nullable: el papel llega incompleto.
            'embarcacion' => $faena->embarcacion,
            'propietario' => $faena->propietario,
            'comandante_barco' => $faena->comandante_barco,
            'matricula_naval' => $faena->matricula_naval,
            'nro_kardex' => $faena->nro_kardex,
            'region_desde' => $faena->region_desde,
            'region_hasta' => $faena->region_hasta,

            'estado' => $faena->estado->value,
            'estado_etiqueta' => $faena->etiquetaEstado(),
            'estado_color' => $faena->colorEstado(),
            'vigente' => $faena->estaVigente(),
            // Una faena vencida LIBERA su volumen: la salida no ocurrió.
            'consume_cupo' => $faena->consumeCupo(),
            'caducada' => $faena->estaCaducada(),
            // Las dos puertas del borrador: estado PENDIENTE y sin un peso
            // cargado. Se resuelven acá para que la pantalla no las recalcule.
            'puede_editarse' => $faena->puedeEditarse(),
            'puede_eliminarse' => $faena->puedeEliminarse(),
            // Por qué todavía no autoriza. Se resuelve en el servidor: React
            // no vuelve a evaluar el estado.
            'motivo_sin_autorizar' => $faena->motivoSinAutorizar(),
            'ya_fue_aprobada' => $faena->yaFueAprobada(),
            'puede_imprimirse' => $faena->puedeImprimirse(),
            // El cobro está en SIREB: la ficha muestra su estado y ofrece verificar el pago.
            'sireb' => $faena->resumenSireb(),
            'puede_verificar_pago' => $faena->estado->estaAbierto(),
            'puede_cargar_pago' => $faena->puedeCargarPago(),

            // El arancel de la salida y cómo va cobrado.
            'monto' => $faena->montoACobrar(),

            // El recibo existe desde el ENVÍO; null mientras es borrador.
            'recibo_id' => $recibo?->id,
            'recibo_numero' => $recibo?->numero_recibo,

            // Cuándo se cargó la fila. Un MOMENTO: con toIso8601String().
            'registrado_en' => $faena->created_at?->toIso8601String(),

            // Son DÍAS, no instantes: con toDateString().
            'fecha_solicitud' => $faena->fecha_solicitud?->toDateString(),
            'fecha_salida' => $faena->fecha_salida?->toDateString(),
            'fecha_desembarque' => $faena->fecha_desembarque?->toDateString(),
        ];
    }
}
