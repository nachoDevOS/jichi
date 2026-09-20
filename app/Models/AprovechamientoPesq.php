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

    //  Relaciones

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

    //  Reglas de negocio

    /**
     * Lo que sale este cupo: el valor de la escala con la que se otorgó.
     */
    public function montoACobrar(): float
    {
        return (float) ($this->categoria?->valor_bs ?? 0.0);
    }

    /**
     * Kilos ya comprometidos por las faenas.
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
     *  LOS KILOS QUE SE PASARON DEL CUPO
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
     *  ¿EL TOPE DE LA BOLSA MADRE SE HACE CUMPLIR?
     */
    /**
     * ¿Se pueden corregir sus datos HOY?
     */
    public function puedeEditarse(): bool
    {
        return $this->estado->permiteEdicion() && $this->montoPagado() <= 0.0;
    }

    /**
     * ¿Se puede borrar la fila entera?
     */
    public function puedeEliminarse(): bool
    {
        return $this->estado->permiteEliminacion()
            && $this->montoPagado() <= 0.0
            && $this->faenas()->doesntExist();
    }

    /**
     * ¿Se le pueden cargar depósitos hoy?
     */
    public function admitePagos(): bool
    {
        return $this->estado->permitePagos();
    }

    /**
     *  ¿SE PUEDE MANDAR A QUE ALGUIEN LO FIRME?
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

    /**
     * ¿Pasó alguna vez por la firma? Es lo que habilita la autorización en papel.
     *
     * A vencido y agotado se llega desde ACTIVO, así que los tres tuvieron su
     * firma; pendiente, en revisión y rechazado no. Un cupo vencido se reimprime
     * igual: puede hacer falta reponer el papel de una gestión cerrada.
     */
    public function yaFueAprobado(): bool
    {
        return in_array($this->estado, [
            EstadoAprovechamiento::Activo,
            EstadoAprovechamiento::Vencido,
            EstadoAprovechamiento::Agotado,
        ], true);
    }

    /**
     * El control es parte de la revisión: en pendiente el expediente todavía
     * cambia entero, y aprobado ya no admite reparos.
     */
    public function admiteControlDePagos(): bool
    {
        return $this->estado->permiteRevision();
    }

    /**
     * Corregir vale con el expediente ABIERTO. En revisión hace falta: es la
     * única salida de una observación, y observar solo pasa ahí.
     */
    public function admiteCorreccionDePagos(): bool
    {
        return $this->estado->estaAbierto();
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
     */
    public function estaVigente(): bool
    {
        return $this->estado->habilita() && $this->estaEnFecha();
    }

    /**
     *  ¿ESTÁ DENTRO DE SU PERÍODO? — sin mirar el estado
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

    //  Scopes

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
     *  LOS CUPOS QUE OCUPAN EL LUGAR DE UNA PERSONA HOY
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
