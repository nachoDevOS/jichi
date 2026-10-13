<?php

namespace App\Services;

use App\Models\ArancelSireb;
use App\Models\ArancelTarifa;
use App\Sireb\TarifarioSireb;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Definir las tarifas de un arancel: etiqueta y tarifa de SIREB. En el carnet
 * las filas son fijas (una por clave); en los de la unidad se agregan y quitan.
 */
class DefinirTarifasService
{
    public function __construct(private readonly TarifarioSireb $tarifario) {}

    /**
     * @param  list<array{id: ?int, clave: ?string, etiqueta: string, sireb_tarifa_id: ?string}>  $filas
     */
    public function guardar(ArancelSireb $arancel, array $filas): void
    {
        if (! $arancel->usaTarifasDefinidas()) {
            throw ValidationException::withMessages(['tarifas' => 'Este arancel no lleva tarifas definidas: ventanilla elige entre todas.']);
        }

        // Solo se vinculan tarifas que el servicio tiene HOY en SIREB.
        $deSireb = collect($this->tarifario->tarifasDelServicio($arancel->servicio_sireb) ?? [])->keyBy('id');

        if ($deSireb->isEmpty()) {
            throw ValidationException::withMessages(['tarifas' => 'Recaudaciones (SIREB) no responde o el servicio no tiene tarifas: no se puede definir ahora.']);
        }

        $fijas = $arancel->clavesFijas();

        foreach ($filas as $i => $fila) {
            if ($fila['sireb_tarifa_id'] !== null && ! $deSireb->has($fila['sireb_tarifa_id'])) {
                throw ValidationException::withMessages(["tarifas.{$i}.sireb_tarifa_id" => 'Esa tarifa no es del servicio de este arancel.']);
            }

            // En los de la unidad una fila sin tarifa no dice nada: se quita con el tacho.
            if ($fijas === null && $fila['sireb_tarifa_id'] === null) {
                throw ValidationException::withMessages(["tarifas.{$i}.sireb_tarifa_id" => 'Elija la tarifa de SIREB.']);
            }
        }

        DB::transaction(fn () => $fijas !== null
            ? $this->guardarFijas($arancel, $fijas, $filas)
            : $this->guardarLibres($arancel, $filas));
    }

    /** El carnet: una fila por clave del código; sin tarifa, la clave queda sin definir. */
    private function guardarFijas(ArancelSireb $arancel, array $fijas, array $filas): void
    {
        $porClave = collect($filas)->keyBy('clave');

        foreach (array_keys($fijas) as $clave) {
            $fila = $porClave->get($clave);
            $actual = $arancel->tarifasDefinidas()->where('clave', $clave)->first();

            if ($fila === null || $fila['sireb_tarifa_id'] === null) {
                if ($actual !== null) {
                    $actual->motivoAuditoria = 'Se quitó la tarifa definida.';
                    $actual->delete();
                }

                continue;
            }

            $datos = ['etiqueta' => $fila['etiqueta'], 'sireb_tarifa_id' => $fila['sireb_tarifa_id']];
            $actual !== null
                ? $actual->update($datos)
                : ArancelTarifa::create(['arancel_id' => $arancel->id, 'clave' => $clave, ...$datos]);
        }
    }

    /** Los de la unidad: se actualizan por id, se crean las nuevas y se dan de baja las que no vinieron. */
    private function guardarLibres(ArancelSireb $arancel, array $filas): void
    {
        $existentes = $arancel->tarifasDefinidas()->get()->keyBy('id');
        $vinieron = collect($filas)->pluck('id')->filter()->all();

        // Una por una y no un delete masivo: así cada baja queda en la auditoría.
        foreach ($existentes as $id => $vieja) {
            if (! in_array($id, $vinieron, true)) {
                $vieja->motivoAuditoria = 'Se quitó la tarifa definida.';
                $vieja->delete();
            }
        }

        foreach ($filas as $fila) {
            $datos = ['etiqueta' => $fila['etiqueta'], 'sireb_tarifa_id' => $fila['sireb_tarifa_id']];
            $existente = $fila['id'] !== null ? $existentes->get($fila['id']) : null;

            $existente !== null
                ? $existente->update($datos)
                : ArancelTarifa::create(['arancel_id' => $arancel->id, ...$datos]);
        }
    }
}
