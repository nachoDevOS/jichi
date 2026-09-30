<?php

namespace App\Traits;

use Illuminate\Support\Facades\Auth;

/**
 * Para los catálogos que apuntan a SIREB (`servicio_sireb` + `tarifa_sireb`):
 * al cambiar cualquiera de los dos, el par anterior se anota en
 * `sireb_historial`. El modelo declara el cast `array` y deja la columna en
 * `$noAuditable`. Ver docs/modulos/SIREB.md.
 */
trait HistorialSireb
{
    public static function bootHistorialSireb(): void
    {
        // En el modelo y no en el controlador: así vale para cualquier camino que lo edite.
        static::updating(function (self $modelo): void {
            if ($modelo->isDirty(['servicio_sireb', 'tarifa_sireb'])) {
                $modelo->anotarSirebAnterior();
            }
        });
    }

    /**
     * Agrega el servicio y la tarifa que había ANTES de este cambio. «Desde» es el
     * fin de la entrada anterior o, si es la primera, el alta de la fila.
     */
    private function anotarSirebAnterior(): void
    {
        $historial = $this->sireb_historial ?? [];
        $anterior = end($historial) ?: null;

        $historial[] = [
            'servicio_sireb' => $this->getOriginal('servicio_sireb'),
            'tarifa_sireb' => $this->getOriginal('tarifa_sireb'),
            'desde' => $anterior['hasta'] ?? $this->created_at?->toIso8601String(),
            'hasta' => now()->toIso8601String(),
            'cambiado_por' => Auth::id(),
        ];

        $this->sireb_historial = $historial;
    }
}
