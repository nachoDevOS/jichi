<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * La CABECERA del comprobante oficial de caja.
 */
#[Fillable([
    'beneficiario_id',
    'numero_recibo',
    'monto_total',
    'concepto',
])]
class Recibo extends Model
{
    use Auditable, SoftDeletes;

    /** Ver Asociacion::$attributes: los defaults de la base no llegan al create(). */
    protected $attributes = [
        'monto_total' => 0,
    ];

    protected function casts(): array
    {
        return [
            'monto_total' => 'decimal:2',
        ];
    }

    //  Relaciones

    /**
     * A nombre de quién sale el papel.
     *
     * Se lee en vivo y no está copiado: corregir un apellido en la ficha
     * corrige también los comprobantes. El costo es el otro lado de esa
     * moneda —una reimpresión puede no decir lo mismo que el papel que la
     * persona se llevó—, y se aceptó a pedido.
     */
    public function beneficiario(): BelongsTo
    {
        return $this->belongsTo(Beneficiario::class);
    }

    /**
     * El detalle: los abonos que este papel ampara.
     */
    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class);
    }

    //  Reglas de negocio

    /**
     * La suma de lo que HOY cuelga de este recibo.
     */
    public function montoCalculado(): float
    {
        if ($this->relationLoaded('pagos')) {
            return (float) $this->pagos->sum('monto_parcial');
        }

        return (float) $this->pagos()->sum('monto_parcial');
    }

    /** ¿Lo impreso coincide con lo que hay? Con un céntimo de tolerancia por el redondeo. */
    public function cuadra(): bool
    {
        return abs($this->montoCalculado() - (float) $this->monto_total) < 0.01;
    }

    /**
     * Recalcula y guarda el total a partir del detalle.
     */
    public function recalcularTotal(): static
    {
        $this->forceFill(['monto_total' => $this->montoCalculado()])->save();

        return $this;
    }

    //  Scopes

    /** Para el arqueo del día. */
    public function scopeDelDia(Builder $query, ?string $fecha = null): Builder
    {
        return $query->whereDate(
            $this->qualifyColumn('created_at'),
            $fecha ?? now()->toDateString(),
        );
    }

    public function scopeOrdenDeSerie(Builder $query): Builder
    {
        return $query->orderBy($this->qualifyColumn('numero_recibo'));
    }
}
