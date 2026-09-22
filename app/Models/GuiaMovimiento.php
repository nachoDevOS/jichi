<?php

namespace App\Models;

use App\Enums\EstadoGuia;
use App\Enums\MedioTransporte;
use App\Enums\TipoTransporte;
use App\Services\CorrelativoService;
use App\Traits\Auditable;
use App\Traits\Codificable;
use App\Traits\Pagable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * El amparo de UN traslado de producto pesquero.
 */
#[Fillable([
    'carnet_id',
    'asociacion_id',
    'numero_guia',
    'monto',
    'origen',
    'origen_departamento',
    'origen_provincia',
    'origen_distrito',
    'destino',
    'destino_departamento',
    'destino_provincia',
    'destino_distrito',
    'medio_transporte',
    'tipo_transporte',
    'transporte_nombre',
    'transporte_placa',
    'transporte_capacidad_kg',
    'peso_total_kg',
    'es_piscicultura',
    'observaciones',
    'estado',
    'fecha_solicitud',
    'fecha_emision',
    'fecha_vencimiento',
])]
class GuiaMovimiento extends Model
{
    use Auditable, Codificable, Pagable, SoftDeletes;

    protected $table = 'guias_movimiento';

    /** VIGENCIA MÁXIMA de una guía, en días. Regla de la resolución, no del formulario. */
    public const DIAS_VIGENCIA = 5;

    /** Lo que se descuenta al producto de criadero. 0.50 = la mitad del arancel. */
    public const DESCUENTO_PISCICULTURA = 0.50;

    /** La serie del correlativo global del talonario. Ver CorrelativoService. */
    public const SERIE = 'GUIA-TRANSPORTE';

    /** Ver Asociacion::$attributes: los defaults de la base no llegan al create(). */
    protected $attributes = [
        'peso_total_kg' => 0,
        'monto' => 0,
        'es_piscicultura' => false,
        'estado' => EstadoGuia::Pendiente->value,
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'peso_total_kg' => 'decimal:2',
            'transporte_capacidad_kg' => 'decimal:2',
            'es_piscicultura' => 'boolean',
            'estado' => EstadoGuia::class,
            'medio_transporte' => MedioTransporte::class,
            'tipo_transporte' => TipoTransporte::class,
            'fecha_solicitud' => 'date',
            'fecha_emision' => 'datetime',
            'fecha_vencimiento' => 'datetime',
        ];
    }

    //  Relaciones

    /** El carnet de comercializador que la ampara. Es su ÚNICA clave hacia la persona. */
    public function carnet(): BelongsTo
    {
        return $this->belongsTo(Carnet::class, 'carnet_id');
    }

    /**
     * El aval impreso. Se COPIA al emitir y no se lee del carnet cada vez: un
     * cambio de gremio posterior no puede reescribir el papel entregado.
     */
    public function asociacion(): BelongsTo
    {
        return $this->belongsTo(Asociacion::class);
    }

    /** Los renglones del cuadro D. */
    public function detalles(): HasMany
    {
        return $this->hasMany(GuiaDetalle::class, 'guia_movimiento_id');
    }

    /**
     * Quien comercializa, alcanzado A TRAVÉS del carnet.
     *
     * No es una relación: la guía no guarda un beneficiario suelto. Quien lo
     * use en un listado carga `carnet.beneficiario` en el `with()`, o son dos
     * consultas por fila.
     */
    public function comercializador(): ?Beneficiario
    {
        return $this->carnet?->beneficiario;
    }

    /**
     * El titular, con el nombre de columna que espera `CobrarService`.
     *
     * Ver PermisoFaena::beneficiarioId(): el accesor deja a la guía hablando
     * el mismo idioma que el carnet sin duplicar la clave en la tabla.
     */
    protected function beneficiarioId(): Attribute
    {
        return Attribute::get(fn (): ?int => $this->carnet?->beneficiario_id);
    }

    //  Lectura

    /** «Trinidad → Santa Cruz», como se lee de un vistazo en el listado. */
    protected function ruta(): Attribute
    {
        return Attribute::get(fn (): string => $this->origen.' → '.$this->destino);
    }

    /** Cómo se lee en un listado: «Guía N° 000308», los seis dígitos del papel. */
    protected function etiqueta(): Attribute
    {
        return Attribute::get(fn (): string => 'Guía N° '.$this->numeroLegible);
    }

    /** El correlativo con el relleno del talonario: 000308. */
    protected function numeroLegible(): Attribute
    {
        return Attribute::get(
            fn (): string => CorrelativoService::rellenar($this->numero_guia),
        );
    }

    //  El circuito: cobrar, presentar y firmar

    /**
     *  EL ÚNICO LUGAR DONDE VIVE EL DESCUENTO DE PISCICULTURA
     */
    public function factorArancel(): float
    {
        return $this->es_piscicultura ? 1.0 - self::DESCUENTO_PISCICULTURA : 1.0;
    }

    /** Lo que sale esta guía a la tarifa dada, con el descuento ya aplicado. */
    public function arancelCalculado(float $tarifaBase): float
    {
        return round($tarifaBase * $this->factorArancel(), 2);
    }

    /**
     * Lo que se cobra por ESTA guía. Exigido por el trait Pagable.
     *
     * Sale de la COLUMNA y no de la config: el arancel se copia al emitir, así
     * que una suba por resolución no mueve el monto de un papel entregado.
     */
    public function montoACobrar(): float
    {
        return (float) ($this->monto ?? $this->arancelCalculado(self::tarifaVigente()));
    }

    /** Lo que se cobra HOY por una guía nueva. La copia la hace el servicio. */
    public static function tarifaVigente(): float
    {
        return (float) config('jichi.guias.tarifa_base', 0);
    }

    /** ¿Se le pueden cargar depósitos hoy? Exigido por el trait Pagable. */
    public function admitePagos(): bool
    {
        return $this->estado->admitePagos();
    }

    /**
     * ¿Se pueden corregir sus datos?
     *
     * El estado no alcanza: un depósito ya cargado significa que el
     * comerciante pagó por ESTE traslado, y mover el peso o la piscicultura
     * después cambiaría lo que se cobró. Se da de baja el depósito primero.
     */
    public function puedeEditarse(): bool
    {
        return $this->estado->permiteEdicion() && $this->montoPagado() <= 0.0;
    }

    /** ¿Se puede borrar la fila entera? Mismo corte que la edición. */
    public function puedeEliminarse(): bool
    {
        return $this->estado->permiteEliminacion() && $this->montoPagado() <= 0.0;
    }

    /** ¿Se puede presentar a revisión? Estado Y arancel cubierto. */
    public function puedeEnviarseARevision(): bool
    {
        return $this->estado->permiteEnvio() && $this->estaPagado();
    }

    /** ¿Está sobre la mesa de quien firma? */
    public function puedeRevisarse(): bool
    {
        return $this->estado->permiteRevision();
    }

    /** ¿Ya pasó por la firma? Es lo que habilita a imprimir el papel. */
    public function yaFueAprobada(): bool
    {
        return ! $this->estado->estaAbierto();
    }

    /** ¿Se pueden CONTROLAR sus boletas? Solo con la guía presentada. */
    public function admiteControlDePagos(): bool
    {
        return $this->estado === EstadoGuia::EnRevision;
    }

    /** Corregir se habilita antes: es lo único que levanta una observación. */
    public function admiteCorreccionDePagos(): bool
    {
        return $this->estado->estaAbierto();
    }

    /**
     * Por qué esta guía todavía no ampara un traslado. Null cuando sí ampara.
     *
     * Se resuelve en el SERVIDOR y se manda resuelto: React no vuelve a
     * evaluar el estado, que es como nacen las pantallas que mienten.
     */
    public function motivoSinAmparar(): ?string
    {
        if ($this->estaVigente()) {
            return null;
        }

        return match (true) {
            $this->estado === EstadoGuia::Pendiente => 'La guía está PENDIENTE: falta cubrir el '.
                'arancel y enviarla a revisión.',
            $this->estado === EstadoGuia::EnRevision => 'La guía está presentada y esperando la '.
                'firma de quien la aprueba.',
            $this->estado === EstadoGuia::Cerrada => 'La carga ya llegó a destino y se descargó.',
            $this->estado === EstadoGuia::Anulada => 'La guía fue anulada.',
            default => 'Pasó su fecha de vencimiento.',
        };
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

    /** Horas que le quedan de validez. Negativo si ya venció, null sin firmar. */
    public function horasRestantes(): ?int
    {
        return $this->fecha_vencimiento === null
            ? null
            : (int) floor(now()->diffInHours($this->fecha_vencimiento, false));
    }

    /**
     * Los kilos que declara el cuadro D.
     *
     * Se pregunta si la CLAVE EXISTE y no si el valor es null: `withSum()`
     * devuelve NULL sobre un conjunto vacío, así que justamente la guía sin
     * detalle se caería a la consulta suelta. Ver CLAUDE.md.
     */
    public function kilosDelDetalle(): float
    {
        if (array_key_exists('detalles_sum_cantidad_kg', $this->getAttributes())) {
            return (float) $this->detalles_sum_cantidad_kg;
        }

        if ($this->relationLoaded('detalles')) {
            return (float) $this->detalles->sum('cantidad_kg');
        }

        return (float) $this->detalles()->sum('cantidad_kg');
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

    /** Las de una persona: se llega por el carnet, que es quien la conoce. */
    public function scopeDeComercializador(Builder $query, int $beneficiarioId): Builder
    {
        return $query->whereHas(
            'carnet',
            fn (Builder $c) => $c->where('carnets.beneficiario_id', $beneficiarioId),
        );
    }
}
