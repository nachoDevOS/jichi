<?php

namespace App\Http\Controllers\Panel;

use App\Exceptions\SolicitudInvalidaException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\RegistrarPagoRequest;
use App\Models\Pago;
use App\Models\Tramite;
use App\Services\PagoTramiteService;
use App\Support\Paginacion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * ============================================================================
 *  MÓDULO PAGOS — Regla C
 * ============================================================================
 *
 * Un trámite se cubre con uno o varios depósitos. Este controlador atiende el
 * caso de «el pescador volvió con otra boleta»; la primera boleta, si la trajo
 * el día del alta, entra por TramiteController::store().
 *
 * NO HAY ALTA SUELTA DE PAGO. Todas las rutas cuelgan de un trámite
 * (/panel/tramites/{tramite}/pagos) porque un pago sin expediente no significa
 * nada: no habría contra qué compararlo ni a quién acreditárselo.
 */
class PagoController extends Controller
{
    public function __construct(private readonly PagoTramiteService $pagos) {}

    /**
     * LIBRO DE CAJA — GET /panel/pagos
     *
     * Todos los depósitos del sistema, para cuadrar contra el extracto del
     * banco. Es la única pantalla de pagos que no cuelga de un trámite, porque
     * justamente lo que se quiere es mirarlos todos juntos.
     */
    public function index(Request $request): Response
    {
        $filtros = [
            'buscar' => $request->string('buscar')->trim()->value() ?: null,
            'desde' => $request->date('desde')?->toDateString(),
            'hasta' => $request->date('hasta')?->toDateString(),
            'por_pagina' => Paginacion::filas($request),
        ];

        $pagos = Pago::query()
            ->with([
                'tramite:id,carnet_id,rubro_id,monto_requerido',
                'tramite.rubro:id,nombre',
                'tramite.carnet:id,beneficiario_id',
                'tramite.carnet.beneficiario:id,primerNombre,segundoNombre,apellidoPaterno,apellidoMaterno,apellidoCasado',
            ])
            ->when($filtros['buscar'], fn ($q, $t) => $q->where('nro_transaccion', 'like', "%{$t}%"))
            // whereDate y no where: `fecha_pago` es un timestamp, y comparar con
            // una fecha suelta dejaría fuera todo lo cargado después de las 00:00
            // del día «hasta».
            ->when($filtros['desde'], fn ($q, $d) => $q->whereDate('fecha_pago', '>=', $d))
            ->when($filtros['hasta'], fn ($q, $h) => $q->whereDate('fecha_pago', '<=', $h))
            ->latest('fecha_pago')
            ->paginate($filtros['por_pagina'])
            ->withQueryString()
            ->through(fn (Pago $p): array => [
                'id' => $p->id,
                'nro_transaccion' => $p->nro_transaccion,
                'monto' => (float) $p->monto,
                'fecha_pago' => $p->fecha_pago?->toIso8601String(),
                'comprobante_url' => $p->comprobante_url,
                'tramite_id' => $p->tramite_id,
                'carnet_registro' => $p->tramite?->carnet?->registro(),
                'beneficiario' => $p->tramite?->carnet?->beneficiario?->nombreCompleto,
                'rubro' => $p->tramite?->rubro?->nombre,
            ]);

        return Inertia::render('panel/pagos/index', [
            'pagos' => $pagos,
            'filtros' => $filtros,
            // El total se calcula sobre TODO lo filtrado, no sobre la página que
            // se está viendo: sumar lo de la página respondería una pregunta que
            // nadie hizo.
            'total' => (float) Pago::query()
                ->when($filtros['buscar'], fn ($q, $t) => $q->where('nro_transaccion', 'like', "%{$t}%"))
                ->when($filtros['desde'], fn ($q, $d) => $q->whereDate('fecha_pago', '>=', $d))
                ->when($filtros['hasta'], fn ($q, $h) => $q->whereDate('fecha_pago', '<=', $h))
                ->sum('monto'),
            'opcionesPorPagina' => Paginacion::OPCIONES,
        ]);
    }

    /**
     * REGISTRAR UN DEPÓSITO — POST /panel/tramites/{tramite}/pagos
     *
     * El método es corto porque no decide nada: si el trámite admite pagos, si
     * el número de transacción ya existe, en qué orden se sube el archivo
     * respecto de la transacción, todo eso lo resuelve PagoTramiteService.
     */
    public function store(RegistrarPagoRequest $request, Tramite $tramite): RedirectResponse
    {
        try {
            $this->pagos->registrar(
                $tramite,
                $request->safe()->except('comprobante'),
                $request->file('comprobante'),
            );
        } catch (SolicitudInvalidaException $e) {
            // El mensaje está escrito para que lo lea el operador. Los archivos
            // no vuelven con withInput —los navegadores no dejan rellenar un
            // input file— así que hay que volver a adjuntar la boleta.
            return back()->withInput()->with('error', $e->getMessage());
        }

        $saldo = $this->pagos->saldoPendiente($tramite);

        return back()->with('exito', $saldo > 0
            // Se le dice cuánto falta en el mismo mensaje: es lo primero que
            // pregunta el pescador en el mostrador.
            ? sprintf('Pago registrado. Queda un saldo de %s.', number_format($saldo, 2, ',', '.'))
            : 'Pago registrado. El trámite quedó totalmente cubierto y ya puede aprobarse.');
    }
}
