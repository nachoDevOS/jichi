<?php

namespace App\Http\Controllers\Panel;

use App\Enums\EstadoPermiso;
use App\Exceptions\PermisoOperativoException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\GuardarFaenaRequest;
use App\Models\Carnet;
use App\Models\Faena;
use App\Services\FaenaService;
use App\Support\Paginacion;
use App\Support\Sql;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * ============================================================================
 *  MÓDULO FAENAS — el permiso por salida de pesca
 * ============================================================================
 *
 * EL CONTROLADOR NO DECIDE NADA, igual que `TramiteController`. Si el carnet
 * emite faenas, si está vigente, si el número del talonario ya se usó: todo eso
 * vive en `FaenaService`. Acá se traduce la petición, se llama al servicio y se
 * convierte lo que devuelva —o la excepción que lance— en un redirect.
 *
 * ----------------------------------------------------------------------------
 *  NO HAY EDICIÓN, Y NO ES UN OLVIDO
 * ----------------------------------------------------------------------------
 *
 * Una faena es un papel del talonario que la persona se lleva en el momento.
 * Editarla después dejaría el sistema diciendo una cosa y el papel otra, sin que
 * nadie pueda notar la diferencia en un control del río. Una faena mal emitida
 * se ANULA —con su motivo escrito— y se emite otra con un número nuevo.
 */
class FaenaController extends Controller
{
    public function __construct(private readonly FaenaService $faenas) {}

    /**
     * LISTADO — GET /panel/faenas
     */
    public function index(Request $request): Response
    {
        $filtros = [
            'buscar' => $request->string('buscar')->trim()->value() ?: null,
            'estado' => $request->string('estado')->trim()->value() ?: null,
            'desde' => $request->date('desde')?->toDateString(),
            'hasta' => $request->date('hasta')?->toDateString(),
            'por_pagina' => Paginacion::filas($request),
        ];

        $faenas = Faena::query()
            /*
             * Sin este eager loading, pintar 15 filas son 46 consultas: cada una
             * pediría su carnet, el beneficiario de ese carnet y sus pagos.
             *
             * OJO CON LAS COLUMNAS DE `beneficiarios`: el accesor
             * `nombreCompleto` se arma con cinco, y si alguna falta devuelve una
             * cadena vacía —no un error— y la columna sale en blanco sin que
             * nada lo explique.
             */
            ->with([
                'carnet:id,beneficiario_id,rubro_id,gestion',
                'carnet.beneficiario:id,primerNombre,segundoNombre,apellidoPaterno,apellidoMaterno,apellidoCasado',
            ])
            // Lo cobrado de cada faena, en la misma consulta. Ver
            // Faena::montoPagado(), que usa este atributo si ya está.
            ->withSum('pagos', 'monto')

            ->when($filtros['estado'], fn ($q, $e) => $q->where('faenas.estado', $e))
            ->when($filtros['desde'], fn ($q, $d) => $q->whereDate('fecha_salida', '>=', $d))
            ->when($filtros['hasta'], fn ($q, $h) => $q->whereDate('fecha_salida', '<=', $h))

            /*
             * La búsqueda acepta el número del permiso, la embarcación y el
             * titular — las tres formas en que se pregunta en el mostrador.
             *
             * whereHas genera un EXISTS: filtra sin multiplicar filas como haría
             * un join.
             */
            ->when($filtros['buscar'], fn ($q, $t) => $q->where(function ($sub) use ($t) {
                // Sql::like() porque PostgreSQL necesita ILIKE para no
                // distinguir mayúsculas y SQLite ya lo hace con LIKE.
                $sub->where('nro_permiso', Sql::like(), "%{$t}%")
                    ->orWhere('embarcacion', Sql::like(), "%{$t}%")
                    ->orWhereHas('carnet.beneficiario', fn ($b) => $b->buscar($t));
            }))

            // Por id y no por fecha: `fecha_salida` empata —varias faenas del
            // mismo día— y con la fecha sola el orden entre ellas lo decide el
            // motor, así que una fila puede repetirse al pasar de página.
            ->orderByDesc('faenas.id')
            ->paginate($filtros['por_pagina'])
            ->withQueryString()
            ->through(fn (Faena $f): array => $this->resumir($f));

        return Inertia::render('panel/faenas/index', [
            'faenas' => $faenas,
            'filtros' => $filtros,
            // El catálogo sale del servidor y no escrito en React: los valores
            // son los del enum, y repetidos en el frontend algún día dirían
            // cosas distintas.
            'estados' => EstadoPermiso::opciones(),
            'opcionesPorPagina' => Paginacion::OPCIONES,
        ]);
    }

    /**
     * FORMULARIO DE ALTA — GET /panel/faenas/crear
     *
     * Acepta `?carnet=` para llegar con el carnet ya elegido desde su ficha, que
     * es el camino más corto: el operador ya tiene la persona en pantalla.
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

            // Si el carnet no emite faenas se descarta en silencio y el
            // formulario abre con el buscador vacío. Rebotar con un error sería
            // peor: el operador no pidió nada malo, llegó por un enlace viejo.
            if ($carnet && ! $carnet->puedeEmitirFaenas()) {
                $carnet = null;
            }
        }

        return Inertia::render('panel/faenas/crear', [
            'carnetElegido' => $carnet ? $this->resumirCarnet($carnet) : null,
            // La tarifa vigente, para que el formulario la muestre ya cargada.
            'montoSugerido' => Faena::TARIFA,
        ]);
    }

    /**
     * ALTA — POST /panel/faenas
     */
    public function store(GuardarFaenaRequest $request): RedirectResponse
    {
        $carnet = Carnet::findOrFail($request->integer('carnet_id'));

        try {
            $faena = $this->faenas->emitir($carnet, $request->validated());
        } catch (PermisoOperativoException $e) {
            // El mensaje está escrito para que lo lea el operador. withInput
            // devuelve lo tipeado para que no tenga que cargarlo de nuevo.
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('faenas.show', $faena->id)
            ->with('exito', "Faena {$faena->nro_permiso} emitida.");
    }

    /**
     * FICHA — GET /panel/faenas/{faena}
     */
    public function show(Faena $faena): Response
    {
        $faena->load([
            'carnet.beneficiario',
            'carnet.rubro:id,nombre',
            'pagos',
        ]);

        return Inertia::render('panel/faenas/ver', [
            'faena' => [
                ...$this->resumir($faena),
                'propietario' => $faena->propietario,
                'matricula_naval' => $faena->matricula_naval,
                'nro_kardex' => $faena->nro_kardex,
                'nro_recibo' => $faena->nro_recibo,
                'region_desde' => $faena->region_desde,
                'region_hasta' => $faena->region_hasta,
                'observaciones' => $faena->observaciones,
                'dias_autorizados' => $faena->diasAutorizados(),
                // Lo decide el modelo y no la pantalla, igual que los `puede_*`
                // del trámite: escrita otra vez en React, la regla terminaría
                // diciendo algo distinto que el servidor.
                'vigente' => $faena->estaVigente(),
                'puede_anularse' => $faena->estaEmitida(),
            ],

            'beneficiario' => [
                'id' => $faena->carnet?->beneficiario?->id,
                'nombreCompleto' => $faena->carnet?->beneficiario?->nombreCompleto,
                'documento_identidad' => $faena->carnet?->beneficiario?->documento_identidad,
                'foto_url' => $faena->carnet?->beneficiario?->foto_url,
            ],

            'carnet' => [
                'id' => $faena->carnet?->id,
                'registro' => $faena->carnet?->registro(),
                'rubro' => $faena->carnet?->rubro?->nombre,
                'gestion' => $faena->carnet?->gestion,
                'vigente' => $faena->carnet?->estaVigente() ?? false,
            ],

            'pagos' => $faena->pagos->map(fn ($p): array => [
                'id' => $p->id,
                'nro_transaccion' => $p->nro_transaccion,
                'monto' => (float) $p->monto,
                'fecha_pago' => $p->fecha_pago?->toIso8601String(),
                'comprobante_url' => $p->comprobante_url,
            ])->all(),
        ]);
    }

    /**
     * ANULAR — PATCH /panel/faenas/{faena}/anular
     *
     * PATCH y no GET, por lo mismo que los pasos del circuito del trámite: un
     * verbo de lectura que escribe se dispara solo. Alcanza con que el navegador
     * precargue el enlace o que alguien lo comparta por chat para que una faena
     * quede anulada sin que nadie la haya tocado.
     */
    public function anular(Request $request, Faena $faena): RedirectResponse
    {
        try {
            $this->faenas->anular($faena, $request->string('motivo')->trim()->value());
        } catch (PermisoOperativoException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('exito', "Faena {$faena->nro_permiso} anulada.");
    }

    /**
     * Los campos que comparten el listado y la ficha.
     *
     * Vive acá y no en el modelo porque es PRESENTACIÓN: qué se muestra y cómo.
     * Las reglas —si está vigente, cuánto falta pagar— las contesta el modelo.
     *
     * @return array<string, mixed>
     */
    private function resumir(Faena $faena): array
    {
        return [
            'id' => $faena->id,
            'nro_permiso' => $faena->nro_permiso,
            'estado' => $faena->estado->value,
            'estado_etiqueta' => $faena->estado->etiqueta(),
            'estado_color' => $faena->estado->color(),
            'embarcacion' => $faena->embarcacion,
            'comandante_barco' => $faena->comandante_barco,
            'fecha_salida' => $faena->fecha_salida?->toDateString(),
            'fecha_desembarque' => $faena->fecha_desembarque?->toDateString(),
            'cantidad_autorizada_kg' => (float) $faena->cantidad_autorizada_kg,
            'cantidad' => $faena->cantidadLegible(),
            'monto' => (float) $faena->monto,
            'monto_pagado' => $faena->montoPagado(),
            'saldo' => $faena->saldoPendiente(),
            'pagada' => $faena->estaPagada(),
            'carnet_id' => $faena->carnet_id,
            'carnet_registro' => $faena->carnet?->registro(),
            'beneficiario' => $faena->carnet?->beneficiario?->nombreCompleto,
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
