<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Una clase de credencial y su arancel: «Carnet de Pescador», 80 Bs.
 *
 * ES EL CATÁLOGO, NO LA REGLA. Qué habilita el documento —si emite faenas o
 * guías, si lleva cupo— lo dice `carnets.tipo_actor`, que es un enum de PHP.
 * De este nombre no cuelga NINGUNA decisión: el mismo documento figura como
 * «Carnet de Pescador» o como «Pescador Artesanal» según quién lo cargó, y un
 * match sobre el texto rompería en silencio el día que alguien lo edite.
 */
#[Fillable(['nombre', 'precio_bs', 'estado'])]
class TipoCarnet extends Model
{
    use Auditable, SoftDeletes;

    /** «TipoCarnet» pluraliza como «tipo_carnets», que no es la tabla. */
    protected $table = 'tipos_carnet';

    /** Ver el comentario de Asociacion::$attributes: los defaults de la base no llegan al create(). */
    protected $attributes = [
        'precio_bs' => 0,
        'estado' => true,
    ];

    protected function casts(): array
    {
        return [
            'precio_bs' => 'decimal:2',
            'estado' => 'boolean',
        ];
    }

    // ------------------------------------------------------------------
    //  Relaciones
    // ------------------------------------------------------------------

    public function carnets(): HasMany
    {
        return $this->hasMany(Carnet::class);
    }

    // ------------------------------------------------------------------
    //  Lectura
    // ------------------------------------------------------------------

    /** «Carnet de Pescador — 80,00 Bs», como se lee en el desplegable. */
    protected function etiqueta(): Attribute
    {
        return Attribute::get(fn (): string => sprintf(
            '%s — %s Bs',
            $this->nombre,
            number_format((float) $this->precio_bs, 2, ',', '.'),
        ));
    }

    /**
     * El precio de HOY, para armar un cobro nuevo.
     *
     * No sirve para leer lo que salió un carnet ya emitido: si el arancel
     * cambió, esta columna ya dice otra cosa. Lo cobrado de verdad está en
     * `pagos`, que no se recalcula nunca.
     */
    public function precioVigente(): float
    {
        return (float) $this->precio_bs;
    }

    // ------------------------------------------------------------------
    //  Scopes
    // ------------------------------------------------------------------

    public function scopeVigentes(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('estado'), true);
    }
}
