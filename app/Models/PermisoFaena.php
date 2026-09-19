<?php

namespace App\Models;

use App\Enums\EstadoFaena;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * La autorización de UNA salida de pesca.
 *
 * ============================================================================
 *  APUNTA A DOS COSAS A LA VEZ, Y LAS DOS HACEN FALTA
 * ============================================================================
 *
 *   - `aprovechamiento` es DE DÓNDE SALEN LOS KILOS: la bolsa madre contra la
 *     que se descuenta.
 *   - `carnet` es QUIÉN LOS EXTRAE: la credencial que un control en el río va
 *     a pedir.
 *
 * Las dos apuntan a la misma persona, pero por caminos distintos y con vidas
 * distintas —el cupo se renueva por resolución y el carnet por gestión, no
 * siempre en la misma fecha—. Guardar solo una obligaría a deducir la otra, y
 * la deducción falla justamente en el caso raro: dos cupos vigentes, o el
 * carnet renovado a mitad de un cupo.
 *
 * ============================================================================
 *  NO SE EDITA NI SE BORRA
 * ============================================================================
 *
 * El número sale de un talonario de papel que el pescador se llevó. Borrar la
 * fila deja un hueco en la serie que nadie puede explicar y libera un número
 * que el índice único volvería a aceptar, así que dos salidas distintas
 * podrían terminar diciendo ser el mismo papel. Se completa o se vence.
 */
#[Fillable([
    'aprovechamiento_id',
    'carnet_id',
    'numero_faena',
    'kilos_extraidos',
    'fecha_salida',
    'fecha_limite',
    'estado',
])]
class PermisoFaena extends Model
{
    use Auditable, SoftDeletes;

    /** «PermisoFaena» pluraliza a «permiso_faenas», que no es la tabla. */
    protected $table = 'permisos_faena';

    /**
     * VIGENCIA MÁXIMA DE UNA FAENA, en días.
     *
     * Está acá y no escrito a mano en el controlador porque es una regla de la
     * resolución, no un detalle del formulario: la usan el alta, la validación
     * y la vista previa del papel. Escrita en tres lados, cambiarla se hace en
     * dos y el tercero sigue emitiendo con el plazo viejo.
     */
    public const DIAS_VIGENCIA = 30;

    /** Ver Asociacion::$attributes: los defaults de la base no llegan al create(). */
    protected $attributes = [
        'kilos_extraidos' => 0,
        'estado' => EstadoFaena::Activo->value,
    ];

    protected function casts(): array
    {
        return [
            'kilos_extraidos' => 'decimal:2',
            'fecha_salida' => 'date',
            'fecha_limite' => 'date',
            'estado' => EstadoFaena::class,
        ];
    }

    // ------------------------------------------------------------------
    //  Relaciones
    // ------------------------------------------------------------------

    public function aprovechamiento(): BelongsTo
    {
        return $this->belongsTo(AprovechamientoPesq::class, 'aprovechamiento_id');
    }

    public function carnet(): BelongsTo
    {
        return $this->belongsTo(Carnet::class, 'carnet_id');
    }

    // ------------------------------------------------------------------
    //  Lectura
    // ------------------------------------------------------------------

    /** Cómo se lee en un listado: «Faena N° 0003». */
    protected function etiqueta(): Attribute
    {
        return Attribute::get(
            fn (): string => 'Faena N° '.str_pad((string) $this->numero_faena, 4, '0', STR_PAD_LEFT),
        );
    }

    // ------------------------------------------------------------------
    //  Reglas de negocio
    // ------------------------------------------------------------------

    /**
     * La fecha límite que corresponde a una salida.
     *
     * Se CALCULA acá y se GUARDA en la fila, en vez de derivarse al leer: si
     * mañana la resolución baja el plazo a quince días, los permisos ya
     * emitidos tienen que seguir venciendo cuando dice el papel que el pescador
     * tiene en la mano.
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

    // ------------------------------------------------------------------
    //  Scopes
    // ------------------------------------------------------------------

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

    /** Las que pesan contra el cupo: todas menos las vencidas. */
    public function scopeQueConsumenCupo(Builder $query): Builder
    {
        return $query->whereNot($this->qualifyColumn('estado'), EstadoFaena::Vencido);
    }

    public function scopeDelCupo(Builder $query, int $aprovechamientoId): Builder
    {
        return $query->where($this->qualifyColumn('aprovechamiento_id'), $aprovechamientoId);
    }
}
