<?php

namespace App\Models;

use App\Support\Archivos;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un depósito bancario aplicado a un trámite.
 *
 * ----------------------------------------------------------------------------
 *  UNO A VARIOS, Y POR QUÉ
 * ----------------------------------------------------------------------------
 *
 * El pescador puede depositar todo junto o en cuotas, y cada depósito llega con
 * su propia boleta del banco. Un solo campo `monto_pagado` en el trámite
 * obligaría a que el operador sumara a mano antes de escribir, y perdería el
 * comprobante de cada parte: cuando alguien reclame, no habría forma de mostrar
 * qué boleta respalda qué monto.
 *
 * NO SE ANULAN NI SE BORRAN. La tabla no tiene `estado` ni `deleted_at` a
 * propósito: una boleta cargada mal se corrige editando la fila, y el trait
 * Auditable deja registrado el valor anterior, quién lo cambió y cuándo. Un
 * pago «anulado» que sigue en la lista solo invita a sumarlo por error.
 */
#[Appends(['comprobante_url'])]
#[Fillable([
    'tramite_id',
    'nro_transaccion',
    'monto',
    'urlFile',
    'fecha_pago',
    'observaciones',
])]
class Pago extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'fecha_pago' => 'datetime',
        ];
    }

    public function tramite(): BelongsTo
    {
        return $this->belongsTo(Tramite::class);
    }

    /**
     * La dirección para abrir la boleta escaneada.
     *
     * Pasa por Archivos::url() y no por Storage::url() porque la columna guarda
     * una ruta cuando el disco es local y una dirección completa cuando es s3, y
     * las dos formas conviven en la misma tabla.
     */
    protected function comprobanteUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => Archivos::url($this->urlFile));
    }
}
