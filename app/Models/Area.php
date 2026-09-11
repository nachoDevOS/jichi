<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'nombre',
    'slug',
    'codigo',
    'descripcion',
    'icono',
    'color',
    'orden',
    'activo',
])]
class Area extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'orden' => 'integer',
        ];
    }

    public function tiposTramite(): HasMany
    {
        return $this->hasMany(TipoTramite::class);
    }

    public function tramites(): HasManyThrough
    {
        return $this->hasManyThrough(Tramite::class, TipoTramite::class);
    }

    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    public function scopeOrdenadas(Builder $query): Builder
    {
        return $query->orderBy('orden')->orderBy('nombre');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
