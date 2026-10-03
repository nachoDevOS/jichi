<?php

namespace App\Models;

use App\Enums\EstadoAprovechamiento;
use App\Enums\EstadoFaena;
use App\Enums\TipoActor;
use App\Services\CorrelativoService;
use App\Traits\Auditable;
use App\Traits\Codificable;
use App\Traits\LiquidableSireb;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * La autorización de UNA salida de pesca.
 */
#[Fillable([
    'carnet_id',
    'numero_faena',
    'monto',
    'sireb_tarifa_id',
    'kilos_extraidos',
    'embarcacion',
    'propietario',
    'comandante_barco',
    'matricula_naval',
    'nro_kardex',
    'region_desde',
    'region_hasta',
    'fecha_solicitud',
    'fecha_salida',
    'fecha_desembarque',
    'estado',
])]
class PermisoFaena extends Model
{
    use Auditable, Codificable, LiquidableSireb, SoftDeletes;

    /** «PermisoFaena» pluraliza a «permiso_faenas», que no es la tabla. */
    protected $table = 'permisos_faena';

    /**
     * Vigencia máxima de una faena, en días.
     */
    public const DIAS_VIGENCIA = 30;

    /** La serie del correlativo global del talonario. Ver CorrelativoService. */
    public const SERIE = 'PERMISO-FAENA';

    /** Ver Asociacion::$attributes: los defaults de la base no llegan al create(). */
    protected $attributes = [
        'kilos_extraidos' => 0,
        'monto' => 0,
        'estado' => EstadoFaena::Pendiente->value,
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'kilos_extraidos' => 'decimal:2',
            'fecha_solicitud' => 'date',
            'fecha_salida' => 'date',
            'fecha_desembarque' => 'date',
            'estado' => EstadoFaena::class,
        ];
    }

    //  Relaciones

    public function carnet(): BelongsTo
    {
        return $this->belongsTo(Carnet::class, 'carnet_id');
    }

    /**
     * El cupo del que descuenta, alcanzado A TRAVÉS del carnet.
     *
     * No es una relación: la faena no guarda `aprovechamiento_id`. Con las dos
     * claves, un permiso podía apuntar a un cupo distinto del que respalda su
     * carnet. Para no disparar dos consultas por fila, quien lo use en un
     * listado carga `carnet.aprovechamiento` en el `with()`.
     */
    public function cupo(): ?AprovechamientoPesq
    {
        return $this->carnet?->aprovechamiento;
    }

    /**
     * El titular, alcanzado por el carnet.
     *
     * Como `beneficiario_id` —la columna que tienen el carnet y el cupo—, así que
     * el accesor deja a la faena hablando el mismo idioma sin duplicar la clave.
     */
    protected function beneficiarioId(): Attribute
    {
        return Attribute::get(fn (): ?int => $this->carnet?->beneficiario_id);
    }

    //  Lectura

    /** Cómo se lee en un listado: «Faena N° 002190», los seis dígitos del papel. */
    protected function etiqueta(): Attribute
    {
        return Attribute::get(fn (): string => 'Faena N° '.$this->numeroLegible);
    }

    /** El correlativo con el relleno del talonario: 000001. */
    protected function numeroLegible(): Attribute
    {
        return Attribute::get(
            fn (): string => CorrelativoService::rellenar($this->numero_faena),
        );
    }

    //  El circuito: pagar en SIREB y aprobar

    public function titularSireb(): Beneficiario
    {
        return $this->carnet->beneficiario;
    }

    public function itemsSireb(): array
    {
        return [['tarifa_id' => $this->sireb_tarifa_id, 'cantidad' => 1]];
    }

    /**
     * Lo que sale ESTE permiso. Es el precio de
     * SIREB congelado al emitir: un cambio de tarifa no mueve un papel entregado.
     */
    public function montoACobrar(): float
    {
        return (float) $this->monto;
    }

    /** ¿Se pueden corregir sus datos? Solo pendiente. */
    public function puedeEditarse(): bool
    {
        return $this->estado->permiteEdicion();
    }

    /** ¿Se puede borrar la fila entera? Mismo corte que la edición. */
    public function puedeEliminarse(): bool
    {
        return $this->estado->permiteEliminacion();
    }

    /** ¿Ya se aprobó? Es lo que habilita a salir a pescar. */
    public function yaFueAprobada(): bool
    {
        return ! $this->estado->estaAbierto();
    }

    /** ¿Se imprime el permiso? Aprobado, no revocado y con la autorización viva. */
    public function puedeImprimirse(): bool
    {
        return $this->yaFueAprobada()
            && $this->estado !== EstadoFaena::Revocado
            && ! $this->autorizacionRevocada();
    }

    /**
     * ¿La autorización de su carnet fue revocada? La faena NO se reescribe:
     * queda `aprobado`, pero sin efecto. Precargar `carnet.aprovechamiento`.
     */
    public function autorizacionRevocada(): bool
    {
        return $this->cupo()?->estado === EstadoAprovechamiento::Revocado;
    }

    /**
     * ¿Hay un carnet de pescador que la ampare? El suyo o el que lo reemplazó:
     * sin ninguno vigente, la faena de un carnet revocado no vale.
     */
    public function tieneCarnetQueLaAmpare(): bool
    {
        return $this->carnet?->amparaSusPapeles() === true;
    }

    /** En fecha y aprobada, pero sin respaldo: autorización revocada o sin carnet vigente. */
    public function sinEfecto(): bool
    {
        return $this->estado === EstadoFaena::Aprobado
            && ($this->autorizacionRevocada() || ($this->estaEnFecha() && ! $this->tieneCarnetQueLaAmpare()));
    }

    /** El texto de «sin efecto» para el QR y las fichas, o null. */
    public function motivoSinEfecto(): ?string
    {
        return match (true) {
            ! $this->sinEfecto() => null,
            $this->autorizacionRevocada() => 'Su Autorización de Pesca para Aprovechamiento Pesquero fue revocada.',
            default => 'El titular no tiene un carnet de pescador vigente que la ampare.',
        };
    }

    /** ¿Está dentro de sus 30 días? Sin mirar el estado ni el carnet. */
    public function estaEnFecha(): bool
    {
        return $this->fecha_desembarque !== null && $this->fecha_desembarque->endOfDay()->isFuture();
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

    /**
     * Por qué esta faena todavía no autoriza a salir. Null cuando sí autoriza.
     *
     * Se resuelve en el SERVIDOR y se manda resuelto: React no vuelve a
     * evaluar el estado, que es como nacen las pantallas que mienten.
     */
    public function motivoSinAutorizar(): ?string
    {
        if ($this->estaVigente()) {
            return null;
        }

        return match (true) {
            $this->autorizacionRevocada() => 'Sin efecto: su Autorización de Pesca para Aprovechamiento '.
                'Pesquero fue revocada, así que ya no autoriza la salida.',
            $this->sinEfecto() => 'Sin efecto: su carnet fue revocado y el titular todavía no tiene otro '.
                'carnet de pescador vigente. Vuelve a valer cuando se apruebe el carnet nuevo.',
            $this->estado === EstadoFaena::Pendiente => 'La faena está PENDIENTE: falta que se pague '.
                'en Recaudaciones.',
            $this->estado === EstadoFaena::Completado => 'La salida ya se cerró: los kilos quedaron '.
                'firmes contra el cupo.',
            $this->estado === EstadoFaena::Vencido => 'Pasó su fecha de desembarque sin cerrarse.',
            // Estado histórico: hasta el 27/09/2026 la revocación lo escribía en cascada.
            $this->estado === EstadoFaena::Revocado => 'Fue revocada junto con su Autorización de Pesca '.
                'para Aprovechamiento Pesquero: ya no autoriza la salida.',
            default => 'Pasó su fecha de desembarque.',
        };
    }

    //  Reglas de negocio

    /** El desembarque que corresponde a una salida: el techo de la resolución. */
    public static function desembarqueDesde(Carbon|string $salida): Carbon
    {
        return Carbon::parse($salida)->addDays(self::DIAS_VIGENCIA)->startOfDay();
    }

    /**
     * ¿Autoriza a estar pescando HOY?
     *
     * Estado Y fecha, por lo mismo de siempre: `vencido` lo escribe un comando
     * diario y entre corrida y corrida la columna miente.
     */
    public function estaVigente(): bool
    {
        return $this->estado->habilita()
            && $this->fecha_desembarque !== null
            && $this->fecha_desembarque->endOfDay()->isFuture()
            && ! $this->autorizacionRevocada()
            // Lo más caro al final: puede consultar si el carnet propio no vale.
            && $this->tieneCarnetQueLaAmpare();
    }

    /** ¿Se pasó de fecha sin cerrarse? Es lo que busca el comando diario. */
    public function estaCaducada(): bool
    {
        return $this->estado === EstadoFaena::Aprobado
            && $this->fecha_desembarque !== null
            && $this->fecha_desembarque->endOfDay()->isPast();
    }

    /** ¿Sus kilos pesan contra el cupo de la bolsa madre? */
    public function consumeCupo(): bool
    {
        return $this->estado->consumeCupo();
    }

    //  Scopes

    /**
     * Las que autorizan hoy. Se califica la columna porque `permisos_faena`,
     * `carnets` y `aprovechamientos_pesq` tienen todas una columna `estado` y
     * los listados las cruzan con join.
     */
    public function scopeVigentes(Builder $query): Builder
    {
        return $query
            ->where($this->qualifyColumn('estado'), EstadoFaena::Aprobado)
            ->whereDate($this->qualifyColumn('fecha_desembarque'), '>=', now()->toDateString())
            ->whereDoesntHave('carnet.aprovechamiento', fn (Builder $a) => $a
                ->where('aprovechamientos_pesq.estado', EstadoAprovechamiento::Revocado))
            ->whereHas('carnet', fn (Builder $c) => Carnet::conCarnetVigenteDelTitular($c, TipoActor::Pescador));
    }
}
