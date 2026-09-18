<?php

namespace App\Models;

use App\Enums\EstadoRubro;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Una actividad que el carnet puede habilitar.
 *
 * Tabla chica, de lectura constante y de escritura rarísima: la carga el seeder
 * y la toca el administrador cuando cambia una ordenanza. Por eso lleva
 * Auditable —hay que poder responder quién subió una tarifa y cuándo— pero no
 * SoftDeletes: un rubro no se borra nunca, se pasa a inactivo.
 */
#[Fillable([
    'nombre',
    'descripcion',
    'costo',
    'requiere_capacidad',
    'emite_faenas',
    'emite_guias',
    'estado',
])]
class Rubro extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'estado' => EstadoRubro::class,
            'costo' => 'decimal:2',
            'requiere_capacidad' => 'boolean',
            'emite_faenas' => 'boolean',
            'emite_guias' => 'boolean',
        ];
    }

    public function tramites(): HasMany
    {
        return $this->hasMany(Tramite::class);
    }

    /**
     * Los carnets emitidos para esta actividad.
     *
     * Era un `belongsToMany` a través de `carnet_rubro`; desde que el carnet es
     * de un solo rubro, la relación es directa y la tabla intermedia ya no
     * existe. `$rubro->carnets` sigue devolviendo lo mismo que antes —los
     * carnets de esta actividad— así que quien la usaba para contar no cambia.
     */
    public function carnets(): HasMany
    {
        return $this->hasMany(Carnet::class);
    }

    public function estaActivo(): bool
    {
        return $this->estado === EstadoRubro::Activo;
    }

    /**
     * ¿Esta actividad se autoriza por volumen?
     *
     * De acá cuelgan cuatro cosas, y por eso conviene preguntarlo por este
     * método y no leer la columna suelta: el formulario muestra u oculta el
     * campo del cupo, la validación lo exige o lo prohíbe, la ficha del carnet
     * lo muestra o no, y el plástico imprime el renglón CUPO o le da la tira
     * entera al nombre del rubro.
     */
    public function requiereCapacidad(): bool
    {
        return (bool) $this->requiere_capacidad;
    }

    /**
     * ========================================================================
     *  QUÉ PERMISO OPERATIVO EMITE ESTA ACTIVIDAD
     * ========================================================================
     *
     * El carnet es la llave anual; con él se emiten los permisos con los que la
     * persona trabaja de verdad, y no son los mismos para toda actividad:
     *
     *     Pescador        ──▶ FAENAS   (una por salida de pesca)
     *     Comercializador ──▶ GUÍAS    (una por carga trasladada)
     *
     * ------------------------------------------------------------------------
     *  SE PREGUNTA ACÁ Y NO SE MIRA EL NOMBRE DEL RUBRO
     * ------------------------------------------------------------------------
     *
     * La tentación es `$rubro->nombre === 'Pescador'`. No sirve, por lo mismo
     * que ya está decidido en `requiereCapacidad()`: el catálogo lo edita la
     * unidad desde el panel, el mismo rubro figura como «Pescador» o como
     * «Faena» según quién lo cargó, y los rubros nuevos entran por ordenanza.
     * Un `match` por nombre se rompe el día que alguien corrige una tilde.
     *
     * ------------------------------------------------------------------------
     *  SON DOS BANDERAS Y NO UNA SOLA, PORQUE NO SON EXCLUYENTES
     * ------------------------------------------------------------------------
     *
     * Hoy cada rubro emite una sola cosa, pero nada en el negocio lo impone:
     * una actividad piscícola necesitaría faena para la cosecha y guía para
     * trasladarla. Con un solo campo `tipo_permiso` eso obligaría a partir el
     * rubro en dos, que es peor.
     */
    public function emiteFaenas(): bool
    {
        return (bool) $this->emite_faenas;
    }

    public function emiteGuias(): bool
    {
        return (bool) $this->emite_guias;
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
