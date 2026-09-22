<?php

namespace App\Models;

use App\Enums\CondicionProducto;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Un renglón del cuadro D de la guía: una especie, su condición y sus kilos.
 */
#[Fillable([
    'guia_movimiento_id',
    'especie',
    'condicion',
    'cantidad_kg',
    'precio_kg',
    'importe_total',
])]
class GuiaDetalle extends Model
{
    use Auditable, SoftDeletes;

    protected $table = 'guia_detalles';

    /** Ver Asociacion::$attributes: los defaults de la base no llegan al create(). */
    protected $attributes = [
        'cantidad_kg' => 0,
        'precio_kg' => 0,
        'importe_total' => 0,
    ];

    protected function casts(): array
    {
        return [
            'cantidad_kg' => 'decimal:2',
            'precio_kg' => 'decimal:2',
            'importe_total' => 'decimal:2',
            'condicion' => CondicionProducto::class,
        ];
    }

    public function guia(): BelongsTo
    {
        return $this->belongsTo(GuiaMovimiento::class, 'guia_movimiento_id');
    }

    /**
     * Lo que suma este renglón: kilos × precio.
     *
     * Se GUARDA además de calcularse porque es dato declarado, no derivado: el
     * comerciante puede haber pagado un redondeo distinto al que sale de
     * multiplicar, y el papel lleva lo que él declaró.
     */
    public static function importeDe(float $cantidad, float $precio): float
    {
        return round($cantidad * $precio, 2);
    }
}
