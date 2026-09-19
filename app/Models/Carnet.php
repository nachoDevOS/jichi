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
 *
 * ============================================================================
 *  EL CARNET ES LA LLAVE ANUAL; CON ÉL SOLO NO SE SALE A TRABAJAR
 * ============================================================================
 *
 *     carnet (pescador)        ──< permisos_faena     (una por salida)
 *     carnet (comercializador) ──< guias_movimiento   (una por traslado)
 *
 * Qué puede emitir lo dice `tipo_actor`, NUNCA el nombre del tipo de carnet:
 * `tipos_carnet` es un catálogo que edita la unidad desde el panel, y el mismo
 * documento figura como «Carnet de Pescador» o «Pescador Artesanal» según quién
 * lo cargó. Ver TipoActor::emiteFaenas() y ::emiteGuias().
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

    // ------------------------------------------------------------------
    //  Relaciones
    // ------------------------------------------------------------------

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

    // ------------------------------------------------------------------
    //  Lectura
    // ------------------------------------------------------------------

    /**
     * El código en grupos de cuatro: «PES2 6000 0017».
     *
     * Se guarda SIN separadores y se muestra con ellos. Un código de catorce
     * caracteres seguidos es imposible de dictar por teléfono o de tipear de un
     * plástico gastado, y los separadores guardados romperían la búsqueda de
     * quien lo escriba sin ellos.
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

    // ------------------------------------------------------------------
    //  Reglas de negocio
    // ------------------------------------------------------------------

    /**
     * Lo que sale esta credencial: el precio de su tipo.
     *
     * Exigido por el trait Pagable. Si la relación no está cargada la consulta
     * sale igual —devolver 0 daría saldo 0 y el sistema creería que está
     * pagado—.
     */
    public function montoACobrar(): float
    {
        return (float) ($this->tipoCarnet?->precio_bs ?? 0.0);
    }

    /**
     * ¿Vale HOY?
     *
     * ------------------------------------------------------------------------
     *  EL ESTADO GUARDADO PUEDE MENTIR, Y POR ESO SE MIRA TAMBIÉN LA FECHA
     * ------------------------------------------------------------------------
     *
     * `vencido` lo escribe un comando programado que corre una vez al día.
     * Entre corrida y corrida, un carnet que venció ayer sigue diciendo
     * «activo» en la base. Ninguna decisión se toma leyendo la columna sola.
     */
    public function estaVigente(): bool
    {
        return $this->estado->habilita()
            && $this->fecha_vencimiento !== null
            && $this->fecha_vencimiento->endOfDay()->isFuture();
    }

    /**
     * ¿Puede emitir permisos de faena?
     *
     * Las tres condiciones son necesarias: que sea de pescador, que el carnet
     * valga hoy, y que tenga una bolsa madre con saldo. Sin la tercera se
     * emitirían faenas sin cupo del que descontarlas.
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
     *
     * NO SE DECIDE CON UN match SOBRE EL NOMBRE DEL TIPO DE CARNET. La pesca se
     * autoriza por volumen —tantos kilos, contrastables contra una guía de
     * transporte—; la comercialización no. De esa distinción cuelgan cuatro
     * cosas: el formulario muestra u oculta el campo, la validación lo exige o
     * lo PROHÍBE, la ficha lo muestra o no, y el plástico imprime el renglón
     * CUPO o le da la tira entera al tipo de actor.
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

    // ------------------------------------------------------------------
    //  Scopes
    // ------------------------------------------------------------------

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
