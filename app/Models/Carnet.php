<?php

namespace App\Models;

use App\Enums\EstadoCarnet;
use App\Enums\TipoActor;
use App\Traits\Auditable;
use App\Traits\Pagable;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * La credencial física que se entrega en ventanilla.
 */
#[Appends(['codigo_legible'])]
#[Fillable([
    'beneficiario_id',
    'asociacion_id',
    'tipo_carnet_id',
    'aprovechamiento_id',
    'tipo_actor',
    'codigo_carnet',
    'estado',
    'fecha_emision',
    'fecha_vencimiento',
])]
class Carnet extends Model
{
    use Auditable, Pagable, SoftDeletes;

    /**
     * Ver el comentario de Asociacion::$attributes: un default de la base NO
     * llega al objeto que devuelve create(). Pasó tres veces en un día en el
     * modelo anterior, y las tres se descubrieron igual: preguntando por el
     * estado justo después del create().
     */
    protected $attributes = [
        'estado' => EstadoCarnet::Activo->value,
    ];

    protected function casts(): array
    {
        return [
            'tipo_actor' => TipoActor::class,
            'estado' => EstadoCarnet::class,
            'fecha_emision' => 'date',
            'fecha_vencimiento' => 'date',
        ];
    }

    //  Relaciones

    public function beneficiario(): BelongsTo
    {
        return $this->belongsTo(Beneficiario::class);
    }

    public function asociacion(): BelongsTo
    {
        return $this->belongsTo(Asociacion::class);
    }

    public function tipoCarnet(): BelongsTo
    {
        return $this->belongsTo(TipoCarnet::class);
    }

    /** La bolsa madre que respalda el cupo impreso. NULL en un comercializador. */
    public function aprovechamiento(): BelongsTo
    {
        return $this->belongsTo(AprovechamientoPesq::class, 'aprovechamiento_id');
    }

    public function faenas(): HasMany
    {
        return $this->hasMany(PermisoFaena::class, 'carnet_id');
    }

    //  Lectura

    /**
     * El código en grupos de cuatro: «PES2 6000 0017».
     */
    protected function codigoLegible(): Attribute
    {
        return Attribute::get(
            fn (): string => trim(chunk_split((string) $this->codigo_carnet, 4, ' ')),
        );
    }

    /** Lo que llega tipeado desde un lector o un buscador, listo para comparar. */
    public static function normalizarCodigo(string $codigo): string
    {
        return Str::upper(preg_replace('/[^A-Za-z0-9]/', '', $codigo) ?? '');
    }

    //  Reglas de negocio

    /**
     * Lo que sale esta credencial: el precio de su tipo.
     */
    public function montoACobrar(): float
    {
        return (float) ($this->tipoCarnet?->precio_bs ?? 0.0);
    }

    /**
     * ¿Vale HOY?
     */
    public function estaVigente(): bool
    {
        return $this->estado->habilita()
            && $this->fecha_vencimiento !== null
            && $this->fecha_vencimiento->endOfDay()->isFuture();
    }

    /**
     * ¿Puede emitir permisos de faena?
     */
    public function puedeEmitirFaenas(): bool
    {
        return $this->tipo_actor->emiteFaenas()
            && $this->estaVigente()
            && $this->aprovechamiento?->puedeEmitirFaena() === true;
    }

    /** ¿Puede emitir guías de movimiento? */
    public function puedeEmitirGuias(): bool
    {
        return $this->tipo_actor->emiteGuias() && $this->estaVigente();
    }

    /**
     * El cupo que se imprime en el plástico, o null si no corresponde.
     */
    public function cupoImpreso(): ?float
    {
        if (! $this->tipo_actor->requiereAprovechamiento()) {
            return null;
        }

        return $this->aprovechamiento === null
            ? null
            : (float) $this->aprovechamiento->volumen_total_kg;
    }

    /** Días que le quedan. Negativo si ya venció; null si no tiene fecha. */
    public function diasParaVencer(): ?int
    {
        return $this->fecha_vencimiento === null
            ? null
            : (int) floor(now()->startOfDay()->diffInDays($this->fecha_vencimiento->startOfDay(), false));
    }

    //  Scopes

    /**
     * Los que valen hoy. La columna se califica porque `carnets`,
     * `asociaciones`, `tipos_carnet` y `aprovechamientos_pesq` tienen todas una
     * columna `estado`, y los listados las cruzan con join: sin calificar,
     * PostgreSQL responde «column reference is ambiguous».
     */
    public function scopeVigentes(Builder $query): Builder
    {
        return $query
            ->where($this->qualifyColumn('estado'), EstadoCarnet::Activo)
            ->whereDate($this->qualifyColumn('fecha_vencimiento'), '>=', now()->toDateString());
    }

    public function scopeDeTipo(Builder $query, TipoActor $tipo): Builder
    {
        return $query->where($this->qualifyColumn('tipo_actor'), $tipo);
    }

    public function scopeDeAsociacion(Builder $query, int $asociacionId): Builder
    {
        return $query->where($this->qualifyColumn('asociacion_id'), $asociacionId);
    }
}
