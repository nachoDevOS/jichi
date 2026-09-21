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
 */
#[Fillable([
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

    //  Lectura

    /** Cómo se lee en un listado: «Faena N° 0003». */
    protected function etiqueta(): Attribute
    {
        return Attribute::get(
            fn (): string => 'Faena N° '.str_pad((string) $this->numero_faena, 4, '0', STR_PAD_LEFT),
        );
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

    /** Las que pesan contra el cupo: todas menos las vencidas. */
    public function scopeQueConsumenCupo(Builder $query): Builder
    {
        return $query->whereNot($this->qualifyColumn('estado'), EstadoFaena::Vencido);
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
