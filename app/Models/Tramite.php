<?php

namespace App\Models;

use App\Enums\EstadoTramite;
use App\Support\Sql;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Appends(['saldo_pendiente', 'esta_pagado'])]
#[Fillable([
    'solicitante_id',
    'tipo_tramite_id',
    'user_id',
    'estado',
    'monto_total',
    'monto_pagado',
    'exencion_id',
    'datos_adicionales',
    'requisitos_validados',
    'observaciones',
    'motivo_rechazo',
    'fecha_recepcion',
    'fecha_revision',
    'fecha_aprobacion',
    'fecha_emision',
    'fecha_entrega',
    'modo_entrega',
    'revisado_por',
    'aprobado_por',
    'entregado_por',
])]
class Tramite extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'estado' => EstadoTramite::class,
            'monto_total' => 'decimal:2',
            'monto_pagado' => 'decimal:2',
            'datos_adicionales' => 'array',
            'requisitos_validados' => 'array',
            'fecha_recepcion' => 'datetime',
            'fecha_revision' => 'datetime',
            'fecha_aprobacion' => 'datetime',
            'fecha_emision' => 'datetime',
            'fecha_entrega' => 'datetime',
        ];
    }

    /**
     * withTrashed() es obligatorio acá.
     *
     * Dar de baja a un solicitante es un borrado lógico, y sin esto el titular
     * de un trámite viejo llegaría como null: el listado, el dashboard y la
     * verificación pública se caerían al pedirle el nombre. El historial tiene
     * que seguir mostrando a quién se le emitió el documento aunque esa persona
     * ya no esté en el padrón.
     */
    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(Solicitante::class)->withTrashed();
    }

    public function tipoTramite(): BelongsTo
    {
        return $this->belongsTo(TipoTramite::class);
    }

    public function exencion(): BelongsTo
    {
        return $this->belongsTo(Exencion::class);
    }

    public function operador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function revisadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisado_por');
    }

    public function aprobadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobado_por');
    }

    public function entregadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entregado_por');
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class);
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(Documento::class);
    }

    /**
     * Documento vigente emitido por este trámite (el último no anulado).
     */
    public function documento(): HasOne
    {
        return $this->hasOne(Documento::class)->latestOfMany();
    }

    protected function saldoPendiente(): Attribute
    {
        return Attribute::get(fn (): float => round(
            max(0, (float) $this->monto_total - (float) $this->monto_pagado), 2
        ));
    }

    protected function estaPagado(): Attribute
    {
        return Attribute::get(fn (): bool => (float) $this->monto_pagado >= (float) $this->monto_total);
    }

    /**
     * La carpeta donde viven los respaldos de este expediente.
     *
     * Una carpeta por trámite y no todos los archivos mezclados: así encontrar
     * los papeles de un expediente es entrar a la carpeta con su número, sin
     * cruzar la base de datos para saber qué archivo es de quién.
     *
     * Vive en el modelo y no escrita en el controlador porque la usan el alta y
     * la corrección, y si fueran dos cadenas armadas a mano, bastaría con
     * cambiar una para que los archivos corregidos cayeran en otra carpeta.
     */
    public function carpeta(): string
    {
        return 'tramites/'.$this->id;
    }

    /**
     * Recalcula lo pagado sumando únicamente los pagos no anulados.
     */
    public function recalcularPagado(): void
    {
        $this->forceFill([
            'monto_pagado' => (float) $this->pagos()->where('estado', 'pagado')->sum('monto'),
        ])->save();
    }

    /*
     * Los scopes califican sus columnas: los reportes unen tramites con pagos
     * y documentos, y las tres tablas tienen `estado`.
     */

    public function scopeEnEstado(Builder $query, EstadoTramite|string ...$estados): Builder
    {
        return $query->whereIn($query->qualifyColumn('estado'), array_map(
            fn (EstadoTramite|string $e) => $e instanceof EstadoTramite ? $e->value : $e,
            $estados
        ));
    }

    public function scopePendientes(Builder $query): Builder
    {
        return $query->whereNotIn($query->qualifyColumn('estado'), [
            EstadoTramite::Entregado->value,
            EstadoTramite::Rechazado->value,
        ]);
    }

    public function scopeDelDia(Builder $query, ?string $fecha = null): Builder
    {
        return $query->whereDate($query->qualifyColumn('created_at'), $fecha ?? now()->toDateString());
    }

    /**
     * Buscador del listado: por lo que la ventanilla tiene a mano.
     *
     * QUÉ SE PUEDE ESCRIBIR EN LA CAJA
     *
     *   - el número de trámite      (60)
     *   - el registro de la credencial  (PES-2026-0001)
     *   - el nombre o la cédula del pescador
     *   - el nombre de la embarcación
     *   - el servicio                (cédula, permiso, guía)
     *
     * Son las cinco cosas con las que alguien vuelve a ventanilla. Nadie
     * llega diciendo «vengo por el trámite en estado aprobado»: llega con un
     * papel en la mano o con su apellido.
     *
     * POR QUÉ whereHas Y NO join
     *
     * `whereHas` arma una subconsulta y deja la tabla `tramites` sola. Con un
     * join habría que calificar cada columna a mano —`tramites`, `pagos` y
     * `documentos` tienen todas una columna `estado`— y cualquier scope que se
     * encadene después heredaría la ambigüedad. Además así se REUSA el
     * buscador de solicitantes, que ya sabe juntar las cinco partes del nombre
     * y entrecomillarlas para PostgreSQL.
     */
    public function scopeBuscar(Builder $query, ?string $termino): Builder
    {
        if (blank($termino)) {
            return $query;
        }

        $termino = trim($termino);

        // Se escapan los comodines del propio LIKE: quien escriba «100%» busca
        // ese texto, no «100» seguido de cualquier cosa.
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $termino).'%';
        $operador = Sql::like($query->getConnection());

        return $query->where(function (Builder $q) use ($termino, $like, $operador): void {
            // El número de trámite se compara exacto, no con LIKE: buscar «6»
            // no tiene por qué traer el 6, el 16, el 60 y el 260.
            if (ctype_digit($termino)) {
                $q->orWhere($q->qualifyColumn('id'), (int) $termino);
            }

            /*
             * El registro y la embarcación viven dentro de la columna JSON
             * `datos_adicionales`, no en columnas propias: cada tipo de trámite
             * guarda ahí sus campos. La flecha `->` es la sintaxis de Eloquent
             * para entrar al JSON, y traduce sola a `->>` en PostgreSQL y a
             * `json_extract()` en SQLite.
             */
            $q->orWhere('datos_adicionales->registro', $operador, $like)
                ->orWhere('datos_adicionales->embarcacion', $operador, $like)
                ->orWhereHas('solicitante', fn (Builder $s) => $s->buscar($termino))
                ->orWhereHas('tipoTramite', fn (Builder $t) => $t->where('nombre', $operador, $like));
        });
    }
}
