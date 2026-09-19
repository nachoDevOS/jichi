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
 *
 * Convierte una decisión administrativa —«a esta persona le corresponde la
 * escala 3»— en los dos números con los que trabaja el sistema: el volumen en
 * kilos y lo que se cobra por él.
 *
 * NO SE BORRA UNA ESCALA. Los aprovechamientos otorgados apuntan acá para
 * dejar constancia de bajo qué tramo se autorizaron; una escala derogada se
 * pone en `estado = false` y desaparece del formulario sin tocar lo histórico.
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

    // ------------------------------------------------------------------
    //  Relaciones
    // ------------------------------------------------------------------

    public function aprovechamientos(): HasMany
    {
        return $this->hasMany(AprovechamientoPesq::class, 'categoria_aprov_id');
    }

    // ------------------------------------------------------------------
    //  Lectura
    // ------------------------------------------------------------------

    /**
     * Cómo se lee en un desplegable: «3 · 201 kg Hasta 500 Kg — 110,00 Bs».
     *
     * Se usa `descripcion_kg` y no los dos decimales, porque el texto oficial
     * no siempre es la lectura literal del rango: el tramo más alto dice
     * «PAICHE» y eso no está en ningún número.
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
     *
     * Los dos extremos entran. Es lo que dice el texto oficial —«1 Kg Hasta 100
     * Kg»— y además, con uno de los dos abierto, un cupo de exactamente 100 kg
     * no caería en ninguna escala.
     */
    public function contiene(float $kilos): bool
    {
        return $kilos >= (float) $this->kilos_min && $kilos <= (float) $this->kilos_max;
    }

    /**
     * El tramo que corresponde a este volumen, o null si se pasa de la escala.
     *
     * Devuelve null en vez de caer al tramo más alto a propósito: un pedido de
     * 5000 kg cuando la escala llega a 2000 no es «el tramo 7», es un pedido
     * que necesita resolución aparte. Silenciarlo cobraría de menos.
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

    // ------------------------------------------------------------------
    //  Scopes
    // ------------------------------------------------------------------

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
