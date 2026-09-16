<?php

namespace App\Models;

use App\Enums\EstadoRubro;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Una actividad que el carnet puede habilitar.
 *
 * Tabla chica, de lectura constante y de escritura rarísima: la carga el seeder
 * y la toca el administrador cuando cambia una ordenanza. Por eso lleva
 * Auditable —hay que poder responder quién subió una tarifa y cuándo— pero no
 * SoftDeletes: un rubro no se borra nunca, se pasa a inactivo.
 */
#[Fillable(['nombre', 'descripcion', 'costo', 'estado'])]
class Rubro extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'estado' => EstadoRubro::class,
            'costo' => 'decimal:2',
        ];
    }

    public function habilitaciones(): HasMany
    {
        return $this->hasMany(CarnetRubro::class);
    }

    public function tramites(): HasMany
    {
        return $this->hasMany(Tramite::class);
    }

    /**
     * Los carnets que tienen este rubro habilitado.
     */
    public function carnets(): BelongsToMany
    {
        return $this->belongsToMany(Carnet::class, 'carnet_rubro')
            ->using(CarnetRubro::class)
            ->withPivot(['id', 'fecha_habilitacion', 'estado'])
            ->withTimestamps();
    }

    public function estaActivo(): bool
    {
        return $this->estado === EstadoRubro::Activo;
    }

    /**
     * Los rubros que se pueden pedir hoy.
     *
     * Lo usa el formulario de solicitud. Los inactivos siguen existiendo y se
     * ven en los carnets que ya los tenían, pero no se ofrecen para pedir.
     */
    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('estado', EstadoRubro::Activo);
    }
}
