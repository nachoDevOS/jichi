<?php

namespace App\Models;

use App\Enums\EstadoFaena;
use App\Services\CorrelativoService;
use App\Traits\Auditable;
use App\Traits\Codificable;
use App\Traits\Pagable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * La autorización de UNA salida de pesca.
 */
#[Fillable([
    'carnet_id',
    'numero_faena',
    'monto',
    'kilos_extraidos',
    'embarcacion',
    'propietario',
    'comandante_barco',
    'matricula_naval',
    'nro_kardex',
    'region_desde',
    'region_hasta',
    'fecha_solicitud',
    'fecha_salida',
    'fecha_desembarque',
    'fecha_limite',
    'fecha_emision',
    'estado',
])]
class PermisoFaena extends Model
{
    use Auditable, Codificable, Pagable, SoftDeletes;

    /** «PermisoFaena» pluraliza a «permiso_faenas», que no es la tabla. */
    protected $table = 'permisos_faena';

    /**
     * VIGENCIA MÁXIMA DE UNA FAENA, en días.
     */
    public const DIAS_VIGENCIA = 30;

    /** La serie del correlativo global del talonario. Ver CorrelativoService. */
    public const SERIE = 'PERMISO-FAENA';

    /** Ver Asociacion::$attributes: los defaults de la base no llegan al create(). */
    protected $attributes = [
        'kilos_extraidos' => 0,
        'monto' => 0,
        'estado' => EstadoFaena::Pendiente->value,
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'kilos_extraidos' => 'decimal:2',
            'fecha_solicitud' => 'date',
            'fecha_salida' => 'date',
            'fecha_desembarque' => 'date',
            'fecha_limite' => 'date',
            'fecha_emision' => 'date',
            'estado' => EstadoFaena::class,
        ];
    }

    //  Relaciones

    public function carnet(): BelongsTo
    {
        return $this->belongsTo(Carnet::class, 'carnet_id');
    }

    /**
     * El cupo del que descuenta, alcanzado A TRAVÉS del carnet.
     *
     * No es una relación: la faena no guarda `aprovechamiento_id`. Con las dos
     * claves, un permiso podía apuntar a un cupo distinto del que respalda su
     * carnet. Para no disparar dos consultas por fila, quien lo use en un
     * listado carga `carnet.aprovechamiento` en el `with()`.
     */
    public function cupo(): ?AprovechamientoPesq
    {
        return $this->carnet?->aprovechamiento;
    }

    /**
     * El titular, alcanzado por el carnet.
     *
     * `CobrarService` lo pide como `beneficiario_id` —la columna que tienen el
     * carnet y el cupo— así que el accesor deja a la faena hablando el mismo
     * idioma sin duplicar la clave en la tabla.
     */
    protected function beneficiarioId(): Attribute
    {
        return Attribute::get(fn (): ?int => $this->carnet?->beneficiario_id);
    }

    //  Lectura

    /** Cómo se lee en un listado: «Faena N° 002190», los seis dígitos del papel. */
    protected function etiqueta(): Attribute
    {
        return Attribute::get(fn (): string => 'Faena N° '.$this->numeroLegible);
    }

    /** El correlativo con el relleno del talonario: 000001. */
    protected function numeroLegible(): Attribute
    {
        return Attribute::get(
            fn (): string => CorrelativoService::rellenar($this->numero_faena),
        );
    }

    //  El circuito: cobrar, presentar y firmar

    /**
     * Lo que sale ESTE permiso. Exigido por el trait Pagable.
     *
     * Sale de la COLUMNA y no de la config: el arancel se copia al emitir,
     * así que una suba por resolución no mueve el monto de un papel entregado.
     */
    public function montoACobrar(): float
    {
        return (float) ($this->monto ?? self::tarifaVigente());
    }

    /** Lo que se cobra HOY por una salida nueva. La copia la hace el servicio. */
    public static function tarifaVigente(): float
    {
        return (float) config('jichi.faenas.tarifa_base', 0);
    }

    /** ¿Se le pueden cargar depósitos hoy? Exigido por el trait Pagable. */
    public function admitePagos(): bool
    {
        return $this->estado->permitePagos();
    }

    /**
     * ¿Se pueden corregir sus datos?
     *
     * El estado no alcanza: un depósito ya cargado significa que el pescador
     * pagó por ESTA salida, y mover los kilos o las fechas después cambiaría
     * lo que se cobró. Se da de baja el depósito primero.
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

    /** ¿Ya pasó por la firma? Es lo que habilita a salir a pescar. */
    public function yaFueAprobada(): bool
    {
        return ! $this->estado->estaAbierto();
    }

    /** ¿Se pueden CONTROLAR sus boletas? Solo con la faena presentada. */
    public function admiteControlDePagos(): bool
    {
        return $this->estado === EstadoFaena::EnRevision;
    }

    /** Corregir se habilita antes: es lo único que levanta una observación. */
    public function admiteCorreccionDePagos(): bool
    {
        return $this->estado->estaAbierto();
    }

    /**
     * Por qué esta faena todavía no autoriza a salir. Null cuando sí autoriza.
     *
     * Se resuelve en el SERVIDOR y se manda resuelto: React no vuelve a
     * evaluar el estado, que es como nacen las pantallas que mienten.
     */
    public function motivoSinAutorizar(): ?string
    {
        if ($this->estaVigente()) {
            return null;
        }

        return match (true) {
            $this->estado === EstadoFaena::Pendiente => 'La faena está PENDIENTE: falta cubrir el '.
                'arancel y enviarla a revisión.',
            $this->estado === EstadoFaena::EnRevision => 'La faena está presentada y esperando la '.
                'firma de quien la aprueba.',
            $this->estado === EstadoFaena::Completado => 'La salida ya se cerró: los kilos quedaron '.
                'firmes contra el cupo.',
            $this->estado === EstadoFaena::Vencido => 'Se pasó su fecha límite sin cerrarse.',
            default => 'Pasó su fecha límite.',
        };
    }

    //  Reglas de negocio

    /**
     * La fecha límite que corresponde a una salida.
     */
    public static function limiteDesde(Carbon|string $salida): Carbon
    {
        return Carbon::parse($salida)->addDays(self::DIAS_VIGENCIA)->startOfDay();
    }

    /**
     * ¿Autoriza a estar pescando HOY?
     *
     * Estado Y fecha, por lo mismo de siempre: `vencido` lo escribe un comando
     * diario y entre corrida y corrida la columna miente.
     */
    public function estaVigente(): bool
    {
        return $this->estado->habilita()
            && $this->fecha_limite !== null
            && $this->fecha_limite->endOfDay()->isFuture();
    }

    /** ¿Se pasó de fecha sin cerrarse? Es lo que busca el comando diario. */
    public function estaCaducada(): bool
    {
        return $this->estado === EstadoFaena::Activo
            && $this->fecha_limite !== null
            && $this->fecha_limite->endOfDay()->isPast();
    }

    /** ¿Sus kilos pesan contra el cupo de la bolsa madre? */
    public function consumeCupo(): bool
    {
        return $this->estado->consumeCupo();
    }

    //  Scopes

    /**
     * Las que autorizan hoy. Se califica la columna porque `permisos_faena`,
     * `carnets` y `aprovechamientos_pesq` tienen todas una columna `estado` y
     * los listados las cruzan con join.
     */
    public function scopeVigentes(Builder $query): Builder
    {
        return $query
            ->where($this->qualifyColumn('estado'), EstadoFaena::Activo)
            ->whereDate($this->qualifyColumn('fecha_limite'), '>=', now()->toDateString());
    }

    /** Las que pesan contra el cupo: las firmadas y las cerradas. */
    public function scopeQueConsumenCupo(Builder $query): Builder
    {
        return $query->whereIn($this->qualifyColumn('estado'), [
            EstadoFaena::Activo,
            EstadoFaena::Completado,
        ]);
    }

    /** Las de un cupo: se llega por el carnet, que es quien lo conoce. */
    public function scopeDelCupo(Builder $query, int $aprovechamientoId): Builder
    {
        return $query->whereHas(
            'carnet',
            fn (Builder $c) => $c->where('carnets.aprovechamiento_id', $aprovechamientoId),
        );
    }
}
