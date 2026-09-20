<?php

namespace App\Http\Controllers\Panel;

use App\Enums\EstadoCarnet;
use App\Enums\TipoActor;
use App\Exceptions\CarnetInvalidoException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\EmitirCarnetRequest;
use App\Http\Requests\Panel\RevocarCarnetRequest;
use App\Models\Asociacion;
use App\Models\Beneficiario;
use App\Models\Carnet;
use App\Models\TipoCarnet;
use App\Services\EmitirCarnetService;
use App\Support\Paginacion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 *  CARNETS — la credencial anual (paso 3 del flujo)
 */
class CarnetController extends Controller
{
    public function __construct(private readonly EmitirCarnetService $servicio) {}

    /**
     * LISTADO — GET /panel/carnets
     */
    public function index(Request $request): Response
    {
        $filtros = [
            'buscar' => $request->string('buscar')->trim()->value() ?: null,
            'estado' => $request->string('estado')->trim()->value() ?: null,
            'actor' => $request->string('actor')->trim()->value() ?: null,
            'por_pagina' => Paginacion::filas($request),
        ];

        $carnets = Carnet::query()
            /*
             * OJO CON PEDIR COLUMNAS SUELTAS: el beneficiario va con las CINCO
             * partes del nombre porque `nombreCompleto` las lee todas, y
             * `tipoCarnet` va ENTERO porque `montoACobrar()` lee `precio_bs`.
             * Una columna que un método consulta y no está en el select vuelve
             * null y el método contesta cualquier cosa, sin ningún error.
             */
            ->with([
                'beneficiario:id,ci,primerNombre,segundoNombre,apellidoPaterno,apellidoMaterno,apellidoCasado',
                'asociacion:id,nombre,sigla',
                'tipoCarnet',
                'aprovechamiento',
            ])
            // Evita una consulta agregada POR FILA al calcular el saldo.
            ->withSum('pagos', 'monto_parcial')
            ->when($filtros['buscar'], fn ($q, $termino) => $q->where(
                fn ($s) => $s
                    ->whereHas('beneficiario', fn ($b) => $b->buscar($termino))
                    // El código se busca NORMALIZADO: la gente lo copia del
                    // plástico con los espacios de los grupos de cuatro.
                    ->orWhere('codigo_carnet', 'like', '%'.Carnet::normalizarCodigo($termino).'%'),
            ))
            ->when($filtros['estado'], fn ($q, $estado) => $q->where('carnets.estado', $estado))
            ->when($filtros['actor'], fn ($q, $actor) => $q->where('carnets.tipo_actor', $actor))
            ->latest('fecha_emision')
            ->paginate($filtros['por_pagina'])
            ->withQueryString()
            ->through($this->resumir(...));

        return Inertia::render('panel/carnets/index', [
            'carnets' => $carnets,
            'filtros' => $filtros,
            'estados' => EstadoCarnet::opciones(),
            'actores' => TipoActor::opciones(),
            'opcionesPorPagina' => Paginacion::OPCIONES,
        ]);
    }

    /**
     * FORMULARIO — GET /panel/carnets/crear
     *
     * Acepta `?beneficiario=7` para llegar desde la ficha de la persona con el
     * buscador ya resuelto.
     */
    public function create(Request $request): Response
    {
        $beneficiario = $request->integer('beneficiario')
            ? Beneficiario::query()->find($request->integer('beneficiario'))
            : null;

        return Inertia::render('panel/carnets/crear', [
            'beneficiario' => $beneficiario ? [
                'id' => $beneficiario->id,
                'nombreCompleto' => $beneficiario->nombreCompleto,
                'documento_identidad' => $beneficiario->documento_identidad,
                'foto_url' => $beneficiario->foto_url,
            ] : null,

            /*
             * EL CUPO VIGENTE DE ESA PERSONA, si la hay.
             */
            'cupoVigente' => $beneficiario?->aprovechamientoVigente() !== null ? [
                'volumen_total_kg' => (float) $beneficiario->aprovechamientoVigente()->volumen_total_kg,
                'saldo_kg' => $beneficiario->aprovechamientoVigente()->saldoKg(),
            ] : null,

            'asociaciones' => Asociacion::query()
                ->activas()
                ->ordenAlfabetico()
                ->get(['id', 'nombre', 'sigla'])
                ->map(fn (Asociacion $a): array => [
                    'id' => $a->id,
                    'nombre' => $a->nombre,
                    'sigla' => $a->sigla,
                ])
                ->all(),

            'tipos' => TipoCarnet::query()
                ->vigentes()
                ->orderBy('nombre')
                ->get()
                ->map(fn (TipoCarnet $t): array => [
                    'id' => $t->id,
                    'nombre' => $t->nombre,
                    'precio_bs' => (float) $t->precio_bs,
                ])
                ->all(),

            'actores' => TipoActor::opciones(),
        ]);
    }

    /**
     * EMITIR — POST /panel/carnets
     */
    public function store(EmitirCarnetRequest $request): RedirectResponse
    {
        $datos = $request->validated();

        try {
            $carnet = $this->servicio->emitir(
                Beneficiario::query()->findOrFail($datos['beneficiario_id']),
                Asociacion::query()->findOrFail($datos['asociacion_id']),
                TipoCarnet::query()->findOrFail($datos['tipo_carnet_id']),
                TipoActor::from($datos['tipo_actor']),
                now()->parse($datos['fecha_emision']),
            );
        } catch (CarnetInvalidoException $e) {
            /*
             * El mensaje vuelve como error DEL CAMPO de la persona y no como un
             * cartel suelto: las tres reglas que puede romper —ya tiene carnet,
             * no tiene cupo— son sobre la persona elegida, así que el texto
             * tiene que aparecer al lado de ese campo.
             */
            return back()
                ->withInput()
                ->withErrors(['beneficiario_id' => $e->getMessage()]);
        }

        return redirect()
            ->route('carnets.show', $carnet)
            ->with('exito', "Carnet {$carnet->codigo_legible} emitido. Ya se puede imprimir.");
    }

    /**
     * FICHA — GET /panel/carnets/{carnet}
     */
    public function show(Carnet $carnet): Response
    {
        $carnet->load([
            'beneficiario:id,ci,primerNombre,segundoNombre,apellidoPaterno,apellidoMaterno,apellidoCasado',
            'asociacion:id,nombre,sigla',
            'tipoCarnet',
            'aprovechamiento.categoria',
        ]);

        return Inertia::render('panel/carnets/ver', [
            'carnet' => [
                ...$this->resumir($carnet),

                // Para la vista previa del plástico y para la ficha.
                'foto_url' => $carnet->beneficiario?->foto_url,
                'documento_identidad' => $carnet->beneficiario?->documento_identidad,
                'ciudad' => $carnet->beneficiario?->ciudad,
                'provincia' => $carnet->beneficiario?->provincia,
                'asociacion_nombre' => $carnet->asociacion?->nombre,

                /*
                 * El cupo con su saldo: en la ficha del carnet interesa saber
                 * cuánto le queda, porque es lo que decide si se le puede emitir
                 * una faena hoy.
                 */
                'cupo' => $carnet->aprovechamiento ? [
                    'id' => $carnet->aprovechamiento->id,
                    'volumen_total_kg' => (float) $carnet->aprovechamiento->volumen_total_kg,
                    'saldo_kg' => $carnet->aprovechamiento->saldoKg(),
                    'porcentaje_usado' => $carnet->aprovechamiento->porcentajeUsado(),
                    'vigente' => $carnet->aprovechamiento->estaVigente(),
                ] : null,
            ],
        ]);
    }

    /**
     * REVOCAR — PATCH /panel/carnets/{carnet}/revocar
     */
    public function revocar(RevocarCarnetRequest $request, Carnet $carnet): RedirectResponse
    {
        try {
            $this->servicio->revocar($carnet, $request->validated()['motivo']);
        } catch (CarnetInvalidoException $e) {
            return back()->withErrors(['motivo' => $e->getMessage()]);
        }

        return redirect()
            ->route('carnets.show', $carnet)
            ->with('exito', 'Carnet revocado. La verificación pública ya lo informa como revocado.');
    }

    //  Auxiliares

    /**
     * Los datos de un carnet que pintan el listado y la ficha.
     *
     * @return array<string, mixed>
     */
    private function resumir(Carnet $carnet): array
    {
        return [
            'id' => $carnet->id,
            'beneficiario_id' => $carnet->beneficiario_id,
            'beneficiario' => $carnet->beneficiario?->nombreCompleto,

            // En grupos de cuatro, igual que va impreso: es lo que se compara
            // contra el plástico y lo que se dicta por teléfono.
            'codigo' => $carnet->codigo_legible,

            'tipo' => $carnet->tipoCarnet?->nombre,
            'tipo_actor' => $carnet->tipo_actor->value,
            'tipo_actor_etiqueta' => $carnet->tipo_actor->etiqueta(),
            'tipo_actor_color' => $carnet->tipo_actor->color(),
            'asociacion' => $carnet->asociacion?->sigla ?? $carnet->asociacion?->nombre,

            // Null en un comercializador, y es la regla: lo decide el enum.
            'cupo_kg' => $carnet->cupoImpreso(),

            'estado' => $carnet->estado->value,
            'estado_etiqueta' => $carnet->estado->etiqueta(),
            'estado_color' => $carnet->estado->color(),
            'vigente' => $carnet->estaVigente(),
            'dias_para_vencer' => $carnet->diasParaVencer(),

            'puede_emitir_faenas' => $carnet->puedeEmitirFaenas(),
            'puede_emitir_guias' => $carnet->puedeEmitirGuias(),

            'monto' => $carnet->montoACobrar(),
            'saldo_pendiente' => $carnet->saldoPendiente(),
            'pagado' => $carnet->estaPagado(),

            // Son DÍAS, no instantes: con toDateString(). Mandados como instante,
            // en UTC-4 la pantalla mostraría el día anterior.
            'fecha_emision' => $carnet->fecha_emision?->toDateString(),
            'fecha_vencimiento' => $carnet->fecha_vencimiento?->toDateString(),
        ];
    }
}
