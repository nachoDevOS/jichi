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
use App\Models\Pago;
use App\Models\PermisoFaena;
use App\Support\Sql;
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
        ]);
    }

    /**
     * Lo que espera a alguien, documento por documento: borradores sin enviar y presentados sin firmar.
     *
     * @return array<int, array<string, mixed>>
     */
    private function pendientes(): array
    {
        $documentos = [
            ['Autorizaciones de pesca', 'aprovechamientos.index', AprovechamientoPesq::query(), EstadoAprovechamiento::Pendiente, EstadoAprovechamiento::EnRevision],
            ['Carnets', 'carnets.index', Carnet::query(), EstadoCarnet::Pendiente, EstadoCarnet::EnRevision],
            ['Permisos de faena', 'faenas.index', PermisoFaena::query(), EstadoFaena::Pendiente, EstadoFaena::EnRevision],
            ['Guías de transporte', 'guias.index', GuiaMovimiento::query(), EstadoGuia::Pendiente, EstadoGuia::EnRevision],
        ];

        return collect($documentos)
            ->map(function (array $d): array {
                [$nombre, $ruta, $consulta, $pendiente, $enRevision] = $d;

                // Una sola consulta por tabla: agrupa los dos estados de una vez.
                $conteo = $consulta
                    ->whereIn('estado', [$pendiente, $enRevision])
                    ->groupBy('estado')
                    ->select('estado', DB::raw('COUNT(*) as cantidad'))
                    ->pluck('cantidad', 'estado');

                return [
                    'documento' => $nombre,
                    'por_enviar' => (int) ($conteo[$pendiente->value] ?? 0),
                    'por_firmar' => (int) ($conteo[$enRevision->value] ?? 0),
                    'url_por_enviar' => route($ruta, ['estado' => $pendiente->value]),
                    'url_por_firmar' => route($ruta, ['estado' => $enRevision->value]),
                ];
            })
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

            // Por `created_at`: es cuando entró la plata, no la fecha que dice la boleta.
            'cobrado_hoy' => (float) Pago::whereDate('created_at', now()->toDateString())->sum('monto_parcial'),
            'cobrado_mes' => (float) Pago::whereBetween(
                'created_at',
                [now()->startOfMonth(), now()->endOfMonth()],
            )->sum('monto_parcial'),
        ];
    }

    /**
     * Recaudación de los últimos 12 meses.
     *
     * @return array<int, array<string, mixed>>
     */
    private function recaudacionMensual(): array
    {
        $desde = now()->startOfMonth()->subMonths(11);

        // La expresión que reduce un timestamp a 'YYYY-MM' cambia entre motores: vive en App\Support\Sql.
        $periodo = Sql::periodoMes('created_at');

        $totales = Pago::query()
            ->where('created_at', '>=', $desde)
            ->groupBy($periodo)
            ->select(
                DB::raw($periodo->getValue(DB::connection()->getQueryGrammar()).' as periodo'),
                DB::raw('SUM(monto_parcial) as total'),
            )
            ->pluck('total', 'periodo');

        // La serie se arma entera en PHP: un mes sin cobros no vuelve en la consulta y el gráfico lo saltaría.
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
            // with() para no caer en N+1: sin esto, diez filas serían 31 consultas.
            ->with([
                'codigo',
                'beneficiario:id,primerNombre,segundoNombre,apellidoPaterno,apellidoMaterno,apellidoCasado',
                'asociacion:id,nombre,sigla',
                'tipoCarnet',
                'aprovechamiento:id,estado',
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
                'estado_etiqueta' => $c->etiquetaEstado(),
                'estado_color' => $c->colorEstado(),
                'monto' => $c->montoACobrar(),
                'saldo_pendiente' => $c->saldoPendiente(),
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

            // Sin kilos aunque la fecha no haya llegado: el pescador necesita una autorización nueva.
            'autorizaciones_agotadas' => AprovechamientoPesq::query()
                ->where('estado', EstadoAprovechamiento::Agotado)
                ->count(),

            'urls' => [
                'carnets' => route('carnets.index', ['estado' => EstadoCarnet::Aprobado->value]),
                'autorizaciones' => route('aprovechamientos.index', ['estado' => EstadoAprovechamiento::Aprobado->value]),
                'agotadas' => route('aprovechamientos.index', ['estado' => EstadoAprovechamiento::Agotado->value]),
            ],
        ];
    }
}
