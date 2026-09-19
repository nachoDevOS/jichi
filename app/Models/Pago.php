<?php

namespace App\Models;

use App\Support\Archivos;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Un abono: una entrega de dinero, contra un trámite y bajo un recibo.
 *
 * ============================================================================
 *  ES POLIMÓRFICA PORQUE EL NÚMERO DE RECIBO ES ÚNICO GLOBAL
 * ============================================================================
 *
 * Se cobran tres cosas —la credencial, el cupo de pesca y la guía de traslado—
 * y las tres se pagan igual. Una tabla de pagos por cada una obligaría a
 * repetir el circuito de caja tres veces, y peor: el mismo papel podría amparar
 * un carnet y una guía sin que nada lo impida.
 *
 * EL COSTO, Y HAY QUE TENERLO PRESENTE: SE PIERDE LA CLAVE FORÁNEA. El motor no
 * puede exigir que `pagable_id` exista, porque no sabe en qué tabla buscarlo.
 * La integridad la sostienen los RESTRICT de las otras tablas y la aplicación.
 *
 * ============================================================================
 *  `monto_parcial` SE LLAMA ASÍ PORQUE LA REGLA ES QUE PUEDE SER PARCIAL
 * ============================================================================
 *
 * Un carnet de 80 Bs admite dos filas de 40, cada una con su recibo y su fecha.
 * Lo que se DEBE no se guarda en ninguna columna: es el precio menos la suma de
 * estas filas, y lo calcula el trait Pagable al leer. Guardado, quedaría
 * desfasado en cuanto alguien corrija un abono.
 */
#[Appends(['comprobante_url'])]
#[Fillable([
    'recibo_id',
    'pagable_type',
    'pagable_id',
    'monto_parcial',
    'nro_transaccion',
    'fecha_deposito',
    'comprobante',
])]
class Pago extends Model
{
    use Auditable, SoftDeletes;

    protected function casts(): array
    {
        return [
            'monto_parcial' => 'decimal:2',
            // Un DÍA, no un instante: es lo que dice la boleta.
            'fecha_deposito' => 'date',
        ];
    }

    // ------------------------------------------------------------------
    //  Relaciones
    // ------------------------------------------------------------------

    public function recibo(): BelongsTo
    {
        return $this->belongsTo(Recibo::class);
    }

    /**
     * El trámite que este abono paga: un Carnet, un AprovechamientoPesq o una
     * GuiaMovimiento.
     *
     * NO SE PRECARGA CON `with('pagable.beneficiario')`. Eloquent no sabe qué
     * es `pagable` hasta que lee la fila, así que no puede resolver lo que
     * cuelga de él: lo escrito así se IGNORA y el N+1 sigue ahí, sin ningún
     * error. Va con morphWith, declarando qué traer para cada tipo:
     *
     *     Pago::with(['pagable' => fn ($m) => $m->morphWith([
     *         Carnet::class              => ['beneficiario', 'tipoCarnet'],
     *         AprovechamientoPesq::class => ['beneficiario', 'categoria'],
     *         GuiaMovimiento::class      => ['comercializador'],
     *     ])])
     */
    public function pagable(): MorphTo
    {
        return $this->morphTo();
    }

    // ------------------------------------------------------------------
    //  Lectura
    // ------------------------------------------------------------------

    /**
     * La dirección completa de la boleta, o null si no hay.
     *
     * La columna guarda una RUTA; quién la convierte en dirección depende del
     * disco activo, y eso lo sabe App\Support\Archivos —el mismo que la escribe
     * y la borra—. Armada acá a mano, escribir y leer podrían mirar discos
     * distintos.
     */
    protected function comprobanteUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => Archivos::url($this->comprobante));
    }

    /**
     * Cómo se nombra el trámite pagado en el detalle del recibo.
     *
     * El `match` va sobre la CLASE y no sobre el texto de `pagable_type`, que
     * es el mismo dato pero sin que el analizador pueda avisar cuando se agrega
     * un tipo nuevo y este método se olvida.
     */
    protected function conceptoDetalle(): Attribute
    {
        return Attribute::get(fn (): string => match (true) {
            $this->pagable instanceof Carnet => 'Credencial '.$this->pagable->tipo_actor->etiqueta(),
            $this->pagable instanceof AprovechamientoPesq => 'Aprovechamiento pesquero',
            $this->pagable instanceof GuiaMovimiento => 'Guía de movimiento',
            default => 'Trámite',
        });
    }

    // ------------------------------------------------------------------
    //  Scopes
    // ------------------------------------------------------------------

    /**
     * Los abonos de un trámite concreto.
     *
     * Recibe el modelo y no el par (tipo, id) a mano: escrito a mano, el tipo
     * se copia como texto y el día que una clase se renombre o se mueva de
     * namespace la consulta deja de encontrar nada, en silencio.
     */
    public function scopeDe(Builder $query, Model $tramite): Builder
    {
        return $query
            ->where($this->qualifyColumn('pagable_type'), $tramite->getMorphClass())
            ->where($this->qualifyColumn('pagable_id'), $tramite->getKey());
    }

    /** Para el arqueo: lo cobrado en una jornada. */
    public function scopeDelDia(Builder $query, ?string $fecha = null): Builder
    {
        return $query->whereDate(
            $this->qualifyColumn('created_at'),
            $fecha ?? now()->toDateString(),
        );
    }

    /**
     * Los depósitos hechos en una fecha, según lo que dice la BOLETA.
     *
     * Es otra pregunta que `delDia()`, que mira `created_at`: un depósito del
     * viernes cargado el lunes entra en uno y no en el otro. El primero cuadra
     * el trabajo del día; este se cruza contra el extracto del banco.
     */
    public function scopeDepositadosEl(Builder $query, ?string $fecha = null): Builder
    {
        return $query->whereDate(
            $this->qualifyColumn('fecha_deposito'),
            $fecha ?? now()->toDateString(),
        );
    }
}
