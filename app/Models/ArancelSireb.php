<?php

namespace App\Models;

use App\Enums\ConceptoArancel;
use App\Traits\Auditable;
use App\Traits\HistorialSireb;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * El servicio de SIREB que cobra un trámite. Solo el servicio: la tarifa se elige
 * al emitir. Se busca por su concepto, nunca por id. Ver docs/modulos/SIREB.md.
 */
#[Fillable(['concepto', 'servicio_sireb'])]
class ArancelSireb extends Model
{
    use Auditable, HistorialSireb, SoftDeletes;

    protected $table = 'aranceles_sireb';

    /** El historial ya ES el registro del cambio: auditarlo lo duplicaría. */
    protected $noAuditable = ['sireb_historial'];

    protected function casts(): array
    {
        return [
            'concepto' => ConceptoArancel::class,
            'sireb_historial' => 'array',
        ];
    }

    /** La fila de un concepto, o null si el seeder no la creó. */
    public static function de(ConceptoArancel $concepto): ?self
    {
        return self::query()->where('concepto', $concepto->value)->first();
    }
}
