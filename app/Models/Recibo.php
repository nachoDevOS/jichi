<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * La CABECERA del comprobante oficial de caja.
 *
 * ============================================================================
 *  UN RECIBO, VARIOS PAGOS, POSIBLEMENTE DE TRÁMITES DISTINTOS
 * ============================================================================
 *
 *     recibo 0016 (180 Bs)  ──< pago 80 Bs  → carnet
 *                           ──< pago 100 Bs → aprovechamiento
 *
 * Esa es la unidad del comprobante: la persona entrega la plata UNA vez y se
 * lleva UN papel, aunque adentro esté pagando dos cosas. Por eso el detalle es
 * polimórfico y la cabecera no sabe a qué trámite pertenece — no pertenece a
 * ninguno en particular.
 *
 * ============================================================================
 *  TODO LO IMPRESO SE COPIA, PORQUE UN COMPROBANTE ES INMUTABLE
 * ============================================================================
 *
 * `nombre_factura`, `nit_ci_factura` y `monto_total` se guardan acá en vez de
 * leerse del beneficiario y de la suma de los pagos. Armado al vuelo, corregir
 * un apellido en la ficha cambiaría los comprobantes ya entregados y una
 * reimpresión de marzo saldría distinta de la original.
 *
 * Y además el comprobante puede ir a nombre de un TERCERO —la empresa que paga
 * por el pescador—, que no es ningún dato de la ficha.
 */
#[Fillable([
    'numero_recibo',
    'monto_total',
    'concepto',
    'nit_ci_factura',
    'nombre_factura',
])]
class Recibo extends Model
{
    use Auditable, SoftDeletes;

    /** Ver Asociacion::$attributes: los defaults de la base no llegan al create(). */
    protected $attributes = [
        'monto_total' => 0,
    ];

    protected function casts(): array
    {
        return [
            'monto_total' => 'decimal:2',
        ];
    }

    // ------------------------------------------------------------------
    //  Relaciones
    // ------------------------------------------------------------------

    /**
     * El detalle: los abonos que este papel ampara.
     *
     * OJO AL RECORRERLO PARA IMPRIMIR: `pagable` es una relación POLIMÓRFICA y
     * NO se puede precargar con `with('pagos.pagable.beneficiario')`. Eloquent
     * no sabe qué es `pagable` hasta que lee la fila, así que lo escrito así se
     * ignora en silencio y el N+1 sigue ahí. Va con `morphWith`, declarando qué
     * traer para cada tipo. Ver Pago::pagable().
     */
    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class);
    }

    // ------------------------------------------------------------------
    //  Reglas de negocio
    // ------------------------------------------------------------------

    /**
     * La suma de lo que HOY cuelga de este recibo.
     *
     * NO es lo mismo que `monto_total`, y la diferencia es el punto: la columna
     * es lo que se IMPRIMIÓ y este método es lo que HAY. Si alguien corrigió un
     * abono después de emitir el papel, los dos números se separan — y eso es
     * justamente lo que un arqueo tiene que poder detectar.
     */
    public function montoCalculado(): float
    {
        if ($this->relationLoaded('pagos')) {
            return (float) $this->pagos->sum('monto_parcial');
        }

        return (float) $this->pagos()->sum('monto_parcial');
    }

    /** ¿Lo impreso coincide con lo que hay? Con un céntimo de tolerancia por el redondeo. */
    public function cuadra(): bool
    {
        return abs($this->montoCalculado() - (float) $this->monto_total) < 0.01;
    }

    /**
     * Recalcula y guarda el total a partir del detalle.
     *
     * Se llama al CERRAR el recibo, antes de imprimirlo — nunca al leerlo. Una
     * vez que el papel salió, este método no se vuelve a tocar: para eso está
     * `cuadra()`, que informa la diferencia en vez de taparla.
     */
    public function recalcularTotal(): static
    {
        $this->forceFill(['monto_total' => $this->montoCalculado()])->save();

        return $this;
    }

    // ------------------------------------------------------------------
    //  Scopes
    // ------------------------------------------------------------------

    /** Para el arqueo del día. */
    public function scopeDelDia(Builder $query, ?string $fecha = null): Builder
    {
        return $query->whereDate(
            $this->qualifyColumn('created_at'),
            $fecha ?? now()->toDateString(),
        );
    }

    public function scopeOrdenDeSerie(Builder $query): Builder
    {
        return $query->orderBy($this->qualifyColumn('numero_recibo'));
    }
}
