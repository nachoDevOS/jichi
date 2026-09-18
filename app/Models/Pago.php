<?php

namespace App\Models;

use App\Enums\EstadoPermiso;
use App\Enums\EstadoTramite;
use App\Enums\EstadoValidacionPago;
use App\Support\Archivos;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Un depósito bancario aplicado a algo que se cobra.
 *
 * ----------------------------------------------------------------------------
 *  YA NO ES SOLO DEL TRÁMITE
 * ----------------------------------------------------------------------------
 *
 * Nació colgando de `tramites`, cuando lo único que se cobraba era la emisión
 * del carnet. Hoy también se cobran las FAENAS y las GUÍAS, y las tres cosas se
 * pagan igual: uno o varios depósitos, cada uno con su boleta y su número de
 * transacción. De ahí la relación polimórfica `pagable()`.
 *
 * Una sola tabla y no tres, y el motivo de fondo es el índice único de
 * `nro_transaccion`: partido en tres tablas dejaría de ser único, y la misma
 * boleta podría pagar un trámite y una faena. Ver la migración
 * `create_pagos_table` para el resto del razonamiento y para lo que se pierde
 * —la clave foránea—.
 *
 * ----------------------------------------------------------------------------
 *  UNO A VARIOS, Y POR QUÉ
 * ----------------------------------------------------------------------------
 *
 * El pescador puede depositar todo junto o en cuotas, y cada depósito llega con
 * su propia boleta del banco. Un solo campo `monto_pagado` en lo que se cobra
 * obligaría a que el operador sumara a mano antes de escribir, y perdería el
 * comprobante de cada parte: cuando alguien reclame, no habría forma de mostrar
 * qué boleta respalda qué monto.
 *
 * NO SE ANULAN NI SE BORRAN. La tabla no tiene `deleted_at` a propósito: una
 * boleta cargada mal se corrige editando la fila, y el trait Auditable deja
 * registrado el valor anterior, quién lo cambió y cuándo. Un pago «anulado» que
 * sigue en la lista solo invita a sumarlo por error.
 *
 * ----------------------------------------------------------------------------
 *  CADA DEPÓSITO SE CONTROLA, Y QUEDA ESCRITO QUIÉN Y CUÁNDO
 * ----------------------------------------------------------------------------
 *
 *     PENDIENTE ──▶ VALIDADO    la boleta cuadra con el extracto
 *               └─▶ OBSERVADO   no cuadra, con el motivo escrito
 *
 * `estado_validacion` NO es el estado del pago —el dinero entró o no entró, y
 * eso no cambia— sino el de su CONTROL. Por eso un depósito observado sigue
 * sumando en `montoPagado()`: existe, está cargado, y lo que está en duda es si
 * respalda lo que dice.
 *
 * Lo que sí impide es aprobar: ver `Tramite::puedeAprobarse()`.
 *
 * QUIEN CARGA NO VALIDA. `registrado_por` y `validado_por` son dos columnas
 * distintas y `ValidacionPagoService` no deja que sean la misma persona — el
 * mismo criterio de «quien arma no firma» que rige para el trámite.
 */
#[Appends(['comprobante_url'])]
#[Fillable([
    /*
     * Las dos columnas del polimorfismo van en la lista porque el servicio
     * puede escribirlas con `create()` directo. Lo normal, en cambio, es no
     * tocarlas: `$tramite->pagos()->create([...])` o `$faena->pagos()->create(...)`
     * las completa solo, y así no hay forma de equivocarse en el tipo.
     */
    'pagable_type',
    'pagable_id',
    'nro_transaccion',
    'monto',
    'urlFile',
    'fecha_pago',
    'observaciones',
    // Quién lo cargó lo escribe PagoTramiteService con el usuario de la sesión.
    // Va en la lista porque el servicio usa create(), y create() descarta en
    // silencio lo que no esté acá.
    'registrado_por',
    /*
     * LAS CUATRO DE ABAJO LAS ESCRIBE SOLO ValidacionPagoService.
     *
     * Ningún FormRequest las valida, así que no pueden llegar desde el
     * navegador. Van igual en la lista porque el servicio usa update(), y
     * update() descarta en silencio todo lo que no esté acá —fallando sin
     * error, que es la peor forma de fallar—.
     */
    'estado_validacion',
    'validado_por',
    'validado_at',
    'motivo_observacion',
])]
class Pago extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'fecha_pago' => 'datetime',
            'estado_validacion' => EstadoValidacionPago::class,
            'validado_at' => 'datetime',
        ];
    }

    /**
     * Nace SIN VALIDAR, también en memoria.
     *
     * La columna ya tiene este default en la base, y aun así hace falta acá: un
     * default de la base lo aplica el INSERT y el objeto que devuelve `create()`
     * no se entera. Sin esto, registrar un depósito y preguntarle
     * `estaValidado()` en la línea siguiente reventaba contra null.
     */
    protected $attributes = [
        'estado_validacion' => EstadoValidacionPago::Pendiente->value,
    ];

    /**
     * QUÉ SE ESTÁ PAGANDO: un Tramite, una Faena o una Guia.
     *
     * No hay morphMap declarado: en `pagable_type` se guarda el nombre completo
     * de la clase. Es más frágil ante un renombre —mover `App\Models\Tramite`
     * de espacio de nombres dejaría huérfanas las filas viejas— pero evita el
     * problema contrario, que es peor: con un alias corto, un `Pago::with('pagable')`
     * en un comando que no cargó el mapa devuelve null sin ningún error.
     *
     * Si algún día hace falta el mapa, va en un ServiceProvider y la migración
     * de datos tiene que reescribir esta columna en las filas existentes.
     */
    public function pagable(): MorphTo
    {
        return $this->morphTo();
    }

    /** Quién cargó el depósito en ventanilla. Null en lo migrado de antes. */
    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    /** Quién controló la boleta contra el extracto. */
    public function validadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validado_por');
    }

    // ------------------------------------------------------------------
    //  Control del depósito
    // ------------------------------------------------------------------

    public function estaValidado(): bool
    {
        return $this->estado_validacion->cuenta();
    }

    public function estaObservado(): bool
    {
        return $this->estado_validacion === EstadoValidacionPago::Observado;
    }

    /**
     * ========================================================================
     *  ¿ESTE ES EL MOMENTO DE CONTROLAR EL DEPÓSITO?
     * ========================================================================
     *
     * El control es parte de la REVISIÓN, y por eso depende del estado de lo que
     * se está pagando, no del depósito.
     *
     *   TRÁMITE     solo EN REVISIÓN. Mientras está PENDIENTE el expediente es
     *               un borrador que ventanilla todavía está armando —se agregan
     *               y se corrigen boletas— así que controlar ahí es revisar algo
     *               que aún puede cambiar. Y una vez APROBADO o RECHAZADO ya no
     *               tiene sentido: observar una boleta de un carnet que ya se
     *               imprimió no deshace nada, y deja el expediente diciendo que
     *               algo está mal cuando la decisión ya se tomó.
     *
     *   FAENA/GUÍA  mientras no estén anuladas. No tienen circuito de revisión
     *               —se llenan y se entregan en el acto— así que el control se
     *               hace cuando se pueda.
     *
     * Si `pagable` no se puede resolver, se deja pasar: es una fila rota y
     * negarle el control no la arregla.
     */
    public function admiteControl(): bool
    {
        $pagable = $this->pagable;

        if ($pagable instanceof Tramite) {
            return $pagable->estado === EstadoTramite::EnRevision;
        }

        if ($pagable instanceof Faena || $pagable instanceof Guia) {
            return $pagable->estado === EstadoPermiso::Emitido;
        }

        return true;
    }

    /**
     * ========================================================================
     *  ¿ESTE USUARIO PUEDE CONTROLAR ESTE DEPÓSITO?
     * ========================================================================
     *
     * LA SEPARACIÓN DE FUNCIONES ES CONFIGURABLE, y esa es la parte importante.
     *
     * Lo correcto con dos personas es que quien carga no valide: la misma
     * persona que dice «entraron 150 Bs» no puede además declarar que lo
     * comprobó, porque entonces no lo comprobó nadie.
     *
     * Pero en una oficina de UNA sola persona esa regla deja el circuito
     * trabado: el mismo usuario carga y por lo tanto no puede validar, y el
     * trámite no se aprueba nunca. Escrita en el código, la única salida era
     * borrarla; como configuración, la unidad la enciende el día que tenga un
     * segundo usuario.
     *
     * Ver `pagos.revisor_distinto` en ConfiguracionSeeder. Apagada por defecto.
     *
     * LO QUE NO DEPENDE DE ESTO es el registro: quién cargó, quién validó y
     * cuándo se guarda siempre. Eso es lo que pidió la unidad, y no se pierde
     * con el interruptor apagado.
     *
     * Cuando `registrado_por` es null —lo cargado antes de que esto existiera—
     * no hay con quién comparar y se deja pasar: negarlo dejaría esos depósitos
     * imposibles de validar para siempre.
     *
     * Devuelve un booleano y no lanza: es lo que la pantalla necesita para
     * mostrar u ocultar el botón. Quien IMPIDE es ValidacionPagoService.
     */
    public function puedeValidarlo(?User $usuario): bool
    {
        if ($usuario === null) {
            return false;
        }

        if (! Configuracion::obtener('pagos.revisor_distinto', false)) {
            return true;
        }

        return $this->registrado_por === null || $this->registrado_por !== $usuario->id;
    }

    /**
     * Por qué NO se puede controlar ahora, en castellano de ventanilla.
     *
     * Devuelve null cuando sí se puede. Existe para que la pantalla EXPLIQUE en
     * vez de esconder los botones y dejar al operador preguntándose qué pasó —
     * que es lo que más confunde cuando algo desaparece sin motivo.
     */
    public function motivoSinControl(?User $usuario): ?string
    {
        if (! $this->admiteControl()) {
            $pagable = $this->pagable;

            if ($pagable instanceof Tramite) {
                return $pagable->estado === EstadoTramite::Pendiente
                    ? 'El expediente todavía es un borrador: se controla al enviarlo a revisión.'
                    : 'El expediente ya está '.mb_strtolower($pagable->estado->etiqueta()).': el control quedó cerrado.';
            }

            return 'Está anulada: ya no hay nada que controlar.';
        }

        if (! $this->puedeValidarlo($usuario)) {
            return 'Lo cargó usted: tiene que revisarlo otra persona.';
        }

        return null;
    }

    // ------------------------------------------------------------------
    //  Corrección de la fila
    // ------------------------------------------------------------------

    /**
     * ========================================================================
     *  ¿SE PUEDE TODAVÍA CORREGIR ESTE DEPÓSITO?
     * ========================================================================
     *
     * Corregir es la ÚNICA salida cuando una boleta se cargó mal: la tabla no
     * tiene `deleted_at` y no hay anulación de pagos, porque un pago «anulado»
     * que sigue en la lista solo invita a sumarlo por error.
     *
     * Dos condiciones, y son de naturaleza distinta:
     *
     *   VALIDADO NO SE TOCA. Alguien ya declaró, con su nombre y la hora, que
     *   esa boleta cuadra con el extracto del banco. Cambiarle el monto después
     *   dejaría esa firma puesta sobre otro número, que es exactamente lo que
     *   el control viene a evitar. Para corregirlo hay que OBSERVARLO primero:
     *   ahí quien revisa retira lo que había dado por bueno, y queda escrito.
     *
     *   LO QUE SE PAGA TIENE QUE SEGUIR ADMITIENDO PAGOS. Un trámite rechazado
     *   ya no cobra nada y una faena o una guía anuladas tampoco: corregir el
     *   número de transacción de una boleta de algo que no existe más no
     *   arregla nada y mueve una fila que ya quedó cerrada.
     *
     * Si `pagable` no se puede resolver se deja pasar, por lo mismo que en
     * `admiteControl()`: es una fila rota y negarle la corrección no la arregla.
     */
    public function admiteCorreccion(): bool
    {
        if ($this->estaValidado()) {
            return false;
        }

        $pagable = $this->pagable;

        if ($pagable instanceof Tramite) {
            return $pagable->estado->permitePagos();
        }

        if ($pagable instanceof Faena || $pagable instanceof Guia) {
            return $pagable->estado === EstadoPermiso::Emitido;
        }

        return true;
    }

    /**
     * Por qué NO se puede corregir, en castellano de ventanilla.
     *
     * Devuelve null cuando sí se puede. Mismo criterio que
     * `motivoSinControl()`: cuando algo no se puede, se DICE, en vez de
     * esconder el botón y dejar al operador preguntándose qué pasó.
     */
    public function motivoSinCorreccion(): ?string
    {
        if ($this->estaValidado()) {
            // «Para tocarlo» y no «para cambiarlo»: este mismo texto lo usa
            // ahora `motivoSinEliminacion()`, y las dos acciones se habilitan
            // con la misma regla. Ver admiteEliminacion().
            return 'ya fue validado. Para tocarlo, primero hay que observarlo.';
        }

        $pagable = $this->pagable;

        if ($pagable instanceof Tramite && ! $pagable->estado->permitePagos()) {
            return 'el expediente está rechazado y ya no cobra nada.';
        }

        if (($pagable instanceof Faena || $pagable instanceof Guia)
            && $pagable->estado !== EstadoPermiso::Emitido) {
            return 'el permiso está anulado y ya no cobra nada.';
        }

        return null;
    }

    /**
     * ========================================================================
     *  ¿SE PUEDE QUITAR ESTE DEPÓSITO DEL EXPEDIENTE?
     * ========================================================================
     *
     * LA MISMA PREGUNTA QUE `admiteCorreccion()`, y por eso delega en ella:
     * mientras la fila se pueda tocar, se puede tanto arreglar como sacar.
     *
     * ------------------------------------------------------------------------
     *  QUITAR FUE MÁS ESTRICTO QUE CORREGIR, Y LA DISTINCIÓN NO SE SOSTENÍA
     * ------------------------------------------------------------------------
     *
     * Estuvo permitido solo en el BORRADOR, con este razonamiento: al enviar
     * salió el RECIBO OFICIAL con el monto cobrado y el pescador se fue con ese
     * papel, así que hacer desaparecer un depósito dejaría el recibo cobrando
     * más de lo que el expediente puede mostrar.
     *
     * El argumento se cae solo, porque **corregir ya hace exactamente eso**. El
     * recibo se arma al vuelo con los depósitos que hay —ver
     * `ReciboTramiteService::detalle()`—, así que bajar un monto de 110 a 30
     * cambia el papel entregado igual que borrar la fila. Si una se permite, la
     * otra no se puede prohibir invocando el recibo.
     *
     * Lo que SÍ las distingue es qué queda: corregir deja la fila con su
     * historial; quitar deja solo la línea de `auditorias`. Eso no justifica
     * otro momento, justifica otro PERMISO —`pagos.eliminar`, de
     * administración— y el motivo obligatorio. Las dos cosas ya estaban.
     *
     * Y hay un caso que la regla vieja dejaba sin salida, que es el que apareció
     * en ventanilla: una boleta cargada dos veces sobre un expediente ya
     * presentado. Corregirla no sirve —no hay dato correcto que poner, ese
     * depósito no existe— y quitarla estaba prohibido. Quedaba sumando para
     * siempre.
     *
     * VALIDADO NO SE TOCA, ni para corregir ni para quitar: alguien firmó con su
     * nombre que ese dinero entró. Primero hay que observarlo.
     */
    public function admiteEliminacion(): bool
    {
        return $this->admiteCorreccion();
    }

    /**
     * Por qué NO se puede quitar. Null cuando sí se puede.
     *
     * Delega por lo mismo que `admiteEliminacion()`: si las dos acciones se
     * habilitan juntas, el motivo por el que no se puede es uno solo, y escrito
     * dos veces terminarían diciendo cosas distintas sobre el mismo depósito.
     */
    public function motivoSinEliminacion(): ?string
    {
        return $this->motivoSinCorreccion();
    }

    /**
     * La dirección para abrir la boleta escaneada.
     *
     * Pasa por Archivos::url() y no por Storage::url() porque la columna guarda
     * una ruta cuando el disco es local y una dirección completa cuando es s3, y
     * las dos formas conviven en la misma tabla.
     */
    protected function comprobanteUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => Archivos::url($this->urlFile));
    }

    // ------------------------------------------------------------------
    //  Scopes
    // ------------------------------------------------------------------

    /**
     * Los pagos de un tipo de cosa: `Pago::deTipo(Faena::class)`.
     *
     * Recibe la clase y no el texto para que un error de tipeo lo agarre el
     * editor y no una consulta que devuelve cero filas sin explicar por qué.
     *
     * qualifyColumn() porque los listados cruzan `pagos` con `tramites` y
     * `carnets`, que tienen columnas homónimas. Ver la regla 9 de CLAUDE.md.
     *
     * @param  class-string<Model>  $clase
     */
    public function scopeDeTipo(Builder $query, string $clase): Builder
    {
        return $query->where($query->qualifyColumn('pagable_type'), $clase);
    }

    /** Los pagos de trámites: la emisión y la actualización de carnets. */
    public function scopeDeTramites(Builder $query): Builder
    {
        return $query->deTipo(Tramite::class);
    }

    /**
     * Los depósitos que todavía nadie dio por buenos.
     *
     * Agrupa PENDIENTE y OBSERVADO: los dos frenan una aprobación, aunque por
     * motivos distintos. Es la cola de trabajo de quien revisa.
     */
    public function scopeSinValidar(Builder $query): Builder
    {
        return $query->where(
            $query->qualifyColumn('estado_validacion'),
            '!=',
            EstadoValidacionPago::Validado,
        );
    }

    /** Los pagos de permisos operativos: faenas y guías. */
    public function scopeDePermisos(Builder $query): Builder
    {
        return $query->whereIn(
            $query->qualifyColumn('pagable_type'),
            [Faena::class, Guia::class],
        );
    }
}
