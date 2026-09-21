<?php

namespace App\Models;

use App\Enums\EstadoValidacionPago;
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
 */
#[Appends(['comprobante_url'])]
#[Fillable([
    'recibo_id',
    'registrado_por',
    'validado_por',
    'pagable_type',
    'pagable_id',
    'monto_parcial',
    'nro_transaccion',
    'fecha_deposito',
    'comprobante',
    'estado_validacion',
    'observacion',
    'validado_en',
])]
class Pago extends Model
{
    use Auditable, SoftDeletes;

    /**
     * El default de la BASE no llega al objeto que devuelve `create()`. Va con
     * `->value` porque `$attributes` se llena antes de los casts.
     */
    protected $attributes = [
        'estado_validacion' => EstadoValidacionPago::Pendiente->value,
    ];

    protected function casts(): array
    {
        return [
            'monto_parcial' => 'decimal:2',
            // Un DÍA, no un instante: es lo que dice la boleta.
            'fecha_deposito' => 'date',
            'estado_validacion' => EstadoValidacionPago::class,
            // Un MOMENTO: cuándo alguien lo miró. Va a React con toIso8601String().
            'validado_en' => 'datetime',
        ];
    }

    //  Relaciones

    public function recibo(): BelongsTo
    {
        return $this->belongsTo(Recibo::class);
    }

    /** Quién cargó el depósito en el mostrador. */
    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    /** Quién comparó la boleta contra el extracto del banco. */
    public function validadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validado_por');
    }

    /**
     * El trámite que este abono paga: un Carnet, un AprovechamientoPesq o una
     * GuiaMovimiento.
     */
    public function pagable(): MorphTo
    {
        return $this->morphTo();
    }

    //  Lectura

    /**
     * La dirección completa de la boleta, o null si no hay.
     */
    protected function comprobanteUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => Archivos::url($this->comprobante));
    }

    /**
     * Cómo se nombra el trámite pagado en el detalle del recibo.
     */
    protected function conceptoDetalle(): Attribute
    {
        return Attribute::get(fn (): string => match (true) {
            // «Cédula de Pescador», igual que el concepto del recibo y que la
            // casilla CÉDULAS del talonario. Decía «Credencial», que no es
            // como se la nombra en el mostrador.
            $this->pagable instanceof Carnet => 'Cédula de '.$this->pagable->tipo_actor->etiqueta(),
            // Con la capacidad: en el cuadro de importes es lo que distingue
            // un cobro de otro. El renglón es angosto, así que va el nombre
            // corto del documento y los kilos.
            $this->pagable instanceof AprovechamientoPesq => 'Autorización de Pesca · '
                .number_format((float) $this->pagable->volumen_total_kg, 0, ',', '.').' kg',
            $this->pagable instanceof GuiaMovimiento => 'Guía de movimiento',
            default => 'Trámite',
        });
    }

    //  El control de la boleta

    /**
     * ¿Se puede validar u observar? Que nadie lo haya mirado Y que el trámite
     * esté en revisión: el control es parte de la revisión.
     */
    public function admiteControl(): bool
    {
        return $this->estado_validacion->admiteControl()
            && ($this->pagable?->admiteControlDePagos() ?? false);
    }

    /**
     * ¿Se puede corregir? Lo decide el trámite: lo que cierra la puerta es que
     * el expediente ya esté firmado.
     */
    public function admiteCorreccion(): bool
    {
        return $this->pagable?->admiteCorreccionDePagos() ?? false;
    }

    /** Lo que muestra la ficha: quién lo controló y cuándo, en una frase. */
    public function estaControlado(): bool
    {
        return $this->estado_validacion->estaControlado();
    }

    //  Scopes

    /** Los que no están dados por buenos. Frena la aprobación. */
    public function scopeSinValidar(Builder $query): Builder
    {
        return $query->where(
            $this->qualifyColumn('estado_validacion'),
            '!=',
            EstadoValidacionPago::Validado->value,
        );
    }

    /**
     * Los abonos de un trámite concreto.
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
     */
    public function scopeDepositadosEl(Builder $query, ?string $fecha = null): Builder
    {
        return $query->whereDate(
            $this->qualifyColumn('fecha_deposito'),
            $fecha ?? now()->toDateString(),
        );
    }
}
