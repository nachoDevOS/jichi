<?php

namespace App\Traits;

use App\Enums\EstadoLiquidacionSireb;
use App\Models\Beneficiario;
use App\Models\Recibo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

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
        ] : null;
    }
}
