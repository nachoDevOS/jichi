<?php

namespace App\Traits;

use App\Models\Codigo;
use App\Services\CodigoService;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Str;

/**
 *  Un documento con código de verificación
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
 *  ⚠️ Toda consulta que muestre el código necesita `with('codigo')`, o hace
 *  N+1 en silencio (en `carnets`, con solo serializar: va en `#[Appends]`).
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
     * En grupos de cuatro con guion, «EFGT-96R4-CJ42-AHYJ», para dictarlo y
     * tipearlo. Es solo presentación: la columna guarda los 16 pelados. Null
     * hasta que el documento se emite. Ver NOTAS-CODIGO.
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
