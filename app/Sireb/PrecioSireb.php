<?php

namespace App\Sireb;

/**
 * El precio de una tarifa de SIREB en el momento de emitir, para congelarlo en
 * el documento. Sin precio cobrable no se emite: ninguna tarifa se escribe a
 * mano. Lo usan el carnet y la guía. Ver docs/modulos/SIREB.md.
 */
class PrecioSireb
{
    /** Los servicios ya pedidos en esta petición: una guía con diez productos del mismo servicio pide uno. */
    private array $servicios = [];

    public function __construct(private SirebService $sireb) {}

    /**
     * @return array{monto: float, tarifa_id: string}
     *
     * @throws SinPrecioException
     */
    public function de(?string $servicioId, ?string $tarifaId): array
    {
        if ($servicioId === null || $tarifaId === null) {
            throw new SinPrecioException('no tiene tarifa de SIREB elegida en el catálogo.');
        }

        try {
            $servicio = $this->servicios[$servicioId] ??= $this->sireb->servicio($servicioId);
        } catch (SirebException) {
            throw new SinPrecioException('Recaudaciones (SIREB) no responde. Intente de nuevo en unos minutos.');
        }

        if ($servicio === null) {
            throw new SinPrecioException('su servicio ya no existe en SIREB.');
        }

        // SIREB manda también lo dado de baja: decidir si se cobra es de Jichi.
        if (($servicio['estado'] ?? null) !== VistaSireb::SERVICIO_ACTIVO) {
            throw new SinPrecioException('su servicio está dado de baja en SIREB.');
        }

        $tarifa = collect($servicio['tarifas'] ?? [])->firstWhere('id', $tarifaId);

        if ($tarifa === null) {
            throw new SinPrecioException('su tarifa ya no está en SIREB.');
        }

        return ['monto' => (float) $tarifa['monto'], 'tarifa_id' => $tarifa['id']];
    }
}
