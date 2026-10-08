<?php

namespace App\Jobs;

use App\Models\Beneficiario;
use App\Services\ConfirmarPagoService;
use App\Sireb\SirebService;
use App\Support\ExpedienteBeneficiario;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Consulta en SIREB el pago de UN trámite abierto. Lo dispara el portal cuando el
 * titular mira sus trámites, DESPUÉS de enviarle la página (`dispatchAfterResponse`):
 * no espera a SIREB y no hace falta un trabajador de cola (en Coolify corre un solo
 * contenedor). El estado se mueve sin que nadie apriete «Verificar pago».
 */
class VerificarPagoJob implements ShouldQueue
{
    use Queueable;

    /** Si SIREB no responde, lo vuelve a intentar el próximo vistazo o el comando. */
    public int $tries = 1;

    public function __construct(public string $clase, public int $id) {}

    public function handle(ConfirmarPagoService $pagos): void
    {
        $documento = $this->clase::query()->find($this->id);

        // Se vuelve a mirar: entre que se encoló y ahora pudo aprobarse o caerse. SIREB caído: no se insiste.
        if ($documento && self::corresponde($documento) && ! SirebService::sinRespuestaReciente()) {
            $pagos->verificar($documento);
        }
    }

    /** Programa la consulta de los trámites abiertos del titular; cada uno, como mucho una vez cada 2 minutos. */
    public static function encolarDe(Beneficiario $beneficiario): void
    {
        $documentos = collect()
            ->merge(ExpedienteBeneficiario::aprovechamientos($beneficiario))
            ->merge(ExpedienteBeneficiario::carnets($beneficiario))
            ->merge(ExpedienteBeneficiario::faenas($beneficiario))
            ->merge(ExpedienteBeneficiario::guias($beneficiario))
            ->filter(self::corresponde(...));

        foreach ($documentos as $documento) {
            // Compartida con el comando: el refresco de cada minuto o varias pestañas no lo consultan de más.
            if (ConfirmarPagoService::reservarConsulta($documento)) {
                self::dispatchAfterResponse($documento::class, $documento->getKey());
            }
        }
    }

    /** Abierto y con liquidación viva en SIREB. La vencida o anulada no se paga: no se consulta. */
    private static function corresponde($documento): bool
    {
        return $documento->estado->estaAbierto() && $documento->registradoEnSireb() && $documento->liquidacionCaida() === null;
    }
}
