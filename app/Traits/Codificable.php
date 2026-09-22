<?php

namespace App\Traits;

use App\Models\Codigo;
use App\Services\CodigoService;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Str;

/**
 *  UN DOCUMENTO CON CÓDIGO DE VERIFICACIÓN
 *
 *  Lo usan los cinco que se entregan: carnet, aprovechamiento, permiso de
 *  faena, guía y recibo. El código vive en la tabla `codigos`, no en una
 *  columna propia. Ver docs/MER.md.
 *
 *  Cómo se lee, y no es intercambiable:
 *    $doc->codigo           -> la FILA de `codigos` (o null)
 *    $doc->codigo?->codigo  -> la tira cruda, para comparar o armar una URL
 *    $doc->codigo_legible   -> «EFGT-96R4-CJ42-AHYJ», para mostrar e imprimir
 *
 *  ⚠️ TODA consulta que vaya a mostrar el código necesita `with('codigo')`.
 *  Sin eso no falla: hace N+1 en silencio, que es la contra conocida de las
 *  relaciones polimórficas en este proyecto. Y `codigo_legible` va en
 *  `#[Appends]`, así que el N+1 aparece con solo serializar el modelo.
 *
 * @mixin Model
 *
 * @property-read Codigo|null $codigo
 * @property-read string|null $codigo_legible
 */
trait Codificable
{
    public function codigo(): MorphOne
    {
        return $this->morphOne(Codigo::class, 'codigable');
    }

    /**
     * En grupos de cuatro, separados por GUION: «EFGT-96R4-CJ42-AHYJ».
     *
     * Dieciséis seguidos no se pueden dictar por teléfono ni tipear de un papel
     * gastado, y de a cuatro sí. El separador es de PRESENTACIÓN —la columna
     * guarda los 16 caracteres pelados—, así que da igual cuál sea:
     * `normalizarCodigo()` borra todo lo que no sea alfanumérico antes de
     * comparar, y se puede tipear con guion, con espacio o sin nada.
     *
     * Guion y no espacio: al copiar y pegar, un espacio se colapsa o se pierde
     * por el camino; el guion viaja entero. Null mientras el documento no tenga
     * código, que es lo que pasa hasta que se emite.
     */
    protected function codigoLegible(): Attribute
    {
        return Attribute::get(function (): ?string {
            $codigo = $this->codigo?->codigo;

            return $codigo === null ? null : trim(chunk_split($codigo, 4, '-'), '-');
        });
    }

    /**
     * Le cuelga su código si no lo tiene. Idempotente: ver CodigoService.
     */
    public function asignarCodigo(): Codigo
    {
        return app(CodigoService::class)->asignar($this);
    }

    /** Lo que llega tipeado desde un lector o un buscador, listo para comparar. */
    public static function normalizarCodigo(string $codigo): string
    {
        return Str::upper(preg_replace('/[^A-Za-z0-9]/', '', $codigo) ?? '');
    }
}
