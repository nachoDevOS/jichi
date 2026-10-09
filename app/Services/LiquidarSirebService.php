<?php

namespace App\Services;

use App\Enums\EstadoLiquidacionSireb;
use App\Sireb\SirebException;
use App\Sireb\SirebService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * La liquidación de un documento en SIREB (trait LiquidableSireb), en dos tiempos:
 * preparar() guarda la clave DENTRO de la transacción del documento y enviar()
 * llama DESPUÉS del commit, así un reintento manda la misma clave y SIREB no
 * duplica. Ver docs/modulos/SIREB.md.
 */
class LiquidarSirebService
{
    public function __construct(private readonly SirebService $sireb) {}

    /** Liquidación nueva, clave nueva, sin llamar a SIREB. */
    public function preparar(Model $documento): void
    {
        $documento->update([
            'sireb_idempotency_key' => (string) Str::uuid(),
            'sireb_liquidacion_id' => null,
            'sireb_codigo_publico' => null,
            'sireb_estado' => EstadoLiquidacionSireb::PorEnviar,
            'sireb_envio' => null,
        ]);
    }

    /**
     * Manda la liquidación con su clave guardada. Lo enviado y la respuesta (o el
     * error) quedan en `sireb_envio`.
     *
     * @throws SirebException
     */
    public function enviar(Model $documento): void
    {
        if ($documento->sireb_estado !== EstadoLiquidacionSireb::PorEnviar) {
            return;
        }

        $titular = $documento->titularSireb();
        $cuerpo = [
            'cliente' => [
                'ci_nit' => $titular->ci.($titular->complemento ? '-'.$titular->complemento : ''),
                'nombre_completo' => $titular->nombreCompleto,
            ],
            'referencia_externa' => $documento->codigo_legible,
            'items' => $documento->itemsSireb(),
        ];
        $envio = ['idempotency_key' => $documento->sireb_idempotency_key, 'enviado' => $cuerpo, 'enviado_en' => now()->toIso8601String()];

        try {
            $liquidacion = $this->sireb->registrarLiquidacion($cuerpo, $documento->sireb_idempotency_key);
        } catch (SirebException $e) {
            Log::channel('sireb')->warning('NO SE REGISTRÓ la liquidación de '.$documento->codigo_legible, ['documento' => $documento::class.':'.$documento->getKey(), 'motivo' => $e->getMessage()]);
            $documento->update(['sireb_envio' => [...$envio, 'error' => $e->getMessage()]]);

            throw $e;
        }

        $documento->update([
            'sireb_liquidacion_id' => $liquidacion['id'],
            'sireb_codigo_publico' => $liquidacion['codigo_publico'] ?? null,
            'sireb_estado' => EstadoLiquidacionSireb::Registrada,
            'sireb_envio' => [...$envio, 'respuesta' => $liquidacion],
            // Se anota al registrarse: lo que SIREB no creó no tiene nada que recordar.
            'sireb_historial' => [...($documento->sireb_historial ?? []), [
                'liquidacion_id' => $liquidacion['id'],
                'codigo_publico' => $liquidacion['codigo_publico'] ?? null,
                'idempotency_key' => $documento->sireb_idempotency_key,
                'items' => $documento->itemsHistorialSireb(),
                'monto' => (float) $documento->monto,
                'solicitada_en' => $envio['enviado_en'],
                'vence_en' => $liquidacion['fecha_vencimiento'] ?? null,
                'estado' => 'registrada',
                'cerrada_en' => null,
                'motivo' => null,
            ]],
        ]);
    }

    /** Marca cómo terminó la liquidación vigente en `sireb_historial`: vencida, anulada o pagada. */
    public function cerrarEnHistorial(Model $documento, string $final, ?string $motivo = null): void
    {
        $documento->update(['sireb_historial' => $this->historialCerrado($documento, $final, $motivo)]);
    }

    /** @return list<array<string, mixed>> */
    private function historialCerrado(Model $documento, string $final, ?string $motivo): array
    {
        return array_map(fn (array $l): array => $l['liquidacion_id'] === $documento->sireb_liquidacion_id && $l['cerrada_en'] === null
            ? [...$l, 'estado' => $final, 'cerrada_en' => now()->toIso8601String(), 'motivo' => $motivo]
            : $l, $documento->sireb_historial ?? []);
    }

    /** Como enviar(), pero si SIREB falla no frena: queda «por enviar» y la ficha ofrece reintentar. */
    public function enviarSinFrenar(Model $documento): void
    {
        try {
            $this->enviar($documento);
        } catch (SirebException) {
            // Ya quedó en el log y en `sireb_envio`.
        }
    }

    /**
     * Al CORREGIR, antes de tocar nada: si cambia lo que se cobra, anula la vieja.
     * Devuelve si hace falta preparar otra (también si la anterior ya estaba anulada).
     *
     * @throws SirebException
     */
    public function anularSiCambia(Model $documento, array $itemsNuevos, float $montoNuevo): bool
    {
        $cambia = $documento->itemsSireb() != $itemsNuevos || (float) $documento->monto !== $montoNuevo;

        if ($cambia) {
            $this->anular($documento, 'Corrección del borrador en Jichi: cambió lo que se cobra.');
        }

        return $cambia || in_array($documento->sireb_estado, [null, EstadoLiquidacionSireb::Anulada], true);
    }

    /**
     * Anula la liquidación en curso. Una por enviar se manda antes con su clave:
     * pudo haber llegado aunque no volvió respuesta. Si SIREB no anula, no se sigue.
     *
     * @throws SirebException
     */
    public function anular(Model $documento, string $motivo): void
    {
        if (in_array($documento->sireb_estado, [null, EstadoLiquidacionSireb::Anulada], true)) {
            return;
        }

        $final = 'anulada';

        try {
            $this->enviar($documento);

            if ($documento->registradoEnSireb()) {
                // Se pregunta ANTES: con un pago cargado —en revisión o validado— no se elimina ni se corrige.
                $liquidacion = $this->sireb->liquidacion($documento->sireb_liquidacion_id);

                if (($liquidacion['estado'] ?? null) === 'pagada' || ($liquidacion['pago']['estado'] ?? null) === 'confirmado') {
                    throw SirebException::liquidacionPagada();
                }

                if (($liquidacion['pago'] ?? null) !== null) {
                    throw SirebException::pagoEnRevision();
                }

                // Vencida o ya anulada no cobra nada, y SIREB no la anula otra vez (422): se sigue sin pedirlo.
                if (in_array($liquidacion['estado'] ?? null, ['vencida', 'anulada'], true)) {
                    $final = $liquidacion['estado'];
                } else {
                    $this->sireb->anularLiquidacion($documento->sireb_liquidacion_id, $motivo);
                }
            }
        } catch (SirebException $e) {
            // Un «no» de SIREB al ENVIAR (422) dice que con esta clave nunca la creó: no hay nada que anular.
            if ($e->codigo === null || $documento->registradoEnSireb()) {
                throw $e;
            }
        }

        $documento->update([
            'sireb_estado' => EstadoLiquidacionSireb::Anulada,
            'sireb_envio' => [...($documento->sireb_envio ?? []), 'anulada' => ['motivo' => $motivo, 'en' => now()->toIso8601String()]],
            'sireb_historial' => $this->historialCerrado($documento, $final, $motivo),
        ]);
    }
}
