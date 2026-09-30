<?php

namespace App\Models;

use App\Enums\TipoActor;
use App\Traits\Auditable;
use App\Traits\HistorialSireb;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Una clase de credencial: «Carnet de Pescador». Su precio lo pone SIREB con
 * `tarifa_sireb`, y cada carnet lo congela al emitirse.
 */
#[Fillable(['nombre', 'tipo_actor', 'servicio_sireb', 'tarifa_sireb', 'estado'])]
class TipoCarnet extends Model
{
    use Auditable, HistorialSireb, SoftDeletes;

    /** «TipoCarnet» pluraliza como «tipo_carnets», que no es la tabla. */
    protected $table = 'tipos_carnet';

    /** El historial ya ES el registro del cambio: auditarlo lo duplicaría. */
    protected $noAuditable = ['sireb_historial'];

    /** Ver el comentario de Asociacion::$attributes: los defaults de la base no llegan al create(). */
    protected $attributes = [
        'tipo_actor' => TipoActor::Pescador->value,
        'estado' => true,
    ];

    protected function casts(): array
    {
        return [
            'tipo_actor' => TipoActor::class,
            'estado' => 'boolean',
            'sireb_historial' => 'array',
        ];
    }

    //  Relaciones

    public function carnets(): HasMany
    {
        return $this->hasMany(Carnet::class);
    }

    //  Scopes

    public function scopeVigentes(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('estado'), true);
    }
}
