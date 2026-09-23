<?php

namespace App\Models;

use App\Enums\EstadoCarnet;
use App\Enums\TipoActor;
use App\Traits\Auditable;
use App\Traits\Codificable;
use App\Traits\Pagable;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * La credencial física que se entrega en ventanilla.
 */
#[Appends(['codigo_legible', 'registro_legible', 'gestion'])]
#[Fillable([
    'beneficiario_id',
    'asociacion_id',
    'tipo_carnet_id',
    'aprovechamiento_id',
    'archivo_ci',
    'archivo_asociacion',
    'tipo_actor',
    'nro_registro',
    'estado',
    'fecha_solicitud',
    'fecha_emision',
    'fecha_vencimiento',
])]
class Carnet extends Model
{
    use Auditable, Codificable, Pagable, SoftDeletes;

    /**
     * Ver el comentario de Asociacion::$attributes: un default de la base NO
     * llega al objeto que devuelve create(). Pasó tres veces en un día en el
     * modelo anterior, y las tres se descubrieron igual: preguntando por el
     * estado justo después del create().
     */
    protected $attributes = [
        // NACE PENDIENTE: se activa al aprobarlo, con el arancel cobrado.
        'estado' => EstadoCarnet::Pendiente->value,
    ];

    protected function casts(): array
    {
        return [
            'tipo_actor' => TipoActor::class,
            'estado' => EstadoCarnet::class,
            'fecha_solicitud' => 'date',
            'fecha_emision' => 'date',
            'fecha_vencimiento' => 'date',
        ];
    }

    //  Relaciones

    public function beneficiario(): BelongsTo
    {
        return $this->belongsTo(Beneficiario::class);
    }

    public function asociacion(): BelongsTo
    {
        return $this->belongsTo(Asociacion::class);
    }

    public function tipoCarnet(): BelongsTo
    {
        return $this->belongsTo(TipoCarnet::class);
    }

    /** La bolsa madre que respalda el cupo impreso. NULL en un comercializador. */
    public function aprovechamiento(): BelongsTo
    {
        return $this->belongsTo(AprovechamientoPesq::class, 'aprovechamiento_id');
    }

    public function faenas(): HasMany
    {
        return $this->hasMany(PermisoFaena::class, 'carnet_id');
    }

    /** Las guías que amparó. Solo las tiene un carnet de comercializador. */
    public function guias(): HasMany
    {
        return $this->hasMany(GuiaMovimiento::class, 'carnet_id');
    }

    //  Lectura

    /**
     * La gestión del registro: el año de la emisión.
     *
     * DERIVADA y no guardada: una columna que repite un dato que ya está en
     * otra termina contradiciéndolo el día que alguien corrige una fecha.
     * Null mientras el carnet no esté firmado, igual que su número.
     */
    protected function gestion(): Attribute
    {
        return Attribute::get(fn (): ?int => $this->fecha_emision?->year);
    }

    /**
     * El número de registro como va impreso: cinco dígitos, «00001».
     *
     * Vacío mientras el carnet no esté aprobado: el número se asigna al
     * firmarlo, así que antes no hay nada que imprimir.
     */
    protected function registroLegible(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->nro_registro === null
            ? null
            : str_pad((string) $this->nro_registro, 5, '0', STR_PAD_LEFT));
    }

    //  Reglas de negocio

    /**
     * Lo que sale esta credencial: el precio de su tipo.
     */
    public function montoACobrar(): float
    {
        return (float) ($this->tipoCarnet?->precio_bs ?? 0.0);
    }

    /**
     *  EL CIRCUITO DE REVISIÓN, resuelto en el modelo
     *
     * Los cinco de abajo son los que el controlador manda a la pantalla en los
     * campos `puede_*`. Ninguno se vuelve a evaluar en React.
     */

    /**
     * ¿Se pueden corregir sus datos HOY?
     *
     * El estado Y que no haya entrado un peso: con un depósito cargado ya hay
     * un cobro contra ESTE carnet, y cambiarle el tipo le cambiaría el arancel
     * por debajo a algo que alguien ya pagó.
     */
    public function puedeEditarse(): bool
    {
        return $this->estado->permiteEdicion() && $this->montoPagado() <= 0.0;
    }

    /**
     * ¿Se puede borrar la fila entera?
     *
     * Las tres cosas. Los motivos son distintos: lo cobrado se resuelve por
     * caja, pero un permiso ya emitido no se resuelve de ninguna manera —el
     * papel está afuera, en manos de la persona—.
     */
    public function puedeEliminarse(): bool
    {
        return $this->estado->permiteEliminacion()
            && $this->montoPagado() <= 0.0
            && $this->sinPermisosEmitidos();
    }

    /**
     * ¿No emitió ninguna faena ni guía?
     *
     * Reusa los `withCount` si vinieron en la consulta. Sin esto, un listado
     * de treinta carnets hacía SESENTA consultas para dibujar el botón de
     * eliminar: dos por fila, y ninguna visible.
     */
    private function sinPermisosEmitidos(): bool
    {
        $atributos = $this->getAttributes();

        if (array_key_exists('faenas_count', $atributos) && array_key_exists('guias_count', $atributos)) {
            return (int) $atributos['faenas_count'] === 0 && (int) $atributos['guias_count'] === 0;
        }

        return $this->faenas()->doesntExist() && $this->guias()->doesntExist();
    }

    /** ¿Se le pueden cargar depósitos hoy? Exigido por el trait Pagable. */
    public function admitePagos(): bool
    {
        return $this->estado->permitePagos();
    }

    /** ¿Se puede presentar a revisión? Pendiente Y con el arancel cubierto. */
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
     * ¿Pasó alguna vez por la firma?
     *
     * A revocado y vencido se llega desde ACTIVO, así que los tres tuvieron su
     * firma; pendiente y en revisión no. Es lo que habilita la impresión: el
     * plástico no sale de un carnet que nadie aprobó.
     */
    public function yaFueAprobado(): bool
    {
        return in_array($this->estado, [
            EstadoCarnet::Activo,
            EstadoCarnet::Revocado,
            EstadoCarnet::Vencido,
        ], true);
    }

    /**
     * El control de las boletas es parte de la REVISIÓN: en pendiente el
     * expediente todavía se arma, y aprobado ya no admite reparos.
     */
    public function admiteControlDePagos(): bool
    {
        return $this->estado->permiteRevision();
    }

    /** Corregir un depósito vale con el expediente ABIERTO. */
    public function admiteCorreccionDePagos(): bool
    {
        return $this->estado->estaAbierto();
    }

    /**
     * ¿Vale HOY?
     */
    public function estaVigente(): bool
    {
        return $this->estado->habilita()
            && $this->fecha_vencimiento !== null
            && $this->fecha_vencimiento->endOfDay()->isFuture();
    }

    /**
     * POR QUÉ no puede emitir todavía, o null si sí puede.
     *
     * `puedeEmitirFaenas()` y `puedeEmitirGuias()` contestan sí o no, y la
     * pantalla inventaba el motivo con un solo mensaje para todos los noes:
     * sobre un carnet PENDIENTE decía «pasó su fecha de vencimiento», que es
     * falso y manda a buscar un problema que no existe.
     */
    public function motivoSinPermisos(): ?string
    {
        $emite = $this->tipo_actor->emiteFaenas()
            ? $this->puedeEmitirFaenas()
            : $this->puedeEmitirGuias();

        if ($emite) {
            return null;
        }

        // El orden importa: el primero que aparece es el que hay que resolver
        // antes que los demás.
        $porElCarnet = match (true) {
            $this->estado === EstadoCarnet::Pendiente => 'El carnet está PENDIENTE: falta cubrir el '
                .'arancel y enviarlo a revisión.',
            $this->estado === EstadoCarnet::EnRevision => 'El carnet está presentado y esperando '
                .'la firma de quien lo aprueba.',
            $this->estado === EstadoCarnet::Revocado => 'El carnet está revocado.',
            ! $this->estaVigente() => 'El carnet venció: hay que emitir el de la gestión en curso.',
            default => null,
        };

        if ($porElCarnet !== null) {
            return $porElCarnet;
        }

        // El carnet vale; entonces lo que falta es del lado del cupo.
        return match (true) {
            $this->aprovechamiento === null => 'No tiene una Autorización de Pesca asociada.',
            ! $this->aprovechamiento->estado->habilita() => 'La Autorización de Pesca está '
                .mb_strtolower($this->aprovechamiento->estado->etiqueta()).': todavía no autoriza faenas.',
            ! $this->aprovechamiento->estaEnFecha() => 'La Autorización de Pesca venció.',
            $this->aprovechamiento->saldoKg() <= 0.0 => 'La Autorización de Pesca se quedó sin '
                .'kilos. Hay que tramitar otra.',
            default => 'No autoriza a emitir.',
        };
    }

    /**
     * ¿Puede emitir permisos de faena?
     */
    public function puedeEmitirFaenas(): bool
    {
        return $this->tipo_actor->emiteFaenas()
            && $this->estaVigente()
            && $this->aprovechamiento?->puedeEmitirFaena() === true;
    }

    /** ¿Puede emitir guías de movimiento? */
    public function puedeEmitirGuias(): bool
    {
        return $this->tipo_actor->emiteGuias() && $this->estaVigente();
    }

    /**
     * El cupo que se imprime en el plástico, o null si no corresponde.
     */
    public function cupoImpreso(): ?float
    {
        if (! $this->tipo_actor->requiereAprovechamiento()) {
            return null;
        }

        return $this->aprovechamiento === null
            ? null
            : (float) $this->aprovechamiento->volumen_total_kg;
    }

    /** Días que le quedan. Negativo si ya venció; null si no tiene fecha. */
    public function diasParaVencer(): ?int
    {
        return $this->fecha_vencimiento === null
            ? null
            : (int) floor(now()->startOfDay()->diffInDays($this->fecha_vencimiento->startOfDay(), false));
    }

    //  Scopes

    /**
     * Los que valen hoy. La columna se califica porque `carnets`,
     * `asociaciones`, `tipos_carnet` y `aprovechamientos_pesq` tienen todas una
     * columna `estado`, y los listados las cruzan con join: sin calificar,
     * PostgreSQL responde «column reference is ambiguous».
     */
    public function scopeVigentes(Builder $query): Builder
    {
        return $query
            ->where($this->qualifyColumn('estado'), EstadoCarnet::Activo)
            ->whereDate($this->qualifyColumn('fecha_vencimiento'), '>=', now()->toDateString());
    }

    public function scopeDeTipo(Builder $query, TipoActor $tipo): Builder
    {
        return $query->where($this->qualifyColumn('tipo_actor'), $tipo);
    }
}
