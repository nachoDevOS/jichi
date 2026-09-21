<?php

namespace App\Http\Controllers\Panel;

use App\Exceptions\CobroInvalidoException;
use App\Http\Controllers\Controller;
use App\Http\Controllers\StorageController;
use App\Http\Requests\Panel\CobrarRequest;
use App\Models\AprovechamientoPesq;
use App\Models\Beneficiario;
use App\Models\Carnet;
use App\Models\GuiaMovimiento;
use App\Models\Pago;
use App\Services\CobrarService;
use App\Support\Archivos;
use App\Support\Paginacion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 *  CAJA — el circuito del dinero
 */
class CajaController extends Controller
{
    public function __construct(private readonly CobrarService $servicio) {}

    /**
     * LISTADO DE ABONOS — GET /panel/caja
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
            /*
             * UNA RELACIÓN POLIMÓRFICA NO SE PRECARGA CON `with('pagable.x')`.
             */
            ->with([
                'recibo:id,numero_recibo,beneficiario_id',
                'recibo.beneficiario:id,primerNombre,segundoNombre,apellidoPaterno,apellidoMaterno,apellidoCasado',
                'pagable' => fn ($m) => $m->morphWith([
                    Carnet::class => ['beneficiario:id,ci,complemento,departamento_id,primerNombre,segundoNombre,apellidoPaterno,apellidoMaterno,apellidoCasado'],
                    AprovechamientoPesq::class => ['beneficiario:id,ci,complemento,departamento_id,primerNombre,segundoNombre,apellidoPaterno,apellidoMaterno,apellidoCasado', 'categoria'],
                    GuiaMovimiento::class => ['carnet.beneficiario:id,ci,complemento,departamento_id,primerNombre,segundoNombre,apellidoPaterno,apellidoMaterno,apellidoCasado'],
                ]),
            ])
            // El nombre ya no está copiado en el recibo: se busca sobre su
            // beneficiario, con el scope del padrón.
            ->when($filtros['buscar'], fn ($q, $termino) => $q->whereHas(
                'recibo',
                fn ($r) => $r->where('numero_recibo', 'like', '%'.mb_strtoupper($termino).'%')
                    ->orWhereHas('beneficiario', fn ($b) => $b->buscar($termino)),
            ))
            /*
             * EL RANGO SE FILTRA POR `created_at`, que es cuando entró la plata.
             * `pagos` no tiene columna de fecha propia a propósito: una segunda
             * fecha solo agregaría la posibilidad de que las dos se
             * contradigan.
             */
            ->when($filtros['desde'], fn ($q, $d) => $q->whereDate('pagos.created_at', '>=', $d))
            ->when($filtros['hasta'], fn ($q, $h) => $q->whereDate('pagos.created_at', '<=', $h))
            ->latest('pagos.created_at')
            ->paginate($filtros['por_pagina'])
            ->withQueryString()
            ->through(fn (Pago $p): array => [
                'id' => $p->id,
                'recibo_id' => $p->recibo_id,
                'numero_recibo' => $p->recibo?->numero_recibo,
                'a_nombre_de' => $p->recibo?->beneficiario?->nombreCompleto,
                'concepto' => $p->concepto_detalle,
                'titular' => $this->titularDe($p),
                'monto_parcial' => (float) $p->monto_parcial,
                // La boleta del banco, para poder abrirla desde el arqueo sin
                // entrar a cada recibo.
                'nro_transaccion' => $p->nro_transaccion,
                'comprobante_url' => $p->comprobante_url,
                // Un DÍA —lo que dice la boleta—: va con toDateString().
                'fecha_deposito' => $p->fecha_deposito?->toDateString(),
                // Es un MOMENTO: cuándo entró el dinero. Va con toIso8601String().
                'cobrado_en' => $p->created_at?->toIso8601String(),
            ]);

        return Inertia::render('panel/caja/index', [
            'pagos' => $pagos,
            'filtros' => $filtros,
            'opcionesPorPagina' => Paginacion::OPCIONES,

            /*
             * EL ARQUEO DEL DÍA, siempre del día de HOY y no del rango filtrado.
             */
            'arqueo' => $this->arqueoDelDia(),
        ]);
    }

    /**
     * FORMULARIO DE COBRO — GET /panel/caja/cobrar
     *
     * Acepta `?beneficiario=7` para llegar desde la ficha de la persona.
     */
    public function create(Request $request): Response
    {
        $beneficiario = $request->integer('beneficiario')
            ? Beneficiario::query()->find($request->integer('beneficiario'))
            : null;

        return Inertia::render('panel/caja/cobrar', [
            'beneficiario' => $beneficiario ? [
                'id' => $beneficiario->id,
                'nombreCompleto' => $beneficiario->nombreCompleto,
                'documento_identidad' => $beneficiario->documento_identidad,
                'foto_url' => $beneficiario->foto_url,
                'carnets_vigentes' => [],
            ] : null,

            'deudas' => $beneficiario ? $this->deudasDe($beneficiario) : [],
        ]);
    }

    /**
     * COBRAR — POST /panel/caja
     */
    public function store(CobrarRequest $request): RedirectResponse
    {
        $datos = $request->validated();

        /*
         *  LA BOLETA SE SUBE ANTES DE ABRIR LA TRANSACCIÓN
         */
        $comprobante = app(StorageController::class)->file($request->file('comprobante'), 'comprobantes');

        try {
            $recibo = $this->servicio->cobrar(
                $datos['lineas'],
                $datos['nro_transaccion'],
                $datos['fecha_deposito'],
                $comprobante,
                $datos['concepto'] ?? null,
            );
        } catch (CobroInvalidoException $e) {
            Archivos::borrar($comprobante);

            // El mensaje se cuelga de `lineas`: todas las reglas que puede
            // romper son sobre qué se está cobrando y por cuánto.
            return back()->withInput()->withErrors(['lineas' => $e->getMessage()]);
        } catch (\Throwable $e) {
            // Cualquier otra cosa —un choque contra el índice de la boleta, por
            // ejemplo— tampoco puede dejar el archivo tirado.
            Archivos::borrar($comprobante);

            throw $e;
        }

        return redirect()
            ->route('recibos.show', $recibo)
            ->with('exito', "Recibo {$recibo->numero_recibo} emitido. Ya se puede imprimir.");
    }

    //  Auxiliares

    /**
     * Todo lo que esta persona debe hoy, listo para cobrar.
     *
     * @return array<int, array<string, mixed>>
     */
    private function deudasDe(Beneficiario $beneficiario): array
    {
        $deudas = [];

        foreach ($beneficiario->carnets()->with('tipoCarnet')->withSum('pagos', 'monto_parcial')->get() as $c) {
            if ($c->saldoPendiente() > 0) {
                $deudas[] = $this->linea('carnet', $c->id, 'Carnet '.$c->codigo_legible,
                    $c->tipoCarnet?->nombre ?? $c->tipo_actor->etiqueta(), $c->montoACobrar(), $c->saldoPendiente());
            }
        }

        foreach ($beneficiario->aprovechamientos()->with('categoria')->withSum('pagos', 'monto_parcial')->get() as $a) {
            if ($a->saldoPendiente() > 0) {
                $deudas[] = $this->linea('cupo', $a->id, 'Aprovechamiento escala '.($a->categoria?->nro_escala ?? '—'),
                    $a->categoria?->descripcion_kg ?? '', $a->montoACobrar(), $a->saldoPendiente());
            }
        }

        foreach ($beneficiario->guias()->withSum('pagos', 'monto_parcial')->get() as $g) {
            // Una guía anulada no admite cobros, así que ni se ofrece: lo que se
            // deba de un papel que no vale se resuelve por caja.
            if ($g->saldoPendiente() > 0 && $g->estado->admitePagos()) {
                $deudas[] = $this->linea('guia', $g->id, 'Guía '.$g->codigo_guia,
                    $g->ruta, $g->montoACobrar(), $g->saldoPendiente());
            }
        }

        foreach ($beneficiario->faenas()->withSum('pagos', 'monto_parcial')->get() as $f) {
            // Solo la que todavía admite depósitos: una faena firmada ya está
            // cobrada por definición.
            if ($f->saldoPendiente() > 0 && $f->admitePagos()) {
                $deudas[] = $this->linea('faena', $f->id, $f->etiqueta,
                    $f->kilos_extraidos.' kg', $f->montoACobrar(), $f->saldoPendiente());
            }
        }

        return $deudas;
    }

    /**
     * @return array<string, mixed>
     */
    private function linea(string $tipo, int $id, string $titulo, string $detalle, float $monto, float $saldo): array
    {
        return [
            'tipo' => $tipo,
            'id' => $id,
            'titulo' => $titulo,
            'detalle' => $detalle,
            'monto' => $monto,
            'saldo' => $saldo,
            // Lo ya abonado, para que se vea que es una cuota y no el total.
            'pagado' => round($monto - $saldo, 2),
        ];
    }

    /**
     * El arqueo del día, en DOS números que no son la misma pregunta.
     *
     * @return array<string, mixed>
     */
    private function arqueoDelDia(): array
    {
        $hoy = now()->toDateString();

        /*
         * YA NO SE REPARTE POR MÉTODO: todo pago es un depósito bancario, así
         * que el reparto tenía una sola columna. Lo que sí hace falta son las
         * DOS fechas, porque no son la misma pregunta:
         */
        $cargadoHoy = Pago::query()->whereDate('created_at', $hoy);
        $depositadoHoy = Pago::query()->whereDate('fecha_deposito', $hoy);

        return [
            'fecha' => $hoy,
            'total' => (float) (clone $cargadoHoy)->sum('monto_parcial'),
            'cantidad' => (int) (clone $cargadoHoy)->count(),
            'total_depositado' => (float) (clone $depositadoHoy)->sum('monto_parcial'),
            'cantidad_depositada' => (int) (clone $depositadoHoy)->count(),
        ];
    }

    /** De quién es el trámite que este abono paga. */
    private function titularDe(Pago $pago): ?string
    {
        $pagable = $pago->pagable;

        return match (true) {
            $pagable instanceof Carnet, $pagable instanceof AprovechamientoPesq => $pagable->beneficiario?->nombreCompleto,
            $pagable instanceof GuiaMovimiento => $pagable->comercializador()?->nombreCompleto,
            default => null,
        };
    }
}
