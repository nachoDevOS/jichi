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
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/*
 * LA PERSONA, UNA SOLA VEZ. No hay columna de rol: quien pesca y además
 * comercializa es UNA ficha con DOS carnets. El rol es del documento.
 *
 * OJO: `nombreCompleto` NO va en Appends, aunque sea un accesor como los otros
 * tres. La razón es el camelCase.
 *
 * Al serializar el modelo a array, Laravel busca el accesor por el nombre del
 * método pasado a snake_case: para `nombreCompleto()` busca la clave
 * `nombre_completo`. Si se lo agrega acá como `nombreCompleto`, no lo reconoce,
 * cae al accesor de estilo viejo y revienta con «Call to undefined method
 * getNombreCompletoAttribute()».
 *
 * No hace falta: `$beneficiario->nombreCompleto` funciona igual, y los
 * controladores ya lo mandan a React con su nombre explícito.
 */
#[Appends(['documento_identidad', 'foto_url', 'edad'])]
#[Fillable([
    'ci',
    'complemento',
    'expedido',
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
     *
     * No existe columna `nombreCompleto`: el nombre se concatena cuando hace
     * falta. Para BUSCAR y para ORDENAR eso tiene que ocurrir dentro de la
     * consulta, porque la base no puede filtrar por algo que solo se calcula en
     * PHP después de traer las filas.
     *
     * Detalles de la expresión:
     *
     *   - COALESCE convierte los NULL en cadena vacía. Sin eso, concatenar un
     *     NULL en SQL da NULL: a quien no tiene segundo nombre se le borraría
     *     el nombre entero y no aparecería en ninguna búsqueda.
     *
     *   - Las comillas dobles alrededor de cada columna son obligatorias por el
     *     camelCase: sin ellas PostgreSQL las pasa a minúscula y responde
     *     «column "primernombre" does not exist». SQLite también las acepta, así
     *     que la misma expresión sirve en los dos motores.
     *
     *   - `||` es el concatenador estándar de SQL y funciona igual en los dos.
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
     *
     * Es un accesor, no una columna: `$beneficiario->nombreCompleto` lo calcula
     * en el momento. Así no puede quedar desfasado de sus partes —corregir un
     * apellido cambia el nombre impreso en el acto—.
     *
     * El apellido de casada se guarda sin el «de» y se le agrega acá. Guardarlo
     * con el «de» adentro rompería la búsqueda: quien escriba «Justiniano» en
     * ventanilla no encontraría a la persona registrada como «de Justiniano».
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
            $this->expedido,
        ]))));
    }

    /**
     * Los años cumplidos hoy. NULL si la ficha no tiene fecha de nacimiento.
     *
     * NO HAY COLUMNA `edad`, Y NO PUEDE HABERLA: la edad cambia sola. Guardada,
     * haría falta un proceso que recorra el padrón todas las noches, y entre
     * corrida y corrida el dato estaría mal para quien cumplió ese día.
     *
     * EL floor() NO SE PUEDE QUITAR. Carbon 3 devuelve un FLOAT en
     * `diffInYears()`: para alguien nacido el 18 de julio de 2006 dice 20.15.
     * Dejar que PHP lo convierta solo funciona —trunca hacia abajo— pero emite
     * un «Deprecated: implicit conversion from float loses precision» en cada
     * lectura, y en un listado de 30 filas eso son 30 líneas de ruido por carga.
     *
     * Con floor() explícito queda dicho además lo que se quiere: años CUMPLIDOS,
     * no redondeados. Redondear haría figurar a alguien con un año de más
     * durante seis meses.
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
     *
     * Pasa por Archivos::url porque la columna guarda una ruta cuando el disco
     * es local y una dirección completa cuando es s3, y las dos formas pueden
     * convivir en la misma tabla.
     */
    protected function fotoUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => Archivos::url($this->foto));
    }

    // ------------------------------------------------------------------
    //  Relaciones
    // ------------------------------------------------------------------

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
     *
     * La clave foránea va explícita porque no sigue la convención: la columna
     * se llama `beneficiario_com_id` justamente para que nadie la confunda con
     * el pescador que extrajo la carga, que es otra persona.
     */
    public function guias(): HasMany
    {
        return $this->hasMany(GuiaMovimiento::class, 'beneficiario_com_id');
    }

    // ------------------------------------------------------------------
    //  Reglas de negocio
    // ------------------------------------------------------------------

    /**
     * ========================================================================
     *  LA CREDENCIAL VIGENTE DE ESTA PERSONA PARA ESTA ACTIVIDAD, O NULL
     * ========================================================================
     *
     * De lo que devuelva depende el resto: sin carnet hay que emitir uno; con
     * carnet se reutiliza el que existe y lo que corresponde es renovarlo.
     *
     * RECIBE EL TIPO DE ACTOR Y ES OBLIGATORIO. Una persona puede tener dos
     * credenciales al mismo tiempo, así que la pregunta sin el tipo no tiene
     * una única respuesta: un método que devolviera «la primera» reutilizaría
     * el carnet de Pescador para un trámite de Comercializador.
     *
     * Filtra por vigencia REAL —estado más fecha— y no solo por el estado,
     * porque `estado` puede estar desfasado: `vencido` lo escribe un comando
     * diario. Ver Carnet::estaVigente().
     *
     * SI LA RELACIÓN YA ESTÁ CARGADA, NO SE VUELVE A CONSULTAR.
     * `$this->carnets()->where(...)` dispara una consulta SIEMPRE, aunque quien
     * llamó haya hecho `with('carnets')` justamente para evitarlo: el `with()`
     * queda escrito, se ve correcto, y el N+1 sigue ahí en silencio.
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
     *
     * Es lo que el formulario de faenas necesita: sin ella no hay de dónde
     * descontar kilos y no se puede emitir el permiso.
     *
     * Mismo cuidado con `relationLoaded()` que arriba, y por el mismo motivo.
     */
    public function aprovechamientoVigente(): ?AprovechamientoPesq
    {
        if ($this->relationLoaded('aprovechamientos')) {
            return $this->aprovechamientos->first(
                fn (AprovechamientoPesq $a): bool => $a->puedeEmitirFaena(),
            );
        }

        return $this->aprovechamientos()
            ->where('estado', EstadoAprovechamiento::Activo)
            ->whereDate('fecha_vencimiento', '>=', now()->toDateString())
            ->latest('fecha_emision')
            ->get()
            ->first(fn (AprovechamientoPesq $a): bool => $a->puedeEmitirFaena());
    }

    /**
     * Lo que debe en total, sumando lo pendiente de sus tres tipos de trámite.
     *
     * EL withSum ES LO QUE EVITA UNA CONSULTA POR TRÁMITE. Sin él, cada
     * `saldoPendiente()` termina llamando a `$this->pagos()->sum(...)` y eso
     * dispara una consulta agregada POR CADA carnet, cupo y guía de la persona.
     * Con él, lo cobrado de todos viene en la MISMA consulta y el trait Pagable
     * lo reusa —por eso `montoPagado()` pregunta primero por
     * `pagos_sum_monto_parcial`—.
     *
     * LA RESTA SÍ SE HACE EN PHP, y es correcto: «cuánto falta» no es una resta
     * a secas, se corta en cero porque pagar de más no genera saldo a favor.
     * Esa regla vive en el trait y no se duplica acá.
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

    // ------------------------------------------------------------------
    //  Scopes
    // ------------------------------------------------------------------

    /**
     * Búsqueda de ventanilla: CI, nombre, email o teléfono.
     *
     * El nombre se compara contra las cinco partes CONCATENADAS y no contra
     * cada una por separado, porque el operador escribe «rosa antezana»: un
     * nombre y un apellido pegados, que no coinciden con ninguna columna suelta.
     *
     * OJO CON EL COSTO: al no haber columna `nombreCompleto` guardada, ningún
     * índice puede ayudar a esta comparación y la base recorre la tabla entera
     * en cada búsqueda. Con el padrón actual es imperceptible; si algún día se
     * vuelve lento, la salida es un índice funcional sobre esta misma expresión,
     * no volver a guardar el nombre.
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
