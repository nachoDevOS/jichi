<?php

namespace App\Traits;

use App\Models\Auditoria;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Auth;

/**
 * Registra en la tabla `auditorias` cada alta, cambio y baja del modelo,
 * junto con el usuario, la IP y los valores antes/después.
 *
 * Los modelos pueden declarar `$noAuditable` para excluir columnas del
 * registro (por ejemplo campos calculados o rutas de archivos temporales).
 */
trait Auditable
{
    /**
     * ========================================================================
     *  EL PORQUÉ DEL PRÓXIMO MOVIMIENTO
     * ========================================================================
     *
     * Los eventos automáticos de abajo registran QUÉ cambió, pero no POR QUÉ. En
     * casi todos los casos alcanza; en los que son decisiones —eliminar un
     * expediente, anular un carnet— el motivo es lo único que sirve después.
     *
     * Quien va a hacer la operación deja el motivo acá antes:
     *
     *     $tramite->motivoAuditoria = $motivo;
     *     $tramite->delete();
     *
     * y la fila de `auditorias` sale con su descripción. Sin esto habría que
     * escribir una segunda fila a mano, y quedarían dos registros del mismo
     * hecho: uno con el dato y otro con la explicación.
     *
     * ES ESPECIALMENTE ÚTIL AL BORRAR. Después del delete la fila ya no existe:
     * si el motivo no viajó con el evento, no hay dónde colgarlo.
     */
    public ?string $motivoAuditoria = null;

    public static function bootAuditable(): void
    {
        static::created(fn (Model $modelo) => $modelo->registrarAuditoria('creado'));

        static::updated(function (Model $modelo) {
            $cambios = $modelo->obtenerCambiosAuditables();

            if ($cambios !== []) {
                $modelo->registrarAuditoria('actualizado', $cambios);
            }
        });

        static::deleted(fn (Model $modelo) => $modelo->registrarAuditoria('eliminado'));
    }

    public function auditorias(): MorphMany
    {
        return $this->morphMany(Auditoria::class, 'auditable')->latest();
    }

    /**
     * @return array<int, string>
     */
    public function columnasNoAuditables(): array
    {
        return [
            ...(property_exists($this, 'noAuditable') ? $this->noAuditable : []),
            'updated_at',
            'created_at',
            'remember_token',
            'password',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function obtenerCambiosAuditables(): array
    {
        return array_diff_key($this->getChanges(), array_flip($this->columnasNoAuditables()));
    }

    /**
     * @param  array<string, mixed>|null  $cambios
     */
    public function registrarAuditoria(string $evento, ?array $cambios = null, ?string $descripcion = null): void
    {
        $nuevos = $cambios ?? array_diff_key($this->attributesToArray(), array_flip($this->columnasNoAuditables()));

        // El motivo dejado por quien hizo la operación, si no se pasó uno
        // explícito. Ver $motivoAuditoria.
        $descripcion ??= $this->motivoAuditoria;

        Auditoria::create([
            'user_id' => Auth::id(),
            'evento' => $evento,
            'auditable_type' => static::class,
            'auditable_id' => $this->getKey(),
            'valores_anteriores' => $evento === 'creado'
                ? null
                : (array_intersect_key($this->getOriginal(), $nuevos) ?: null),
            'valores_nuevos' => $evento === 'eliminado' ? null : $nuevos,
            'descripcion' => $descripcion,
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'url' => request()->fullUrl(),
        ]);
    }
}
