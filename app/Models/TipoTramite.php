<?php

namespace App\Models;

use App\Enums\CategoriaDocumento;
use App\Enums\VigenciaTipo;
use App\Support\Habilitacion;
use App\Traits\Auditable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

#[Table('tipos_tramite')]
#[Fillable([
    'area_id',
    'nombre',
    'slug',
    'codigo',
    'descripcion',
    'categoria_documento',
    'monto',
    'ordenanza',
    'requiere_foto',
    'vigencia_tipo',
    'vigencia_dias',
    'uso_unico',
    'requiere_credencial',
    'requisitos',
    'texto_plantilla',
    'requiere_aprobacion',
    'activo',
])]
class TipoTramite extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'categoria_documento' => CategoriaDocumento::class,
            'vigencia_tipo' => VigenciaTipo::class,
            'requiere_foto' => 'boolean',
            'requiere_aprobacion' => 'boolean',
            'uso_unico' => 'boolean',
            'requiere_credencial' => 'boolean',
            'activo' => 'boolean',
            'monto' => 'decimal:2',
            'vigencia_dias' => 'integer',
            'requisitos' => 'array',
        ];
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function exenciones(): HasMany
    {
        return $this->hasMany(Exencion::class);
    }

    public function tramites(): HasMany
    {
        return $this->hasMany(Tramite::class);
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    public function scopeDelArea(Builder $query, int|string $area): Builder
    {
        return $query->whereHas('area', fn (Builder $q) => is_numeric($area)
            ? $q->whereKey($area)
            : $q->where('slug', $area));
    }

    /**
     * Hasta cuándo vale un documento de este tipo emitido en `$emision`.
     *
     * La cuenta no la hace este modelo: la hace el enum, que es donde vive la
     * diferencia entre «un mes desde la emisión» y «hasta el cierre de la
     * gestión». Ver App\Enums\VigenciaTipo.
     */
    public function calcularVencimiento(?CarbonInterface $emision = null): ?Carbon
    {
        return $this->vigencia_tipo->calcularVencimiento(
            $emision ?? Carbon::now(),
            $this->vigencia_dias,
        );
    }

    /**
     * Cómo se le explica la vigencia al ciudadano en ventanilla.
     */
    public function vigenciaLegible(): string
    {
        $texto = $this->vigencia_tipo->descripcion($this->vigencia_dias);

        // El uso único es más restrictivo que la fecha y hay que decirlo: un
        // permiso por faena vale un mes, pero si se usa a los tres días se
        // terminó. Sin esta aclaración el operador cree que le quedan 27.
        return $this->uso_unico ? $texto.' · un solo uso' : $texto;
    }

    /**
     * LA COMPUERTA: ¿este solicitante puede pedir este trámite?
     *
     * Hoy la única condición es la cédula de pescador vigente, y solo la
     * exigen los tipos marcados con `requiere_credencial` —el permiso por
     * faena y la guía de transporte—. La cédula misma no se exige a sí misma.
     *
     * Devuelve un objeto y no un booleano porque el operador tiene al pescador
     * enfrente y necesita saber qué falta. Ver App\Support\Habilitacion.
     */
    public function habilitacionPara(Solicitante $solicitante): Habilitacion
    {
        /*
         * LA FOTOGRAFÍA NO SE COMPRUEBA ACÁ.
         *
         * La credencial no se puede imprimir sin la cara del titular, pero eso
         * no es motivo para no dejar entrar al formulario: si al pescador le
         * falta la foto, el arreglo es sacársela en ese mismo momento, no
         * mandarlo a otra pantalla. El formulario de la cédula la pide cuando
         * falta y la guarda en la FICHA del solicitante, que es donde vive por
         * ser un dato personal. Ver TramiteController::validarAdjuntos().
         */
        if (! $this->requiere_credencial) {
            return Habilitacion::permitida();
        }

        $credencial = $solicitante->credencialVigente();

        if ($credencial !== null) {
            return Habilitacion::permitida($credencial);
        }

        // No hay vigente. Antes de decir que no tiene, se mira si ya la pidió:
        // la cédula pasa por revisión y aprobación antes de emitirse, y en ese
        // rato no habilita nada pero tampoco hay que volver a cargarla.
        $enTramite = $solicitante->credencialEnTramite();

        if ($enTramite !== null) {
            return Habilitacion::credencialEnRevision($enTramite);
        }

        // Se busca la última que tuvo para poder distinguir «nunca sacó la
        // cédula» de «se le venció», que son dos conversaciones distintas en
        // ventanilla.
        $ultima = $solicitante->ultimaCredencial();

        return $ultima === null
            ? Habilitacion::sinCredencial()
            : Habilitacion::credencialVencida($ultima);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
