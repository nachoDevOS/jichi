<?php

namespace App\Sireb;

/**
 * El precio de una tarifa de SIREB en el momento de emitir, para congelarlo en
 * el documento. Sin tarifa y servicio activos no se emite: ninguna tarifa se
 * escribe a mano. Lo usan los cuatro documentos. Ver docs/modulos/SIREB.md.
 */
class PrecioSireb
{
    /** Las tarifas ya pedidas en esta petición: una guía con el mismo producto dos veces pide una. */
    private array $tarifas = [];

    public function __construct(private SirebService $sireb) {}

    /**
     * `$exigirLiquidable`: además de activa, que SIREB la dé hoy por cobrable
     * (tarifario vigente). Por ahora solo lo pide la autorización.
     *
     * @return array{monto: float, tarifa_id: string}
     *
     * @throws SinPrecioException
     */
    public function de(?string $servicioId, ?string $tarifaId, bool $exigirLiquidable = false): array
    {
        if ($servicioId === null || $tarifaId === null) {
            throw new SinPrecioException('no tiene tarifa de SIREB elegida en el catálogo.');
        }

        try {
            $tarifa = $this->tarifas[$servicioId.'|'.$tarifaId] ??= $this->sireb->tarifa($servicioId, $tarifaId);
        } catch (SirebException) {
            $e = new SinPrecioException('Recaudaciones (SIREB) no responde. Intente de nuevo en unos minutos.');
            $e->sinRespuesta = true;

            throw $e;
        }

        // El 404 no distingue si falta el servicio o la tarifa dentro de él.
        if ($tarifa === null) {
            throw new SinPrecioException('su tarifa ya no está en SIREB.');
        }

        // SIREB manda también lo dado de baja: decidir si se cobra es de Jichi.
        if (($tarifa['servicio']['estado'] ?? null) !== VistaSireb::SERVICIO_ACTIVO) {
            throw new SinPrecioException('su servicio está dado de baja en SIREB.');
        }

        if (($tarifa['estado'] ?? null) !== VistaSireb::TARIFA_ACTIVA) {
            throw new SinPrecioException('su tarifa está dada de baja en SIREB.');
        }

        if ($exigirLiquidable && ($tarifa['liquidable'] ?? false) !== true) {
            throw new SinPrecioException('su tarifa no se puede cobrar hoy en SIREB (tarifario no vigente).');
        }

        return ['monto' => (float) $tarifa['monto'], 'tarifa_id' => $tarifa['id']];
    }
}
