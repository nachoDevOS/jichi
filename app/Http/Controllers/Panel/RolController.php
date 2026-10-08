<?php

namespace App\Http\Controllers\Panel;

use App\Enums\RolSistema;
use App\Exceptions\RolInvalidoException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\GuardarRolRequest;
use App\Models\Rol;
use App\Services\GestionarRolService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 *  Roles — qué puede hacer cada uno. Los de RolSistema son fijos; el resto
 *  los arma la unidad desde acá.
 */
class RolController extends Controller
{
    public function __construct(private GestionarRolService $servicio) {}

    /**
     * Lista de roles — GET /panel/seguridad/roles
     */
    public function index(): Response
    {
        // Se lee de la BASE, no del enum: es lo que el middleware hace cumplir.
        $roles = Rol::query()
            ->withCount(['users', 'permissions'])
            ->orderBy('id')
            ->get()
            ->map(function (Rol $rol): array {
                return [
                    'id' => $rol->id,
                    'nombre' => $rol->name,
                    'etiqueta' => $rol->etiqueta(),
                    'descripcion' => RolSistema::tryFrom($rol->name)?->descripcion(),
                    'del_sistema' => $rol->esDelSistema(),
                    'usuarios' => $rol->users_count,
                    'permisos' => $rol->permissions_count,
                    'puede_editarse' => ! $rol->esDelSistema(),
                    'puede_eliminarse' => $rol->puedeEliminarse(),
                ];
            });

        return Inertia::render('panel/seguridad/roles', [
            'roles' => $roles,
            'totalPermisos' => count(RolSistema::todosLosPermisos()),
        ]);
    }

    /**
     * Formulario de alta — GET /panel/seguridad/roles/crear
     */
    public function create(): Response
    {
        return $this->formulario(null);
    }

    /**
     * Alta — POST /panel/seguridad/roles
     */
    public function store(GuardarRolRequest $request): RedirectResponse
    {
        $datos = $request->validated();
        $rol = $this->servicio->crear($datos['nombre'], $datos['permisos']);

        return redirect()
            ->route('roles.index')
            ->with('exito', "Rol «{$rol->name}» creado con ".count($datos['permisos']).' permisos.');
    }

    /**
     * Formulario de edición — GET /panel/seguridad/roles/{rol}/editar
     */
    public function edit(Rol $rol): Response|RedirectResponse
    {
        if ($rol->esDelSistema()) {
            return redirect()->route('roles.index')
                ->with('error', RolInvalidoException::delSistema($rol->etiqueta())->getMessage());
        }

        return $this->formulario($rol);
    }

    /**
     * Edición — PUT /panel/seguridad/roles/{rol}
     */
    public function update(GuardarRolRequest $request, Rol $rol): RedirectResponse
    {
        $datos = $request->validated();

        try {
            $this->servicio->editar($rol, $datos['nombre'], $datos['permisos']);
        } catch (RolInvalidoException $e) {
            return redirect()->route('roles.index')->with('error', $e->getMessage());
        }

        return redirect()
            ->route('roles.index')
            ->with('exito', "Rol «{$rol->name}» actualizado.");
    }

    /**
     * Baja — DELETE /panel/seguridad/roles/{rol}
     */
    public function destroy(Rol $rol): RedirectResponse
    {
        try {
            $this->servicio->eliminar($rol);
        } catch (RolInvalidoException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('roles.index')
            ->with('exito', "Rol «{$rol->name}» eliminado.");
    }

    /** La misma pantalla para alta (sin rol) y edición. */
    private function formulario(?Rol $rol): Response
    {
        $rol?->load('permissions:id,name');

        return Inertia::render('panel/seguridad/roles-formulario', [
            'rol' => $rol === null ? null : [
                'id' => $rol->id,
                'nombre' => $rol->name,
                'permisos' => $rol->permissions->pluck('name')->values(),
            ],
            'modulos' => $this->modulos(),
            // Permiso → lo que arrastra: el formulario marca y desmarca en cadena.
            'requiere' => collect(RolSistema::todosLosPermisos())
                ->mapWithKeys(fn (string $p): array => [$p => RolSistema::requiere($p)])
                ->filter()
                ->all(),
            // Para «Copiar permisos de»: arrancar de un rol parecido.
            'plantillas' => Rol::query()
                ->with('permissions:id,name')
                ->when($rol, fn ($q) => $q->whereKeyNot($rol->id))
                ->orderBy('id')
                ->get()
                ->map(fn (Rol $r): array => [
                    'nombre' => $r->etiqueta(),
                    'permisos' => $r->permissions->pluck('name')->values(),
                ]),
        ]);
    }

    /**
     * El catálogo de permisos por módulo, en el orden del menú y con su sección.
     *
     * @return list<array{clave: string, etiqueta: string, grupo: string, permisos: list<array{nombre: string, accion: string}>}>
     */
    private function modulos(): array
    {
        return collect(RolSistema::CATALOGO)
            ->map(fn (array $m, string $clave): array => [
                'clave' => $clave,
                'etiqueta' => $m[0],
                'grupo' => $m[1],
                'permisos' => array_map(fn (string $accion): array => [
                    'nombre' => "{$clave}.{$accion}",
                    'accion' => RolSistema::ACCIONES[$accion] ?? $accion,
                ], $m[2]),
            ])
            ->values()
            ->all();
    }
}
