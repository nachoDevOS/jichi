<?php

namespace App\Http\Controllers\Panel;

use App\Enums\EstadoAprovechamiento;
use App\Enums\EstadoCarnet;
use App\Enums\EstadoFaena;
use App\Enums\EstadoGuia;
use App\Enums\TipoActor;
use App\Http\Controllers\Controller;
use App\Models\AprovechamientoPesq;
use App\Models\Carnet;
use App\Models\GuiaMovimiento;
use App\Models\PermisoFaena;
use App\Models\Recibo;
use App\Support\Sql;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * El tablero: qué espera trabajo, cuánto hay vigente, cuánto se cobró y qué está por vencer.
 */
class DashboardController extends Controller
{
    /**
     * Con cuántos días de anticipación se avisa que algo está por caducar.
     */
    private const DIAS_AVISO = 30;

    /** Las columnas que lee `nombreCompleto` y `foto_url`: la que falte vuelve null sin avisar. */
    private const COLUMNAS_NOMBRE = 'id,primerNombre,segundoNombre,apellidoPaterno,apellidoMaterno,apellidoCasado,foto';

    public function __invoke(): Response
    {
        $gestion = (int) now()->format('Y');

        return Inertia::render('panel/dashboard', [
            'gestion' => $gestion,
            'pendientes' => fn (): array => $this->pendientes(),
            'resumen' => fn (): array => $this->resumen(),
            'porMes' => fn (): array => $this->recaudacionMensual(),
            'ultimosCarnets' => fn (): array => $this->ultimosCarnets(),
            'avisos' => fn (): array => $this->avisos(),
            'pesca' => fn (): array => $this->pesca(),
            'kilosPorMes' => fn (): array => $this->serieMensual(GuiaMovimiento::query(), 'peso_total_kg'),
            'porConcepto' => fn (): array => $this->porConcepto(),
            'casiAgotadas' => fn (): array => $this->casiAgotadas(),
            'topPescadores' => fn (): array => $this->topPescadores(),
            'destinos' => fn (): array => $this->destinos(),
            'actividad' => fn (): array => $this->actividad(),
        ]);
    }

    /**
     * Lo que espera el pago en SIREB, documento por documento.
     *
     * @return array<int, array<string, mixed>>
     */
    private function pendientes(): array
    {
        $documentos = [
            ['Autorizaciones de pesca', 'aprovechamientos', AprovechamientoPesq::query(), EstadoAprovechamiento::Pendiente],
            ['Carnets', 'carnets', Carnet::query(), EstadoCarnet::Pendiente],
            ['Permisos de faena', 'faenas', PermisoFaena::query(), EstadoFaena::Pendiente],
            ['Guías de transporte', 'guias', GuiaMovimiento::query(), EstadoGuia::Pendiente],
        ];

        return collect($documentos)
            ->map(fn (array $d): array => [
                'documento' => $d[0],
                'por_pagar' => $d[2]->where('estado', $d[3])->count(),
                'url_por_pagar' => $this->siPuedeVer($d[1], ['estado' => $d[3]->value]),
            ])
            ->all();
    }

    /**
     * Los cuatro números del tablero.
     *
     * @return array<string, mixed>
     */
    private function resumen(): array
    {
        $porActor = Carnet::query()
            ->vigentes()
            ->groupBy('tipo_actor')
            ->select('tipo_actor', DB::raw('COUNT(*) as cantidad'))
            ->pluck('cantidad', 'tipo_actor');

        return [
            'pescadores' => (int) ($porActor[TipoActor::Pescador->value] ?? 0),
            'comercializadores' => (int) ($porActor[TipoActor::Comercializador->value] ?? 0),
            'autorizaciones_vigentes' => AprovechamientoPesq::vigentes()->count(),
            'faenas_vigentes' => PermisoFaena::vigentes()->count(),
            'guias_vigentes' => GuiaMovimiento::vigentes()->count(),

            // Por los recibos: se emiten cuando SIREB confirma el pago.
            'cobrado_hoy' => (float) Recibo::whereDate('created_at', now()->toDateString())->sum('monto_total'),
            'cobrado_mes' => (float) Recibo::whereBetween(
                'created_at',
                [now()->startOfMonth(), now()->endOfMonth()],
            )->sum('monto_total'),
        ];
    }

    /**
     * Recaudación de los últimos 12 meses.
     *
     * @return array<int, array<string, mixed>>
     */
    private function recaudacionMensual(): array
    {
        return $this->serieMensual(Recibo::query(), 'monto_total');
    }

    /**
     * La suma de una columna por mes, los últimos 12, con los meses vacíos en cero.
     *
     * @return array<int, array<string, mixed>>
     */
    private function serieMensual(Builder $consulta, string $columna): array
    {
        $desde = now()->startOfMonth()->subMonths(11);

        // La expresión que reduce un timestamp a 'YYYY-MM' cambia entre motores: vive en App\Support\Sql.
        $periodo = Sql::periodoMes($consulta->qualifyColumn('created_at'));

        $totales = $consulta
            ->where($consulta->qualifyColumn('created_at'), '>=', $desde)
            ->groupBy($periodo)
            ->select(
                DB::raw($periodo->getValue(DB::connection()->getQueryGrammar()).' as periodo'),
                DB::raw("SUM({$columna}) as total"),
            )
            ->pluck('total', 'periodo');

        // La serie se arma entera en PHP: un mes sin movimiento no vuelve en la consulta y el gráfico lo saltaría.
        return collect(range(0, 11))
            ->map(function (int $i) use ($desde, $totales): array {
                $mes = $desde->copy()->addMonths($i);

                return [
                    'periodo' => $mes->format('Y-m'),
                    'etiqueta' => ucfirst($mes->locale('es')->isoFormat('MMM YY')),
                    'total' => (float) ($totales[$mes->format('Y-m')] ?? 0),
                ];
            })
            ->all();
    }

    /**
     * La pesca de las autorizaciones vigentes: lo autorizado, lo que bajaron las guías y lo que queda.
     *
     * @return array<string, float|int>
     */
    private function pesca(): array
    {
        $fila = AprovechamientoPesq::vigentes()
            ->selectRaw('COUNT(*) as cantidad, SUM(volumen_total_kg) as autorizado, SUM(kilos_disponibles) as disponible')
            ->first();
        $autorizado = (float) ($fila->autorizado ?? 0);
        $disponible = (float) ($fila->disponible ?? 0);

        return [
            'autorizaciones' => (int) ($fila->cantidad ?? 0),
            'autorizado' => $autorizado,
            'disponible' => $disponible,
            // Solo la guía descuenta: lo vendido es lo que falta del autorizado.
            'vendido' => max(0, $autorizado - $disponible),
        ];
    }

    /**
     * Lo cobrado en la gestión por trámite: de dónde sale la plata.
     *
     * @return array<int, array<string, mixed>>
     */
    private function porConcepto(): array
    {
        $totales = Recibo::query()
            ->whereBetween('created_at', [now()->startOfYear(), now()->endOfYear()])
            ->groupBy('recibible_type')
            ->select('recibible_type', DB::raw('SUM(monto_total) as total'), DB::raw('COUNT(*) as cantidad'))
            ->get()
            ->keyBy('recibible_type');

        return collect([
            ['aprovechamientos', 'Autorizaciones', AprovechamientoPesq::class],
            ['carnets', 'Carnets', Carnet::class],
            ['faenas', 'Permisos de faena', PermisoFaena::class],
            ['guias', 'Guías de transporte', GuiaMovimiento::class],
        ])
            ->map(fn (array $t): array => [
                'clave' => $t[0],
                'etiqueta' => $t[1],
                'total' => (float) ($totales[$t[2]]->total ?? 0),
                'cantidad' => (int) ($totales[$t[2]]->cantidad ?? 0),
            ])
            ->all();
    }

    /**
     * Las autorizaciones vigentes con el 20 % o menos: las próximas guías se van a frenar.
     *
     * @return array<int, array<string, mixed>>
     */
    private function casiAgotadas(): array
    {
        return AprovechamientoPesq::vigentes()
            ->with('beneficiario:'.self::COLUMNAS_NOMBRE)
            ->whereRaw('aprovechamientos_pesq.kilos_disponibles <= aprovechamientos_pesq.volumen_total_kg * 0.2')
            ->orderByRaw('aprovechamientos_pesq.kilos_disponibles / NULLIF(aprovechamientos_pesq.volumen_total_kg, 0)')
            ->limit(5)
            ->get()
            ->map(fn (AprovechamientoPesq $a): array => [
                'id' => $a->id,
                'beneficiario' => $a->beneficiario?->nombreCompleto,
                'foto' => $a->beneficiario?->foto_url,
                'disponible' => (float) $a->kilos_disponibles,
                'total' => (float) $a->volumen_total_kg,
            ])
            ->all();
    }

    /**
     * Los pescadores que más vendieron con su autorización vigente.
     *
     * @return array<int, array<string, mixed>>
     */
    private function topPescadores(): array
    {
        return AprovechamientoPesq::vigentes()
            ->with('beneficiario:'.self::COLUMNAS_NOMBRE)
            ->whereColumn('aprovechamientos_pesq.kilos_disponibles', '<', 'aprovechamientos_pesq.volumen_total_kg')
            ->orderByRaw('aprovechamientos_pesq.volumen_total_kg - aprovechamientos_pesq.kilos_disponibles DESC')
            ->limit(5)
            ->get()
            ->map(fn (AprovechamientoPesq $a): array => [
                'id' => $a->id,
                'beneficiario_id' => $a->beneficiario_id,
                'beneficiario' => $a->beneficiario?->nombreCompleto,
                'foto' => $a->beneficiario?->foto_url,
                'vendido' => (float) $a->volumen_total_kg - (float) $a->kilos_disponibles,
                'total' => (float) $a->volumen_total_kg,
            ])
            ->all();
    }

    /**
     * Adónde va el pescado: los destinos de las guías de la gestión, por kilos.
     *
     * @return array<int, array<string, mixed>>
     */
    private function destinos(): array
    {
        return GuiaMovimiento::query()
            ->whereBetween('created_at', [now()->startOfYear(), now()->endOfYear()])
            ->groupBy('destino')
            ->select('destino', DB::raw('SUM(peso_total_kg) as kilos'), DB::raw('COUNT(*) as guias'))
            ->orderByDesc('kilos')
            ->limit(5)
            ->get()
            ->map(fn ($d): array => [
                'destino' => $d->destino ?? '—',
                'kilos' => (float) $d->kilos,
                'guias' => (int) $d->guias,
            ])
            ->all();
    }

    /**
     * Lo último que se aprobó: cada recibo es un trámite que SIREB dio por pagado.
     *
     * @return array<int, array<string, mixed>>
     */
    private function actividad(): array
    {
        $claves = [
            AprovechamientoPesq::class => 'aprovechamientos',
            Carnet::class => 'carnets',
            PermisoFaena::class => 'faenas',
            GuiaMovimiento::class => 'guias',
        ];

        return Recibo::query()
            ->with('beneficiario:'.self::COLUMNAS_NOMBRE)
            ->latest('created_at')
            ->limit(7)
            ->get()
            ->map(fn (Recibo $r): array => [
                'id' => $r->id,
                'tipo' => $claves[$r->recibible_type] ?? null,
                'documento_id' => $r->recibible_id,
                'concepto' => $r->concepto,
                'beneficiario' => $r->beneficiario?->nombreCompleto,
                'monto' => (float) $r->monto_total,
                // Un MOMENTO: con hora.
                'cuando' => $r->created_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * Las últimas credenciales emitidas.
     *
     * @return array<int, array<string, mixed>>
     */
    private function ultimosCarnets(): array
    {
        return Carnet::query()
            // with() para no caer en N+1: sin esto, diez filas serían 31 consultas.
            ->with([
                'codigo',
                'beneficiario:id,primerNombre,segundoNombre,apellidoPaterno,apellidoMaterno,apellidoCasado',
                'asociacion:id,nombre,sigla',
                'aprovechamiento:id,estado',
            ])
            ->latest('fecha_emision')
            ->limit(10)
            ->get()
            ->map(fn (Carnet $c): array => [
                'id' => $c->id,
                'codigo' => $c->codigo_legible,
                'beneficiario' => $c->beneficiario?->nombreCompleto,
                'asociacion' => $c->asociacion?->sigla ?? $c->asociacion?->nombre,
                'tipo_actor' => $c->tipo_actor->value,
                'tipo_actor_etiqueta' => $c->tipo_actor->etiqueta(),
                'tipo_actor_color' => $c->tipo_actor->color(),
                'estado' => $c->estado->value,
                'estado_etiqueta' => $c->etiquetaEstado(),
                'estado_color' => $c->colorEstado(),
                'monto' => $c->montoACobrar(),
                'saldo_pendiente' => $c->porPagar(),
                // Es un DÍA, no un instante: va con toDateString() o en UTC-4 se mostraría el día anterior.
                'fecha_emision' => $c->fecha_emision?->toDateString(),
            ])
            ->all();
    }

    /**
     * Lo que vence pronto o ya venció y todavía pide una acción.
     *
     * @return array<string, mixed>
     */
    private function avisos(): array
    {
        $limite = now()->addDays(self::DIAS_AVISO)->toDateString();

        return [
            'dias_aviso' => self::DIAS_AVISO,

            'carnets_por_vencer' => Carnet::vigentes()
                ->whereDate('fecha_vencimiento', '<=', $limite)
                ->count(),

            'autorizaciones_por_vencer' => AprovechamientoPesq::vigentes()
                ->whereDate('fecha_vencimiento', '<=', $limite)
                ->count(),

            'urls' => [
                'carnets' => $this->siPuedeVer('carnets', ['estado' => EstadoCarnet::Aprobado->value]),
                'autorizaciones' => $this->siPuedeVer('aprovechamientos', ['estado' => EstadoAprovechamiento::Aprobado->value]),
            ],
        ];
    }

    /**
     * El listado del módulo, o null si el rol no lo ve: el número se muestra
     * igual, pero sin un enlace que termine en 403.
     *
     * @param  array<string, string>  $filtros
     */
    private function siPuedeVer(string $modulo, array $filtros): ?string
    {
        return auth()->user()?->can("{$modulo}.ver") ? route("{$modulo}.index", $filtros) : null;
    }
}
