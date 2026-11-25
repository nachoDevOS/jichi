<?php

namespace App\Http\Resources\ApiApp;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * ApiApp · SOLO de la app BeniDigital (docs/api/README.md § 4).
 *
 * La cuenta del portal como la recibe la app BeniDigital. Cambiar un campo acá
 * rompe la app: el contrato está en docs/api/portal.md.
 *
 * @mixin User
 */
class CuentaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'nombre' => $this->beneficiario->nombreCompleto,
            'ci' => $this->beneficiario->documento_identidad,
            'debe_cambiar_password' => $this->debe_cambiar_password,
            'ultimo_acceso' => $this->ultimo_acceso_at?->toIso8601String(),
        ];
    }
}
