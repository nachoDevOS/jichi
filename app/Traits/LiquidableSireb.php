<?php

namespace App\Traits;

use App\Enums\EstadoLiquidacionSireb;
use App\Models\Beneficiario;
use App\Models\Recibo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Carbon;

/**
 * Un documento que se paga en SIREB: autorización, carnet, faena y guía. Lleva
 * las columnas `sireb_*` y su recibo; la liquidación la maneja
 * App\Services\LiquidarSirebService. Ver docs/modulos/SIREB.md.
 *
 * @property EstadoLiquidacionSireb|null $sireb_estado
 */
trait LiquidableSireb
{
    /** Quién paga: a su nombre va la liquidación y el recibo. */
    abstract public function titularSireb(): Beneficiario;

    /**
     * Lo que se le cobra en SIREB.
     *
     * @return list<array{tarifa_id: string, cantidad: float|int}>
     */
    abstract public function itemsSireb(): array;

    /** Las columnas y los casts, para no repetirlos en los cuatro modelos. */
    public function initializeLiquidableSireb(): void
    {
        $this->mergeFillable(['sireb_idempotency_key', 'sireb_liquidacion_id', 'sireb_codigo_publico', 'sireb_estado', 'sireb_envio']);
        $this->mergeCasts(['sireb_estado' => EstadoLiquidacionSireb::class, 'sireb_envio' => 'array']);
    }

    /** El recibo, emitido cuando SIREB confirmó el pago. */
    public function recibo(): MorphOne
    {
        return $this->morphOne(Recibo::class, 'recibible');
    }

    /** ¿SIREB ya tiene registrada su liquidación? */
    public function registradoEnSireb(): bool
    {
        return $this->sireb_estado === EstadoLiquidacionSireb::Registrada;
    }

    /**
     * ¿Se ofrece «Cargar pago»? Con lo que se sabe de la última consulta; el servicio
     * vuelve a preguntar a SIREB antes de cargar. Ver CargarPagoService.
     */
    public function puedeCargarPago(): bool
    {
        return $this->estado->estaAbierto() && $this->registradoEnSireb() && empty($this->sireb_envio['pago']);
    }

    /** Lo que falta pagar en SIREB: el monto mientras esté pendiente, cero después. */
    public function porPagar(): float
    {
        return $this->estado->estaAbierto() ? $this->montoACobrar() : 0.0;
    }

    /** La venta en SIREB tal como la muestra la ficha. Null si nunca se preparó. */
    public function resumenSireb(): ?array
    {
        return $this->sireb_estado ? [
            'estado' => $this->sireb_estado->value,
            'estado_etiqueta' => $this->sireb_estado->etiqueta(),
            'estado_color' => $this->sireb_estado->color(),
            'codigo_publico' => $this->sireb_codigo_publico,
            'pago' => $this->pagoSireb(),
            // Distingue «nunca se preguntó» de «se preguntó y no hay pago cargado».
            'pago_consultado' => array_key_exists('pago', $this->sireb_envio ?? []),
        ] : null;
    }

    /** La boleta que SIREB informó en la última consulta. SIREB no expone la imagen del comprobante. */
    private function pagoSireb(): ?array
    {
        $pago = $this->sireb_envio['pago'] ?? null;

        return $pago ? [
            'estado' => $pago['estado'] ?? null,
            'monto_pagado' => (float) ($pago['monto_pagado'] ?? 0),
            'numero_boleta' => $pago['numero_boleta'] ?? null,
            'entidad_bancaria' => $pago['entidad_bancaria'] ?? null,
            // Un DÍA, igual que en el recibo; la validación es un MOMENTO.
            'fecha_pago' => isset($pago['fecha_pago']) ? Carbon::parse($pago['fecha_pago'])->timezone(config('app.timezone'))->toDateString() : null,
            'fecha_validacion' => isset($pago['fecha_validacion']) ? Carbon::parse($pago['fecha_validacion'])->toIso8601String() : null,
        ] : null;
    }
}
