<?php

namespace App\Models;

use App\Enums\EstadoAprovechamiento;
use App\Exceptions\PermisoOperativoException;
use App\Services\CorrelativoService;
use App\Traits\Auditable;
use App\Traits\Codificable;
use App\Traits\LiquidableSireb;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * La autorización de pesca del pescador: el volumen anual en kilos, con fecha.
 * Las faenas NO descuentan; las GUÍAS que le compran sí (`kilos_disponibles`).
 */
#[Fillable([
    'beneficiario_id',
    'nro',
    'escala',
    'volumen_total_kg',
    'kilos_disponibles',
    'monto',
    'sireb_tarifa_id',
    'tipo_embarcacion',
    'estado',
    'fecha_solicitud',
    'fecha_emision',
    'fecha_vencimiento',
])]
class AprovechamientoPesq extends Model
{
    use Auditable, Codificable, LiquidableSireb, SoftDeletes;

    /** El historial ya es el registro de cada liquidación: no se copia a `auditorias`. */
    protected $noAuditable = ['sireb_historial'];

    /** «AprovechamientoPesq» no pluraliza a «aprovechamientos_pesq» por sí solo. */
    protected $table = 'aprovechamientos_pesq';

    /** La serie del correlativo continuo del N° impreso. Ver CorrelativoService. */
    public const SERIE = 'AUTORIZACION-PESCA';

    /**
     * Ver el comentario de Asociacion::$attributes: un default de la base NO
     * llega al objeto que devuelve create(), así que otorgar un cupo y
     * preguntarle el estado en la línea siguiente contestaría null.
     */
    protected $attributes = [
        // Nace pendiente: se aprueba cuando SIREB confirma el pago. Ver ConfirmarPagoService.
        'estado' => EstadoAprovechamiento::Pendiente->value,
    ];

    protected function casts(): array
    {
        return [
            'volumen_total_kg' => 'decimal:2',
            'kilos_disponibles' => 'decimal:2',
            'monto' => 'decimal:2',
            'estado' => EstadoAprovechamiento::class,
            'fecha_solicitud' => 'date',
            'fecha_emision' => 'date',
            'fecha_vencimiento' => 'date',
        ];
    }

    //  Relaciones

    public function beneficiario(): BelongsTo
    {
        return $this->belongsTo(Beneficiario::class);
    }

    /**
     * Las faenas de esta autorización, A TRAVÉS de los carnets: `permisos_faena`
     * no guarda `aprovechamiento_id`, la faena cuelga del carnet.
     */
    public function faenas(): HasManyThrough
    {
        return $this->hasManyThrough(
            PermisoFaena::class,
            Carnet::class,
            'aprovechamiento_id',  // FK en carnets
            'carnet_id',           // FK en permisos_faena
            'id',
            'id',
        );
    }

    /** Las guías que le compraron pescado: cada una descontó sus kilos. */
    public function guias(): HasMany
    {
        return $this->hasMany(GuiaMovimiento::class, 'aprovechamiento_id');
    }

    /**
     * Las credenciales que se apoyan en este cupo.
     *
     * Son varias en teoría —el carnet se renueva a mitad de un cupo vigente—
     * aunque en la práctica casi siempre haya una.
     */
    public function carnets(): HasMany
    {
        return $this->hasMany(Carnet::class, 'aprovechamiento_id');
    }

    //  Reglas de negocio

    /**
     * Lo que sale este cupo: el precio de SIREB, congelado al otorgar o corregir.
     */
    public function montoACobrar(): float
    {
        return (float) $this->monto;
    }

    /** El N° de la autorización impresa, con los ceros del talonario: «000001». */
    public function numeroLegible(): string
    {
        return CorrelativoService::rellenar($this->nro);
    }

    /**
     * ¿Se pueden corregir sus datos HOY?
     */
    public function puedeEditarse(): bool
    {
        return $this->estado->permiteEdicion();
    }

    /**
     * ¿Se puede borrar la fila entera?
     */
    public function puedeEliminarse(): bool
    {
        return $this->estado->permiteEliminacion() && $this->faenas()->doesntExist();
    }

    /**
     * ¿Pasó alguna vez por la firma? A revocado se llega desde APROBADO; el
     * pendiente no. Fuera de fecha se reimprime igual.
     */
    public function yaFueAprobado(): bool
    {
        return in_array($this->estado, [EstadoAprovechamiento::Aprobado, EstadoAprovechamiento::Revocado], true);
    }

    /** ¿Se imprime la autorización? Aprobada y no revocada, como el carnet. */
    public function titularSireb(): Beneficiario
    {
        return $this->beneficiario;
    }

    public function itemsSireb(): array
    {
        return [['tarifa_id' => $this->sireb_tarifa_id, 'cantidad' => 1]];
    }

    /** ¿Se imprime la autorización? Aprobada y no revocada, como el carnet. */
    public function puedeImprimirse(): bool
    {
        return $this->yaFueAprobado() && $this->estado !== EstadoAprovechamiento::Revocado;
    }

    /** ¿Se puede revocar hoy? Lo decide el estado; ver `permiteRevocacion()`. */
    public function puedeRevocarse(): bool
    {
        return $this->estado->permiteRevocacion();
    }

    /**
     * ¿Vale HOY?
     */
    public function estaVigente(): bool
    {
        return $this->estado->habilita() && $this->estaEnFecha();
    }

    /**
     *  ¿Está dentro de su período? — sin mirar el estado
     */
    public function estaEnFecha(): bool
    {
        return $this->fecha_vencimiento !== null
            && $this->fecha_vencimiento->endOfDay()->isFuture();
    }

    /**
     * Resta kilos del cupo. Solo sobre la copia bloqueada con lockForUpdate(),
     * dentro de la transacción de la guía: si no alcanza, no toca nada.
     */
    public function descontar(float $kilos): void
    {
        if (round($kilos, 2) > round((float) $this->kilos_disponibles, 2)) {
            throw PermisoOperativoException::sinKilosDisponibles(
                $this->beneficiario?->nombreCompleto ?? 'El pescador',
                (float) $this->kilos_disponibles,
                $kilos,
            );
        }

        $this->update(['kilos_disponibles' => round((float) $this->kilos_disponibles - $kilos, 2)]);
    }

    /** Devuelve kilos de una guía eliminada o corregida, sin pasarse de lo autorizado. */
    public function devolver(float $kilos): void
    {
        $this->update(['kilos_disponibles' => min((float) $this->volumen_total_kg, round((float) $this->kilos_disponibles + $kilos, 2))]);
    }

    /** POR QUÉ no se le puede comprar pescado (guía), o null si sí. */
    public function motivoSinGuia(): ?string
    {
        return match (true) {
            $this->estaVigente() => null,
            $this->estado === EstadoAprovechamiento::Revocado => 'su autorización fue revocada',
            $this->estado === EstadoAprovechamiento::Pendiente => 'su autorización todavía no se pagó',
            default => 'su autorización venció',
        };
    }

    /** ¿Se le puede colgar una faena hoy? Aprobada y en fecha: las faenas no descuentan kilos. */
    public function puedeEmitirFaena(): bool
    {
        return $this->estaVigente();
    }

    /** POR QUÉ no se le puede colgar una faena, o null si sí se puede. */
    public function motivoSinFaena(): ?string
    {
        return match (true) {
            $this->puedeEmitirFaena() => null,
            $this->estado === EstadoAprovechamiento::Revocado => 'No autoriza faenas: la autorización fue revocada.',
            $this->estado === EstadoAprovechamiento::Pendiente => 'Todavía no autoriza carnet ni faenas: falta que se pague en Recaudaciones.',
            default => 'No autoriza faenas: la autorización venció.',
        };
    }

    //  Scopes

    /**
     * Los cupos utilizables hoy. Se califica la columna porque `carnets`,
     * `permisos_faena` y esta tabla tienen todas una columna `estado`: un
     * `where('estado', ...)` sin calificar sobre una consulta con join responde
     * «column reference is ambiguous».
     */
    public function scopeVigentes(Builder $query): Builder
    {
        return $query
            ->where($this->qualifyColumn('estado'), EstadoAprovechamiento::Aprobado)
            ->whereDate($this->qualifyColumn('fecha_vencimiento'), '>=', now()->toDateString());
    }

    /**
     *  Los cupos que ocupan el lugar de una persona hoy
     */
    public function scopeEnCurso(Builder $query): Builder
    {
        return $query
            ->whereIn($this->qualifyColumn('estado'), [
                EstadoAprovechamiento::Pendiente,
                EstadoAprovechamiento::Aprobado,
            ])
            ->whereDate($this->qualifyColumn('fecha_vencimiento'), '>=', now()->toDateString());
    }

    public function scopeDeBeneficiario(Builder $query, int $beneficiarioId): Builder
    {
        return $query->where($this->qualifyColumn('beneficiario_id'), $beneficiarioId);
    }
}
