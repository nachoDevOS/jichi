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
use Illuminate\Console\Command;

/**
 * Pregunta a SIREB por cada documento pendiente con su liquidación registrada y
 * aprueba los que ya están pagados. SIREB no avisa (no tiene webhooks): corre
 * cada 10 minutos, ver routes/console.php. En producción necesita el cron de Laravel.
 */
class VerificarPagosCommand extends Command
{
    protected $signature = 'jichi:verificar-pagos';

    protected $description = 'Aprueba los documentos pendientes que SIREB ya da por pagados';

    public function handle(ConfirmarPagoService $pagos): int
    {
        $aprobados = 0;

        $documentos = [
            AprovechamientoPesq::class => EstadoAprovechamiento::Pendiente,
            Carnet::class => EstadoCarnet::Pendiente,
            PermisoFaena::class => EstadoFaena::Pendiente,
            GuiaMovimiento::class => EstadoGuia::Pendiente,
        ];

        foreach ($documentos as $clase => $pendiente) {
            $pendientes = $clase::query()
                ->where('estado', $pendiente)
                ->where('sireb_estado', EstadoLiquidacionSireb::Registrada)
                ->get();

            foreach ($pendientes as $documento) {
                $resultado = $pagos->verificar($documento);
                $aprobados += (int) $resultado['aprobado'];
                $this->line(class_basename($clase).' #'.$documento->id.': '.$resultado['mensaje']);
            }
        }

        $this->info("Aprobados: {$aprobados}.");

        return self::SUCCESS;
    }
}
