<?php

namespace App\Models;

use App\Enums\EstadoAsociacion;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * El gremio al que pertenece la persona.
 *
 * Es un catálogo que edita la unidad desde el panel. No se borra una fila: se
 * pone en `inactivo`, porque los carnets y las guías ya emitidas apuntan acá.
 */
#[Fillable(['nombre', 'sigla', 'estado'])]
class Asociacion extends Model
{
    use Auditable, SoftDeletes;

    /**
     * El nombre de la tabla va explícito porque el plural que Laravel deduce de
     * «Asociacion» es «asociacions». Eloquent pluraliza en inglés y el español
     * no le entra: sin esta línea el modelo consulta una tabla que no existe.
     */
    protected $table = 'asociaciones';

    /**
     * UN default de la base NO llega al objeto que devuelve create().
     *
     * El INSERT lo aplica el motor y el modelo en memoria se queda con la
     * columna en null hasta que alguien haga refresh(). Eso rompe lo obvio:
     * crear una asociación y preguntarle el estado en la línea siguiente
     * contesta null, con la fila ya escrita y correcta en la base.
     *
     * Va con `->value` y no con el enum: `$attributes` se llena ANTES de que
     * corran los casts.
     */
    protected $attributes = [
        'estado' => EstadoAsociacion::Activo->value,
    ];

    protected function casts(): array
    {
        return [
            'estado' => EstadoAsociacion::class,
        ];
    }

    // ------------------------------------------------------------------
    //  Relaciones
    // ------------------------------------------------------------------

    public function carnets(): HasMany
    {
        return $this->hasMany(Carnet::class);
    }

    public function guias(): HasMany
    {
        return $this->hasMany(GuiaMovimiento::class);
    }

    // ------------------------------------------------------------------
    //  Lectura
    // ------------------------------------------------------------------

    /**
     * Cómo se muestra en un desplegable: «ASOPESCA — Asociación de Pescadores».
     *
     * La sigla va adelante porque es lo que el operador tipea para filtrar, y
     * muchas asociaciones tienen nombres que empiezan igual.
     */
    protected function etiqueta(): Attribute
    {
        return Attribute::get(fn (): string => filled($this->sigla)
            ? $this->sigla.' — '.$this->nombre
            : $this->nombre);
    }

    /** ¿Se puede elegir hoy en un alta nueva? */
    public function estaActiva(): bool
    {
        return $this->estado->seleccionable();
    }

    // ------------------------------------------------------------------
    //  Scopes
    // ------------------------------------------------------------------

    /**
     * Las que se pueden elegir. Se califica la columna porque `carnets`,
     * `guias_movimiento` y esta tabla tienen todas una columna `estado`, y un
     * `where('estado', ...)` sin calificar sobre una consulta con join responde
     * «column reference is ambiguous».
     */
    public function scopeActivas(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('estado'), EstadoAsociacion::Activo);
    }

    public function scopeOrdenAlfabetico(Builder $query): Builder
    {
        return $query->orderBy($this->qualifyColumn('nombre'));
    }
}
