<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'tipo_tramite_id',
    'nombre',
    'descripcion',
    'tipo',
    'valor',
    'respaldo_legal',
    'requiere_documento',
    'activo',
])]
class Exencion extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $table = 'exenciones';

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'requiere_documento' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    public function tipoTramite(): BelongsTo
    {
        return $this->belongsTo(TipoTramite::class);
    }

    /**
     * Descuento en BOB que esta exención aplica sobre un monto bruto.
     */
    public function descuentoSobre(float $montoBruto): float
    {
        $descuento = $this->tipo === 'porcentaje'
            ? $montoBruto * ((float) $this->valor / 100)
            : (float) $this->valor;

        return round(min($descuento, $montoBruto), 2);
    }

    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    /**
     * Exenciones del tipo de trámite indicado más las de alcance general.
     */
    public function scopeAplicablesA(Builder $query, int $tipoTramiteId): Builder
    {
        return $query->where(function (Builder $q) use ($tipoTramiteId) {
            $q->where('tipo_tramite_id', $tipoTramiteId)->orWhereNull('tipo_tramite_id');
        });
    }
}
