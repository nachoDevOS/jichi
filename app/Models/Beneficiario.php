<?php

namespace App\Models;

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
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

/*
 * OJO: `nombreCompleto` NO va en Appends, aunque sea un accesor como los otros
 * dos. La razón es el camelCase.
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
    'ci_nit',
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

    /**
     * La cédula tal como se imprime en el carnet: «1234567-1A BN».
     */
    protected function documentoIdentidad(): Attribute
    {
        return Attribute::get(fn (): string => trim(implode(' ', array_filter([
            $this->ci_nit.($this->complemento ? '-'.$this->complemento : ''),
            $this->expedido,
        ]))));
    }

    /**
     * Los años cumplidos hoy. NULL si la ficha no tiene fecha de nacimiento.
     *
     * ------------------------------------------------------------------------
     *  NO HAY COLUMNA `edad`, Y NO PUEDE HABERLA
     * ------------------------------------------------------------------------
     *
     * La edad cambia sola: una persona de 39 años pasa a tener 40 el día de su
     * cumpleaños sin que nadie toque el sistema. Guardada en una columna, haría
     * falta un proceso que recorra el padrón todas las noches corrigiéndola, y
     * entre corrida y corrida el dato estaría mal para quien cumplió ese día.
     *
     * Calculada al leer no puede desfasarse nunca, porque no guarda nada: es la
     * respuesta a «cuántos años tiene ESTA persona HOY».
     *
     * ------------------------------------------------------------------------
     *  EL floor() NO SE PUEDE QUITAR
     * ------------------------------------------------------------------------
     *
     * Carbon 3 devuelve un FLOAT en `diffInYears()`: para alguien nacido el 18 de
     * julio de 2006 dice 20.15, no 20. Dejar que PHP lo convierta solo al tipo
     * de retorno funciona —trunca hacia abajo, que es lo que se quiere— pero
     * emite un «Deprecated: implicit conversion from float loses precision» en
     * cada lectura, y en un listado de 30 filas eso son 30 líneas de ruido en el
     * log por cada carga de pantalla.
     *
     * Con floor() explícito queda dicho además lo que se quiere de verdad: años
     * CUMPLIDOS, no redondeados. Es como se lee una edad en cualquier trámite —
     * quien nació en diciembre de 1990 tiene 34 en noviembre de 2025, no 35—, y
     * redondear haría que alguien figure con un año de más durante seis meses.
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
     * convivir en la misma tabla. Ver App\Support\Archivos.
     */
    protected function fotoUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => Archivos::url($this->foto));
    }

    // ------------------------------------------------------------------
    //  Relaciones
    // ------------------------------------------------------------------

    public function carnets(): HasMany
    {
        return $this->hasMany(Carnet::class);
    }

    /**
     * Todos los trámites de la persona, de cualquier gestión.
     *
     * Va por hasManyThrough porque `tramites` no apunta al beneficiario sino a
     * su carnet: la gestión de cada expediente queda dada por construcción en
     * vez de tener que deducirse. Ver la migración de `tramites`.
     */
    public function tramites(): HasManyThrough
    {
        return $this->hasManyThrough(Tramite::class, Carnet::class);
    }

    // ------------------------------------------------------------------
    //  Reglas de negocio
    // ------------------------------------------------------------------

    /**
     * El carnet de la gestión indicada, o NULL si no tiene.
     *
     * ESTE MÉTODO ES LA REGLA A DEL MÓDULO. De lo que devuelva depende todo lo
     * demás: sin carnet el trámite es EMISIÓN INICIAL y hay que crear el
     * documento; con carnet es ADICIÓN DE RUBRO y se reutiliza el que existe.
     *
     * Se busca por `gestion` y no por rango de fechas a propósito: un carnet
     * emitido el 2 de enero para cerrar la gestión anterior existe, y por fecha
     * de emisión caería en el año equivocado.
     *
     * NO filtra por estado: devuelve también el anulado y el vencido. Quien
     * decide qué hacer con eso es SolicitudCarnetService, porque la respuesta
     * cambia según el caso —un carnet anulado no admite adiciones, pero tampoco
     * permite emitir otro en la misma gestión: el índice único no lo dejaría—.
     *
     * ------------------------------------------------------------------------
     *  SI LA RELACIÓN YA ESTÁ CARGADA, NO SE VUELVE A CONSULTAR
     * ------------------------------------------------------------------------
     *
     * `$this->carnets()->where(...)` dispara una consulta SIEMPRE, aunque quien
     * llamó haya hecho `with('carnets')` justamente para evitarlo. Eso convertía
     * el autocompletado —que arma la situación de diez personas de una vez— en
     * un N+1 silencioso: el `with()` estaba escrito, se veía correcto, y aun así
     * salían dieciocho consultas por tecleada.
     *
     * `relationLoaded()` distingue los dos casos. Con la relación cargada se
     * filtra en memoria, sobre las filas que ya están; sin ella se consulta como
     * siempre. Quien llama no tiene que saber cuál de los dos es.
     */
    public function carnetDeGestion(?int $gestion = null): ?Carnet
    {
        $gestion ??= (int) now()->format('Y');

        if ($this->relationLoaded('carnets')) {
            return $this->carnets->firstWhere('gestion', $gestion);
        }

        return $this->carnets()->where('gestion', $gestion)->first();
    }

    public function tieneCarnetEnGestion(?int $gestion = null): bool
    {
        return $this->carnetDeGestion($gestion) !== null;
    }

    /**
     * Lo que debe en total, sumando todos sus trámites no rechazados.
     *
     * ------------------------------------------------------------------------
     *  EL withSum ES LO QUE EVITA UNA CONSULTA POR TRÁMITE
     * ------------------------------------------------------------------------
     *
     * Sin él, `saldoPendiente()` termina llamando a `$this->pagos()->sum(...)` y
     * eso dispara una consulta agregada POR CADA trámite de la persona. Con él,
     * lo cobrado de todos viene en la MISMA consulta, y `Tramite::montoPagado()`
     * lo reusa —por eso ese método pregunta primero por `pagos_sum_monto`—.
     *
     * Es el N+1 silencioso que documenta CLAUDE.md: la relación se ve bien
     * escrita, no falla nada, y las consultas se multiplican sin que nadie lo
     * note.
     *
     * LA RESTA SÍ SE HACE EN PHP, y es correcto: «cuánto falta» no es una resta
     * a secas, se corta en cero porque pagar de más no genera saldo a favor. Esa
     * regla vive en `Tramite::saldoPendiente()` y no se duplica acá.
     */
    public function deudaTotal(): float
    {
        return (float) $this->tramites()
            ->noRechazados()
            ->withSum('pagos', 'monto')
            ->get()
            ->sum(fn (Tramite $t): float => $t->saldoPendiente());
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
            $q->where('ci_nit', $operador, $like)
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
