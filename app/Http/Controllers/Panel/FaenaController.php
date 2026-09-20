<?php

namespace App\Http\Controllers\Panel;

use App\Enums\EstadoFaena;
use App\Exceptions\PermisoOperativoException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\CompletarFaenaRequest;
use App\Http\Requests\Panel\EmitirFaenaRequest;
use App\Models\AprovechamientoPesq;
use App\Models\Beneficiario;
use App\Models\Carnet;
use App\Models\PermisoFaena;
use App\Services\EmitirFaenaService;
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
                'carnet.beneficiario:id,ci,primerNombre,segundoNombre,apellidoPaterno,apellidoMaterno,apellidoCasado',
            ])
            ->when($filtros['buscar'], fn ($q, $termino) => $q->where(
                fn ($s) => $s
                    ->whereHas('carnet.beneficiario', fn ($b) => $b->buscar($termino))
                    ->orWhere('numero_faena', 'like', '%'.preg_replace('/\D/', '', $termino).'%'),
            ))
            ->when($filtros['estado'], fn ($q, $estado) => $q->where('permisos_faena.estado', $estado))
            ->latest('fecha_salida')
            ->latest('numero_faena')
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
                            ->withSum('faenasQueConsumen', 'kilos_extraidos')
                            ->withMax('faenas', 'numero_faena'),
                    ])
                    ->get()
                    ->map($this->resumirCarnetParaEmitir(...))
                    ->values()
                    ->all(),
            ] : null,

            'diasVigencia' => PermisoFaena::DIAS_VIGENCIA,

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
                (int) $datos['numero_faena'],
                (float) $datos['kilos_extraidos'],
                now()->parse($datos['fecha_salida']),
            );
        } catch (PermisoOperativoException $e) {
            /*
             * El mensaje vuelve como error del campo que el operador puede
             * corregir. Las reglas del carnet no tienen arreglo desde este
             * formulario —hay que ir a emitir o renovar el carnet— así que se
             * cuelgan de `carnet_id`; el exceso de cupo y el número repetido sí
             * se corrigen acá.
             */
            $campo = match (true) {
                str_contains($e->getMessage(), 'quedan') => 'kilos_extraidos',
                str_contains($e->getMessage(), 'número') => 'numero_faena',
                default => 'carnet_id',
            };

            return back()->withInput()->withErrors([$campo => $e->getMessage()]);
        }

        return redirect()
            ->route('faenas.show', $faena)
            ->with('exito', "Faena N° {$faena->numero_faena} emitida. Vence el {$faena->fecha_limite->format('d/m/Y')}.");
    }

    /**
     * FICHA — GET /panel/faenas/{faena}
     */
    public function show(PermisoFaena $faena): Response
    {
        $faena->load([
            'carnet:id,beneficiario_id,codigo_carnet,tipo_actor,asociacion_id',
            'carnet.beneficiario:id,ci,primerNombre,segundoNombre,apellidoPaterno,apellidoMaterno,apellidoCasado',
            'carnet.asociacion:id,nombre,sigla',
            'aprovechamiento.categoria',
        ]);

        return Inertia::render('panel/faenas/ver', [
            'faena' => [
                ...$this->resumir($faena),
                'asociacion' => $faena->carnet?->asociacion?->sigla ?? $faena->carnet?->asociacion?->nombre,

                /*
                 * EL CUPO DEL QUE SALIERON LOS KILOS.
                 */
                'cupo' => $faena->aprovechamiento ? [
                    'id' => $faena->aprovechamiento->id,
                    'escala' => $faena->aprovechamiento->categoria?->nro_escala,
                    'volumen_total_kg' => (float) $faena->aprovechamiento->volumen_total_kg,
                    'saldo_kg' => $faena->aprovechamiento->saldoKg(),
                    'porcentaje_usado' => $faena->aprovechamiento->porcentajeUsado(),
                ] : null,
            ],
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

    //  Auxiliares

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
            'siguiente_numero_faena' => $carnet->aprovechamiento
                ? (int) ($carnet->aprovechamiento->faenas_max_numero_faena ?? 0) + 1
                : null,
        ];
    }

    /**
     * Los datos de una faena que pintan el listado y la ficha.
     *
     * @return array<string, mixed>
     */
    private function resumir(PermisoFaena $faena): array
    {
        return [
            'id' => $faena->id,
            'numero_faena' => $faena->numero_faena,
            'etiqueta' => $faena->etiqueta,

            'carnet_id' => $faena->carnet_id,
            'carnet_codigo' => $faena->carnet?->codigo_legible,
            'beneficiario_id' => $faena->carnet?->beneficiario_id,
            'beneficiario' => $faena->carnet?->beneficiario?->nombreCompleto,

            'kilos_extraidos' => (float) $faena->kilos_extraidos,

            'estado' => $faena->estado->value,
            'estado_etiqueta' => $faena->estado->etiqueta(),
            'estado_color' => $faena->estado->color(),
            'vigente' => $faena->estaVigente(),
            // Una faena vencida LIBERA su volumen: la salida no ocurrió.
            'consume_cupo' => $faena->consumeCupo(),
            'caducada' => $faena->estaCaducada(),
            'puede_completarse' => $faena->estado === EstadoFaena::Activo,

            // Son DÍAS, no instantes: con toDateString().
            'fecha_salida' => $faena->fecha_salida?->toDateString(),
            'fecha_limite' => $faena->fecha_limite?->toDateString(),
        ];
    }
}
