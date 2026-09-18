<?php

namespace App\Models;

use App\Enums\EstadoPermiso;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * El permiso de UNA salida de pesca.
 *
 * ----------------------------------------------------------------------------
 *  EL CARNET ES LA LLAVE ANUAL; LA FAENA ES EL PERMISO DE CADA VIAJE
 * ----------------------------------------------------------------------------
 *
 * Un carnet de Pescador habilita a pescar durante la gestión. La faena autoriza
 * una salida concreta: esta embarcación, este comandante, de tal día a tal día,
 * con tanto en kilos. Se emiten muchas por carnet.
 *
 * Cuelga del CARNET y no del beneficiario por lo mismo que `tramites`: así la
 * gestión y la actividad vienen dadas por construcción. A la persona se llega
 * con un salto más, que resuelve `beneficiario()`.
 *
 * ----------------------------------------------------------------------------
 *  QUÉ NO COMPRUEBA ESTE MODELO
 * ----------------------------------------------------------------------------
 *
 * Que el carnet sea de un rubro que emita faenas y que esté vigente. Eso es una
 * REGLA DE NEGOCIO y va en el servicio —igual que la emisión del carnet vive en
 * `SolicitudCarnetService` y no en `Carnet`—, porque tiene que poder explicar
 * en castellano por qué no se puede emitir.
 */
#[Fillable([
    'carnet_id',
    'nro_permiso',
    'nro_recibo',
    'monto',
    'embarcacion',
    'propietario',
    'comandante_barco',
    'matricula_naval',
    'nro_kardex',
    'region_desde',
    'region_hasta',
    'fecha_salida',
    'fecha_desembarque',
    'cantidad_autorizada_kg',
    'observaciones',
    'estado',
])]
class Faena extends Model
{
    use Auditable;

    /**
     * La tarifa por salida, en bolivianos.
     *
     * Está duplicada en el `default` de la columna a propósito: la base la aplica
     * a lo que se inserte por consola o por migración, y esta constante es la que
     * el formulario muestra ya cargada. Si cambia por ordenanza hay que tocar las
     * dos —y si eso pasa seguido, el lugar correcto es `configuraciones`—.
     */
    public const TARIFA = 15.00;

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
        // Por lo mismo: el default de `monto` lo pone la base, y sin esto
        // `$faena->monto` vuelve null del `create()` —así que `estaPagada()`
        // contestaba que sí, con cero cobrado, porque comparaba contra cero—.
        'monto' => self::TARIFA,
    ];

    protected function casts(): array
    {
        return [
            'estado' => EstadoPermiso::class,
            'monto' => 'decimal:2',
            // decimal:2 y no float, igual que el cupo del carnet: lo autorizado
            // se compara contra kilos declarados, y en punto flotante
            // 600.1 + 0.2 no da 600.3. Una diferencia de centésimas en un
            // control es un reclamo.
            'cantidad_autorizada_kg' => 'decimal:2',
            'fecha_salida' => 'date',
            'fecha_desembarque' => 'date',
        ];
    }

    // ------------------------------------------------------------------
    //  Relaciones
    // ------------------------------------------------------------------

    public function carnet(): BelongsTo
    {
        return $this->belongsTo(Carnet::class);
    }

    /**
     * El titular, saltando por el carnet.
     *
     * Sin columna `beneficiario_id` propia, por lo mismo que en `Tramite`:
     * guardar el id acá además de en el carnet abriría la puerta a que los dos
     * digan cosas distintas, y la base no podría impedirlo.
     */
    public function beneficiario(): HasOneThrough
    {
        return $this->hasOneThrough(
            Beneficiario::class,
            Carnet::class,
            'id',              // carnets.id
            'id',              // beneficiarios.id
            'carnet_id',       // faenas.carnet_id
            'beneficiario_id', // carnets.beneficiario_id
        )->withTrashed();
    }

    /**
     * Los depósitos con que se pagó esta faena.
     *
     * morphMany y no hasMany: `pagos` es polimórfica desde que también se cobran
     * faenas y guías. Ver la migración `create_pagos_table` para el porqué de una
     * sola tabla y no tres.
     */
    public function pagos(): MorphMany
    {
        return $this->morphMany(Pago::class, 'pagable')->orderBy('fecha_pago');
    }

    // ------------------------------------------------------------------
    //  Dinero
    // ------------------------------------------------------------------

    /**
     * Lo cobrado hasta ahora.
     *
     * Misma decisión que en `Tramite`: NO hay columna `monto_pagado`. Una
     * columna así hay que mantenerla al día en cada alta y cada corrección, y
     * el día que alguien inserte un pago sin acordarse, el sistema cobra de
     * menos sin avisar.
     *
     * El listado trae el total con `withSum('pagos', 'monto')` para no hacer
     * N+1; si ese atributo ya vino, se usa y no se vuelve a consultar.
     */
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
        return max(0, (float) $this->monto - $this->montoPagado());
    }

    /**
     * ¿Está cubierto el costo?
     *
     * Con `>=` y no con `==` porque un depósito puede venir por unos centavos
     * de más y eso no debe trabar nada.
     */
    public function estaPagada(): bool
    {
        return $this->montoPagado() >= (float) $this->monto;
    }

    // ------------------------------------------------------------------
    //  Estado
    // ------------------------------------------------------------------

    public function estaEmitida(): bool
    {
        return $this->estado === EstadoPermiso::Emitido;
    }

    /**
     * ¿Autoriza a pescar HOY?
     *
     * SON TRES COSAS Y LAS TRES HACEN FALTA:
     *
     *   - la faena no está anulada;
     *   - hoy cae dentro de su ventana de salida y desembarque;
     *   - el carnet del que cuelga sigue valiendo.
     *
     * La tercera es la que se olvida, y es la que importa: un carnet suspendido
     * en marzo no deja vigentes las faenas que emitió en febrero. Por eso se
     * pregunta al carnet en vez de confiar en que la faena existe.
     */
    public function estaVigente(): bool
    {
        if (! $this->estado->habilita()) {
            return false;
        }

        $hoy = now()->startOfDay();

        $dentroDeVentana = $this->fecha_salida?->startOfDay()->lessThanOrEqualTo($hoy)
            && $this->fecha_desembarque?->endOfDay()->greaterThanOrEqualTo($hoy);

        return (bool) $dentroDeVentana && (bool) $this->carnet?->estaVigente();
    }

    /**
     * Cuántos días cubre el permiso, contando los dos extremos.
     *
     * Se suma uno porque una faena que sale y desembarca el mismo día dura un
     * día, no cero: la resta de fechas da la distancia entre ellas, no la
     * cantidad de jornadas autorizadas.
     */
    public function diasAutorizados(): ?int
    {
        if ($this->fecha_salida === null || $this->fecha_desembarque === null) {
            return null;
        }

        return $this->fecha_salida->diffInDays($this->fecha_desembarque) + 1;
    }

    /**
     * Lo autorizado tal como se escribe: «600 KG».
     *
     * Se recorta la cola de decimales cuando son cero, por lo mismo que en
     * `Carnet::capacidadLegible()`: la unidad trabaja en kilos enteros y
     * «600,00 KG» gasta cuatro caracteres al pedo en el papel.
     */
    public function cantidadLegible(): ?string
    {
        if ($this->cantidad_autorizada_kg === null) {
            return null;
        }

        $kg = (float) $this->cantidad_autorizada_kg;
        $numero = fmod($kg, 1.0) === 0.0
            ? number_format($kg, 0, ',', '.')
            : number_format($kg, 2, ',', '.');

        return "{$numero} KG";
    }

    // ------------------------------------------------------------------
    //  Scopes
    // ------------------------------------------------------------------

    /**
     * Los scopes califican la columna con qualifyColumn() porque `faenas`,
     * `carnets`, `tramites` y `rubros` tienen todas una columna `estado`, y los
     * reportes las cruzan con join. Sin calificar, PostgreSQL responde
     * «column reference "estado" is ambiguous». Ver la regla 9 de CLAUDE.md.
     */
    public function scopeEmitidas(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('estado'), EstadoPermiso::Emitido);
    }

    public function scopeAnuladas(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('estado'), EstadoPermiso::Anulado);
    }

    /** Las faenas cuya ventana incluye el día indicado —hoy si no se pasa otro—. */
    public function scopeVigentesAl(Builder $query, ?string $fecha = null): Builder
    {
        $fecha ??= now()->toDateString();

        return $query->emitidas()
            ->whereDate($query->qualifyColumn('fecha_salida'), '<=', $fecha)
            ->whereDate($query->qualifyColumn('fecha_desembarque'), '>=', $fecha);
    }

    /**
     * Las faenas de una gestión.
     *
     * Se filtra por la gestión del CARNET y no por el año de `fecha_salida`:
     * una faena emitida el 30 de diciembre con desembarque en enero pertenece
     * al carnet de la gestión que la emitió, y contarla en la siguiente
     * descuadraría los dos años.
     */
    public function scopeDeGestion(Builder $query, ?int $gestion = null): Builder
    {
        $gestion ??= (int) now()->format('Y');

        return $query->whereHas('carnet', fn (Builder $q) => $q->where('gestion', $gestion));
    }
}
