<?php

namespace App\Http\Resources\ApiApp;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

/**
 * ApiApp · SOLO de la app BeniDigital (docs/api/README.md § 4).
 *
 * Un documento del portal (autorización, carnet, faena o guía) como lo arma
 * ResumenPortal, para la app. Sin las URLs de la web: piden la sesión del
 * navegador, que la app no tiene. Contrato en docs/api/portal.md.
 *
 * @property array<string, mixed> $resource
 */
class DocumentoResource extends JsonResource
{
    /** Lo que solo sirve en el portal web: vista previa, verificación y descarga con sesión. */
    private const SOLO_WEB = ['verificar', 'vista_previa', 'descargar'];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...Arr::except($this->resource, self::SOLO_WEB),
            // Vigente hoy: el PDF se podrá bajar por la API (fase 4).
            'puede_descargar' => ($this->resource['descargar'] ?? null) !== null,
        ];
    }
}
