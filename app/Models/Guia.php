<?php

namespace App\Models;

use App\Enums\EstadoPermiso;
use App\Enums\TipoTransporte;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * La Guía Única de Transporte de Productos Ictícolas: el papel que acompaña a
 * la carga.
 *
 * ----------------------------------------------------------------------------
 *  LA CABECERA ACÁ, LA CARGA EN `guia_detalles`
 * ----------------------------------------------------------------------------
 *
 * Esta fila dice quién traslada, desde dónde, hasta dónde y en qué. Lo que se
 * traslada —especie por especie, con su condición y sus kilos— vive en
 * `GuiaDetalle`, porque una guía lleva varias y meterlas acá obligaría a un
 * `jsonb` o a columnas numeradas, y con las dos cosas se pierde poder preguntar
 * cuántos kilos de surubí salieron del Beni este año.
 *
 * Cuelga del CARNET de Comercializador, por lo mismo que la faena del de
 * Pescador: así la gestión y la actividad vienen dadas por construcción.
 *
 * Que el carnet emita guías —`rubros.emite_guias`— y que esté vigente lo
 * comprueba el SERVICIO, no este modelo: son reglas que tienen que poder
 * explicarse en castellano en el mostrador.
 */
#[Fillable([
    'carnet_id',
    'nro_guia',
    'nro_recibo',
    'origen_lugar',
    'origen_depto',
    'origen_provincia',
    'origen_distrito',
    'destino_lugar',
    'destino_depto',
    'destino_provincia',
    'destino_distrito',
    'tipo_transporte',
    'transporte_nombre',
    'transporte_placa',
    'capacidad_maxima',
    'observaciones',
    'estado',
])]
class Guia extends Model
{
    use Auditable;

    /**
     * El estado con el que nace, TAMBIÉN EN MEMORIA.
     *
     * La columna ya tiene este mismo valor por defecto en la base, y aun así
     * hace falta declararlo acá: un default de la base lo aplica el INSERT, y el
     * objeto que devuelve `create()` NO se entera —`$permiso->estado` vuelve
     * null hasta que alguien haga `refresh()`—. Eso rompe lo obvio: emitir y
     * preguntar `estaEmitida()` en la misma línea contestaba que no.
     *
     * Va el `->value` y no el caso del enum porque `$attributes` se llena antes
     * de que corran los casts.
     */
    protected $attributes = [
        'estado' => EstadoPermiso::Emitido->value,
    ];

    protected function casts(): array
    {
        return [
            'estado' => EstadoPermiso::class,
            'tipo_transporte' => TipoTransporte::class,
            'capacidad_maxima' => 'decimal:2',
        ];
    }

    // ------------------------------------------------------------------
    //  Relaciones
    // ------------------------------------------------------------------

    public function carnet(): BelongsTo
    {
        return $this->belongsTo(Carnet::class);
    }

    /** El titular, saltando por el carnet. Ver la nota en Faena::beneficiario(). */
    public function beneficiario(): HasOneThrough
    {
        return $this->hasOneThrough(
            Beneficiario::class,
            Carnet::class,
            'id',              // carnets.id
            'id',              // beneficiarios.id
            'carnet_id',       // guias.carnet_id
            'beneficiario_id', // carnets.beneficiario_id
        )->withTrashed();
    }

    /**
     * Las filas de carga.
     *
     * Ordenadas por `id` y no por especie: el orden en que el operador las
     * cargó es el orden en que están escritas en el papel, y la pantalla tiene
     * que coincidir con lo que la persona tiene en la mano.
     */
    public function detalles(): HasMany
    {
        return $this->hasMany(GuiaDetalle::class)->orderBy('id');
    }

    /**
     * Los depósitos con que se pagó esta guía.
     *
     * morphMany, como en `Faena`: `pagos` es polimórfica. Ver la migración
     * `create_pagos_table`.
     */
    public function pagos(): MorphMany
    {
        return $this->morphMany(Pago::class, 'pagable')->orderBy('fecha_pago');
    }

    // ------------------------------------------------------------------
    //  La carga
    // ------------------------------------------------------------------

    /**
     * El total de kilos declarados, sumando todas las filas del detalle.
     *
     * No hay columna que lo guarde, por lo mismo que no hay `monto_pagado` en
     * el trámite: el día que alguien agregue o corrija una fila del detalle sin
     * acordarse de recalcular, la guía diría un total que sus propias líneas
     * contradicen. Sumar al leer no se puede desfasar.
     *
     * Usa la relación cargada cuando ya vino con `with('detalles')`. Sin esa
     * comprobación, `$this->detalles()->sum(...)` CONSULTA IGUAL aunque quien
     * llamó haya hecho el eager loading — es la trampa que ya costó 18
     * consultas por tecleada en el autocompletado de beneficiarios.
     */
    public function totalKg(): float
    {
        if ($this->relationLoaded('detalles')) {
            return (float) $this->detalles->sum(fn (GuiaDetalle $d): float => (float) $d->cantidad_kg);
        }

        return (float) $this->detalles()->sum('cantidad_kg');
    }

    /**
     * ¿La carga declarada entra en el vehículo?
     *
     * Devuelve null cuando no se conoce la capacidad —de una canoa nadie la
     * sabe—, y ahí no se avisa nada: un false en ese caso haría que la pantalla
     * gritara por un dato que nunca se cargó.
     */
    public function excedeCapacidad(): ?bool
    {
        if ($this->capacidad_maxima === null) {
            return null;
        }

        return $this->totalKg() > (float) $this->capacidad_maxima;
    }

    // ------------------------------------------------------------------
    //  Dinero
    // ------------------------------------------------------------------

    /**
     * LO QUE HAY QUE COBRAR POR ESTA GUÍA.
     *
     * No es una columna, y esa es la diferencia con la faena: la faena tiene una
     * tarifa fija por salida, y la guía se cobra sobre el valor de lo que se
     * traslada. El importe sale de las filas del detalle.
     *
     * Se usa `imponible` cuando está cargado, y recién si no está se cae a
     * cantidad × precio. NO al revés: `imponible` es lo que la unidad escribió
     * en el papel, y cuando aplicó una rebaja o redondeó, no coincide con la
     * multiplicación. Recalcular lo pisaría, y el sistema contradiría una guía
     * ya firmada.
     */
    public function montoRequerido(): float
    {
        $detalles = $this->relationLoaded('detalles') ? $this->detalles : $this->detalles()->get();

        return (float) $detalles->sum(fn (GuiaDetalle $d): float => $d->importe());
    }

    /** Lo cobrado hasta ahora. Ver la nota en Faena::montoPagado(). */
    public function montoPagado(): float
    {
        if ($this->pagos_sum_monto !== null) {
            return (float) $this->pagos_sum_monto;
        }

        return (float) $this->pagos()->sum('monto');
    }

    /** Lo que falta pagar. Nunca negativo: el sistema no devuelve dinero. */
    public function saldoPendiente(): float
    {
        return max(0, $this->montoRequerido() - $this->montoPagado());
    }

    /**
     * ¿Está cubierto el importe?
     *
     * Con `>=` y no con `==`, por los centavos de más de un depósito.
     *
     * Ojo con el caso de importe cero —una guía sin precios cargados—: da
     * `true`, y está bien. Esa guía no tiene nada que cobrar, y devolver false
     * la dejaría marcada como impaga para siempre.
     */
    public function estaPagada(): bool
    {
        return $this->montoPagado() >= $this->montoRequerido();
    }

    // ------------------------------------------------------------------
    //  Estado
    // ------------------------------------------------------------------

    public function estaEmitida(): bool
    {
        return $this->estado === EstadoPermiso::Emitido;
    }

    /**
     * ¿Ampara el traslado HOY?
     *
     * La guía no tiene ventana de fechas como la faena —acompaña a la carga y
     * se consume en el viaje—, así que su vigencia es la del carnet que la
     * emitió más que la suya. Se pregunta al carnet en vez de suponer que una
     * guía emitida sigue valiendo: un carnet suspendido en marzo no deja
     * vigentes las guías de febrero.
     */
    public function estaVigente(): bool
    {
        return $this->estado->habilita() && (bool) $this->carnet?->estaVigente();
    }

    // ------------------------------------------------------------------
    //  Scopes
    // ------------------------------------------------------------------

    /** qualifyColumn() por la columna `estado` repetida. Ver la regla 9 de CLAUDE.md. */
    public function scopeEmitidas(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('estado'), EstadoPermiso::Emitido);
    }

    public function scopeAnuladas(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('estado'), EstadoPermiso::Anulado);
    }

    public function scopeDeTransporte(Builder $query, TipoTransporte $tipo): Builder
    {
        return $query->where($query->qualifyColumn('tipo_transporte'), $tipo);
    }

    /**
     * Las guías de una gestión, por la gestión del CARNET y no por la fecha de
     * emisión. Ver la nota en Faena::scopeDeGestion().
     */
    public function scopeDeGestion(Builder $query, ?int $gestion = null): Builder
    {
        $gestion ??= (int) now()->format('Y');

        return $query->whereHas('carnet', fn (Builder $q) => $q->where('gestion', $gestion));
    }
}
