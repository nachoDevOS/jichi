<?php

namespace App\Models;

use App\Enums\EstadoGuia;
use App\Traits\Auditable;
use App\Traits\Pagable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * El amparo de UN traslado de producto pesquero.
 */
#[Fillable([
    'beneficiario_com_id',
    'asociacion_id',
    'codigo_guia',
    'origen',
    'destino',
    'peso_total_kg',
    'es_piscicultura',
    'estado',
    'fecha_emision',
    'fecha_vencimiento',
])]
class GuiaMovimiento extends Model
{
    use Auditable, Pagable, SoftDeletes;

    protected $table = 'guias_movimiento';

    /** VIGENCIA MÁXIMA de una guía, en días. Regla de la resolución, no del formulario. */
    public const DIAS_VIGENCIA = 5;

    /** Lo que se descuenta al producto de criadero. 0.50 = la mitad del arancel. */
    public const DESCUENTO_PISCICULTURA = 0.50;

    /** Ver Asociacion::$attributes: los defaults de la base no llegan al create(). */
    protected $attributes = [
        'peso_total_kg' => 0,
        'es_piscicultura' => false,
        'estado' => EstadoGuia::Activa->value,
    ];

    protected function casts(): array
    {
        return [
            'peso_total_kg' => 'decimal:2',
            'es_piscicultura' => 'boolean',
            'estado' => EstadoGuia::class,
            'fecha_emision' => 'datetime',
            'fecha_vencimiento' => 'datetime',
        ];
    }

    //  Relaciones

    /** Quien comercializa. La clave va explícita: la columna no sigue la convención. */
    public function comercializador(): BelongsTo
    {
        return $this->belongsTo(Beneficiario::class, 'beneficiario_com_id');
    }

    public function asociacion(): BelongsTo
    {
        return $this->belongsTo(Asociacion::class);
    }

    //  Lectura

    /** «Trinidad → Santa Cruz», como se lee de un vistazo en el listado. */
    protected function ruta(): Attribute
    {
        return Attribute::get(fn (): string => $this->origen.' → '.$this->destino);
    }

    //  Reglas de negocio

    /**
     * El vencimiento que corresponde a una emisión.
     */
    public static function vencimientoDesde(Carbon|string $emision): Carbon
    {
        return Carbon::parse($emision)->addDays(self::DIAS_VIGENCIA);
    }

    /**
     *  EL ÚNICO LUGAR DONDE VIVE EL DESCUENTO DE PISCICULTURA
     */
    public function factorArancel(): float
    {
        return $this->es_piscicultura ? 1.0 - self::DESCUENTO_PISCICULTURA : 1.0;
    }

    /** Lo que sale esta guía, con el descuento ya aplicado si corresponde. */
    public function arancelCalculado(float $tarifaBase): float
    {
        return round($tarifaBase * $this->factorArancel(), 2);
    }

    /**
     * Lo que se cobra por esta guía. Exigido por el trait Pagable.
     */
    public function montoACobrar(): float
    {
        return $this->arancelCalculado((float) config('jichi.guias.tarifa_base', 0));
    }

    /**
     * ¿Ampara un traslado HOY?
     *
     * Estado Y fecha: `cerrada` la escribe un comando diario, así que entre
     * corrida y corrida la columna puede estar desfasada.
     */
    public function estaVigente(): bool
    {
        return $this->estado->habilita()
            && $this->fecha_vencimiento !== null
            && $this->fecha_vencimiento->isFuture();
    }

    /** ¿Se pasó de fecha sin cerrarse? Es lo que busca el comando diario. */
    public function estaCaducada(): bool
    {
        return $this->estado === EstadoGuia::Activa
            && $this->fecha_vencimiento !== null
            && $this->fecha_vencimiento->isPast();
    }

    /** Horas que le quedan de validez. Negativo si ya venció. */
    public function horasRestantes(): ?int
    {
        return $this->fecha_vencimiento === null
            ? null
            : (int) floor(now()->diffInHours($this->fecha_vencimiento, false));
    }

    //  Scopes

    /**
     * Las que amparan un traslado hoy. Se califica la columna porque
     * `guias_movimiento`, `asociaciones` y `carnets` tienen todas una columna
     * `estado` y los reportes las cruzan con join.
     */
    public function scopeVigentes(Builder $query): Builder
    {
        return $query
            ->where($this->qualifyColumn('estado'), EstadoGuia::Activa)
            ->where($this->qualifyColumn('fecha_vencimiento'), '>=', now());
    }

    public function scopeDePiscicultura(Builder $query, bool $si = true): Builder
    {
        return $query->where($this->qualifyColumn('es_piscicultura'), $si);
    }

    public function scopeDeComercializador(Builder $query, int $beneficiarioId): Builder
    {
        return $query->where($this->qualifyColumn('beneficiario_com_id'), $beneficiarioId);
    }
}
