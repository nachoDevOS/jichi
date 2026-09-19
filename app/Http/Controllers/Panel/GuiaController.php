<?php

namespace App\Http\Controllers\Panel;

use App\Enums\EstadoGuia;
use App\Exceptions\PermisoOperativoException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\AnularGuiaRequest;
use App\Http\Requests\Panel\CerrarGuiaRequest;
use App\Http\Requests\Panel\EmitirGuiaRequest;
use App\Models\Beneficiario;
use App\Models\Carnet;
use App\Models\GuiaMovimiento;
use App\Services\EmitirGuiaService;
use App\Support\Paginacion;
use App\Support\Sql;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * ============================================================================
 *  GUÍAS DE MOVIMIENTO — un traslado de producto (paso 4, rama comercializador)
 * ============================================================================
 *
 *     carnet (comercializador) ──▶ guía ──▶ ampara UN traslado, 5 días
 *
 * ----------------------------------------------------------------------------
 *  NO HAY `edit` NI `destroy`
 * ----------------------------------------------------------------------------
 *
 * El código sale de un talonario de papel que viaja dentro del camión. Borrar
 * la fila deja un hueco en la serie y libera un código que el índice único
 * volvería a aceptar: dos traslados podrían terminar diciendo ser el mismo
 * papel.
 *
 * Lo que se escribe después de emitir son dos cosas: CERRAR —la carga llegó, y
 * ahí se corrige el peso contra la balanza del destino— y ANULAR, con motivo.
 */
class GuiaController extends Controller
{
    public function __construct(private readonly EmitirGuiaService $servicio) {}

    /**
     * LISTADO — GET /panel/guias
     */
    public function index(Request $request): Response
    {
        $filtros = [
            'buscar' => $request->string('buscar')->trim()->value() ?: null,
            'estado' => $request->string('estado')->trim()->value() ?: null,
            'piscicultura' => $request->has('piscicultura') && $request->input('piscicultura') !== ''
                ? $request->boolean('piscicultura')
                : null,
            'por_pagina' => Paginacion::filas($request),
        ];

        $guias = GuiaMovimiento::query()
            ->with([
                'comercializador:id,ci,primerNombre,segundoNombre,apellidoPaterno,apellidoMaterno,apellidoCasado',
                'asociacion:id,nombre,sigla',
            ])
            // Evita una consulta agregada POR FILA al calcular el saldo.
            ->withSum('pagos', 'monto_parcial')
            ->when($filtros['buscar'], function ($q, $termino) {
                $operador = Sql::like($q->getConnection());
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], mb_strtoupper($termino)).'%';

                $q->where(fn ($s) => $s
                    ->whereHas('comercializador', fn ($b) => $b->buscar($termino))
                    ->orWhere('codigo_guia', $operador, $like)
                    // El origen y el destino son lo que un control pregunta:
                    // «¿qué salió para Santa Cruz esta semana?».
                    ->orWhere('origen', $operador, $like)
                    ->orWhere('destino', $operador, $like));
            })
            ->when($filtros['estado'], fn ($q, $estado) => $q->where('guias_movimiento.estado', $estado))
            ->when($filtros['piscicultura'] !== null, fn ($q) => $q->where(
                'guias_movimiento.es_piscicultura',
                $filtros['piscicultura'],
            ))
            ->latest('fecha_emision')
            ->paginate($filtros['por_pagina'])
            ->withQueryString()
            ->through($this->resumir(...));

        return Inertia::render('panel/guias/index', [
            'guias' => $guias,
            'filtros' => $filtros,
            'estados' => EstadoGuia::opciones(),
            'opcionesPorPagina' => Paginacion::OPCIONES,
        ]);
    }

    /**
     * FORMULARIO — GET /panel/guias/crear
     */
    public function create(Request $request): Response
    {
        $beneficiario = $request->integer('beneficiario')
            ? Beneficiario::query()->find($request->integer('beneficiario'))
            : null;

        return Inertia::render('panel/guias/crear', [
            'beneficiario' => $beneficiario ? [
                'id' => $beneficiario->id,
                'nombreCompleto' => $beneficiario->nombreCompleto,
                'documento_identidad' => $beneficiario->documento_identidad,
                'foto_url' => $beneficiario->foto_url,

                // Misma forma que devuelve el autocompletado, para que la
                // pantalla trate igual a la persona preseleccionada y a la que
                // se busca a mano.
                'carnets_vigentes' => $beneficiario->carnets()
                    ->vigentes()
                    ->with('tipoCarnet:id,nombre')
                    ->get()
                    ->map(fn (Carnet $c): array => [
                        'id' => $c->id,
                        'codigo' => $c->codigo_legible,
                        'tipo' => $c->tipoCarnet?->nombre,
                        'tipo_actor' => $c->tipo_actor->value,
                        'tipo_actor_etiqueta' => $c->tipo_actor->etiqueta(),
                        'puede_emitir_faenas' => $c->puedeEmitirFaenas(),
                        'puede_emitir_guias' => $c->puedeEmitirGuias(),
                        'saldo_kg' => null,
                        'siguiente_numero_faena' => null,
                    ])
                    ->values()
                    ->all(),
            ] : null,

            'diasVigencia' => GuiaMovimiento::DIAS_VIGENCIA,

            /*
             * LA TARIFA Y EL DESCUENTO SALEN DEL SERVIDOR, no escritos en React.
             *
             * El formulario muestra cuánto va a costar apenas se marca la casilla
             * de piscicultura, y ese número tiene que ser EL MISMO que cobra
             * `GuiaMovimiento::montoACobrar()`. Escrito en los dos lados, el día
             * que la resolución cambie el 50% a 40% la pantalla seguiría
             * prometiendo un precio que la caja no cobra.
             */
            'tarifaBase' => (float) config('jichi.guias.tarifa_base'),
            'descuentoPiscicultura' => GuiaMovimiento::DESCUENTO_PISCICULTURA,
        ]);
    }

    /**
     * EMITIR — POST /panel/guias
     */
    public function store(EmitirGuiaRequest $request): RedirectResponse
    {
        $datos = $request->validated();

        try {
            $guia = $this->servicio->emitir(
                Carnet::query()->findOrFail($datos['carnet_id']),
                $datos['codigo_guia'],
                $datos['origen'],
                $datos['destino'],
                (float) $datos['peso_total_kg'],
                (bool) $datos['es_piscicultura'],
                now()->parse($datos['fecha_emision']),
            );
        } catch (PermisoOperativoException $e) {
            // El código repetido se corrige en su campo; lo del carnet no tiene
            // arreglo desde este formulario, así que se cuelga de ahí.
            $campo = str_contains($e->getMessage(), 'código') ? 'codigo_guia' : 'carnet_id';

            return back()->withInput()->withErrors([$campo => $e->getMessage()]);
        }

        return redirect()
            ->route('guias.show', $guia)
            ->with('exito', "Guía {$guia->codigo_guia} emitida. Vence el {$guia->fecha_vencimiento->format('d/m/Y H:i')}.");
    }

    /**
     * FICHA — GET /panel/guias/{guia}
     */
    public function show(GuiaMovimiento $guia): Response
    {
        $guia->load([
            'comercializador:id,ci,primerNombre,segundoNombre,apellidoPaterno,apellidoMaterno,apellidoCasado',
            'asociacion:id,nombre,sigla',
        ]);

        return Inertia::render('panel/guias/ver', [
            'guia' => [
                ...$this->resumir($guia),
                'asociacion_nombre' => $guia->asociacion?->nombre,
                'documento' => $guia->comercializador?->documento_identidad,
            ],
        ]);
    }

    /**
     * CERRAR — PATCH /panel/guias/{guia}/cerrar
     */
    public function cerrar(CerrarGuiaRequest $request, GuiaMovimiento $guia): RedirectResponse
    {
        $peso = $request->validated()['peso_total_kg'] ?? null;

        try {
            $this->servicio->cerrar($guia, $peso !== null ? (float) $peso : null);
        } catch (PermisoOperativoException $e) {
            return back()->withErrors(['peso_total_kg' => $e->getMessage()]);
        }

        return redirect()
            ->route('guias.show', $guia)
            ->with('exito', 'Guía cerrada. La carga llegó a destino.');
    }

    /**
     * ANULAR — PATCH /panel/guias/{guia}/anular
     */
    public function anular(AnularGuiaRequest $request, GuiaMovimiento $guia): RedirectResponse
    {
        try {
            $this->servicio->anular($guia, $request->validated()['motivo']);
        } catch (PermisoOperativoException $e) {
            return back()->withErrors(['motivo' => $e->getMessage()]);
        }

        return redirect()
            ->route('guias.show', $guia)
            ->with('exito', 'Guía anulada. El código queda ocupado: la hoja del talonario se gastó.');
    }

    // ------------------------------------------------------------------
    //  Auxiliares
    // ------------------------------------------------------------------

    /**
     * Los datos de una guía que pintan el listado y la ficha.
     *
     * `vigente`, `caducada`, `puede_cerrarse` y `puede_anularse` llegan
     * RESUELTOS. Las cuatro son reglas —la primera mira el estado Y la hora, y
     * la última además prohíbe anular una guía CERRADA, porque el traslado ya
     * ocurrió— y deducirlas en la pantalla sería una segunda copia.
     *
     * @return array<string, mixed>
     */
    private function resumir(GuiaMovimiento $guia): array
    {
        return [
            'id' => $guia->id,
            'codigo_guia' => $guia->codigo_guia,

            'beneficiario_id' => $guia->beneficiario_com_id,
            'comercializador' => $guia->comercializador?->nombreCompleto,
            'asociacion' => $guia->asociacion?->sigla ?? $guia->asociacion?->nombre,

            'origen' => $guia->origen,
            'destino' => $guia->destino,
            'ruta' => $guia->ruta,
            'peso_total_kg' => (float) $guia->peso_total_kg,

            'es_piscicultura' => (bool) $guia->es_piscicultura,
            // El factor va explícito para que la ficha pueda decir «se cobró al
            // 50%» sin recalcularlo: la regla vive en el modelo, en un solo lado.
            'factor_arancel' => $guia->factorArancel(),

            'estado' => $guia->estado->value,
            'estado_etiqueta' => $guia->estado->etiqueta(),
            'estado_color' => $guia->estado->color(),
            'vigente' => $guia->estaVigente(),
            'caducada' => $guia->estaCaducada(),
            'horas_restantes' => $guia->horasRestantes(),

            'puede_cerrarse' => $guia->estado === EstadoGuia::Activa,
            'puede_anularse' => $guia->estado === EstadoGuia::Activa,

            'monto' => $guia->montoACobrar(),
            'saldo_pendiente' => $guia->saldoPendiente(),
            'pagado' => $guia->estaPagado(),

            /*
             * Son MOMENTOS, no días: van con toIso8601String().
             *
             * Los cinco días se cuentan desde la HORA de emisión —una guía de
             * las 18:00 del lunes vence a las 18:00 del sábado— así que mandarlas
             * como día perdería justamente el dato que decide la vigencia.
             */
            'fecha_emision' => $guia->fecha_emision?->toIso8601String(),
            'fecha_vencimiento' => $guia->fecha_vencimiento?->toIso8601String(),
        ];
    }
}
