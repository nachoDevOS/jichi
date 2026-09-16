<?php

namespace App\Models;

use App\Enums\EstadoTramite;
use App\Enums\TipoTramite;
use App\Support\Archivos;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

/**
 * El expediente de una solicitud: «esta persona pide este rubro».
 *
 * Cuelga del CARNET y no del beneficiario. Así la gestión del expediente viene
 * dada por construcción —todo trámite de un carnet de 2026 es de 2026— en vez de
 * tener que deducirse de una fecha. A la persona se llega con un salto más, que
 * resuelve la relación `beneficiario()` de abajo.
 */
#[Appends(['ci_file_url', 'cert_asociacion_file_url'])]
#[Fillable([
    'carnet_id',
    'rubro_id',
    'tipo_tramite',
    'estado',
    'ciFile',
    'certAsociacionFile',
    'asociacion',
    'capacidad_kg',
    'monto_requerido',
    'fecha_solicitud',
    'observaciones',
    // Las cuatro de abajo las escribe SOLO SolicitudCarnetService, nunca un
    // formulario: no hay ningún FormRequest que las valide, así que no pueden
    // llegar desde el navegador. Van igual en la lista porque el servicio usa
    // update(), y update() descarta en silencio todo lo que no esté acá
    // —fallando sin error, que es la peor forma de fallar—.
    'fecha_revision',
    'fecha_aprobacion',
    'fecha_generacion',
    'fecha_entrega',
    'motivo_rechazo',
])]
class Tramite extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'tipo_tramite' => TipoTramite::class,
            'estado' => EstadoTramite::class,
            'monto_requerido' => 'decimal:2',
            // decimal:2 y no float: el cupo se compara contra kilos declarados
            // en guías de transporte, y en punto flotante 600.1 + 0.2 no da
            // 600.3. Un cupo que no cierra por centésimas es un reclamo.
            'capacidad_kg' => 'decimal:2',
            'fecha_solicitud' => 'datetime',
            'fecha_revision' => 'datetime',
            'fecha_aprobacion' => 'datetime',
            'fecha_generacion' => 'datetime',
            'fecha_entrega' => 'datetime',
        ];
    }

    // ------------------------------------------------------------------
    //  Relaciones
    // ------------------------------------------------------------------

    public function carnet(): BelongsTo
    {
        return $this->belongsTo(Carnet::class);
    }

    public function rubro(): BelongsTo
    {
        return $this->belongsTo(Rubro::class);
    }

    /**
     * El titular, saltando por el carnet.
     *
     * hasOneThrough y no una columna `beneficiario_id` propia: guardar el id acá
     * además de en el carnet abriría la puerta a que los dos digan cosas
     * distintas —un trámite apuntando a una persona y a un carnet de otra— y la
     * base no podría impedirlo.
     */
    public function beneficiario(): HasOneThrough
    {
        return $this->hasOneThrough(
            Beneficiario::class,
            Carnet::class,
            'id',              // carnets.id
            'id',              // beneficiarios.id
            'carnet_id',       // tramites.carnet_id
            'beneficiario_id', // carnets.beneficiario_id
        )->withTrashed();
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class)->orderBy('fecha_pago');
    }

    // ------------------------------------------------------------------
    //  Dinero — Regla C
    // ------------------------------------------------------------------

    /**
     * Lo cobrado hasta ahora, sumando todos los depósitos.
     *
     * NO HAY COLUMNA `monto_pagado`, y es a propósito. Una columna así hay que
     * mantenerla al día en cada alta y cada corrección de pago; el día que
     * alguien inserte una fila en `pagos` sin acordarse de actualizarla, el
     * sistema cobra de menos y nada avisa. La suma no puede desfasarse porque no
     * guarda nada: es la respuesta de los datos que realmente hay.
     *
     * El costo es una consulta agregada por trámite. En los listados eso sería
     * N+1, y por eso el controlador usa withSum('pagos', 'monto') para traerlo
     * todo en la misma consulta; este método es para la ficha individual.
     */
    public function montoPagado(): float
    {
        // El withSum del listado deja el resultado en este atributo. Si ya está,
        // se usa y no se vuelve a consultar.
        if ($this->pagos_sum_monto !== null) {
            return (float) $this->pagos_sum_monto;
        }

        return (float) $this->pagos()->sum('monto');
    }

    /**
     * Lo que falta pagar. Nunca negativo.
     *
     * Un depósito de más no genera saldo a favor —el sistema no devuelve
     * dinero—, así que se corta en cero: mostrar «-20» en ventanilla solo
     * confundiría al operador.
     */
    public function saldoPendiente(): float
    {
        return max(0, (float) $this->monto_requerido - $this->montoPagado());
    }

    /**
     * ¿Está cubierto el costo del trámite?
     *
     * Es la comprobación de la Regla C y la condición para aprobar. Se compara
     * con `>=` y no con `==` porque un depósito puede venir por unos centavos de
     * más y eso no debe trabar la aprobación.
     */
    public function estaPagado(): bool
    {
        return $this->montoPagado() >= (float) $this->monto_requerido;
    }

    // ------------------------------------------------------------------
    //  Estado
    // ------------------------------------------------------------------

    public function estaPendiente(): bool
    {
        return $this->estado === EstadoTramite::Pendiente;
    }

    public function estaEnRevision(): bool
    {
        return $this->estado === EstadoTramite::EnRevision;
    }

    /**
     * ¿El expediente sigue esperando resolución?
     *
     * Agrupa PENDIENTE y EN REVISIÓN. Se usa donde confundirlos sería un error:
     * el contador de trabajo del tablero y la comprobación de solicitud
     * duplicada. Ver EstadoTramite::estaAbierto().
     */
    public function estaAbierto(): bool
    {
        return $this->estado->estaAbierto();
    }

    public function estaAprobado(): bool
    {
        return $this->estado === EstadoTramite::Aprobado;
    }

    /**
     * ========================================================================
     *  QUÉ PAPELES LE FALTAN AL EXPEDIENTE PARA PODER REVISARSE
     * ========================================================================
     *
     * Devuelve la lista de lo que falta, en castellano de ventanilla y ya listo
     * para mostrar. Vacío significa que está completo.
     *
     * Son los DOS ADJUNTOS DEL EXPEDIENTE, y nada más.
     *
     * ------------------------------------------------------------------------
     *  POR QUÉ SE COMPRUEBA ACÁ SI EL FORMULARIO YA LOS EXIGE
     * ------------------------------------------------------------------------
     *
     * Porque las columnas son `nullable` a propósito: un expediente migrado del
     * padrón en papel o cargado por consola puede llegar sin ellos, y
     * «Editar trámite» permite dejar uno vacío. La obligatoriedad del
     * formulario es una regla de pantalla; esta es la del expediente.
     *
     * ------------------------------------------------------------------------
     *  LA FOTOGRAFÍA NO ENTRA ACÁ, Y ES DELIBERADO
     * ------------------------------------------------------------------------
     *
     * Se probó exigiéndola y se retiró a pedido de la unidad. El motivo es de
     * mostrador: la foto no hace falta para REVISAR un expediente —revisar es
     * verificar papeles y pagos—, hace falta para IMPRIMIR el carnet, que pasa
     * mucho después. Bloquear la revisión por algo que se resuelve más adelante
     * frena la ventanilla sin necesidad.
     *
     * Que falte igual se avisa, pero donde corresponde y sin impedir nada: la
     * vista previa del carnet lo dice con todas las letras —«La ficha no tiene
     * fotografía. El carnet se va a imprimir con el recuadro vacío»—. Ver
     * `components/panel/tramites/vista-previa-carnet.tsx`.
     *
     * @return array<int, string>
     */
    public function faltantesParaRevision(): array
    {
        $faltan = [];

        if (blank($this->ciFile)) {
            $faltan[] = 'la fotocopia del carnet de identidad';
        }

        if (blank($this->certAsociacionFile)) {
            $faltan[] = 'el certificado de la asociación';
        }

        return $faltan;
    }

    public function expedienteCompleto(): bool
    {
        return $this->faltantesParaRevision() === [];
    }

    /**
     * ¿Se puede ENVIAR a revisión?
     *
     * Dos condiciones, y las dos hacen falta: que el salto de estado valga —eso
     * lo decide el enum, ver EstadoTramite::siguientes()— y que el expediente
     * esté completo.
     *
     * La segunda es la que impide que un trámite se envíe sin los papeles: si no
     * estuviera acá, el botón aparecería igual y el operador se llevaría el
     * rechazo recién al apretarlo.
     */
    public function puedeEnviarse(): bool
    {
        return $this->estado->puedePasarA(EstadoTramite::EnRevision)
            && $this->expedienteCompleto();
    }

    /**
     * ¿Se puede aprobar HOY?
     *
     * Son dos condiciones y las dos hacen falta: que el salto de estado sea
     * válido —no se aprueba lo ya resuelto— y que el monto esté cubierto. La
     * segunda es la Regla C, y se comprueba contra la suma de los pagos y no
     * contra un campo guardado que podría estar desfasado.
     */
    public function puedeAprobarse(): bool
    {
        return $this->estado->puedePasarA(EstadoTramite::Aprobado) && $this->estaPagado();
    }

    public function puedeRechazarse(): bool
    {
        return $this->estado->puedePasarA(EstadoTramite::Rechazado);
    }

    /**
     * ¿Se puede imprimir el carnet?
     *
     * Solo con el expediente aprobado. Que el carnet exista en la base desde el
     * momento de la solicitud no significa que se pueda entregar: la fila se
     * crea al registrar para poder colgarle el trámite, pero el documento
     * físico recién sale cuando un supervisor firmó.
     */
    public function puedeGenerarse(): bool
    {
        return $this->estaAprobado() && $this->fecha_generacion === null;
    }

    public function puedeEntregarse(): bool
    {
        return $this->estaAprobado() && $this->fecha_generacion !== null && $this->fecha_entrega === null;
    }

    // ------------------------------------------------------------------
    //  Adjuntos
    // ------------------------------------------------------------------

    /**
     * Las columnas guardan una ruta cuando el disco es local y una dirección
     * completa cuando es s3, y las dos formas conviven en la misma tabla. Por
     * eso el enlace se arma siempre con Archivos::url() y no con Storage::url().
     */
    protected function ciFileUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => Archivos::url($this->ciFile));
    }

    protected function certAsociacionFileUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => Archivos::url($this->certAsociacionFile));
    }

    // ------------------------------------------------------------------
    //  Scopes
    // ------------------------------------------------------------------

    /**
     * Los scopes califican la columna con qualifyColumn() porque `tramites`,
     * `pagos`, `carnets` y `rubros` tienen todas una columna `estado`, y los
     * reportes las cruzan con join. Sin calificar, PostgreSQL responde
     * «column reference "estado" is ambiguous» y la consulta ni corre.
     */
    public function scopePendientes(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('estado'), EstadoTramite::Pendiente);
    }

    public function scopeEnRevision(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('estado'), EstadoTramite::EnRevision);
    }

    /**
     * Los expedientes que todavía esperan resolución: PENDIENTE o EN REVISIÓN.
     *
     * NO es lo mismo que `pendientes()`, y confundirlos tiene consecuencias
     * concretas: un trámite ya enviado a revisión sigue siendo trabajo
     * sin terminar para el tablero, y sigue bloqueando una segunda solicitud
     * del mismo rubro —si no bloqueara, el beneficiario pagaría dos veces por
     * una sola habilitación—.
     */
    public function scopeAbiertos(Builder $query): Builder
    {
        return $query->whereIn($query->qualifyColumn('estado'), EstadoTramite::abiertos());
    }

    public function scopeAprobados(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('estado'), EstadoTramite::Aprobado);
    }

    public function scopeNoRechazados(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('estado'), '!=', EstadoTramite::Rechazado);
    }

    public function scopeDeGestion(Builder $query, ?int $gestion = null): Builder
    {
        $gestion ??= (int) now()->format('Y');

        return $query->whereHas('carnet', fn (Builder $q) => $q->where('gestion', $gestion));
    }
}
