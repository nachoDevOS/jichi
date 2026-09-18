<?php

namespace App\Models;

use App\Enums\EstadoCarnet;
use App\Enums\EstadoTramite;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * El documento anual de UNA actividad. Uno por persona, rubro y gestión — ver
 * la migración `carnets`, donde esa regla está escrita como índice único.
 *
 * Una persona que pesca y comercializa tiene DOS carnets en 2026, cada uno con
 * su plástico, su firma y su cupo. El carnet ES la habilitación: no hay tabla
 * intermedia, y por eso tampoco hay `habilitaciones()` — la que había se retiró
 * junto con `carnet_rubro`.
 *
 * OJO CON `#[Fillable]`: lo que no esté en esta lista, `update()` lo descarta
 * EN SILENCIO, sin lanzar ningún error. `rubro_id` y `capacidad_kg` están acá
 * porque el servicio las escribe al emitir y al aprobar.
 */
#[Fillable([
    'beneficiario_id',
    'rubro_id',
    'firma_validacion',
    'gestion',
    'asociacion',
    'capacidad_kg',
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
            // decimal:2 y no float, por el mismo motivo que en `tramites`: el
            // cupo se compara contra kilos declarados en guías de transporte, y
            // en punto flotante 600.1 + 0.2 no da 600.3.
            'capacidad_kg' => 'decimal:2',
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

    /**
     * LA ACTIVIDAD QUE HABILITA ESTE CARNET.
     *
     * Es un `belongsTo` y no la lista que había antes: el carnet tiene UN rubro,
     * no varios. Donde el código viejo pedía `$carnet->rubros` o
     * `$carnet->habilitaciones`, ahora pide `$carnet->rubro`.
     */
    public function rubro(): BelongsTo
    {
        return $this->belongsTo(Rubro::class);
    }

    /*
     * ------------------------------------------------------------------
     *  LOS PERMISOS OPERATIVOS QUE CUELGAN DEL CARNET
     * ------------------------------------------------------------------
     *
     * El carnet es la LLAVE ANUAL; con él solo no se sale a trabajar. Lo que
     * autoriza el trabajo de cada día son estos dos, y se emiten muchos por
     * gestión:
     *
     *     carnet de Pescador        ──< faenas  (una por salida)
     *     carnet de Comercializador ──< guías   (una por carga trasladada)
     *
     * LAS DOS RELACIONES EXISTEN EN TODO CARNET, aunque un carnet dado solo use
     * una. Limitarlas por rubro desde el modelo obligaría a preguntar antes de
     * poder consultar, y devolver una colección vacía dice exactamente lo
     * mismo. Qué puede emitir cada actividad lo dicen `Rubro::emiteFaenas()` y
     * `Rubro::emiteGuias()`, y lo hace cumplir el servicio al crear.
     *
     * Ordenadas de la más nueva a la más vieja porque la ficha muestra primero
     * lo último emitido, que es lo que se consulta.
     */

    public function faenas(): HasMany
    {
        return $this->hasMany(Faena::class)->latest('fecha_salida');
    }

    public function guias(): HasMany
    {
        return $this->hasMany(Guia::class)->latest('id');
    }

    // ------------------------------------------------------------------
    //  Reglas de negocio
    // ------------------------------------------------------------------

    /**
     * ¿Vale hoy este carnet? ¿Autoriza a trabajar?
     *
     * SE MIRAN LAS DOS COSAS, Y NO ES REDUNDANTE:
     *
     *   - el estado, porque un carnet ANULADO o SUSPENDIDO conserva la fecha de
     *     vencimiento de diciembre y la fecha sola no lo delataría;
     *   - la fecha, porque el estado `vencido` lo escribe un comando programado
     *     que corre una vez al día, y entre corrida y corrida un carnet que
     *     venció ayer sigue diciendo «vigente».
     *
     * Cada una tapa el agujero de la otra. Ver App\Enums\EstadoCarnet.
     *
     * DESDE EL MODELO NUEVO, ESTO RESPONDE ADEMÁS «¿PUEDE EJERCER ESTE RUBRO?».
     * Antes eran dos preguntas —el carnet valía, y aparte cada rubro estaba
     * habilitado o suspendido—; hoy el carnet es el rubro y se responden juntas.
     */
    public function estaVigente(): bool
    {
        return $this->estado->habilita()
            && $this->fecha_vencimiento?->endOfDay()->isFuture();
    }

    /**
     * ¿Se le puede presentar un trámite de actualización?
     *
     * Reemplaza al viejo `admiteAdiciones()`: ya no se le suman rubros a un
     * carnet —cada rubro es un carnet—, pero sí se le puede presentar un
     * expediente para corregir el cupo o la asociación.
     */
    public function admiteTramites(): bool
    {
        return $this->estado->admiteTramites() && $this->estaVigente();
    }

    /**
     * ========================================================================
     *  ¿SE LE PUEDE EMITIR UNA FAENA / UNA GUÍA A ESTE CARNET?
     * ========================================================================
     *
     * SON DOS CONDICIONES Y LAS DOS HACEN FALTA:
     *
     *   - QUE LA ACTIVIDAD LO EMITA. Un carnet de Comercializador no da faenas
     *     por más vigente que esté: son permisos de pesca. Lo dice el catálogo
     *     —`rubros.emite_faenas`— y no un `match` sobre el nombre del rubro, por
     *     lo mismo que `requiereCapacidad()`: el nombre lo edita la unidad desde
     *     el panel y cambia.
     *   - QUE EL CARNET VALGA HOY. Un carnet vencido o suspendido no habilita a
     *     salir, así que tampoco puede autorizar una salida nueva.
     *
     * Devuelve un booleano y no lanza nada: es la pregunta que hace la pantalla
     * para mostrar u ocultar el botón. Quien IMPIDE la emisión es el servicio,
     * que además tiene que explicar en castellano cuál de las dos falló.
     */
    public function puedeEmitirFaenas(): bool
    {
        return $this->estaVigente() && (bool) $this->rubro?->emiteFaenas();
    }

    public function puedeEmitirGuias(): bool
    {
        return $this->estaVigente() && (bool) $this->rubro?->emiteGuias();
    }

    /**
     * ¿HAY ALGO QUE IMPRIMIR?
     *
     * Lo decide el servidor y viaja a la pantalla en `puede_imprimirse`, por la
     * misma razón que los `puede_*` del trámite: escrita otra vez en React, la
     * regla terminaría diciendo algo distinto que el controlador.
     *
     * ------------------------------------------------------------------------
     *  LA SEGUNDA CONDICIÓN CAMBIÓ DE FORMA, NO DE SENTIDO
     * ------------------------------------------------------------------------
     *
     * Antes se exigía «al menos un rubro habilitado», porque el carnet nacía
     * con el trámite PENDIENTE y la habilitación recién aparecía al aprobar:
     * sin esa condición se imprimía una credencial que no autorizaba a nada.
     *
     * Hoy no hay habilitaciones que contar, pero el problema es el mismo: el
     * carnet sigue naciendo con el trámite en PENDIENTE. Lo que se exige ahora
     * es que ALGÚN trámite suyo esté aprobado — que es exactamente lo que antes
     * significaba tener un rubro habilitado.
     *
     * Las otras dos condiciones no cambian:
     *
     *   - EL CARNET NO PUEDE ESTAR ANULADO. Anular es una sanción: volver a
     *     sacar el plástico dejaría en la calle un documento que el sistema ya
     *     desconoció, con su QR intacto.
     *   - VENCIDO SÍ SE IMPRIME. Es la reimpresión de un documento que existió:
     *     el plástico dice su gestión y su fecha de vencimiento, y la
     *     verificación pública ya avisa que caducó.
     *
     * SUSPENDIDO TAMBIÉN SE IMPRIME, por lo mismo: el documento existe y la
     * verificación pública informa que está cortado. Negar la reimpresión no
     * quitaría de circulación el plástico que la persona ya tiene.
     */
    public function puedeImprimirse(): bool
    {
        if ($this->estado === EstadoCarnet::Anulado) {
            return false;
        }

        // Si quien llamó ya trajo los trámites con `with()`, se cuentan en
        // memoria. `$this->tramites()->where(...)` consultaría IGUAL, con el
        // eager loading puesto y todo — es la trampa que ya costó 18 consultas
        // por tecleada en el autocompletado de beneficiarios.
        if ($this->relationLoaded('tramites')) {
            return $this->tramites->contains(
                fn (Tramite $t): bool => $t->estado === EstadoTramite::Aprobado,
            );
        }

        return $this->tramites()->where('estado', EstadoTramite::Aprobado)->exists();
    }

    /**
     * El cupo tal como se escribe en el plástico: «600 KG».
     *
     * Devuelve null cuando no hay cupo cargado, para que la maqueta impresa
     * pueda saltear el renglón entero en vez de dibujar «KG» sin número.
     *
     * Se recorta la cola de decimales cuando son cero: la unidad trabaja en
     * kilos enteros, y «600,00 KG» en una tarjeta CR80 gasta cuatro caracteres
     * de un renglón que ya viene justo.
     */
    public function capacidadLegible(): ?string
    {
        if ($this->capacidad_kg === null) {
            return null;
        }

        $kg = (float) $this->capacidad_kg;
        $numero = fmod($kg, 1.0) === 0.0
            ? number_format($kg, 0, ',', '.')
            : number_format($kg, 2, ',', '.');

        return "{$numero} KG";
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
            ->where($query->qualifyColumn('estado'), EstadoCarnet::Vigente)
            ->whereDate($query->qualifyColumn('fecha_vencimiento'), '>=', now()->toDateString());
    }

    /**
     * Los carnets de una actividad.
     *
     * qualifyColumn() porque los reportes cruzan `carnets` con `rubros` y con
     * `tramites`, y las tres tienen columnas que se llaman igual. Ver la regla 9
     * de CLAUDE.md.
     */
    public function scopeDeRubro(Builder $query, int $rubroId): Builder
    {
        return $query->where($query->qualifyColumn('rubro_id'), $rubroId);
    }
}
