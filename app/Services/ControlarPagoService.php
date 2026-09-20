<?php

namespace App\Services;

use App\Enums\EstadoValidacionPago;
use App\Exceptions\CobroInvalidoException;
use App\Models\Pago;
use App\Support\Archivos;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * El control de las boletas: validar, observar y corregir.
 */
class ControlarPagoService
{
    /** La boleta cuadra con el extracto. */
    public function validar(Pago $pago): Pago
    {
        return DB::transaction(function () use ($pago): Pago {
            $bloqueado = $this->bloquear($pago);

            $this->exigirQuePuedaControlarse($bloqueado);

            $bloqueado->motivoAuditoria = 'Boleta verificada contra el extracto del banco.';
            $bloqueado->update([
                'estado_validacion' => EstadoValidacionPago::Validado,
                // La observación queda resuelta: se limpia.
                'observacion' => null,
                'validado_por' => Auth::id(),
                'validado_en' => now(),
            ]);

            return $pago->refresh();
        });
    }

    /** No cuadra. El motivo es obligatorio: sin él nadie sabe qué corregir. */
    public function observar(Pago $pago, string $motivo): Pago
    {
        return DB::transaction(function () use ($pago, $motivo): Pago {
            $bloqueado = $this->bloquear($pago);

            $this->exigirQuePuedaControlarse($bloqueado);

            $bloqueado->motivoAuditoria = $motivo;
            $bloqueado->update([
                'estado_validacion' => EstadoValidacionPago::Observado,
                'observacion' => $motivo,
                'validado_por' => Auth::id(),
                'validado_en' => now(),
            ]);

            return $pago->refresh();
        });
    }

    /**
     * Corregir el depósito: única salida de una observación.
     *
     * @param  array{monto_parcial?: float|string, nro_transaccion?: string, fecha_deposito?: string, comprobante?: string|null}  $datos
     */
    public function corregir(Pago $pago, array $datos): Pago
    {
        return DB::transaction(function () use ($pago, $datos): Pago {
            $bloqueado = $this->bloquear($pago);

            if (! $bloqueado->admiteCorreccion()) {
                throw CobroInvalidoException::noAdmiteCorreccion(
                    $bloqueado->concepto_detalle,
                );
            }

            $anterior = $bloqueado->comprobante;
            $nuevo = $datos['comprobante'] ?? null;

            $cambios = [
                'estado_validacion' => EstadoValidacionPago::Pendiente,
                'observacion' => null,
                // El control se borra entero: quien validó miró otros números.
                'validado_por' => null,
                'validado_en' => null,
            ];

            foreach (['monto_parcial', 'nro_transaccion', 'fecha_deposito'] as $campo) {
                if (array_key_exists($campo, $datos)) {
                    $cambios[$campo] = $datos[$campo];
                }
            }

            if ($nuevo !== null) {
                $cambios['comprobante'] = $nuevo;
            }

            $bloqueado->motivoAuditoria = 'Depósito corregido: vuelve a quedar sin validar.';
            $bloqueado->update($cambios);

            // Recién con la fila escrita: si el UPDATE falla, la boleta vieja
            // sigue siendo la buena.
            if ($nuevo !== null && $anterior !== null && $anterior !== $nuevo) {
                Archivos::borrar($anterior);
            }

            return $pago->refresh();
        });
    }

    //  Auxiliares

    /**
     * La fila bloqueada: dos revisores a la vez se pisarían el reparo.
     *
     * Devuelve la COPIA; quien llamó tiene la original, así que arriba se
     * escribe acá y se devuelve `$pago->refresh()`.
     */
    private function bloquear(Pago $pago): Pago
    {
        $bloqueado = Pago::query()->whereKey($pago->getKey())->lockForUpdate()->firstOrFail();

        // Las dos preguntas del control lo necesitan; sin esto, dos consultas.
        $bloqueado->load('pagable');

        return $bloqueado;
    }

    /**
     * Las dos razones por las que no se puede controlar, con el mensaje que
     * dice qué hacer en cada caso.
     */
    private function exigirQuePuedaControlarse(Pago $pago): void
    {
        if ($pago->admiteControl()) {
            return;
        }

        if (! $pago->estado_validacion->admiteControl()) {
            throw CobroInvalidoException::noAdmiteControl(
                $pago->estado_validacion === EstadoValidacionPago::Observado
                    ? 'Este depósito está observado: primero hay que CORREGIRLO. '.
                      'Validarlo sin tocar el dato sería dar por bueno lo que se marcó como malo.'
                    : 'Este depósito ya está validado: no hay nada que controlar.',
            );
        }

        throw CobroInvalidoException::noAdmiteControl(
            'Las boletas se controlan con el trámite EN REVISIÓN. '.
            'Mientras está pendiente todavía se está armando, y aprobado ya no admite reparos.',
        );
    }
}
