<?php

namespace App\Http\Controllers\Panel;

use App\Enums\EstadoAprovechamiento;
use App\Enums\EstadoFaena;
use App\Enums\EstadoGuia;
use App\Enums\TipoActor;
use App\Http\Controllers\Controller;
use App\Models\AprovechamientoPesq;
use App\Models\Carnet;
use App\Models\GuiaMovimiento;
use App\Models\Pago;
use App\Models\PermisoFaena;
use App\Models\TipoCarnet;
use App\Support\Sql;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * El tablero de la gestión en curso.
 */
class DashboardController extends Controller
{
    /**
     * Con cuántos días de anticipación se avisa que algo está por caducar.
     */
    private const DIAS_AVISO = 30;

    public function __invoke(): Response
    {
        $hoy = now()->toDateString();
        $gestion = (int) now()->format('Y');

        return Inertia::render('panel/dashboard', [
            'gestion' => $gestion,
            'resumen' => fn (): array => $this->resumen($hoy, $gestion),
            'porDia' => fn (): array => $this->actividadDiaria(),
            'porTipoCarnet' => fn (): array => $this->carnetsPorTipo(),
            'porActor' => fn (): array => $this->carnetsPorActor(),
            'porMes' => fn (): array => $this->recaudacionMensual(),
            'ultimosCarnets' => fn (): array => $this->ultimosCarnets(),
            'avisos' => fn (): array => $this->avisos($gestion),
        ]);
    }

    /**
     * Los números grandes del encabezado.
     *
     * @return array<string, mixed>
     */
    private function resumen(string $hoy, int $gestion): array
    {
        return [
            'fecha' => $hoy,
            'gestion' => $gestion,

            /*
             * CUÁNTA GENTE ESTÁ HABILITADA HOY.
             */
            'carnets_vigentes' => Carnet::vigentes()->count(),

            // Emitidos en la gestión, vigentes o no. La diferencia entre los dos
            // números es la que alimenta el desglose de la tarjeta.
            'carnets_gestion' => Carnet::whereYear('fecha_emision', $gestion)->count(),

            'cupos_activos' => AprovechamientoPesq::vigentes()->count(),

            'faenas_vigentes' => PermisoFaena::vigentes()->count(),
            'guias_vigentes' => GuiaMovimiento::vigentes()->count(),

            // Lo emitido hoy, sumando los tres documentos que se entregan en
            // ventanilla. Es el pulso del día.
            'documentos_hoy' => Carnet::whereDate('fecha_emision', $hoy)->count()
                + PermisoFaena::whereDate('fecha_salida', $hoy)->count()
                + GuiaMovimiento::whereDate('fecha_emision', $hoy)->count(),

            /*
             * LA RECAUDACIÓN SALE DE `created_at`, NO DE UNA COLUMNA DE FECHA.
             */
            'recaudado_hoy' => (float) Pago::whereDate('created_at', $hoy)->sum('monto_parcial'),
            'recaudado_mes' => (float) Pago::whereBetween(
                'created_at',
                [now()->startOfMonth(), now()->endOfMonth()],
            )->sum('monto_parcial'),

            'por_cobrar' => $this->porCobrar(),
        ];
    }

    /**
     * Lo que falta cobrar, sumando los tres trámites que se cobran.
     */
    private function porCobrar(): float
    {
        $pendiente = fn ($coleccion): float => $coleccion->sum(
            fn ($tramite): float => $tramite->saldoPendiente(),
        );

        return round(
            $pendiente(Carnet::with('tipoCarnet')->withSum('pagos', 'monto_parcial')->get())
            + $pendiente(AprovechamientoPesq::with('categoria')->withSum('pagos', 'monto_parcial')->get())
            + $pendiente(GuiaMovimiento::withSum('pagos', 'monto_parcial')->get()),
            2,
        );
    }

    /**
     * Los últimos catorce días, jornada por jornada: cuántos documentos se
     * emitieron y cuánto se cobró.
     *
     * @return array<int, array<string, mixed>>
     */
    private function actividadDiaria(): array
    {
        $desde = now()->startOfDay()->subDays(13);

        $carnets = $this->porDia(Carnet::query(), 'fecha_emision', $desde);
        $faenas = $this->porDia(PermisoFaena::query(), 'fecha_salida', $desde);
        $guias = $this->porDia(GuiaMovimiento::query(), 'fecha_emision', $desde);
        $cobros = $this->porDia(Pago::query(), 'created_at', $desde, 'monto_parcial');

        // La serie se arma entera en PHP, igual que la mensual: un día sin
        // movimiento no vuelve en la consulta, y salteárselo haría que la línea
        // una el jueves con el sábado como si el viernes no hubiera existido.
        return collect(range(0, 13))
            ->map(function (int $i) use ($desde, $carnets, $faenas, $guias, $cobros): array {
                $dia = $desde->copy()->addDays($i);
                $clave = $dia->format('Y-m-d');

                return [
                    'dia' => $clave,
                    'etiqueta' => $dia->locale('es')->isoFormat('D MMM'),
                    'documentos' => (int) ($carnets[$clave] ?? 0)
                        + (int) ($faenas[$clave] ?? 0)
                        + (int) ($guias[$clave] ?? 0),
                    'recaudado' => (float) ($cobros[$clave] ?? 0),
                ];
            })
            ->all();
    }

    /**
     * Agrupa una tabla por jornada. Cuenta filas, o suma una columna si se le
     * pasa una.
     *
     * @return Collection<string, mixed>
     */
    private function porDia(
        Builder $consulta,
        string $columna,
        Carbon $desde,
        ?string $sumar = null,
    ) {
        $dia = Sql::periodoDia($columna);
        $gramatica = DB::connection()->getQueryGrammar();

        return $consulta
            ->where($columna, '>=', $desde)
            ->groupBy($dia)
            ->select(
                DB::raw($dia->getValue($gramatica).' as dia'),
                DB::raw($sumar === null ? 'COUNT(*) as valor' : "SUM($sumar) as valor"),
            )
            ->pluck('valor', 'dia');
    }

    /**
     * Cuántos carnets vigentes hay de cada tipo del catálogo.
     *
     * @return array<int, array<string, mixed>>
     */
    private function carnetsPorTipo(): array
    {
        $conteo = Carnet::query()
            ->vigentes()
            ->groupBy('tipo_carnet_id')
            ->select('tipo_carnet_id', DB::raw('COUNT(*) as cantidad'))
            ->pluck('cantidad', 'tipo_carnet_id');

        return TipoCarnet::query()
            ->orderBy('nombre')
            ->get(['id', 'nombre'])
            ->map(fn (TipoCarnet $t): array => [
                'tipo' => $t->nombre,
                'cantidad' => (int) ($conteo[$t->id] ?? 0),
            ])
            ->all();
    }

    /**
     * Pescadores contra comercializadores, entre los carnets vigentes.
     *
     * @return array<int, array<string, mixed>>
     */
    private function carnetsPorActor(): array
    {
        $conteo = Carnet::query()
            ->vigentes()
            ->groupBy('tipo_actor')
            ->select('tipo_actor', DB::raw('COUNT(*) as cantidad'))
            ->pluck('cantidad', 'tipo_actor');

        return collect(TipoActor::cases())
            ->map(fn (TipoActor $a): array => [
                'tipo' => $a->value,
                'etiqueta' => $a->etiqueta(),
                'color' => $a->color(),
                'cantidad' => (int) ($conteo[$a->value] ?? 0),
            ])
            ->all();
    }

    /**
     * Recaudación de los últimos 12 meses.
     *
     * @return array<int, array<string, mixed>>
     */
    private function recaudacionMensual(): array
    {
        $desde = now()->startOfMonth()->subMonths(11);

        // La expresión que reduce un timestamp a 'YYYY-MM' cambia entre motores.
        // Vive en App\Support\Sql para que la misma consulta corra igual en
        // PostgreSQL y en SQLite.
        $periodo = Sql::periodoMes('created_at');

        $totales = Pago::query()
            ->where('created_at', '>=', $desde)
            ->groupBy($periodo)
            ->select(
                DB::raw($periodo->getValue(DB::connection()->getQueryGrammar()).' as periodo'),
                DB::raw('SUM(monto_parcial) as total'),
            )
            ->pluck('total', 'periodo');

        // Se arma la serie de 12 meses completa en PHP y no se usa lo que
        // devolvió la base: un mes sin cobros no vuelve en la consulta, y el
        // gráfico saltaría de marzo a mayo como si abril no hubiera existido.
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
     * Las últimas credenciales emitidas.
     *
     * @return array<int, array<string, mixed>>
     */
    private function ultimosCarnets(): array
    {
        return Carnet::query()
            /*
             * with() para no caer en N+1: sin esto, diez filas serían 31
             * consultas.
             */
            ->with([
                'codigo',
                'beneficiario:id,primerNombre,segundoNombre,apellidoPaterno,apellidoMaterno,apellidoCasado',
                'asociacion:id,nombre,sigla',
                'tipoCarnet',
            ])
            ->withSum('pagos', 'monto_parcial')
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
                'estado_etiqueta' => $c->estado->etiqueta(),
                'estado_color' => $c->estado->color(),
                'monto' => $c->montoACobrar(),
                'saldo_pendiente' => $c->saldoPendiente(),
                // Es un DÍA, no un instante: va con toDateString(). Mandado como
                // instante, en UTC-4 se mostraría el día anterior.
                'fecha_emision' => $c->fecha_emision?->toDateString(),
            ])
            ->all();
    }

    /**
     * Lo que está por caducar o ya caducó sin cerrarse.
     *
     * @return array<string, mixed>
     */
    private function avisos(int $gestion): array
    {
        $hoy = now()->toDateString();
        $limite = now()->addDays(self::DIAS_AVISO)->toDateString();

        return [
            'gestion' => $gestion,
            'dias_aviso' => self::DIAS_AVISO,

            // Vigentes hoy que dejan de estarlo dentro del plazo de aviso.
            'carnets_por_vencer' => Carnet::vigentes()
                ->whereDate('fecha_vencimiento', '<=', $limite)
                ->count(),

            'cupos_por_vencer' => AprovechamientoPesq::vigentes()
                ->whereDate('fecha_vencimiento', '<=', $limite)
                ->count(),

            /*
             * Cupos SIN KILOS, que es una situación distinta de la anterior y
             * se le explica distinto al pescador, aunque los dos terminen en un
             * trámite nuevo.
             * Por eso `EstadoAprovechamiento` los separa en vez de tener un
             * único «inactivo».
             */
            'cupos_agotados' => AprovechamientoPesq::query()
                ->where('estado', EstadoAprovechamiento::Agotado)
                ->count(),

            'faenas_vencidas' => PermisoFaena::query()
                ->where('estado', EstadoFaena::Activo)
                ->whereDate('fecha_limite', '<', $hoy)
                ->count(),

            'guias_vencidas' => GuiaMovimiento::query()
                ->where('estado', EstadoGuia::Activa)
                ->where('fecha_vencimiento', '<', now())
                ->count(),
        ];
    }
}
