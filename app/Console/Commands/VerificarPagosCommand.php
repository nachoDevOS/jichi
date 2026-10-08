<?php

namespace App\Console\Commands;

use App\Enums\EstadoAprovechamiento;
use App\Enums\EstadoCarnet;
use App\Enums\EstadoFaena;
use App\Enums\EstadoGuia;
use App\Enums\EstadoLiquidacionSireb;
use App\Models\AprovechamientoPesq;
use App\Models\Carnet;
use App\Models\GuiaMovimiento;
use App\Models\PermisoFaena;
use App\Services\ConfirmarPagoService;
use App\Sireb\SirebService;
use Illuminate\Console\Command;

/**
 * Pregunta a SIREB por cada documento pendiente con su liquidación registrada y
 * aprueba los que ya están pagados. SIREB no avisa (no tiene webhooks): corre
 * cada 10 minutos, ver routes/console.php. En producción necesita el cron de Laravel.
 */
class VerificarPagosCommand extends Command
{
    protected $signature = 'jichi:verificar-pagos {--maximo=150 : Tope de consultas a SIREB por pasada}';

    protected $description = 'Aprueba los documentos pendientes que SIREB ya da por pagados';

    /** Pasada más larga que esto se corta: la próxima arranca en 10 minutos y no deben pisarse. */
    private const MINUTOS_MAXIMOS = 8;

    public function handle(ConfirmarPagoService $pagos): int
    {
        $aprobados = 0;
        $consultados = 0;
        $maximo = max(1, (int) $this->option('maximo'));
        $inicio = now();

        $documentos = [
            AprovechamientoPesq::class => EstadoAprovechamiento::Pendiente,
            Carnet::class => EstadoCarnet::Pendiente,
            PermisoFaena::class => EstadoFaena::Pendiente,
            GuiaMovimiento::class => EstadoGuia::Pendiente,
        ];

        foreach ($documentos as $clase => $pendiente) {
            // De a 100: no se carga en memoria todo lo pendiente.
            $pendientes = $clase::query()
                ->where('estado', $pendiente)
                ->where('sireb_estado', EstadoLiquidacionSireb::Registrada)
                ->lazyById(100);

            foreach ($pendientes as $documento) {
                // SIREB caído: no se insiste, cada intento esperaría el timeout entero.
                if (SirebService::sinRespuestaReciente()) {
                    $this->warn('SIREB no responde: la pasada se corta y sigue en la próxima.');
                    break 2;
                }

                if ($consultados >= $maximo || $inicio->diffInMinutes(now()) >= self::MINUTOS_MAXIMOS) {
                    $this->warn("Tope de la pasada ({$consultados} consultas): el resto sigue en la próxima.");
                    break 2;
                }

                // Vencida o anulada ya no se paga; y lo que el portal consultó hace poco, no se repite.
                if ($documento->liquidacionCaida() !== null || ! ConfirmarPagoService::reservarConsulta($documento)) {
                    continue;
                }

                $resultado = $pagos->verificar($documento);
                $consultados++;
                $aprobados += (int) $resultado['aprobado'];
                $this->line(class_basename($clase).' #'.$documento->id.': '.$resultado['mensaje']);
            }
        }

        $this->info("Consultados: {$consultados}. Aprobados: {$aprobados}.");

        return self::SUCCESS;
    }
}
