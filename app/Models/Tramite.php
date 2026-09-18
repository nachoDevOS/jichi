<?php

namespace App\Models;

use App\Enums\EstadoTramite;
use App\Enums\EstadoValidacionPago;
use App\Enums\TipoTramite;
use App\Support\Archivos;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;

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

    /**
     * Los depósitos con que se cubre el costo del trámite.
     *
     * morphMany y no hasMany, porque `pagos` también cobra faenas y guías. Para
     * quien consume la relación no cambia nada —`$tramite->pagos` sigue
     * devolviendo lo mismo— pero NO hay columna `tramite_id`: lo que consulte
     * `pagos` va por `pagable_type` + `pagable_id`. Ver `create_pagos_table`.
     */
    public function pagos(): MorphMany
    {
        return $this->morphMany(Pago::class, 'pagable')->orderBy('fecha_pago');
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
     * Son los DOS ADJUNTOS DEL EXPEDIENTE y EL DINERO.
     *
     * ------------------------------------------------------------------------
     *  EL MONTO TIENE QUE ESTAR CUBIERTO ANTES DE ENVIAR, NO RECIÉN AL APROBAR
     * ------------------------------------------------------------------------
     *
     * La suma de los depósitos tiene que llegar al costo del rubro. Antes esto
     * se comprobaba solo al APROBAR, y eso dejaba pasar lo que no se puede
     * deshacer: al enviar a revisión queda habilitado el RECIBO OFICIAL, con el
     * monto y la fecha congelados, y el pescador se va del mostrador con ese
     * papel. Emitirlo por un expediente a medio pagar es entregar un
     * comprobante por dinero que no entró.
     *
     * Y encaja con lo que significa cada estado: PENDIENTE es el borrador donde
     * la persona está juntando la plata —ahí se cargan las boletas de a una—;
     * ENVIAR es declarar que el expediente está completo. Un expediente
     * completo incluye lo cobrado.
     *
     * Se compara con `>=` y no con `==`, igual que en `estaPagado()`: un
     * depósito puede venir por unos centavos de más y eso no debe trabar nada.
     *
     * El rubro EXENTO por ordenanza —costo cero— pasa sin depósitos, porque la
     * suma de nada ya cubre un costo de cero.
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

        // El texto DICE CUÁNTO FALTA, no «el pago»: el operador necesita saber
        // por cuánto tiene que volver la persona al banco, y ese número es lo
        // primero que le van a preguntar en el mostrador.
        if (! $this->estaPagado()) {
            $faltan[] = sprintf(
                'cubrir %s del costo (se cobró %s de %s)',
                number_format($this->saldoPendiente(), 2, ',', '.'),
                number_format($this->montoPagado(), 2, ',', '.'),
                number_format((float) $this->monto_requerido, 2, ',', '.'),
            );
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
     * ¿TODOS los depósitos están controlados?
     *
     * Que el monto esté cubierto y que el dinero esté comprobado son dos cosas
     * distintas: la suma sale de filas que tipeó una persona, y hasta que
     * alguien MÁS mire la boleta contra el extracto, lo que hay es una
     * declaración. Ver App\Enums\EstadoValidacionPago.
     *
     * Un trámite SIN depósitos devuelve true, y está bien: es el caso del rubro
     * exento por ordenanza —costo cero— que se aprueba sin cobrar nada. Quien
     * frena ahí es `estaPagado()`, que es otra pregunta.
     *
     * Usa la relación cargada cuando ya vino con `with('pagos')`. Sin esa
     * comprobación, `$this->pagos()->where(...)` CONSULTA IGUAL aunque quien
     * llamó haya hecho el eager loading.
     */
    public function pagosValidados(): bool
    {
        if ($this->relationLoaded('pagos')) {
            return $this->pagos->every(fn (Pago $p): bool => $p->estaValidado());
        }

        return ! $this->pagos()->sinValidar()->exists();
    }

    /**
     * Cuántos depósitos siguen frenando la aprobación, por estado.
     *
     * Se devuelven separados porque las dos salidas son distintas: un pendiente
     * se valida, un observado hay que corregirlo antes. El mensaje de error lo
     * dice, y la pantalla lo muestra.
     *
     * @return array{pendientes: int, observados: int}
     */
    public function pagosPorControlar(): array
    {
        $pagos = $this->relationLoaded('pagos') ? $this->pagos : $this->pagos()->get();

        return [
            'pendientes' => $pagos->where('estado_validacion', EstadoValidacionPago::Pendiente)->count(),
            'observados' => $pagos->where('estado_validacion', EstadoValidacionPago::Observado)->count(),
        ];
    }

    /**
     * ¿Se puede aprobar HOY?
     *
     * SON TRES CONDICIONES Y LAS TRES HACEN FALTA:
     *
     *   - que el salto de estado sea válido — no se aprueba lo ya resuelto;
     *   - que el monto esté cubierto (Regla C), contra la SUMA de los pagos y no
     *     contra un campo guardado que podría estar desfasado;
     *   - que todos esos depósitos estén CONTROLADOS.
     *
     * La tercera se agregó después, y es la que convierte el control en control:
     * sin ella se podía aprobar un expediente sin que nadie hubiera abierto una
     * sola boleta, y la columna de validación quedaba decorativa.
     */
    public function puedeAprobarse(): bool
    {
        return $this->estado->puedePasarA(EstadoTramite::Aprobado)
            && $this->estaPagado()
            && $this->pagosValidados();
    }

    public function puedeRechazarse(): bool
    {
        return $this->estado->puedePasarA(EstadoTramite::Rechazado);
    }

    /**
     * ¿Se puede REABRIR para seguir trabajándolo?
     *
     * Es el camino de vuelta de un rechazo: el pescador trajo lo que faltaba y
     * el expediente vuelve al borrador con sus depósitos y su historial.
     *
     * Solo mira el salto de estado. La otra condición —que el carnet no tenga
     * YA otro expediente abierto— no se comprueba acá a propósito: exigiría una
     * consulta por fila y el listado la haría por cada trámite de la página.
     * La aplica `SolicitudCarnetService::reabrir()`, y su mensaje explica el
     * caso mejor de lo que podría hacerlo un botón escondido.
     */
    public function puedeReabrirse(): bool
    {
        return $this->estado->puedePasarA(EstadoTramite::Pendiente);
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
