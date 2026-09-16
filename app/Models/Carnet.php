<?php

namespace App\Models;

use App\Enums\EstadoCarnet;
use App\Enums\EstadoHabilitacion;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * El documento anual. Uno por persona y por gestión — ver la migración
 * `carnets`, donde esa regla está escrita como índice único.
 */
#[Fillable([
    'beneficiario_id',
    'firma_validacion',
    'gestion',
    'asociacion',
    'fecha_emision',
    'fecha_vencimiento',
    'estado',
])]
class Carnet extends Model
{
    use Auditable;

    /*
     * La firma SÍ se audita, a diferencia de antes.
     *
     * Cuando el carnet tenía además un `codigo` público, la firma era el secreto
     * que protegía la verificación y no correspondía escribirla en `auditorias`
     * —que lee cualquiera con permiso `auditoria.ver`—.
     *
     * Ahora la firma ES el identificador del carnet: va impresa, se muestra en
     * el panel y se dicta en ventanilla. Excluirla de la auditoría dejaría las
     * filas sin el dato que permite saber de qué carnet hablan.
     */

    protected function casts(): array
    {
        return [
            'estado' => EstadoCarnet::class,
            'gestion' => 'integer',
            'fecha_emision' => 'date',
            'fecha_vencimiento' => 'date',
        ];
    }

    /**
     * Largo de la firma de validación. Coincide con el de la columna.
     *
     * 16 caracteres alfanuméricos son ~95 bits de entropía: adivinar uno por
     * fuerza bruta es imposible en la práctica, y al mismo tiempo entra en una
     * línea legible bajo el código del carnet para quien tenga que escribirlo a
     * mano porque el QR no se deja escanear.
     */
    public const LARGO_FIRMA = 16;

    // ------------------------------------------------------------------
    //  Relaciones
    // ------------------------------------------------------------------

    /**
     * withTrashed() a propósito: si la ficha del beneficiario se dio de baja,
     * el carnet histórico tiene que seguir siendo legible. Sin esto, la ficha
     * del carnet mostraría el titular en blanco y la verificación pública
     * respondería como si el documento no existiera.
     */
    public function beneficiario(): BelongsTo
    {
        return $this->belongsTo(Beneficiario::class)->withTrashed();
    }

    public function tramites(): HasMany
    {
        return $this->hasMany(Tramite::class);
    }

    public function habilitaciones(): HasMany
    {
        return $this->hasMany(CarnetRubro::class);
    }

    /**
     * Los rubros del carnet, con los datos de la habilitación en el pivote.
     */
    public function rubros(): BelongsToMany
    {
        return $this->belongsToMany(Rubro::class, 'carnet_rubro')
            ->using(CarnetRubro::class)
            ->withPivot(['id', 'fecha_habilitacion', 'estado'])
            ->withTimestamps();
    }

    // ------------------------------------------------------------------
    //  Reglas de negocio
    // ------------------------------------------------------------------

    /**
     * ¿Vale hoy este carnet?
     *
     * SE MIRAN LAS DOS COSAS, Y NO ES REDUNDANTE:
     *
     *   - el estado, porque un carnet ANULADO en marzo conserva la fecha de
     *     vencimiento de diciembre y la fecha sola no lo delataría;
     *   - la fecha, porque el estado `vencido` lo escribe un comando programado
     *     que corre una vez al día, y entre corrida y corrida un carnet que
     *     venció ayer sigue diciendo «vigente».
     *
     * Cada una tapa el agujero de la otra. Ver App\Enums\EstadoCarnet.
     */
    public function estaVigente(): bool
    {
        return $this->estado === EstadoCarnet::Vigente
            && $this->fecha_vencimiento?->endOfDay()->isFuture();
    }

    /**
     * ¿Se le puede sumar un rubro más?
     */
    public function admiteAdiciones(): bool
    {
        return $this->estado->admiteAdiciones() && $this->estaVigente();
    }

    /**
     * ¿HAY ALGO QUE IMPRIMIR?
     *
     * Lo decide el servidor y viaja a la pantalla en `puede_imprimirse`, por la
     * misma razón que los `puede_*` del trámite: escrita otra vez en React, la
     * regla terminaría diciendo algo distinto que el controlador.
     *
     * Dos condiciones, y cada una tapa un caso real de ventanilla:
     *
     *   - EL CARNET NO PUEDE ESTAR ANULADO. Anular es una sanción: volver a
     *     sacar el plástico dejaría en la calle un documento que el sistema ya
     *     desconoció, con su QR intacto.
     *   - TIENE QUE TENER AL MENOS UN RUBRO HABILITADO. El carnet nace con el
     *     trámite —en PENDIENTE ya existe la fila—, pero la habilitación recién
     *     aparece al APROBAR. Imprimir antes entregaría una credencial que no
     *     autoriza a nada y que todavía puede no estar pagada.
     *
     * VENCIDO SÍ SE IMPRIME. Es la reimpresión de un documento que existió: el
     * plástico dice su gestión y su fecha de vencimiento, y la verificación
     * pública ya avisa que caducó. Negarla obligaría a explicar a mano por qué
     * el sistema no puede mostrar lo que emitió el año pasado.
     */
    public function puedeImprimirse(): bool
    {
        if ($this->estado === EstadoCarnet::Anulado) {
            return false;
        }

        // Si quien llamó ya trajo las habilitaciones con `with()`, se cuentan en
        // memoria. `$this->habilitaciones()->exists()` consultaría IGUAL, con el
        // eager loading puesto y todo — es la trampa que ya costó 18 consultas
        // por tecleada en el autocompletado de beneficiarios.
        if ($this->relationLoaded('habilitaciones')) {
            return $this->habilitaciones->isNotEmpty();
        }

        return $this->habilitaciones()->exists();
    }

    /**
     * ¿Este rubro ya está habilitado en el carnet?
     *
     * Es la comprobación que impide cobrar dos veces la misma adición. La red
     * de abajo es el índice único `carnet_rubro_unico`, que sí resiste dos
     * peticiones simultáneas; esta sirve para poder dar un mensaje entendible
     * en ventanilla antes de llegar a ese error.
     *
     * Cuenta también los rubros SUSPENDIDOS: la habilitación existe, lo que
     * corresponde es levantar la suspensión, no volver a tramitarla.
     */
    public function tieneRubro(int $rubroId): bool
    {
        return $this->habilitaciones()->where('rubro_id', $rubroId)->exists();
    }

    /**
     * Los rubros que hoy autorizan a trabajar, sin los suspendidos.
     *
     * Es lo que se imprime en el reverso y lo que ve el inspector al escanear.
     */
    public function rubrosHabilitados(): Collection
    {
        return $this->rubros()
            ->wherePivot('estado', EstadoHabilitacion::Habilitado->value)
            ->get();
    }

    // ------------------------------------------------------------------
    //  Emisión
    // ------------------------------------------------------------------

    /*
     * NO HAY GENERADOR DE CÓDIGOS, y no es un olvido.
     *
     * El carnet tenía una columna `codigo` —primero el correlativo
     * CARNET-2026-0001, después XXXX-XXXX-XXXX al azar— y se retiró a pedido. El
     * carnet se identifica por su firma de validación, que es única.
     *
     * Lo que se pierde con eso, y conviene tener presente: ya no hay un número
     * corto y amable para nombrar un carnet en ventanilla. Quien necesite
     * referirse a uno usa la cédula del titular o el id del trámite.
     */

    /**
     * Una firma nueva que no esté usada.
     *
     * ------------------------------------------------------------------------
     *  POR QUÉ SE COMPRUEBA CONTRA LA BASE
     * ------------------------------------------------------------------------
     *
     * Con 16 caracteres alfanuméricos la repetición es imposible en la práctica,
     * pero desde que la firma es el ÚNICO identificador del carnet ya no alcanza
     * con que sea improbable: dos carnets con la misma firma serían dos
     * documentos indistinguibles, y la verificación pública devolvería
     * cualquiera de los dos.
     *
     * Se comprueba antes en vez de dejar fallar el INSERT porque esto corre
     * DENTRO de una transacción, y en PostgreSQL un INSERT fallido la aborta
     * entera: no se puede reintentar sin savepoints. El índice único de la
     * columna sigue siendo la garantía final.
     *
     * El tope de intentos existe para que un error de programación no se
     * convierta en un bucle infinito que cuelgue la petición.
     */
    public static function nuevaFirma(): string
    {
        foreach (range(1, 10) as $intento) {
            $firma = self::generarFirma();

            if (! self::query()->where('firma_validacion', $firma)->exists()) {
                return $firma;
            }
        }

        throw new \RuntimeException(
            'No se pudo generar una firma de carnet sin repetir después de 10 intentos.',
        );
    }

    /**
     * Str::random() usa el generador criptográfico del sistema, no rand().
     *
     * La diferencia importa acá: los números pseudoaleatorios comunes son
     * predecibles si se conoce la semilla, y una firma predecible dejaría de
     * proteger la verificación pública —que ahora depende solo de ella—.
     */
    private static function generarFirma(): string
    {
        return Str::upper(Str::random(self::LARGO_FIRMA));
    }

    /**
     * Deja una firma tipeada a mano como está guardada: 16 caracteres, sin
     * separadores y en mayúscula.
     *
     * Se imprime en grupos de cuatro para poder leerla —4K7R J2MX P9TQ 3WHB— y
     * quien la copia escribe lo que ve: con espacios, con guiones, o todo junto.
     * Y en minúscula, porque el teclado del teléfono arranca así.
     *
     * La regla vive acá y no en el controlador público porque la comparten dos
     * lados: esa pantalla, que recibe lo que el ciudadano tipeó, y el generador,
     * que decide la forma canónica.
     */
    public static function normalizarFirma(string $firma): string
    {
        return (string) preg_replace('/[^A-Z0-9]/', '', strtoupper(trim($firma)));
    }

    /**
     * La firma en grupos de cuatro: 4K7R-J2MX-P9TQ-3WHB.
     *
     * Es la forma en que se muestra dentro del PANEL y se dicta por teléfono
     * cuando el QR no se deja escanear. Guardada va sin separadores —una sola
     * forma canónica—; agruparla es cosa de la presentación.
     *
     * OJO: la firma NO va impresa en el carnet. En el plástico se imprime el
     * número de registro y el QR; la firma viaja solo dentro de ese QR. Ver
     * `registro()`.
     */
    public function firmaLegible(): string
    {
        return implode('-', str_split($this->firma_validacion, 4));
    }

    /**
     * LA DIRECCIÓN QUE SE CODIFICA EN EL QR.
     *
     * Vive en el modelo y no en un controlador porque la usan dos: la ficha del
     * panel la muestra como enlace y la impresión la mete dentro del código. La
     * misma dirección tiene que salir de los dos lados —si difirieran, el QR
     * impreso llevaría a otro lugar que el del panel y nadie lo notaría hasta
     * que un carnet no verifique—.
     *
     * La base sale de config/jichi.php y no se arma con url() porque el
     * ciudadano escanea el QR desde su teléfono, fuera de la red departamental:
     * una dirección como http://jichi.test/verificar no abriría nada.
     *
     * Lleva SOLO la firma. Antes eran dos datos —código en la ruta y firma en
     * la query— porque el código era público y la firma el secreto; desde que
     * el código se retiró, la firma cumple los dos papeles y la URL queda más
     * corta. Un QR con menos caracteres necesita menos módulos, y menos módulos
     * se leen mejor con la cámara sucia y el plástico rayado.
     */
    public function urlVerificacion(): string
    {
        $base = rtrim((string) config('jichi.url_verificacion'), '/');

        if ($base === '') {
            $base = rtrim(url('/verificar'), '/');
        }

        return "{$base}/{$this->firma_validacion}";
    }

    /** Cuántos dígitos tiene el número de registro impreso. */
    public const DIGITOS_REGISTRO = 6;

    /**
     * ========================================================================
     *  EL NÚMERO DE REGISTRO — lo que se imprime en el carnet
     * ========================================================================
     *
     * Es el `id` de la fila, rellenado con ceros: 000013.
     *
     * ------------------------------------------------------------------------
     *  POR QUÉ EL ID Y NO LA FIRMA
     * ------------------------------------------------------------------------
     *
     * El plástico necesita un número que una persona pueda leer, anotar y decir
     * en voz alta. Dieciséis caracteres al azar no sirven para eso: nadie dicta
     * «TZUK F1V1 FAIP M9EP» sin equivocarse, y en una CR80 ocupan un renglón
     * entero.
     *
     * Que sea correlativo y adivinable no importa, porque el registro NO abre
     * nada: la verificación pública pide la firma, y la firma viaja únicamente
     * dentro del QR. Quien lea «000013» en un carnet ajeno no puede consultarlo.
     *
     * Ese reparto es el que hace que las dos cosas funcionen: un número corto y
     * público para nombrar el carnet, y un valor largo y secreto para abrirlo.
     *
     * ------------------------------------------------------------------------
     *  LA CONTRAPARTIDA
     * ------------------------------------------------------------------------
     *
     * Si el QR queda ilegible —rayado, mojado, despintado— no hay forma de
     * verificar ese carnet desde la calle: la firma no está impresa en ningún
     * lado. Hay que pasar por la oficina, donde el panel sí la muestra. Es el
     * precio de que la firma sea un secreto de verdad.
     *
     * ------------------------------------------------------------------------
     *  LOS CEROS A LA IZQUIERDA
     * ------------------------------------------------------------------------
     *
     * No son decoración: mantienen el ancho estable. Sin ellos, el renglón del
     * carnet mide distinto según el carnet sea el número 7 o el 1247, y en una
     * tarjeta impresa a molde eso se nota.
     */
    public function registro(): string
    {
        return str_pad((string) $this->id, self::DIGITOS_REGISTRO, '0', STR_PAD_LEFT);
    }

    /**
     * El último día de la gestión.
     *
     * TODOS LOS CARNETS DE UNA GESTIÓN VENCEN EL MISMO DÍA, no a los 365 días de
     * emitidos. Sacado en enero dura casi doce meses, sacado en diciembre dura
     * unas semanas. Es la forma en que se maneja el talonario en papel y el
     * sistema la copia: si venciera a los N días, en la práctica habría
     * carnets de la gestión 2026 válidos en mayo de 2027.
     */
    public static function vencimientoDeGestion(int $gestion): Carbon
    {
        return Carbon::createFromDate($gestion, 12, 31)->startOfDay();
    }

    // ------------------------------------------------------------------
    //  Scopes
    // ------------------------------------------------------------------

    public function scopeDeGestion(Builder $query, ?int $gestion = null): Builder
    {
        return $query->where('gestion', $gestion ?? (int) now()->format('Y'));
    }

    public function scopeVigentes(Builder $query): Builder
    {
        return $query
            ->where('estado', EstadoCarnet::Vigente)
            ->whereDate('fecha_vencimiento', '>=', now()->toDateString());
    }
}
