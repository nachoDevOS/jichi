<?php

namespace App\Models;

use App\Enums\EstadoAprovechamiento;
use App\Enums\EstadoFaena;
use App\Enums\ModalidadAprovechamiento;
use App\Traits\Auditable;
use App\Traits\Pagable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * LA BOLSA MADRE del pescador: el cupo anual en kilos, con fecha.
 *
 * ============================================================================
 *  LA REGLA DEL MÓDULO ES UNA RESTA, Y VIVE EN saldoKg()
 * ============================================================================
 *
 *     volumen_total_kg − (kilos de las faenas que consumen cupo) = saldo
 *
 * Cuando el saldo llega a cero no se emiten más faenas. Esa resta NO se guarda
 * en ninguna columna: una columna `saldo` hay que actualizarla en cada alta,
 * cada anulación y cada corrección, y se olvida una sola vez para que el número
 * quede mintiendo para siempre sin ningún error que lo delate.
 *
 * ============================================================================
 *  UNA FAENA ACTIVA YA CONSUME CUPO, AUNQUE NO SE HAYA DESCARGADO NADA
 * ============================================================================
 *
 * Es lo contrario de lo que parece intuitivo, y es el punto del cupo: si solo
 * contaran las completadas, un pescador podría tener diez faenas abiertas por
 * el volumen entero cada una. Lo que libera el volumen es que la faena VENZA
 * sin cerrarse — ahí la salida no ocurrió. Ver EstadoFaena::consumeCupo().
 *
 * @property-read string|null $faenas_que_consumen_sum_kilos_extraidos columna virtual que agrega withSum()
 */
#[Fillable([
    'beneficiario_id',
    'categoria_aprov_id',
    'modalidad',
    'volumen_total_kg',
    'tipo_embarcacion',
    'estado',
    'fecha_emision',
    'fecha_vencimiento',
])]
class AprovechamientoPesq extends Model
{
    use Auditable, Pagable, SoftDeletes;

    /** «AprovechamientoPesq» no pluraliza a «aprovechamientos_pesq» por sí solo. */
    protected $table = 'aprovechamientos_pesq';

    /**
     * Ver el comentario de Asociacion::$attributes: un default de la base NO
     * llega al objeto que devuelve create(), así que otorgar un cupo y
     * preguntarle el estado en la línea siguiente contestaría null.
     */
    protected $attributes = [
        // NACE PENDIENTE: se activa cuando se termina de cobrar. Ver
        // EstadoAprovechamiento y CobrarService::activarSiQuedoPagado().
        'estado' => EstadoAprovechamiento::Pendiente->value,
        'modalidad' => ModalidadAprovechamiento::EscalaGeneral->value,
    ];

    protected function casts(): array
    {
        return [
            'volumen_total_kg' => 'decimal:2',
            'estado' => EstadoAprovechamiento::class,
            'modalidad' => ModalidadAprovechamiento::class,
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

    /** La escala bajo la que se otorgó. Solo referencia: el volumen ya está copiado. */
    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaAprovechamiento::class, 'categoria_aprov_id');
    }

    public function faenas(): HasMany
    {
        return $this->hasMany(PermisoFaena::class, 'aprovechamiento_id');
    }

    /**
     * Las credenciales que se apoyan en este cupo.
     *
     * Son varias en teoría —el carnet se renueva a mitad de un cupo vigente—
     * aunque en la práctica casi siempre haya una.
     */
    public function carnets(): HasMany
    {
        return $this->hasMany(Carnet::class, 'aprovechamiento_id');
    }

    // ------------------------------------------------------------------
    //  Reglas de negocio
    // ------------------------------------------------------------------

    /**
     * Lo que sale este cupo: el valor de la escala con la que se otorgó.
     *
     * Exigido por el trait Pagable. Si la categoría no está cargada se consulta
     * —no se puede devolver 0 y seguir, porque eso daría un saldo de 0 y el
     * sistema creería que el cupo está pagado—.
     */
    public function montoACobrar(): float
    {
        return (float) ($this->categoria?->valor_bs ?? 0.0);
    }

    /**
     * Kilos ya comprometidos por las faenas.
     *
     * Igual que en `montoPagado()` del trait, la primera rama es lo que evita
     * una consulta agregada por fila en un listado: quien arma la pantalla hace
     * `withSum('faenasQueConsumen', 'kilos_extraidos')` y acá se reusa.
     *
     * Y por lo mismo se pregunta si la CLAVE EXISTE y no si el valor es
     * distinto de null: `withSum` devuelve NULL sobre un conjunto vacío, así
     * que un cupo recién otorgado —sin ninguna faena— se caería a la consulta
     * suelta con el withSum puesto.
     */
    public function kilosConsumidos(): float
    {
        if (array_key_exists('faenas_que_consumen_sum_kilos_extraidos', $this->getAttributes())) {
            return (float) $this->faenas_que_consumen_sum_kilos_extraidos;
        }

        if ($this->relationLoaded('faenas')) {
            return (float) $this->faenas
                ->filter(fn (PermisoFaena $f): bool => $f->estado->consumeCupo())
                ->sum('kilos_extraidos');
        }

        return (float) $this->faenasQueConsumen()->sum('kilos_extraidos');
    }

    /** Las faenas cuyo volumen pesa contra el cupo: todas menos las vencidas. */
    public function faenasQueConsumen(): HasMany
    {
        return $this->faenas()->whereNot('estado', EstadoFaena::Vencido);
    }

    /**
     * Kilos que quedan. Se corta en cero: un cupo excedido no es un saldo
     * negativo del que se pueda seguir restando, es un cupo agotado.
     */
    public function saldoKg(): float
    {
        return max(0.0, round((float) $this->volumen_total_kg - $this->kilosConsumidos(), 2));
    }

    /**
     * ========================================================================
     *  LOS KILOS QUE SE PASARON DEL CUPO
     * ========================================================================
     *
     * En modo ESTRICTO siempre es cero: la emisión no deja pasar una faena que
     * no entre. Existe por el modo FLEXIBLE, donde el tope no se comprueba.
     *
     * Y es justamente lo que `saldoKg()` no puede decir: ese método se corta en
     * cero —un cupo excedido no es un saldo negativo del que seguir restando—
     * así que sin este número el exceso sería invisible y volver a encender el
     * modo estricto dejaría gente por encima sin que nadie supiera cuánto.
     */
    public function kilosExcedidos(): float
    {
        return max(0.0, round($this->kilosConsumidos() - (float) $this->volumen_total_kg, 2));
    }

    /** ¿Se pasó del volumen otorgado? Solo puede ocurrir en modo flexible. */
    public function estaExcedido(): bool
    {
        return $this->kilosExcedidos() > 0.0;
    }

    /**
     * ========================================================================
     *  ¿EL TOPE DE LA BOLSA MADRE SE HACE CUMPLIR?
     * ========================================================================
     *
     * Sale de `jichi.aprovechamiento.estricto`, que a su vez lee
     * APROVECHAMIENTO_ESTRICTO del .env. Por defecto TRUE: un sistema que
     * arranca sin control y hay que acordarse de encender no controla nada.
     *
     * Vive en el modelo y no repartido por los servicios y las pantallas porque
     * la respuesta tiene que ser LA MISMA en los tres lugares donde se
     * pregunta: el servicio que emite, el método que dice si se puede emitir, y
     * el formulario que avisa antes. Leída tres veces con `config()` suelto,
     * alcanza con que alguien cambie la clave en un lado.
     *
     * NUNCA con env() acá: con `config:cache` activo devuelve null fuera de
     * config/, y el error sería silencioso — el sistema creería que el modo es
     * flexible y dejaría de controlar el cupo sin avisar.
     */
    /**
     * ¿Se pueden corregir sus datos HOY?
     *
     * ------------------------------------------------------------------------
     *  NO ALCANZA CON EL ESTADO: SE MIRA TAMBIÉN SI ENTRÓ PLATA
     * ------------------------------------------------------------------------
     *
     * El estado es la regla, pero puede quedar desfasado por abajo. Si alguien
     * cargara un pago sin pasar por `CobrarService` —una corrección a mano en la
     * base, una importación— el cupo seguiría diciendo `pendiente` con un recibo
     * ya emitido detrás, y editarlo cambiaría lo que ese papel dice.
     *
     * Preguntar las dos cosas cuesta una consulta y cierra el agujero.
     */
    public function puedeEditarse(): bool
    {
        return $this->estado->permiteEdicion() && $this->montoPagado() <= 0.0;
    }

    /**
     * ¿Se puede borrar la fila entera?
     *
     * Mismo criterio que la edición MÁS las faenas: un cupo sin pagos puede
     * igual tener permisos emitidos encima —el talonario de faenas es papel y se
     * llena antes de cobrar— y borrarlo dejaría esas salidas sin la bolsa madre
     * que las respalda.
     */
    public function puedeEliminarse(): bool
    {
        return $this->estado->permiteEliminacion()
            && $this->montoPagado() <= 0.0
            && $this->faenas()->doesntExist();
    }

    /**
     * ¿Se le pueden cargar depósitos hoy?
     *
     * Solo en pendiente: en revisión el monto ya está cubierto y el expediente
     * presentado, y después de aprobado la plata que entre de más no es de este
     * trámite.
     */
    public function admitePagos(): bool
    {
        return $this->estado->permitePagos();
    }

    /**
     * ========================================================================
     *  ¿SE PUEDE MANDAR A QUE ALGUIEN LO FIRME?
     * ========================================================================
     *
     * DOS condiciones, y la segunda es la que pidió la unidad: el estado tiene
     * que ser PENDIENTE y los depósitos tienen que CUBRIR el monto.
     *
     * No alcanza con el estado porque un cupo a medio pagar sigue siendo
     * pendiente, y presentarlo así obligaría a quien firma a devolverlo — que es
     * trabajo de ida y vuelta por algo que la pantalla puede ver antes.
     *
     * Se compara con `>= 0` sobre el saldo y no con una igualdad: pagar de más
     * no deja saldo negativo —`saldoPendiente()` se corta en cero— así que lo
     * que se pregunta es si quedó algo sin cubrir.
     */
    public function puedeEnviarseARevision(): bool
    {
        return $this->estado->permiteEnvio() && $this->saldoPendiente() <= 0.0;
    }

    /** ¿Está presentado y esperando una firma? */
    public function puedeRevisarse(): bool
    {
        return $this->estado->permiteRevision();
    }

    public static function modoEstricto(): bool
    {
        return (bool) config('jichi.aprovechamiento.estricto', true);
    }

    /** Qué porcentaje del cupo se usó. Para la barra de progreso de la ficha. */
    public function porcentajeUsado(): float
    {
        $total = (float) $this->volumen_total_kg;

        // El cupo de 0 kg no debería existir, pero si existe la división
        // reventaría y la ficha entera dejaría de abrir por un dato mal cargado.
        return $total <= 0.0 ? 100.0 : min(100.0, round($this->kilosConsumidos() / $total * 100, 1));
    }

    /**
     * ¿Vale HOY?
     *
     * Mira el estado Y la fecha. El estado solo no alcanza: `vencido` lo
     * escribe un comando que corre una vez al día, así que entre corrida y
     * corrida un cupo que venció ayer sigue diciendo «activo» en la base.
     */
    public function estaVigente(): bool
    {
        return $this->estado->habilita() && $this->estaEnFecha();
    }

    /**
     * ========================================================================
     *  ¿ESTÁ DENTRO DE SU PERÍODO? — sin mirar el estado
     * ========================================================================
     *
     * Es la mitad «calendario» de `estaVigente()`, y existe separada porque
     * confundir las dos ya costó dos errores reales:
     *
     *   - EMITIR UNA FAENA hacía lo mismo y devolvía «no tiene aprovechamiento
     *     vigente» sobre un cupo que existe y está en fecha, mandando al
     *     operador a otorgar uno nuevo, que la regla de una bolsa por
     *     persona iba a rechazar.
     *
     * La diferencia en una línea: un cupo agotado SÍ está en fecha —lo que se
     * le acabó son los kilos, no el tiempo— así que lo que corresponde decir es
     * «quedan 0 kg», no «no existe».
     */
    public function estaEnFecha(): bool
    {
        return $this->fecha_vencimiento !== null
            && $this->fecha_vencimiento->endOfDay()->isFuture();
    }

    /**
     * ¿Se le puede colgar una faena hoy?
     *
     * Las tres condiciones juntas: vigente, con saldo, y el volumen pedido —si
     * se pasa— entrando en ese saldo. Sin la tercera, el cupo sería decorativo.
     */
    public function puedeEmitirFaena(?float $kilos = null): bool
    {
        /*
         * LA FECHA MANDA EN LOS DOS MODOS. Un cupo vencido no habilita nada, y
         * eso no lo afloja el modo flexible: lo que ese modo relaja es el TOPE
         * en kilos, no el calendario. Una faena colgada de un cupo del año
         * pasado sería un permiso sin ninguna autorización detrás.
         */
        if (! $this->estaEnFecha()) {
            return false;
        }

        /*
         * EN MODO FLEXIBLE NO SE MIRA EL SALDO, y por eso tampoco se mira el
         * ESTADO: un cupo `agotado` sigue emitiendo, que es exactamente lo que
         * ese modo significa. Preguntar por `estaVigente()` acá lo bloquearía
         * igual —`agotado` no habilita— y el modo no serviría de nada.
         */
        if (! self::modoEstricto()) {
            return true;
        }

        if (! $this->estado->habilita() || $this->saldoKg() <= 0.0) {
            return false;
        }

        return $kilos === null || $kilos <= $this->saldoKg();
    }

    /** ¿Se le acabaron los kilos, aunque la fecha no haya llegado? */
    public function estaAgotado(): bool
    {
        return $this->saldoKg() <= 0.0;
    }

    /**
     * El número que va a llevar la próxima hoja del talonario.
     *
     * Sale del máximo y no de un `count()`: las faenas anuladas o vencidas
     * siguen ocupando su número, así que contar filas repetiría uno.
     */
    public function siguienteNumeroFaena(): int
    {
        return (int) $this->faenas()->max('numero_faena') + 1;
    }

    // ------------------------------------------------------------------
    //  Scopes
    // ------------------------------------------------------------------

    /**
     * Los cupos utilizables hoy. Se califica la columna porque `carnets`,
     * `permisos_faena` y esta tabla tienen todas una columna `estado`: un
     * `where('estado', ...)` sin calificar sobre una consulta con join responde
     * «column reference is ambiguous».
     */
    public function scopeVigentes(Builder $query): Builder
    {
        return $query
            ->where($this->qualifyColumn('estado'), EstadoAprovechamiento::Activo)
            ->whereDate($this->qualifyColumn('fecha_vencimiento'), '>=', now()->toDateString());
    }

    /**
     * ========================================================================
     *  LOS CUPOS QUE OCUPAN EL LUGAR DE UNA PERSONA HOY
     * ========================================================================
     *
     * Es `vigentes()` MÁS los pendientes de pago, y la diferencia importa en dos
     * lugares donde usar `vigentes()` estaría mal:
     *
     *   - LA REGLA DE UNA BOLSA POR PERSONA. Un cupo sin cobrar ocupa el lugar
     *     igual: si no contara, alguien podría otorgar cinco cupos seguidos sin
     *     pagar ninguno y quedarse con el más conveniente.
     *
     *   - EL CARNET DE PESCADOR, que imprime el volumen. Lo único que necesita
     *     del cupo es que exista y esté en fecha; el carnet también nace sin
     *     pagar, y los dos se cobran juntos en el mismo recibo. Exigiendo
     *     `vigentes()` no se podría emitir la credencial hasta cobrar el cupo, y
     *     la ventanilla no podría cobrar las dos cosas de una.
     *
     * EN REVISIÓN entra por los dos motivos a la vez: ocupa el lugar igual —si no
     * contara, alguien podría recibir un segundo cupo mientras el primero está
     * presentado— y el carnet tiene que poder emitirse mientras tanto, porque lo
     * único que necesita del cupo es el volumen, que ya está decidido.
     *
     * NO incluye `agotado` —eso no cambió— ni, obviamente, los vencidos.
     */
    public function scopeEnCurso(Builder $query): Builder
    {
        return $query
            ->whereIn($this->qualifyColumn('estado'), [
                EstadoAprovechamiento::Pendiente,
                EstadoAprovechamiento::EnRevision,
                EstadoAprovechamiento::Activo,
            ])
            ->whereDate($this->qualifyColumn('fecha_vencimiento'), '>=', now()->toDateString());
    }

    public function scopeDeBeneficiario(Builder $query, int $beneficiarioId): Builder
    {
        return $query->where($this->qualifyColumn('beneficiario_id'), $beneficiarioId);
    }
}
