<?php

namespace App\Http\Resources\ApiApp;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

/**
 * ApiApp · SOLO de la app BeniDigital (docs/api/README.md § 4).
 *
 * «Mi perfil» para la app: lo mismo que PerfilPortal::de(), salvo la foto. Su URL
 * sale de APP_URL, que el celular no alcanza (y sería pública): va por
 * GET /perfil/foto con el token. Contrato en docs/api/portal.md.
 *
 * @property array{datos: array<string, mixed>, actividades: list<array<string, mixed>>, cuenta: array<string, mixed>} $resource
 */
class PerfilResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'datos' => [
                ...Arr::except($this->resource['datos'], 'foto_url'),
                'tiene_foto' => $this->resource['datos']['foto_url'] !== null,
            ],
            'actividades' => $this->resource['actividades'],
            'cuenta' => $this->resource['cuenta'],
        ];
    }
}
