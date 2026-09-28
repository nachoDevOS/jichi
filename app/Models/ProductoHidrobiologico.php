<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Una especie del cuadro D de la guía y la tasa por kilo que se cobra al
 * trasladarla: «Surubí, 0,50 Bs/kg». La guía cobra la suma de su cuadro D.
 */
#[Fillable(['nombre', 'precio_kg', 'estado'])]
class ProductoHidrobiologico extends Model
{
    use Auditable, SoftDeletes;

    protected $table = 'productos_hidrobiologicos';

    /** Ver Asociacion::$attributes: los defaults de la base no llegan al create(). */
    protected $attributes = [
        'precio_kg' => 0,
        'estado' => true,
    ];

    protected function casts(): array
    {
        return [
            'precio_kg' => 'decimal:2',
            'estado' => 'boolean',
        ];
    }

    /** Los renglones de guía que lo usaron: es lo que impide borrarlo. */
    public function detalles(): HasMany
    {
        return $this->hasMany(GuiaDetalle::class, 'producto_id');
    }

    /** Los que se pueden elegir en una guía nueva. */
    public function scopeVigentes(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('estado'), true);
    }
}
