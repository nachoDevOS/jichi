<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Uno de los nueve departamentos de Bolivia.
 *
 * Es un catálogo CERRADO: se siembra en su migración y no tiene pantalla, ni
 * alta, ni baja. No se agrega un departamento.
 */
class Departamento extends Model
{
    /** No hay filas que alguien cargue o corrija: no llevan fecha. */
    public $timestamps = false;

    /** «Departamento» pluraliza a «departamentos», pero va explícito igual. */
    protected $table = 'departamentos';

    //  Relaciones

    /** Las personas con cédula expedida acá. */
    public function beneficiarios(): HasMany
    {
        return $this->hasMany(Beneficiario::class);
    }

    //  Lectura

    /** Cómo se lee en un desplegable: «BN — Beni». */
    public function etiqueta(): string
    {
        return "{$this->codigo} — {$this->nombre}";
    }

    /**
     * El código de un departamento por su id: «BN».
     *
     * Va contra un MAPA memorizado y no contra la relación a propósito. El
     * código lo necesita `Beneficiario::documentoIdentidad()`, que se usa en
     * cada fila de cada listado: con la relación habría que acordarse de
     * `with('beneficiario.departamento')` en trece consultas, y el día que
     * alguien lo olvide aparece un N+1 en silencio. Son nueve filas que no
     * cambian nunca, así que se leen UNA vez por petición y se guardan.
     */
    public static function codigoDe(?int $id): ?string
    {
        return $id === null ? null : (self::mapa()[$id] ?? null);
    }

    /**
     * id => código, leído una sola vez por petición.
     *
     * @return array<int, string>
     */
    public static function mapa(): array
    {
        return self::$mapa ??= self::query()->pluck('codigo', 'id')->all();
    }

    /** @var array<int, string>|null */
    private static ?array $mapa = null;

    /**
     * Los nueve, como los quiere un `<select>`.
     *
     * @return array<int, array{value: int, label: string}>
     */
    public static function opciones(): array
    {
        return self::query()
            ->orderBy('nombre')
            ->get()
            ->map(fn (self $d): array => [
                // El VALOR es el id: es lo que guarda `beneficiarios`.
                'value' => $d->id,
                'label' => $d->etiqueta(),
            ])
            ->all();
    }
}
