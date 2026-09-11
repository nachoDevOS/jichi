<?php

namespace App\Models;

use App\Enums\FormaPago;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'nro_comprobante',
    'tramite_id',
    'user_id',
    'monto_bruto',
    'descuento',
    'monto',
    'forma_pago',
    'referencia',
    'banco',
    'fecha_pago',
    'estado',
    'pdf_path',
    'observaciones',
    'anulado_por',
    'anulado_at',
    'motivo_anulacion',
])]
class Pago extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'monto_bruto' => 'decimal:2',
            'descuento' => 'decimal:2',
            'monto' => 'decimal:2',
            'forma_pago' => FormaPago::class,
            'fecha_pago' => 'datetime',
            'anulado_at' => 'datetime',
        ];
    }

    public function tramite(): BelongsTo
    {
        return $this->belongsTo(Tramite::class);
    }

    public function cajero(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function anuladoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulado_por');
    }

    public function estaAnulado(): bool
    {
        return $this->estado === 'anulado';
    }

    /*
     * Los scopes califican sus columnas porque los reportes cruzan pagos con
     * tramites, y ambas tablas tienen `estado`.
     */

    public function scopeVigentes(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('estado'), 'pagado');
    }

    public function scopeEntreFechas(Builder $query, string $desde, string $hasta): Builder
    {
        return $query->whereBetween($query->qualifyColumn('fecha_pago'), [
            $desde.' 00:00:00',
            $hasta.' 23:59:59',
        ]);
    }

    public function scopeDelDia(Builder $query, ?string $fecha = null): Builder
    {
        return $query->whereDate($query->qualifyColumn('fecha_pago'), $fecha ?? now()->toDateString());
    }
}
