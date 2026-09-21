<?php

namespace App\Models;

use App\Enums\EstadoAprovechamiento;
use App\Enums\EstadoCarnet;
use App\Enums\TipoActor;
use App\Support\Archivos;
use App\Support\Sql;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

/*
 * LA PERSONA, UNA SOLA VEZ. No hay columna de rol: quien pesca y además
 * comercializa es UNA ficha con DOS carnets. El rol es del documento.
 */
#[Appends(['documento_identidad', 'foto_url', 'edad'])]
#[Fillable([
    'ci',
    'complemento',
    'departamento_id',
    'primerNombre',
    'segundoNombre',
    'apellidoPaterno',
    'apellidoMaterno',
    'apellidoCasado',
    'fechaNacimiento',
    'genero',
    'nacionalidad',
    'direccion',
    'ciudad',
    'provincia',
    'telefono',
    'email',
])]
class Beneficiario extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    /**
     * `foto` NO está en Fillable a propósito: la ruta la escribe
     * StorageController después de subir el archivo, y dejarla asignable en
     * masa permitiría que un formulario mandara una ruta arbitraria.
     */
    protected $attributes = [
        'nacionalidad' => 'Boliviana',
    ];

    protected function casts(): array
    {
        return [
            'fechaNacimiento' => 'date',
        ];
    }

    /**
     * El nombre armado, en SQL.
     */
    private const SQL_NOMBRE = <<<'SQL'
        COALESCE("primerNombre", '') || ' ' ||
        COALESCE("segundoNombre", '') || ' ' ||
        COALESCE("apellidoPaterno", '') || ' ' ||
        COALESCE("apellidoMaterno", '') || ' ' ||
        COALESCE("apellidoCasado", '')
        SQL;

    /**
     * Junta las cinco partes del nombre en el orden en que se lee una cédula.
     */
    protected function nombreCompleto(): Attribute
    {
        return Attribute::get(function (): string {
            $partes = [
                $this->primerNombre,
                $this->segundoNombre,
                $this->apellidoPaterno,
                $this->apellidoMaterno,
            ];

            if (filled($this->apellidoCasado)) {
                $partes[] = 'de '.$this->apellidoCasado;
            }

            // array_filter descarta las partes vacías —segundo nombre y apellido
            // materno son opcionales— y así no quedan dobles espacios en medio.
            return trim(implode(' ', array_filter($partes, fn ($p): bool => filled($p))));
        });
    }

    /** La cédula tal como se imprime en el carnet: «1234567-1A BN». */
    protected function documentoIdentidad(): Attribute
    {
        return Attribute::get(fn (): string => trim(implode(' ', array_filter([
            $this->ci.($this->complemento ? '-'.$this->complemento : ''),
            // El código sale del mapa memorizado y NO de la relación: se usa
            // en cada fila de cada listado. Ver Departamento::codigoDe().
            Departamento::codigoDe($this->departamento_id),
        ]))));
    }

    /** El código del departamento, suelto: «BN». Null si no se cargó. */
    protected function expedido(): Attribute
    {
        return Attribute::get(fn (): ?string => Departamento::codigoDe($this->departamento_id));
    }

    /**
     * Los años cumplidos hoy. NULL si la ficha no tiene fecha de nacimiento.
     */
    protected function edad(): Attribute
    {
        return Attribute::get(
            fn (): ?int => $this->fechaNacimiento === null
                ? null
                : (int) floor($this->fechaNacimiento->diffInYears(now())),
        );
    }

    /**
     * La dirección para mostrar la foto. NULL si la ficha no tiene.
     */
    protected function fotoUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => Archivos::url($this->foto));
    }

    //  Relaciones

    /**
     * Dónde se expidió su cédula.
     *
     * Se usa para mostrar el NOMBRE completo —«Beni»—; el código corto lo
     * resuelve el accesor sin tocar esta relación, así que los listados no
     * necesitan cargarla.
     */
    public function departamento(): BelongsTo
    {
        return $this->belongsTo(Departamento::class);
    }

    /** Sus credenciales: puede tener una de pescador y otra de comercializador. */
    public function carnets(): HasMany
    {
        return $this->hasMany(Carnet::class);
    }

    /** Sus bolsas madre, de todas las gestiones. */
    public function aprovechamientos(): HasMany
    {
        return $this->hasMany(AprovechamientoPesq::class);
    }

    /**
     * Las guías que emitió COMO COMERCIALIZADOR.
     */
    public function guias(): HasManyThrough
    {
        // A TRAVÉS de sus carnets: la guía cuelga del carnet, no de la persona.
        return $this->hasManyThrough(
            GuiaMovimiento::class,
            Carnet::class,
            'beneficiario_id',  // FK en carnets
            'carnet_id',        // FK en guias_movimiento
            'id',
            'id',
        );
    }

    /**
     * Los permisos de faena que pidió COMO PESCADOR.
     */
    public function faenas(): HasManyThrough
    {
        // A TRAVÉS de sus carnets, como las guías: la faena cuelga del carnet.
        return $this->hasManyThrough(
            PermisoFaena::class,
            Carnet::class,
            'beneficiario_id',  // FK en carnets
            'carnet_id',        // FK en permisos_faena
            'id',
            'id',
        );
    }

    //  Reglas de negocio

    /**
     *  LA CREDENCIAL VIGENTE DE ESTA PERSONA PARA ESTA ACTIVIDAD, O NULL
     */
    public function carnetVigenteDe(TipoActor $tipo): ?Carnet
    {
        if ($this->relationLoaded('carnets')) {
            return $this->carnets->first(
                fn (Carnet $c): bool => $c->tipo_actor === $tipo && $c->estaVigente(),
            );
        }

        return $this->carnets()
            ->where('tipo_actor', $tipo)
            ->where('estado', EstadoCarnet::Activo)
            ->whereDate('fecha_vencimiento', '>=', now()->toDateString())
            ->latest('fecha_emision')
            ->first();
    }

    /** ¿Tiene credencial vigente de esta actividad? */
    public function tieneCarnetVigenteDe(TipoActor $tipo): bool
    {
        return $this->carnetVigenteDe($tipo) !== null;
    }

    /**
     * Su bolsa madre utilizable HOY, o null.
     */
    public function aprovechamientoVigente(): ?AprovechamientoPesq
    {
        if ($this->relationLoaded('aprovechamientos')) {
            return $this->aprovechamientos->first(
                fn (AprovechamientoPesq $a): bool => $a->puedeEmitirFaena(),
            );
        }

        return $this->aprovechamientos()
            ->where('estado', EstadoAprovechamiento::Aprobado)
            ->whereDate('fecha_vencimiento', '>=', now()->toDateString())
            ->latest('fecha_emision')
            ->get()
            ->first(fn (AprovechamientoPesq $a): bool => $a->puedeEmitirFaena());
    }

    /**
     * Lo que debe en total, sumando lo pendiente de sus tres tipos de trámite.
     */
    public function deudaTotal(): float
    {
        $pendiente = fn ($coleccion): float => $coleccion->sum(
            fn ($tramite): float => $tramite->saldoPendiente(),
        );

        return round(
            $pendiente($this->carnets()->with('tipoCarnet')->withSum('pagos', 'monto_parcial')->get())
            + $pendiente($this->aprovechamientos()->with('categoria')->withSum('pagos', 'monto_parcial')->get())
            + $pendiente($this->guias()->withSum('pagos', 'monto_parcial')->get()),
            2,
        );
    }

    //  Scopes

    /**
     * Búsqueda de ventanilla: CI, nombre, email o teléfono.
     */
    public function scopeBuscar(Builder $query, ?string $termino): Builder
    {
        if (blank($termino)) {
            return $query;
        }

        // Se escapan % y _ porque en LIKE son comodines: una búsqueda de
        // «100%» traería todo el padrón si no se neutralizan.
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], trim($termino)).'%';
        $operador = Sql::like($query->getConnection());

        return $query->where(function (Builder $q) use ($like, $operador) {
            $q->where('ci', $operador, $like)
                ->orWhereRaw('('.self::SQL_NOMBRE.') '.$operador.' ?', [$like])
                ->orWhere('email', $operador, $like)
                ->orWhere('telefono', $operador, $like);
        });
    }

    /**
     * Orden alfabético del padrón: por apellido, como cualquier lista oficial.
     *
     * Se ordena por columnas reales y no por el nombre concatenado justamente
     * para que el índice `beneficiarios_nombre_index` pueda usarse.
     */
    public function scopeOrdenAlfabetico(Builder $query): Builder
    {
        return $query
            ->orderBy('apellidoPaterno')
            ->orderBy('apellidoMaterno')
            ->orderBy('primerNombre');
    }
}
