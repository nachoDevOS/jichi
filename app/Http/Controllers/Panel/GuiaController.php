<?php

namespace App\Http\Controllers\Panel;

use App\Enums\CondicionProducto;
use App\Enums\EstadoPermiso;
use App\Enums\TipoTransporte;
use App\Exceptions\PermisoOperativoException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\GuardarGuiaRequest;
use App\Models\Carnet;
use App\Models\Guia;
use App\Models\GuiaDetalle;
use App\Services\GuiaService;
use App\Support\Paginacion;
use App\Support\Sql;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * ============================================================================
 *  MÓDULO GUÍAS — la Guía Única de Transporte de Productos Ictícolas
 * ============================================================================
 *
 * Mismo reparto que en `FaenaController`: acá se traduce la petición y se
 * llama a `GuiaService`, que es donde viven las reglas.
 *
 * NO HAY EDICIÓN DE LA CABECERA, por lo mismo que en las faenas: la guía es un
 * papel que ya viaja con la carga. Sí se puede REEMPLAZAR EL DETALLE, y esa es
 * la única excepción del módulo — el peso real se conoce recién en la balanza, y
 * hasta que la carga sale, corregir la grilla es parte del trabajo normal. La
 * cabecera —quién, desde dónde, hasta dónde— no cambia nunca.
 */
class GuiaController extends Controller
{
    public function __construct(private readonly GuiaService $guias) {}

    /**
     * LISTADO — GET /panel/guias
     */
    public function index(Request $request): Response
    {
        $filtros = [
            'buscar' => $request->string('buscar')->trim()->value() ?: null,
            'estado' => $request->string('estado')->trim()->value() ?: null,
            'transporte' => $request->string('transporte')->trim()->value() ?: null,
            'por_pagina' => Paginacion::filas($request),
        ];

        $guias = Guia::query()
            ->with([
                'carnet:id,beneficiario_id,rubro_id,gestion',
                'carnet.beneficiario:id,primerNombre,segundoNombre,apellidoPaterno,apellidoMaterno,apellidoCasado',
            ])
            /*
             * withSum sobre `detalles` trae los kilos de cada guía en la misma
             * consulta. Sin esto, `totalKg()` de cada fila sería una consulta
             * agregada por fila — y la columna «kilos» es justamente la que se
             * mira primero en el listado.
             *
             * OJO: el nombre del atributo lo arma Laravel —`detalles_sum_cantidad_kg`—
             * y no coincide con el que `Guia::totalKg()` reusa, así que acá se
             * lee directo.
             */
            ->withSum('detalles', 'cantidad_kg')

            ->when($filtros['estado'], fn ($q, $e) => $q->where('guias.estado', $e))
            ->when($filtros['transporte'], fn ($q, $t) => $q->where('tipo_transporte', $t))

            // El número, el transportista, el destino y el titular: las cuatro
            // formas en que se pregunta por una guía en el mostrador.
            ->when($filtros['buscar'], fn ($q, $t) => $q->where(function ($sub) use ($t) {
                $sub->where('nro_guia', Sql::like(), "%{$t}%")
                    ->orWhere('transporte_nombre', Sql::like(), "%{$t}%")
                    ->orWhere('destino_lugar', Sql::like(), "%{$t}%")
                    ->orWhereHas('carnet.beneficiario', fn ($b) => $b->buscar($t));
            }))

            // Por id: es estrictamente creciente y nunca empata, así que la
            // paginación queda estable. Ver la nota de FaenaController.
            ->orderByDesc('guias.id')
            ->paginate($filtros['por_pagina'])
            ->withQueryString()
            ->through(fn (Guia $g): array => $this->resumir($g));

        return Inertia::render('panel/guias/index', [
            'guias' => $guias,
            'filtros' => $filtros,
            'estados' => EstadoPermiso::opciones(),
            'transportes' => TipoTransporte::opciones(),
            'opcionesPorPagina' => Paginacion::OPCIONES,
        ]);
    }

    /**
     * FORMULARIO DE ALTA — GET /panel/guias/crear
     *
     * Acepta `?carnet=` para llegar con el carnet ya elegido desde su ficha.
     */
    public function create(Request $request): Response
    {
        $carnet = null;

        if ($id = $request->integer('carnet')) {
            /*
             * OJO CON LAS COLUMNAS QUE SE PIDEN DE `rubros`.
             *
             * `emite_faenas` y `emite_guias` TIENEN QUE ESTAR: `puedeEmitirFaenas()`
             * las lee, y si no vinieron en el select devuelven null —no un error—
             * así que el carnet se descartaba en silencio y el formulario abría
             * vacío sin que nada lo explicara. Es la misma trampa que con las cinco
             * columnas del nombre del beneficiario.
             */
            $carnet = Carnet::query()
                ->with([
                    'beneficiario:id,ci_nit,complemento,expedido,primerNombre,segundoNombre,apellidoPaterno,apellidoMaterno,apellidoCasado',
                    'rubro:id,nombre,emite_faenas,emite_guias',
                ])
                ->find($id);

            // Un carnet que no emite guías se descarta en silencio: el operador
            // llegó por un enlace viejo, no pidió nada malo.
            if ($carnet && ! $carnet->puedeEmitirGuias()) {
                $carnet = null;
            }
        }

        return Inertia::render('panel/guias/crear', [
            'carnetElegido' => $carnet ? $this->resumirCarnet($carnet) : null,
            // Los dos catálogos salen del servidor: son los valores de los
            // enums, y escritos también en React algún día dirían otra cosa.
            'transportes' => TipoTransporte::opciones(),
            'condiciones' => CondicionProducto::opciones(),
        ]);
    }

    /**
     * ALTA — POST /panel/guias
     */
    public function store(GuardarGuiaRequest $request): RedirectResponse
    {
        $carnet = Carnet::findOrFail($request->integer('carnet_id'));
        $datos = $request->validated();

        try {
            $guia = $this->guias->emitir($carnet, $datos, $datos['detalles']);
        } catch (PermisoOperativoException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('guias.show', $guia->id)
            ->with('exito', "Guía {$guia->nro_guia} emitida.");
    }

    /**
     * FICHA — GET /panel/guias/{guia}
     */
    public function show(Guia $guia): Response
    {
        $guia->load([
            'carnet.beneficiario',
            'carnet.rubro:id,nombre',
            'detalles',
            'pagos',
        ]);

        return Inertia::render('panel/guias/ver', [
            'guia' => [
                ...$this->resumir($guia),
                'nro_recibo' => $guia->nro_recibo,
                'origen' => $this->ubicacion($guia, 'origen'),
                'destino' => $this->ubicacion($guia, 'destino'),
                'transporte_placa' => $guia->transporte_placa,
                // El rótulo cambia con el medio: a una canoa no se le pide la
                // «placa». Ver TipoTransporte::rotuloIdentificacion().
                'rotulo_identificacion' => $guia->tipo_transporte->rotuloIdentificacion(),
                'capacidad_maxima' => $guia->capacidad_maxima !== null ? (float) $guia->capacidad_maxima : null,
                'excede_capacidad' => $guia->excedeCapacidad(),
                'observaciones' => $guia->observaciones,
                'vigente' => $guia->estaVigente(),
                'puede_anularse' => $guia->estaEmitida(),
                // El detalle se corrige mientras la guía valga: el peso real
                // sale de la balanza y puede no coincidir con lo declarado.
                'puede_editar_detalle' => $guia->estaEmitida(),
            ],

            'beneficiario' => [
                'id' => $guia->carnet?->beneficiario?->id,
                'nombreCompleto' => $guia->carnet?->beneficiario?->nombreCompleto,
                'documento_identidad' => $guia->carnet?->beneficiario?->documento_identidad,
                'foto_url' => $guia->carnet?->beneficiario?->foto_url,
            ],

            'carnet' => [
                'id' => $guia->carnet?->id,
                'registro' => $guia->carnet?->registro(),
                'rubro' => $guia->carnet?->rubro?->nombre,
                'gestion' => $guia->carnet?->gestion,
                'vigente' => $guia->carnet?->estaVigente() ?? false,
            ],

            'detalles' => $guia->detalles->map(fn (GuiaDetalle $d): array => [
                'id' => $d->id,
                'especie' => $d->especie,
                'condicion' => $d->condicion->value,
                'condicion_etiqueta' => $d->condicion->etiqueta(),
                'condicion_color' => $d->condicion->color(),
                'cantidad_kg' => (float) $d->cantidad_kg,
                'precio_unitario' => $d->precio_unitario !== null ? (float) $d->precio_unitario : null,
                'imponible' => $d->imponible !== null ? (float) $d->imponible : null,
                'importe' => $d->importe(),
            ])->all(),

            'condiciones' => CondicionProducto::opciones(),

            'pagos' => $guia->pagos->map(fn ($p): array => [
                'id' => $p->id,
                'nro_transaccion' => $p->nro_transaccion,
                'monto' => (float) $p->monto,
                'fecha_pago' => $p->fecha_pago?->toIso8601String(),
                'comprobante_url' => $p->comprobante_url,
            ])->all(),
        ]);
    }

    /**
     * REEMPLAZAR EL DETALLE — PUT /panel/guias/{guia}/detalle
     *
     * La grilla llega completa y reemplaza a la anterior: no es un diff. Ver
     * GuiaService::reemplazarDetalle(), que explica por qué.
     */
    public function actualizarDetalle(Request $request, Guia $guia): RedirectResponse
    {
        $datos = $request->validate([
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.especie' => ['required', 'string', 'max:100'],
            'detalles.*.condicion' => ['required', 'string', 'max:50'],
            'detalles.*.cantidad_kg' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'detalles.*.precio_unitario' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'detalles.*.imponible' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
        ]);

        try {
            $this->guias->reemplazarDetalle($guia, $datos['detalles']);
        } catch (PermisoOperativoException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('exito', 'Detalle de la guía actualizado.');
    }

    /**
     * ANULAR — PATCH /panel/guias/{guia}/anular
     *
     * PATCH y no GET: un verbo de lectura que escribe se dispara solo con que el
     * navegador precargue el enlace.
     */
    public function anular(Request $request, Guia $guia): RedirectResponse
    {
        try {
            $this->guias->anular($guia, $request->string('motivo')->trim()->value());
        } catch (PermisoOperativoException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('exito', "Guía {$guia->nro_guia} anulada.");
    }

    /**
     * Los campos que comparten el listado y la ficha.
     *
     * @return array<string, mixed>
     */
    private function resumir(Guia $guia): array
    {
        /*
         * El listado trae los kilos con `withSum`; la ficha carga el detalle
         * entero. Se prefiere el atributo del withSum cuando está para no
         * disparar una consulta por fila —`$this->detalles()->sum()` consulta
         * IGUAL aunque haya eager loading—.
         */
        $kilos = $guia->detalles_sum_cantidad_kg !== null
            ? (float) $guia->detalles_sum_cantidad_kg
            : $guia->totalKg();

        return [
            'id' => $guia->id,
            'nro_guia' => $guia->nro_guia,
            'estado' => $guia->estado->value,
            'estado_etiqueta' => $guia->estado->etiqueta(),
            'estado_color' => $guia->estado->color(),
            'tipo_transporte' => $guia->tipo_transporte->value,
            'transporte_etiqueta' => $guia->tipo_transporte->etiqueta(),
            'transporte_color' => $guia->tipo_transporte->color(),
            'transporte_nombre' => $guia->transporte_nombre,
            'origen_lugar' => $guia->origen_lugar,
            'destino_lugar' => $guia->destino_lugar,
            'total_kg' => $kilos,
            // `montoRequerido()` recorre el detalle, así que en el listado —donde
            // no está cargado— saldría una consulta por fila. Solo se manda
            // cuando la relación ya vino.
            'monto_requerido' => $guia->relationLoaded('detalles') ? $guia->montoRequerido() : null,
            'monto_pagado' => $guia->montoPagado(),
            'fecha' => $guia->created_at?->toIso8601String(),
            'carnet_id' => $guia->carnet_id,
            'carnet_registro' => $guia->carnet?->registro(),
            'beneficiario' => $guia->carnet?->beneficiario?->nombreCompleto,
        ];
    }

    /**
     * Junta los cuatro campos de origen o de destino en una línea legible.
     *
     * Se arma en el servidor y no en React porque lo necesitan dos pantallas —la
     * ficha y, mañana, el PDF de la guía— y escrito dos veces una de las dos se
     * queda vieja. `array_filter` saca los vacíos: el papel llega incompleto
     * seguido, y «Trinidad, , Cercado, » no se lee.
     *
     * @return array<string, mixed>
     */
    private function ubicacion(Guia $guia, string $prefijo): array
    {
        $partes = array_filter([
            $guia->{"{$prefijo}_lugar"},
            $guia->{"{$prefijo}_provincia"},
            $guia->{"{$prefijo}_depto"},
        ]);

        return [
            'lugar' => $guia->{"{$prefijo}_lugar"},
            'depto' => $guia->{"{$prefijo}_depto"},
            'provincia' => $guia->{"{$prefijo}_provincia"},
            'distrito' => $guia->{"{$prefijo}_distrito"},
            'completo' => implode(', ', $partes),
        ];
    }

    /**
     * El carnet tal como lo muestra el buscador del formulario.
     *
     * @return array<string, mixed>
     */
    private function resumirCarnet(Carnet $carnet): array
    {
        return [
            'id' => $carnet->id,
            'registro' => $carnet->registro(),
            'gestion' => $carnet->gestion,
            'rubro' => $carnet->rubro?->nombre,
            'beneficiario' => $carnet->beneficiario?->nombreCompleto,
            'documento_identidad' => $carnet->beneficiario?->documento_identidad,
            'capacidad' => $carnet->capacidadLegible(),
        ];
    }
}
