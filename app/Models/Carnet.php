<?php

namespace App\Models;

use App\Enums\EstadoAprovechamiento;
use App\Enums\EstadoCarnet;
use App\Enums\TipoActor;
use App\Services\CorrelativoService;
use App\Traits\Auditable;
use App\Traits\Codificable;
use App\Traits\LiquidableSireb;
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
    'monto',
    'sireb_tarifa_id',
    'nro',
    'estado',
    'fecha_solicitud',
    'fecha_emision',
    'fecha_vencimiento',
])]
class Carnet extends Model
{
    use Auditable, Codificable, LiquidableSireb, SoftDeletes;

    /**
     * Ver el comentario de Asociacion::$attributes: un default de la base NO
     * llega al objeto que devuelve create(). Pasó tres veces en un día en el
     * modelo anterior, y las tres se descubrieron igual: preguntando por el
     * estado justo después del create().
     */
    protected $attributes = [
        // Nace pendiente: se activa al aprobarlo, con el arancel cobrado.
        'estado' => EstadoCarnet::Pendiente->value,
        'monto' => 0,
    ];

    protected function casts(): array
    {
        return [
            'tipo_actor' => TipoActor::class,
            'monto' => 'decimal:2',
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
     * El número de registro como va impreso: seis dígitos, «000001».
     *
     * Vacío mientras el carnet no esté aprobado: el número se asigna al
     * firmarlo, así que antes no hay nada que imprimir.
     */
    protected function registroLegible(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->nro === null
            ? null
            : CorrelativoService::rellenar($this->nro));
    }

    //  Reglas de negocio

    /**
     * Lo que sale esta credencial: el precio de SIREB congelado al emitirla.
     */
    public function montoACobrar(): float
    {
        return (float) $this->monto;
    }

    public function titularSireb(): Beneficiario
    {
        return $this->beneficiario;
    }

    public function itemsSireb(): array
    {
        return [['tarifa_id' => $this->sireb_tarifa_id, 'cantidad' => 1]];
    }

    /** ¿Se pueden corregir sus datos HOY? Corregir anula la liquidación en SIREB y registra otra. */
    public function puedeEditarse(): bool
    {
        return $this->estado->permiteEdicion();
    }

    /**
     * ¿Se puede borrar la fila entera?
     *
     * Un permiso ya emitido no se resuelve de ninguna manera: el papel está
     * afuera, en manos de la persona.
     */
    public function puedeEliminarse(): bool
    {
        return $this->estado->permiteEliminacion() && $this->sinPermisosEmitidos();
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

    /**
     * ¿Pasó alguna vez por la firma?
     *
     * A revocado se llega desde APROBADO, así que los dos se pagaron;
     * pendiente y no pagado no. Es lo que habilita la impresión: el
     * plástico no sale de un carnet que nadie aprobó.
     */
    public function yaFueAprobado(): bool
    {
        return in_array($this->estado, [
            EstadoCarnet::Aprobado,
            EstadoCarnet::Revocado,
        ], true);
    }

    /**
     * ¿Vale HOY?
     */
    public function estaVigente(): bool
    {
        return $this->estado->habilita()
            && $this->fecha_vencimiento !== null
            && $this->fecha_vencimiento->endOfDay()->isFuture()
            && ! $this->autorizacionRevocada();
    }

    /**
     * ¿Su autorización fue revocada? Revocarla NO reescribe el carnet: queda
     * `aprobado`, pero sin efecto. La vigencia se calcula mirando al padre.
     */
    public function autorizacionRevocada(): bool
    {
        return $this->aprovechamiento_id !== null
            && $this->aprovechamiento?->estado === EstadoAprovechamiento::Revocado;
    }

    /**
     * ¿Este carnet —o el que lo reemplazó— ampara HOY los papeles que emitió?
     * La faena o la guía de un carnet perdido y revocado sigue valiendo solo si
     * el titular ya tiene otro carnet aprobado y vigente de la misma actividad.
     */
    public function amparaSusPapeles(): bool
    {
        return $this->estaVigente()
            || self::query()
                ->vigentes()
                ->deTipo($this->tipo_actor)
                ->where('beneficiario_id', $this->beneficiario_id)
                ->exists();
    }

    /**
     * La misma pregunta en SQL, para los `scopeVigentes()` de faenas y guías:
     * el carnet del papel pertenece a alguien con un carnet vigente de esa actividad.
     */
    public static function conCarnetVigenteDelTitular(Builder $carnets, TipoActor $actor): Builder
    {
        return $carnets->whereIn(
            'carnets.beneficiario_id',
            self::query()->vigentes()->deTipo($actor)->select('carnets.beneficiario_id'),
        );
    }

    /** El texto de «sin efecto» para el QR y las fichas, o null. */
    public function motivoSinEfecto(): ?string
    {
        return $this->sinEfecto()
            ? 'Su Autorización de Pesca para Aprovechamiento Pesquero fue revocada.'
            : null;
    }

    /** Aprobado pero con la autorización revocada: se muestra así en todo el panel. */
    public function sinEfecto(): bool
    {
        return $this->estado === EstadoCarnet::Aprobado && $this->autorizacionRevocada();
    }

    /** La etiqueta de estado que ven las pantallas: «Sin efecto» manda sobre «Aprobado». */
    public function etiquetaEstado(): string
    {
        return $this->sinEfecto() ? 'Sin efecto' : $this->estado->etiqueta();
    }

    public function colorEstado(): string
    {
        return $this->sinEfecto() ? 'rose' : $this->estado->color();
    }

    /** ¿Se imprime el plástico? Firmado, no revocado y con la autorización viva. */
    public function puedeImprimirse(): bool
    {
        return $this->yaFueAprobado()
            && $this->estado !== EstadoCarnet::Revocado
            && ! $this->autorizacionRevocada();
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
            $this->estado === EstadoCarnet::Pendiente => 'El carnet está PENDIENTE: falta que se pague '
                .'en Recaudaciones.',
            $this->estado === EstadoCarnet::Revocado => 'El carnet está revocado.',
            $this->autorizacionRevocada() => 'Sin efecto: su Autorización de Pesca para Aprovechamiento '
                .'Pesquero fue revocada. Hace falta una autorización nueva y un carnet nuevo.',
            ! $this->estaVigente() => 'El carnet venció: hay que emitir el de la gestión en curso.',
            default => null,
        };

        if ($porElCarnet !== null) {
            return $porElCarnet;
        }

        // El carnet vale; entonces lo que falta es del lado del cupo.
        return match (true) {
            $this->aprovechamiento === null => 'No tiene una Autorización de Pesca para Aprovechamiento Pesquero asociada.',
            $this->aprovechamiento->estado === EstadoAprovechamiento::Revocado => 'La Autorización de Pesca para Aprovechamiento Pesquero '
                .'fue revocada: no autoriza faenas.',
            // Antes que `habilita()`: agotada no es «todavía no», es «ya no».
            $this->aprovechamiento->estado === EstadoAprovechamiento::Agotado => 'La Autorización de Pesca para Aprovechamiento '
                .'Pesquero se quedó sin kilos. Hay que tramitar otra.',
            ! $this->aprovechamiento->estado->habilita() => 'La Autorización de Pesca para Aprovechamiento Pesquero está '
                .mb_strtolower($this->aprovechamiento->estado->etiqueta()).': todavía no autoriza faenas.',
            ! $this->aprovechamiento->estaEnFecha() => 'La Autorización de Pesca para Aprovechamiento Pesquero venció.',
            $this->aprovechamiento->saldoKg() <= 0.0 => 'La Autorización de Pesca para Aprovechamiento Pesquero se quedó sin '
                .'kilos. Hay que tramitar otra.',
            $this->aprovechamiento->libreKg() <= 0.0 => $this->aprovechamiento->motivoReservado(),
            default => 'No autoriza a emitir.',
        };
    }

    /**
     * El carnet como lo muestran el buscador y los formularios de faena y guía.
     * Un solo lugar: con tres copias, una se queda vieja. Precargar `codigo`,
     * `tipoCarnet` y `aprovechamiento.categoria` con el `withSum` del saldo.
     *
     * @return array<string, mixed>
     */
    public function resumenParaEmitir(): array
    {
        $cupo = $this->aprovechamiento;

        return [
            'id' => $this->id,
            'codigo' => $this->codigo_legible,
            'registro' => $this->registro_legible,
            'tipo' => $this->tipoCarnet?->nombre,
            'tipo_actor' => $this->tipo_actor->value,
            'tipo_actor_etiqueta' => $this->tipo_actor->etiqueta(),
            'puede_emitir_faenas' => $this->puedeEmitirFaenas(),
            'puede_emitir_guias' => $this->puedeEmitirGuias(),
            'fecha_vencimiento' => $this->fecha_vencimiento?->toDateString(),

            // El cupo que respalda al pescador. Null en el comercializador.
            'capacidad' => $cupo?->categoria?->descripcion_kg,
            'volumen_total_kg' => $cupo !== null ? (float) $cupo->volumen_total_kg : null,
            'saldo_kg' => $cupo?->saldoKg(),
            // Lo que una faena nueva puede pedir: el saldo menos lo reservado.
            'reservado_kg' => $cupo?->kilosReservados(),
            'libre_kg' => $cupo?->libreKg(),
        ];
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
            ->where($this->qualifyColumn('estado'), EstadoCarnet::Aprobado)
            ->whereDate($this->qualifyColumn('fecha_vencimiento'), '>=', now()->toDateString())
            // Sin efecto no ocupa el lugar: si no, la persona no podría sacar el carnet nuevo.
            ->whereDoesntHave('aprovechamiento', fn (Builder $a) => $a
                ->where('aprovechamientos_pesq.estado', EstadoAprovechamiento::Revocado));
    }

    public function scopeDeTipo(Builder $query, TipoActor $tipo): Builder
    {
        return $query->where($this->qualifyColumn('tipo_actor'), $tipo);
    }
}
