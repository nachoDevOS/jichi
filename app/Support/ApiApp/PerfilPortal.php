<?php

namespace App\Support\ApiApp;

use App\Models\User;
use App\Support\ExpedienteBeneficiario;

/**
 * ApiApp · COMPARTIDO con el portal web: cambiarlo cambia la app y la web (docs/api/README.md § 4).
 *
 * Los datos de «Mis datos»: los usan la pantalla web (Portal\PerfilController) y
 * «Mi perfil» de la app (ApiApp\PerfilController). Solo lectura: se corrigen en ventanilla.
 */
class PerfilPortal
{
    /** @return array{datos: array<string, mixed>, actividades: list<array<string, mixed>>, cuenta: array<string, mixed>} */
    public static function de(User $cuenta): array
    {
        $b = $cuenta->beneficiario()->with('departamento')->first();

        return [
            'datos' => [
                'nombre' => $b->nombreCompleto,
                'nombres' => trim($b->primerNombre.' '.$b->segundoNombre),
                'apellido_paterno' => $b->apellidoPaterno,
                'apellido_materno' => $b->apellidoMaterno,
                'apellido_casado' => $b->apellidoCasado,
                'documento_identidad' => $b->documento_identidad,
                'expedido' => $b->departamento?->nombre,
                'foto_url' => $b->foto_url,
                'fecha_nacimiento' => $b->fechaNacimiento?->toDateString(),
                'edad' => $b->edad,
                'genero' => $b->genero,
                'nacionalidad' => $b->nacionalidad,
                'telefono' => $b->telefono,
                'email' => $b->email,
                'direccion' => $b->direccion,
                'ciudad' => $b->ciudad,
                'provincia' => $b->provincia,
                'registrado' => $b->created_at?->toIso8601String(),
            ],

            // Sus actividades: una por carnet vigente, con el gremio que lo avala.
            'actividades' => ExpedienteBeneficiario::carnets($b)
                ->filter->estaVigente()
                ->map(fn ($c): array => [
                    'actividad' => $c->tipo_actor->etiqueta(),
                    'asociacion' => $c->asociacion?->nombre,
                    'vence' => $c->fecha_vencimiento?->toDateString(),
                ])
                ->values()
                ->all(),

            'cuenta' => [
                'creada' => $cuenta->created_at?->toIso8601String(),
                'ultimo_acceso' => $cuenta->ultimo_acceso_at?->toIso8601String(),
            ],
        ];
    }
}
