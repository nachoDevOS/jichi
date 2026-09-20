<?php

namespace App\Models;

use App\Enums\ModalidadAprovechamiento;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Un tramo de la ESCALA OFICIAL de aprovechamiento pesquero.
 */
#[Fillable([
    'nro_escala',
    'modalidad',
    'descripcion_kg',
    'kilos_min',
    'kilos_max',
    'valor_bs',
    'estado',
])]
class CategoriaAprovechamiento extends Model
{
    use Auditable, SoftDeletes;

    /**
     * Explícito: de «CategoriaAprovechamiento» Laravel deduce
     * «categoria_aprovechamientos», que no es el nombre de la tabla.
     */
    protected $table = 'categorias_aprovechamiento';

    /** Ver el comentario de Asociacion::$attributes: los defaults de la base no llegan al create(). */
    protected $attributes = [
        'estado' => true,
        // Va con `->value` y no con el enum: `$attributes` se llena ANTES de que
        // corran los casts.
        'modalidad' => ModalidadAprovechamiento::EscalaGeneral->value,
    ];

    protected function casts(): array
    {
        return [
            'kilos_min' => 'decimal:2',
            'kilos_max' => 'decimal:2',
            'valor_bs' => 'decimal:2',
            'estado' => 'boolean',
            'modalidad' => ModalidadAprovechamiento::class,
        ];
    }

    //  Relaciones

    public function aprovechamientos(): HasMany
    {
        return $this->hasMany(AprovechamientoPesq::class, 'categoria_aprov_id');
    }

    //  Lectura

    /**
     * Cómo se lee en un desplegable: «3 · 201 kg Hasta 500 Kg — 110,00 Bs».
     */
    protected function etiqueta(): Attribute
    {
        return Attribute::get(fn (): string => sprintf(
            '%d · %s — %s Bs',
            $this->nro_escala,
            $this->descripcion_kg,
            number_format((float) $this->valor_bs, 2, ',', '.'),
        ));
    }

    /**
     * ¿Este volumen cae dentro del tramo?
     */
    public function contiene(float $kilos): bool
    {
        return $kilos >= (float) $this->kilos_min && $kilos <= (float) $this->kilos_max;
    }

    /**
     * El tramo que corresponde a este volumen, o null si se pasa de la escala.
     */
    public static function paraVolumen(float $kilos): ?self
    {
        return static::query()
            ->vigentes()
            ->where('kilos_min', '<=', $kilos)
            ->where('kilos_max', '>=', $kilos)
            ->orderBy('nro_escala')
            ->first();
    }

    //  Scopes

    public function scopeVigentes(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('estado'), true);
    }

    /** En el orden oficial de la resolución, que es como se lee el papel. */
    public function scopeEnOrdenDeEscala(Builder $query): Builder
    {
        return $query->orderBy($this->qualifyColumn('nro_escala'));
    }
}
