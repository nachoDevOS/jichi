<?php

namespace App\Models;

use App\Enums\CategoriaDocumento;
use App\Enums\EstadoDocumento;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

#[Appends(['url_verificacion', 'dias_para_vencer'])]
#[Fillable([
    'codigo_verificacion',
    'tramite_id',
    'tipo',
    'fecha_emision',
    'fecha_vencimiento',
    'estado',
    'pdf_path',
    'hash_pdf',
    'datos_snapshot',
    'emitido_por',
    'anulado_por',
    'anulado_at',
    'motivo_anulacion',
])]
class Documento extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    /**
     * Columnas que el trait Auditable debe ignorar.
     *
     * Son estadística de la página pública, no datos del documento: no tiene
     * sentido escribir una fila de auditoría cada vez que alguien escanea
     * un QR desde la calle.
     *
     * @var array<int, string>
     */
    protected array $noAuditable = ['veces_verificado', 'ultima_verificacion_at'];

    protected function casts(): array
    {
        return [
            'tipo' => CategoriaDocumento::class,
            'estado' => EstadoDocumento::class,
            'fecha_emision' => 'date',
            'fecha_vencimiento' => 'date',
            'datos_snapshot' => 'array',
            'ultima_verificacion_at' => 'datetime',
            'anulado_at' => 'datetime',
            'veces_verificado' => 'integer',
        ];
    }

    public function tramite(): BelongsTo
    {
        return $this->belongsTo(Tramite::class);
    }

    public function emitidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'emitido_por');
    }

    public function anuladoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulado_por');
    }

    /**
     * URL pública que codifica el QR impreso en el documento.
     */
    protected function urlVerificacion(): Attribute
    {
        return Attribute::get(fn (): string => route('verificar.show', $this->codigo_verificacion));
    }

    protected function diasParaVencer(): Attribute
    {
        return Attribute::get(fn (): ?int => $this->fecha_vencimiento
            ? (int) now()->startOfDay()->diffInDays($this->fecha_vencimiento, false)
            : null);
    }

    /**
     * Suma una visita al contador del QR público.
     *
     * OJO CON EL DETALLE: acá NO se usa $documento->increment(), aunque sea
     * lo natural. increment() dispara el evento `updated` del modelo, y este
     * modelo usa el trait Auditable, que escucha ese evento y escribe una
     * fila en la tabla `auditorias`.
     *
     * Como esta ruta es pública y sin login, cada escaneo terminaba haciendo
     * DOS escrituras: el contador y una auditoría. Con tráfico real la tabla
     * de auditoría se llenaba de ruido anónimo y tapaba lo que sí importa
     * (quién aprobó, quién anuló, quién cobró).
     *
     * Actualizando por query builder no se dispara ningún evento de modelo.
     * withoutTimestamps() además evita tocar `updated_at`, que haría parecer
     * que el documento fue modificado cuando solo lo miraron.
     */
    public function registrarVerificacion(): void
    {
        static::withoutTimestamps(fn () => static::query()
            ->whereKey($this->getKey())
            ->update([
                'veces_verificado' => DB::raw('veces_verificado + 1'),
                'ultima_verificacion_at' => now(),
            ]));
    }

    /**
     * Estado real considerando la fecha de vencimiento. El estado persistido
     * puede quedar desactualizado entre corridas del comando de vencimiento.
     */
    public function estadoEfectivo(): EstadoDocumento
    {
        if ($this->estado === EstadoDocumento::Anulado) {
            return EstadoDocumento::Anulado;
        }

        if ($this->fecha_vencimiento && $this->fecha_vencimiento->isPast()) {
            return EstadoDocumento::Vencido;
        }

        return EstadoDocumento::Vigente;
    }

    public function scopeVigentes(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('estado'), EstadoDocumento::Vigente);
    }

    /**
     * Documentos vigentes que vencen dentro de los próximos N días.
     */
    public function scopePorVencer(Builder $query, int $dias = 30): Builder
    {
        return $query->where($query->qualifyColumn('estado'), EstadoDocumento::Vigente)
            ->whereNotNull($query->qualifyColumn('fecha_vencimiento'))
            ->whereBetween($query->qualifyColumn('fecha_vencimiento'), [
                now()->toDateString(),
                now()->addDays($dias)->toDateString(),
            ]);
    }

    public function getRouteKeyName(): string
    {
        return 'codigo_verificacion';
    }
}
