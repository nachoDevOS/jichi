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
use Illuminate\Support\Facades\Auth;

/**
 * Un tramo de la ESCALA OFICIAL de aprovechamiento pesquero.
 */
#[Fillable([
    'nro_escala',
    'modalidad',
    'descripcion_kg',
    'kilos_min',
    'kilos_max',
    'servicio_sireb',
    'tarifa_sireb',
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

    /** El historial ya ES el registro del cambio: auditarlo lo duplicaría. */
    protected $noAuditable = ['sireb_historial'];

    /**
     * Al cambiar el servicio o la tarifa, el par anterior se anota en
     * `sireb_historial`. En el modelo y no en el controlador: así vale para
     * cualquier camino que edite el tramo.
     */
    protected static function booted(): void
    {
        static::updating(function (self $tramo): void {
            if ($tramo->isDirty(['servicio_sireb', 'tarifa_sireb'])) {
                $tramo->anotarSirebAnterior();
            }
        });
    }

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
            'estado' => 'boolean',
            'modalidad' => ModalidadAprovechamiento::class,
            'sireb_historial' => 'array',
        ];
    }

    /**
     * Agrega al historial el servicio y la tarifa que el tramo tenía ANTES de
     * este cambio. «Desde» es el fin de la entrada anterior o, si es la primera,
     * el alta del tramo.
     */
    private function anotarSirebAnterior(): void
    {
        $historial = $this->sireb_historial ?? [];
        $anterior = end($historial) ?: null;

        $historial[] = [
            'servicio_sireb' => $this->getOriginal('servicio_sireb'),
            'tarifa_sireb' => $this->getOriginal('tarifa_sireb'),
            'desde' => $anterior['hasta'] ?? $this->created_at?->toIso8601String(),
            'hasta' => now()->toIso8601String(),
            'cambiado_por' => Auth::id(),
        ];

        $this->sireb_historial = $historial;
    }

    //  Relaciones

    public function aprovechamientos(): HasMany
    {
        return $this->hasMany(AprovechamientoPesq::class, 'categoria_aprov_id');
    }

    //  Lectura

    /**
     * Cómo se lee en un desplegable: «3 · 201 kg Hasta 400 Kg». Sin el id de
     * SIREB: un uuid no le dice nada al operador.
     */
    protected function etiqueta(): Attribute
    {
        return Attribute::get(fn (): string => sprintf('%d · %s', $this->nro_escala, $this->descripcion_kg));
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
