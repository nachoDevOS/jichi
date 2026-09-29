<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\CambiarClaveRequest;
use App\Services\CuentaPortalService;
use App\Support\ExpedienteBeneficiario;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Sus datos, para leer: se corrigen en ventanilla con la cédula en la mano.
 * Lo único que cambia él mismo es su contraseña.
 */
class PerfilController extends Controller
{
    public function __construct(private readonly CuentaPortalService $servicio) {}

    public function show(Request $request): Response
    {
        $cuenta = $request->user();
        $b = $cuenta->beneficiario()->with('departamento')->first();

        return Inertia::render('portal/perfil', [
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
        ]);
    }

    public function editarClave(Request $request): Response
    {
        return Inertia::render('portal/clave', [
            'obligatorio' => $request->user()->debe_cambiar_password,
        ]);
    }

    public function actualizarClave(CambiarClaveRequest $request): RedirectResponse
    {
        $this->servicio->cambiarClave($request->user(), $request->validated('password'));

        return redirect()->route('portal.inicio')->with('exito', 'Contraseña actualizada.');
    }
}
