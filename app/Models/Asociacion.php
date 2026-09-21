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
#[Fillable(['nombre', 'sigla', 'datos', 'estado'])]
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
     * LOS CAMPOS DE LA FICHA DEL GREMIO, y el rótulo con el que se muestran.
     *
     * Es la lista CERRADA de claves que admite la columna `datos`: el
     * formulario dibuja estos campos y el Request descarta cualquier otro. Con
     * un JSON abierto, la misma tabla termina guardando «telefono», «teléfono»
     * y «tel», y después ningún reporte los puede cruzar.
     *
     * Para agregar un dato se suma acá y listo: no hace falta migrar.
     *
     * @var array<string, string>
     */
    public const CAMPOS = [
        'personeria_juridica' => 'Personería jurídica',
        'representante' => 'Representante legal',
        'ci_representante' => 'C.I. del representante',
        'telefono' => 'Teléfono',
        'correo' => 'Correo electrónico',
        'direccion' => 'Dirección',
        'municipio' => 'Municipio',
        'comunidad' => 'Comunidad',
        'fundacion' => 'Fecha de fundación',
    ];

    /**
     * UN default de la base NO llega al objeto que devuelve create().
     */
    protected $attributes = [
        'estado' => EstadoAsociacion::Activo->value,
    ];

    protected function casts(): array
    {
        return [
            // `array` y no `object`: el resto del código lee `$a->datos['x']`.
            'datos' => 'array',
            'estado' => EstadoAsociacion::class,
        ];
    }

    //  Relaciones

    public function carnets(): HasMany
    {
        return $this->hasMany(Carnet::class);
    }

    public function guias(): HasMany
    {
        return $this->hasMany(GuiaMovimiento::class);
    }

    //  Lectura

    /**
     * Un dato de la ficha, o null si no se cargó.
     *
     * `datos` es nullable y sus claves son opcionales, así que leerlo directo
     * con `$a->datos['telefono']` revienta con «Undefined array key» en cuanto
     * una asociación vieja no lo tenga.
     */
    public function dato(string $clave): ?string
    {
        $valor = $this->datos[$clave] ?? null;

        return is_string($valor) && trim($valor) !== '' ? $valor : null;
    }

    /**
     * La ficha completa, con TODAS las claves —las vacías en null— y su
     * rótulo. Es lo que necesitan el formulario y la impresión: sin las
     * ausentes, el formulario dibujaría menos campos según la fila.
     *
     * @return array<string, string|null>
     */
    public function fichaCompleta(): array
    {
        return collect(self::CAMPOS)
            ->map(fn (string $rotulo, string $clave): ?string => $this->dato($clave))
            ->all();
    }

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

    //  Scopes

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
