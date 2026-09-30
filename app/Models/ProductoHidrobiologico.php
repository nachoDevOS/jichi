<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\HistorialSireb;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Una especie del cuadro D de la guía: «Surubí». Su precio por kilo lo pone
 * SIREB con `tarifa_sireb`, y cada guía lo congela al emitirse.
 */
#[Fillable(['nombre', 'servicio_sireb', 'tarifa_sireb', 'estado'])]
class ProductoHidrobiologico extends Model
{
    use Auditable, HistorialSireb, SoftDeletes;

    protected $table = 'productos_hidrobiologicos';

    /** El historial ya ES el registro del cambio: auditarlo lo duplicaría. */
    protected $noAuditable = ['sireb_historial'];

    /** Ver Asociacion::$attributes: los defaults de la base no llegan al create(). */
    protected $attributes = [
        'estado' => true,
    ];

    protected function casts(): array
    {
        return [
            'estado' => 'boolean',
            'sireb_historial' => 'array',
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
