<?php

namespace App\Models;

use App\Enums\ConceptoArancel;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Una tarifa definida de un arancel: su etiqueta y la tarifa de SIREB que la
 * cobra. La define la unidad en Catálogos › Aranceles › Definir tarifas.
 */
#[Fillable(['arancel_id', 'etiqueta', 'clave', 'sireb_tarifa_id'])]
class ArancelTarifa extends Model
{
    use Auditable, SoftDeletes;

    protected $table = 'arancel_tarifas';

    public function arancel(): BelongsTo
    {
        return $this->belongsTo(ArancelSireb::class, 'arancel_id');
    }

    /** El id de SIREB de una clave del arancel de un trámite, o null. */
    public static function idDe(ConceptoArancel $concepto, string $clave): ?string
    {
        return self::query()
            ->where('clave', $clave)
            ->whereHas('arancel', fn ($q) => $q->where('concepto', $concepto->value))
            ->value('sireb_tarifa_id');
    }
}
