<?php

namespace App\Support;

use App\Models\AprovechamientoPesq;
use App\Models\Beneficiario;
use App\Models\Carnet;
use App\Models\GuiaMovimiento;
use App\Models\PermisoFaena;
use App\Models\Recibo;
use Illuminate\Database\Eloquent\Collection;

/**
 * Las consultas del expediente de una persona, compartidas por la ficha del
 * panel y el portal del beneficiario. Lo delicado son los `with()`: escritos dos
 * veces, el día que se arregla un N+1 o una columna faltante uno queda viejo.
 * Cada lado arma su propia respuesta con lo que devuelven.
 */
class ExpedienteBeneficiario
{
    /** @return Collection<int, Carnet> */
    public static function carnets(Beneficiario $beneficiario): Collection
    {
        return $beneficiario->carnets()
            // El cupo con su saldo precargado: `motivoSinPermisos()` y
            // `puedeEmitirFaenas()` lo leen, y sin esto son dos consultas por carnet.
            ->with([
                'codigo',
                'asociacion:id,nombre,sigla',
                'tipoCarnet',
                'aprovechamiento' => fn ($a) => $a
                    ->with('categoria')
                    ->withSum('faenasQueConsumen', 'kilos_extraidos')
                    ->withSum('faenasQueReservan', 'kilos_extraidos'),
            ])
            ->withSum('pagos', 'monto_parcial')
            // Por id y no por emisión: la emisión es NULL hasta la firma, y
            // cada motor ordena los NULL en otra punta.
            ->orderByDesc('id')
            ->get();
    }

    /** @return Collection<int, PermisoFaena> */
    public static function faenas(Beneficiario $beneficiario): Collection
    {
        return $beneficiario->faenas()
            ->with('carnet.aprovechamiento:id,estado')
            ->withSum('pagos', 'monto_parcial')
            ->orderByDesc('permisos_faena.numero_faena')
            ->get();
    }

    /** @return Collection<int, GuiaMovimiento> */
    public static function guias(Beneficiario $beneficiario): Collection
    {
        return $beneficiario->guias()
            ->withSum('pagos', 'monto_parcial')
            ->orderByDesc('guias_movimiento.numero_guia')
            ->get();
    }

    /** Los comprobantes que se llevó, del más nuevo al más viejo. @return Collection<int, Recibo> */
    public static function recibos(Beneficiario $beneficiario): Collection
    {
        return Recibo::query()
            ->where('beneficiario_id', $beneficiario->id)
            ->withCount('pagos')
            ->orderByDesc('id')
            ->get();
    }

    /** @return Collection<int, AprovechamientoPesq> */
    public static function aprovechamientos(Beneficiario $beneficiario): Collection
    {
        return $beneficiario->aprovechamientos()
            ->with('categoria')
            ->withSum('faenasQueConsumen', 'kilos_extraidos')
            ->withSum('faenasQueReservan', 'kilos_extraidos')
            ->withSum('pagos', 'monto_parcial')
            // Por la SOLICITUD: la emisión está en NULL hasta la firma.
            ->orderByDesc('fecha_solicitud')
            ->get();
    }
}
