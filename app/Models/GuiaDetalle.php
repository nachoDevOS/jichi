<?php

namespace App\Models;

use App\Enums\CondicionProducto;
use App\Support\Sql;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una línea de la carga que ampara una guía: «Surubí, fresco, 120 kg».
 *
 * ----------------------------------------------------------------------------
 *  NO VALE POR SÍ SOLO
 * ----------------------------------------------------------------------------
 *
 * Es la única tabla del módulo que se borra en cascada con su padre. Un detalle
 * sin su guía no dice nada, y el formulario edita la grilla entera —borra las
 * filas y las vuelve a insertar—, así que el borrado es parte del uso normal.
 *
 * Las faenas, las guías y los pagos son al revés: cada uno respalda un papel
 * que salió a la calle, y por eso van con `restrictOnDelete`.
 *
 * ----------------------------------------------------------------------------
 *  LA TABLA VA DECLARADA A MANO
 * ----------------------------------------------------------------------------
 *
 * Eloquent la deduciría bien hoy —`GuiaDetalle` da `guia_detalles`—, pero esa
 * deducción pluraliza en inglés la ÚLTIMA palabra del nombre. Declararla deja
 * el vínculo escrito: si mañana la clase se renombra, el desajuste se ve en
 * este archivo y no como un «relation does not exist» en producción.
 */
#[Fillable([
    'guia_id',
    'especie',
    'condicion',
    'cantidad_kg',
    'precio_unitario',
    'imponible',
])]
class GuiaDetalle extends Model
{
    use Auditable;

    /** Declarada a mano: ver la nota del encabezado sobre la pluralización. */
    protected $table = 'guia_detalles';

    protected function casts(): array
    {
        return [
            'condicion' => CondicionProducto::class,
            // decimal:2 en los tres, y no float: son kilos y dinero, y sobre
            // ellos se calcula lo que la persona paga. En punto flotante
            // 0.1 + 0.2 no da 0.3, y una guía que no cierra por centésimas es
            // un reclamo en la ventanilla.
            'cantidad_kg' => 'decimal:2',
            'precio_unitario' => 'decimal:2',
            'imponible' => 'decimal:2',
        ];
    }

    public function guia(): BelongsTo
    {
        return $this->belongsTo(Guia::class);
    }

    /**
     * LO QUE VALE ESTA LÍNEA.
     *
     * Devuelve `imponible` cuando está cargado, y recién si no está multiplica
     * cantidad por precio.
     *
     * EL ORDEN IMPORTA Y NO ES INTERCAMBIABLE: `imponible` es la base de cálculo
     * que la unidad escribió en el papel, y cuando aplicó una rebaja o redondeó,
     * no coincide con la multiplicación. Si acá se recalculara siempre, el
     * sistema mostraría un importe distinto del que dice la guía firmada, y la
     * que tiene razón es la guía.
     *
     * Sin ninguno de los dos da cero: hay guías que solo declaran volumen, sin
     * precios. Esas no tienen nada que cobrar.
     */
    public function importe(): float
    {
        if ($this->imponible !== null) {
            return (float) $this->imponible;
        }

        if ($this->precio_unitario === null) {
            return 0.0;
        }

        return (float) $this->cantidad_kg * (float) $this->precio_unitario;
    }

    /**
     * La línea tal como se lee: «Surubí (fresco) — 120 KG».
     *
     * Vive en el modelo y no en la maqueta porque la usan las dos: la pantalla
     * del panel y el PDF de la guía. Escrita dos veces, una de las dos se queda
     * vieja.
     */
    public function descripcion(): string
    {
        $kg = (float) $this->cantidad_kg;
        $numero = fmod($kg, 1.0) === 0.0
            ? number_format($kg, 0, ',', '.')
            : number_format($kg, 2, ',', '.');

        return "{$this->especie} ({$this->condicion->etiqueta()}) — {$numero} KG";
    }

    /**
     * Las líneas de una especie, sin distinguir mayúsculas ni acentos de más.
     *
     * La especie se guarda como texto libre —no hay padrón escrito de las
     * especies del Beni—, así que «Surubí», «surubi» y «SURUBI» conviven en la
     * columna. El reporte por especie sería inútil comparando con `=`.
     *
     * Se usa `Sql::like()` porque PostgreSQL necesita ILIKE y SQLite ya trata
     * LIKE como insensible. Ver la regla 8 de CLAUDE.md.
     */
    public function scopeDeEspecie(Builder $query, string $especie): Builder
    {
        return $query->where(
            $query->qualifyColumn('especie'),
            Sql::like(),
            trim($especie),
        );
    }
}
