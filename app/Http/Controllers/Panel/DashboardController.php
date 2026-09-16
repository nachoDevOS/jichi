<?php

namespace App\Http\Controllers\Panel;

use App\Enums\EstadoTramite;
use App\Enums\TipoTramite;
use App\Http\Controllers\Controller;
use App\Models\Carnet;
use App\Models\Pago;
use App\Models\Rubro;
use App\Models\Tramite;
use App\Support\Sql;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * El tablero de la gestión en curso.
 *
 * ----------------------------------------------------------------------------
 *  POR QUÉ CADA BLOQUE VA ENVUELTO EN UN fn()
 * ----------------------------------------------------------------------------
 *
 * Inertia evalúa las closures solo cuando la prop se va a enviar de verdad. En
 * una visita parcial —cuando la pantalla pide refrescar únicamente el gráfico
 * de recaudación, por ejemplo— las demás no se ejecutan, y esas consultas
 * agregadas no se corren al pedo. Pasadas como valores sueltos se calcularían
 * las seis en cada refresco.
 */
class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $hoy = now()->toDateString();
        $gestion = (int) now()->format('Y');

        return Inertia::render('panel/dashboard', [
            'gestion' => $gestion,
            'resumen' => fn (): array => $this->resumenDelDia($hoy, $gestion),
            'porDia' => fn (): array => $this->actividadDiaria(),
            'porRubro' => fn (): array => $this->carnetsPorRubro($gestion),
            'porMes' => fn (): array => $this->recaudacionMensual(),
            'porTipo' => fn (): array => $this->tramitesPorTipo($gestion),
            'ultimosTramites' => fn (): array => $this->ultimosTramites(),
            'porVencer' => fn (): array => $this->carnetsPorVencer($gestion),
        ]);
    }

    /**
     * Los números de arriba del tablero.
     *
     * @return array<string, mixed>
     */
    private function resumenDelDia(string $hoy, int $gestion): array
    {
        return [
            'fecha' => $hoy,
            'gestion' => $gestion,

            'tramites_hoy' => Tramite::whereDate('fecha_solicitud', $hoy)->count(),

            /*
             * EL TRABAJO SIN TERMINAR.
             *
             * Se cuenta con `abiertos()` y no con `pendientes()`: un expediente
             * ya enviado a revisión sigue sin resolverse, y dejarlo
             * fuera del número haría que el tablero mostrara menos trabajo del
             * que hay justo cuando el equipo empieza a atenderlo.
             *
             * Y se cuenta de TODAS las gestiones: un expediente del año pasado
             * sin resolver sigue siendo trabajo.
             */
            'tramites_abiertos' => Tramite::abiertos()->count(),

            'tramites_en_revision' => Tramite::enRevision()->count(),

            /*
             * De los abiertos, cuántos ya están cobrados y solo esperan la
             * firma. Es la diferencia entre «hay 40 sin resolver» y «hay 12
             * listos para aprobar», que es lo accionable.
             *
             * EL withSum NO ES OPCIONAL ACÁ. Sin él, `estaPagado()` cae en
             * `$this->pagos()->sum('monto')` y dispara UNA CONSULTA POR FILA:
             * con 40 expedientes abiertos son 41 consultas, en la pantalla a la
             * que cae todo el mundo al entrar. Con él, la suma viene resuelta en
             * la misma consulta y `Tramite::montoPagado()` la reusa —por eso ese
             * método pregunta primero por `pagos_sum_monto`—.
             *
             * El filtro se hace en PHP y no en SQL a propósito: comparar la suma
             * contra `monto_requerido` dentro de la consulta exigiría un HAVING
             * sobre un agregado, y eso se escribe distinto en PostgreSQL que en
             * SQLite. La regla de qué cuenta como pagado vive en el modelo, en un
             * solo lugar. Ver `Tramite::estaPagado()`.
             */
            'listos_para_aprobar' => Tramite::abiertos()
                ->withSum('pagos', 'monto')
                ->get()
                ->filter(fn (Tramite $t): bool => $t->estaPagado())
                ->count(),

            'carnets_gestion' => Carnet::deGestion($gestion)->count(),
            'carnets_vigentes' => Carnet::deGestion($gestion)->vigentes()->count(),

            'recaudado_hoy' => (float) Pago::whereDate('fecha_pago', $hoy)->sum('monto'),
            'recaudado_mes' => (float) Pago::whereBetween(
                'fecha_pago',
                [now()->startOfMonth(), now()->endOfMonth()],
            )->sum('monto'),
        ];
    }

    /**
     * Los últimos catorce días, jornada por jornada: cuántos expedientes
     * entraron y cuánto se cobró.
     *
     * ------------------------------------------------------------------------
     *  PARA QUÉ, SI LOS NÚMEROS YA ESTÁN ARRIBA
     * ------------------------------------------------------------------------
     *
     * Alimenta las líneas chicas que van al pie de los indicadores. No son
     * adorno: un número solo —«0 trámites hoy»— no dice si eso es lo normal de
     * un martes o si la ventanilla se paró. La línea de atrás lo pone en
     * contexto sin gastar una tarjeta entera en un gráfico aparte.
     *
     * CATORCE DÍAS, no treinta: el dibujo mide unos 60 px de alto y ahí adentro
     * treinta puntos se pisan entre sí y quedan como una mancha. Dos semanas
     * alcanzan para ver el ritmo y para que se distinga un lunes de un sábado.
     *
     * @return array<int, array<string, mixed>>
     */
    private function actividadDiaria(): array
    {
        $desde = now()->startOfDay()->subDays(13);
        $gramatica = DB::connection()->getQueryGrammar();

        // Reducir un timestamp al día se escribe distinto en cada motor; la
        // expresión vive en App\Support\Sql por la misma razón que periodoMes.
        $diaTramite = Sql::periodoDia('fecha_solicitud');
        $diaPago = Sql::periodoDia('fecha_pago');

        $tramites = Tramite::query()
            ->where('fecha_solicitud', '>=', $desde)
            ->groupBy($diaTramite)
            ->select(
                DB::raw($diaTramite->getValue($gramatica).' as dia'),
                DB::raw('COUNT(*) as cantidad'),
            )
            ->pluck('cantidad', 'dia');

        $cobros = Pago::query()
            ->where('fecha_pago', '>=', $desde)
            ->groupBy($diaPago)
            ->select(
                DB::raw($diaPago->getValue($gramatica).' as dia'),
                DB::raw('SUM(monto) as total'),
            )
            ->pluck('total', 'dia');

        // La serie se arma entera en PHP, igual que la mensual: un día sin
        // movimiento no vuelve en la consulta, y salteárselo haría que la línea
        // una el jueves con el sábado como si el viernes no hubiera existido.
        return collect(range(0, 13))
            ->map(function (int $i) use ($desde, $tramites, $cobros): array {
                $dia = $desde->copy()->addDays($i);
                $clave = $dia->format('Y-m-d');

                return [
                    'dia' => $clave,
                    'etiqueta' => $dia->locale('es')->isoFormat('D MMM'),
                    'tramites' => (int) ($tramites[$clave] ?? 0),
                    'recaudado' => (float) ($cobros[$clave] ?? 0),
                ];
            })
            ->all();
    }

    /**
     * Cuántos carnets vigentes hay de cada actividad en la gestión.
     *
     * @return array<int, array<string, mixed>>
     */
    private function carnetsPorRubro(int $gestion): array
    {
        /*
         * LA CONSULTA SE SIMPLIFICÓ AL DESAPARECER EL PIVOTE.
         *
         * Antes había que cruzar `carnet_rubro` con `carnets` para saber a qué
         * gestión pertenecía cada habilitación. Hoy el rubro está en la propia
         * fila del carnet, así que es un group by sobre una sola tabla.
         *
         * Se cuentan solo los VIGENTES —y no todos los emitidos— porque la
         * pregunta del tablero es «cuánta gente está habilitada hoy en cada
         * actividad», y un carnet suspendido o anulado no habilita a nadie. Es
         * lo mismo que hacía el filtro `estado = habilitado` de la versión
         * anterior, expresado sobre el estado del carnet.
         */
        $conteo = Carnet::query()
            ->deGestion($gestion)
            ->vigentes()
            ->groupBy('rubro_id')
            ->select('rubro_id', DB::raw('COUNT(*) as cantidad'))
            ->pluck('cantidad', 'rubro_id');

        // Se recorre el catálogo entero y no solo lo que devolvió la consulta:
        // un rubro con cero carnets también es información —dice que nadie lo
        // pide— y si no aparece, el gráfico miente por omisión.
        return Rubro::query()
            ->orderBy('nombre')
            ->get(['id', 'nombre'])
            ->map(fn (Rubro $r): array => [
                'rubro' => $r->nombre,
                'cantidad' => (int) ($conteo[$r->id] ?? 0),
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
        $periodo = Sql::periodoMes('fecha_pago');

        $totales = Pago::query()
            ->where('fecha_pago', '>=', $desde)
            ->groupBy($periodo)
            ->select(
                DB::raw($periodo->getValue(DB::connection()->getQueryGrammar()).' as periodo'),
                DB::raw('SUM(monto) as total'),
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
     * Emisiones iniciales contra adiciones de rubro, en la gestión.
     *
     * Es el número que muestra cómo está funcionando la Regla A: cuánta gente
     * saca carnet por primera vez este año y cuánta ya lo tenía y vuelve a
     * sumar actividades.
     *
     * @return array<int, array<string, mixed>>
     */
    private function tramitesPorTipo(int $gestion): array
    {
        $conteo = Tramite::query()
            ->deGestion($gestion)
            ->groupBy('tipo_tramite')
            ->select('tipo_tramite', DB::raw('COUNT(*) as cantidad'))
            ->pluck('cantidad', 'tipo_tramite');

        return collect(TipoTramite::cases())
            ->map(fn ($tipo): array => [
                'tipo' => $tipo->value,
                'etiqueta' => $tipo->etiqueta(),
                'color' => $tipo->color(),
                'cantidad' => (int) ($conteo[$tipo->value] ?? 0),
            ])
            ->all();
    }

    /**
     * Los últimos expedientes que entraron.
     *
     * @return array<int, array<string, mixed>>
     */
    private function ultimosTramites(): array
    {
        return Tramite::query()
            // with() para no caer en N+1: sin esto, diez filas serían 31
            // consultas.
            ->with([
                'rubro:id,nombre',
                'carnet:id,beneficiario_id',
                'carnet.beneficiario:id,primerNombre,segundoNombre,apellidoPaterno,apellidoMaterno,apellidoCasado',
            ])
            ->withSum('pagos', 'monto')
            ->latest('fecha_solicitud')
            ->limit(10)
            ->get()
            ->map(fn (Tramite $t): array => [
                'id' => $t->id,
                'carnet_registro' => $t->carnet?->registro(),
                'beneficiario' => $t->carnet?->beneficiario?->nombreCompleto,
                'rubro' => $t->rubro?->nombre,
                'tipo_etiqueta' => $t->tipo_tramite->etiqueta(),
                'tipo_color' => $t->tipo_tramite->color(),
                'estado' => $t->estado->value,
                'estado_etiqueta' => $t->estado->etiqueta(),
                'estado_color' => $t->estado->color(),
                'monto_requerido' => (float) $t->monto_requerido,
                'saldo_pendiente' => $t->saldoPendiente(),
                'fecha_solicitud' => $t->fecha_solicitud?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * El aviso de fin de gestión.
     *
     * TODOS los carnets vigentes vencen el mismo día —el 31 de diciembre—, así
     * que este bloque no es una lista de casos sueltos como en un sistema de
     * vencimientos escalonados: es el recordatorio de cuánta gente va a tener
     * que renovar de golpe, y con cuánto tiempo.
     *
     * @return array<string, mixed>
     */
    private function carnetsPorVencer(int $gestion): array
    {
        $vencimiento = Carnet::vencimientoDeGestion($gestion);

        return [
            'fecha_vencimiento' => $vencimiento->toDateString(),
            'dias_restantes' => max(0, (int) now()->startOfDay()->diffInDays($vencimiento, false)),
            'cantidad' => Carnet::deGestion($gestion)->vigentes()->count(),

            // Expedientes aprobados cuyo carnet todavía no se imprimió: es
            // trabajo pendiente de ventanilla, no del supervisor.
            'sin_imprimir' => Tramite::query()
                ->deGestion($gestion)
                ->where('tramites.estado', EstadoTramite::Aprobado)
                ->whereNull('fecha_generacion')
                ->count(),

            // Impresos y sin entregar: carnets en el cajón esperando que el
            // titular pase a retirarlos.
            'sin_entregar' => Tramite::query()
                ->deGestion($gestion)
                ->where('tramites.estado', EstadoTramite::Aprobado)
                ->whereNotNull('fecha_generacion')
                ->whereNull('fecha_entrega')
                ->count(),
        ];
    }
}
