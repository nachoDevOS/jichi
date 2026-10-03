<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\Codificable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * El comprobante oficial de un documento, emitido cuando SIREB confirmó el pago.
 * Copia la boleta tal como la validó SIREB. Ver ConfirmarPagoService.
 */
#[Fillable([
    'beneficiario_id',
    'recibible_type',
    'recibible_id',
    'numero_recibo',
    'monto_total',
    'concepto',
    'numero_boleta',
    'entidad_bancaria',
    'fecha_pago',
])]
class Recibo extends Model
{
    use Auditable, Codificable, SoftDeletes;

    /** Ver Asociacion::$attributes: los defaults de la base no llegan al create(). */
    protected $attributes = [
        'monto_total' => 0,
    ];

    protected function casts(): array
    {
        return [
            'monto_total' => 'decimal:2',
            'fecha_pago' => 'date',
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

    /** El documento pagado: autorización, carnet, faena o guía. */
    public function recibible(): MorphTo
    {
        return $this->morphTo()->withTrashed();
    }
}
