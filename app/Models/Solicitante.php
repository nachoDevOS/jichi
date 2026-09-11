<?php

namespace App\Models;

use App\Enums\CategoriaDocumento;
use App\Enums\EstadoDocumento;
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
 * No hace falta: `$solicitante->nombreCompleto` funciona igual, y los
 * controladores ya lo mandan a React con su nombre explícito.
 */
#[Appends(['documento_identidad', 'foto_url'])]
#[Fillable([
    'ci_nit',
    'complemento',
    'expedido',
    'primerNombre',
    'segundoNombre',
    'apellidoPaterno',
    'apellidoMaterno',
    'apellidoCasada',
    'fechaNacimiento',
    'genero',
    'nacionalidad',
    'direccion',
    'ciudad',
    'provincia',
    'telefono',
    'email',
])]
class Solicitante extends Model
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
     * consulta, porque la base no puede filtrar por algo que solo se calcula
     * en PHP después de traer las filas.
     *
     * Detalles de la expresión:
     *
     *   - COALESCE convierte los NULL en cadena vacía. Sin eso, concatenar un
     *     NULL en SQL da NULL: a quien no tiene segundo nombre se le borraría
     *     el nombre entero y no aparecería en ninguna búsqueda.
     *
     *   - Las comillas dobles alrededor de cada columna son obligatorias por
     *     el camelCase: sin ellas PostgreSQL las pasa a minúscula y no las
     *     encuentra. SQLite también las acepta, así que la misma expresión
     *     sirve en los dos motores.
     *
     *   - `||` es el concatenador estándar de SQL, y funciona igual en
     *     PostgreSQL y en SQLite.
     */
    private const SQL_NOMBRE = <<<'SQL'
        COALESCE("primerNombre", '') || ' ' ||
        COALESCE("segundoNombre", '') || ' ' ||
        COALESCE("apellidoPaterno", '') || ' ' ||
        COALESCE("apellidoMaterno", '') || ' ' ||
        COALESCE("apellidoCasada", '')
        SQL;

    /**
     * Junta las cinco partes del nombre en el orden en que se lee una cédula.
     *
     * Es un accesor, no una columna: `$solicitante->nombreCompleto` lo calcula
     * en el momento. Así no puede quedar desfasado de sus partes —corregir un
     * apellido cambia el nombre impreso en el acto.
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

            if (filled($this->apellidoCasada)) {
                $partes[] = 'de '.$this->apellidoCasada;
            }

            // array_filter descarta las partes vacías —segundo nombre y
            // apellido materno son opcionales— y así no quedan dobles
            // espacios en el medio.
            return trim(implode(' ', array_filter($partes, fn ($p): bool => filled($p))));
        });
    }

    public function tramites(): HasMany
    {
        return $this->hasMany(Tramite::class);
    }

    public function documentos(): HasManyThrough
    {
        return $this->hasManyThrough(Documento::class, Tramite::class);
    }

    public function pagos(): HasManyThrough
    {
        return $this->hasManyThrough(Pago::class, Tramite::class);
    }

    /**
     * La cédula tal como se imprime en la credencial: «1234567-1A BN».
     */
    protected function documentoIdentidad(): Attribute
    {
        return Attribute::get(fn (): string => trim(implode(' ', array_filter([
            $this->ci_nit.($this->complemento ? '-'.$this->complemento : ''),
            $this->expedido,
        ]))));
    }

    /**
     * La direccion para mostrar la foto. NULL si la ficha no tiene.
     *
     * Pasa por Archivos::url porque la columna guarda una ruta cuando el disco
     * es local y una direccion completa cuando es s3, y las dos formas pueden
     * convivir en la misma tabla. Ver App\Support\Archivos.
     */
    protected function fotoUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => Archivos::url($this->foto));
    }

    /**
     * Saldo pendiente sumando los trámites con pago incompleto.
     */
    public function deudaTotal(): float
    {
        return (float) $this->tramites()
            ->whereColumn('monto_pagado', '<', 'monto_total')
            ->selectRaw('COALESCE(SUM(monto_total - monto_pagado), 0) as saldo')
            ->value('saldo');
    }

    /**
     * La Cédula de Pescador vigente hoy, o NULL si no tiene.
     *
     * Es la llave del sistema: sin ella no se le puede emitir ni un permiso
     * por faena ni una guía de transporte. Ver TipoTramite::habilitacionPara().
     *
     * Se filtra por `fecha_vencimiento` y no solo por el estado guardado a
     * propósito. El estado se actualiza con un comando programado, así que
     * entre corrida y corrida una credencial vencida ayer puede seguir
     * figurando como vigente en la columna. La fecha nunca miente.
     */
    public function credencialVigente(): ?Documento
    {
        return $this->documentos()
            ->where('documentos.tipo', CategoriaDocumento::Credencial)
            ->where('documentos.estado', '!=', EstadoDocumento::Anulado)
            ->whereDate('documentos.fecha_vencimiento', '>=', now()->toDateString())
            ->orderByDesc('documentos.fecha_vencimiento')
            ->first();
    }

    public function tieneCredencialVigente(): bool
    {
        return $this->credencialVigente() !== null;
    }

    /**
     * El trámite de cédula que está en curso, sin documento emitido todavía.
     *
     * La cédula NO habilita al pedirla. El trámite entra como recibido, pasa a
     * revisión, un supervisor lo aprueba y recién ahí se emite el documento.
     * Hasta ese momento el pescador no puede sacar faena ni guía.
     *
     * Sirve para no confundir «nunca la sacó» con «ya la pidió y está en
     * revisión»: en el primer caso ventanilla tiene que cargarle la cédula, en
     * el segundo cargarla otra vez sería duplicar el trámite.
     */
    public function credencialEnTramite(): ?Tramite
    {
        return $this->tramites()
            ->whereHas('tipoTramite', fn ($q) => $q->where('categoria_documento', CategoriaDocumento::Credencial->value))
            ->pendientes()
            ->whereDoesntHave('documentos')
            ->latest()
            ->first();
    }

    /**
     * La última credencial que tuvo, esté vigente o no.
     *
     * Sirve para distinguir «nunca sacó la cédula» de «se le venció»: en
     * ventanilla son dos conversaciones distintas y dos trámites distintos.
     */
    public function ultimaCredencial(): ?Documento
    {
        return $this->documentos()
            ->where('documentos.tipo', CategoriaDocumento::Credencial)
            ->where('documentos.estado', '!=', EstadoDocumento::Anulado)
            ->orderByDesc('documentos.fecha_emision')
            ->first();
    }

    public function tieneDocumentosVencidos(): bool
    {
        return $this->documentos()
            ->where('documentos.estado', EstadoDocumento::Vencido)
            ->exists();
    }

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
     * vuelve lento, la salida es un índice funcional sobre esta misma
     * expresión, no volver a guardar el nombre.
     */
    public function scopeBuscar(Builder $query, ?string $termino): Builder
    {
        if (blank($termino)) {
            return $query;
        }

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
     * para que el índice `solicitantes_nombre_index` pueda usarse.
     */
    public function scopeOrdenAlfabetico(Builder $query): Builder
    {
        return $query
            ->orderBy('apellidoPaterno')
            ->orderBy('apellidoMaterno')
            ->orderBy('primerNombre');
    }
}
