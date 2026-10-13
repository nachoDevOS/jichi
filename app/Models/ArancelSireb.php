<?php

namespace App\Models;

use App\Enums\ConceptoArancel;
use App\Traits\Auditable;
use App\Traits\HistorialSireb;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Un arancel: nombre y servicio de SIREB. El que usa un trámite lleva `concepto`
 * y se busca por él, nunca por id. Ver docs/modulos/SIREB.md.
 */
#[Fillable(['nombre', 'concepto', 'servicio_sireb'])]
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

    public function tarifasDefinidas(): HasMany
    {
        return $this->hasMany(ArancelTarifa::class, 'arancel_id');
    }

    /** Las claves que fija el código (el carnet); null si la unidad arma las suyas. */
    public function clavesFijas(): ?array
    {
        return $this->concepto?->claves();
    }

    /** Si lleva tarifas definidas: el carnet y los que registra la unidad. */
    public function usaTarifasDefinidas(): bool
    {
        return $this->concepto === null || $this->concepto->claves() !== [];
    }

    /** La fila de un concepto, o null si el seeder no la creó. */
    public static function de(ConceptoArancel $concepto): ?self
    {
        return self::query()->where('concepto', $concepto->value)->first();
    }
}
