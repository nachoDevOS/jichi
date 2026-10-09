<?php

namespace App\Http\Controllers\Panel;

use App\Enums\RolSistema;
use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\GuardarUsuarioRequest;
use App\Models\Rol;
use App\Models\User;
use App\Support\Paginacion;
use App\Support\Sql;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 *  Usuarios — todas las cuentas: funcionarios y beneficiarios. Al beneficiario
 *  se le da, resetea y desactiva el acceso en su ficha (`CuentaPortalController`).
 */
class UsuarioController extends Controller
{
    /** Cómo está la cuenta. No es una columna: sale de `activo` y `debe_cambiar_password`. */
    private const ESTADOS = [
        'activa' => ['Activa', 'emerald'],
        'temporal' => ['Clave temporal', 'amber'],
        'desactivada' => ['Desactivada', 'slate'],
    ];

    private const TIPOS = ['funcionario' => 'Funcionarios', 'beneficiario' => 'Beneficiarios'];

    /**
     * Lista — GET /panel/seguridad/usuarios
     */
    public function index(Request $request): Response
    {
        $filtros = [
            'buscar' => $request->string('buscar')->trim()->value() ?: null,
            'tipo' => array_key_exists((string) $request->query('tipo'), self::TIPOS) ? $request->query('tipo') : null,
            'estado' => array_key_exists((string) $request->query('estado'), self::ESTADOS) ? $request->query('estado') : null,
            'por_pagina' => Paginacion::filas($request),
        ];

        $usuarios = User::query()
            ->with(['beneficiario', 'roles'])
            // La cuenta de un beneficiario dado de baja no entra: no tiene a quién mostrar.
            ->where(fn (Builder $q) => $q->whereNull('beneficiario_id')->orWhereHas('beneficiario'))
            ->when($filtros['tipo'] === 'funcionario', fn (Builder $q) => $q->whereNull('beneficiario_id'))
            ->when($filtros['tipo'] === 'beneficiario', fn (Builder $q) => $q->whereNotNull('beneficiario_id'))
            ->when($filtros['buscar'], fn (Builder $q, string $t) => $this->buscar($q, $t))
            ->when($filtros['estado'], fn (Builder $q, string $e) => $this->filtrarEstado($q, $e))
            // Primero el personal, después los beneficiarios; los más nuevos arriba.
            ->orderByRaw('beneficiario_id IS NOT NULL')
            ->latest('id')
            ->paginate($filtros['por_pagina'])
            ->withQueryString()
            ->through(function (User $u): array {
                [$etiqueta, $color] = self::ESTADOS[$this->estadoDe($u)];
                $beneficiario = $u->beneficiario;

                return [
                    'id' => $u->id,
                    'beneficiario_id' => $u->beneficiario_id,
                    'nombre' => $beneficiario?->nombreCompleto ?? $u->name,
                    // El funcionario entra con su correo; el beneficiario, con su C.I.
                    'detalle' => $beneficiario ? 'C.I. '.$beneficiario->documento_identidad
                        : collect([$u->email, $u->mamore_id ? 'Ibare '.$u->mamore_id : null])->filter()->implode(' · '),
                    'foto_url' => $beneficiario ? $beneficiario->foto_url : User::FOTO,
                    'rol' => $beneficiario ? 'Beneficiario' : ($u->roles->map(fn (Rol $r) => $r->etiqueta())->implode(', ') ?: 'Sin rol'),
                    'estado_etiqueta' => $etiqueta,
                    'estado_color' => $color,
                    'ultimo_acceso' => $u->ultimo_acceso_at?->toIso8601String(),
                    'creada' => $u->created_at?->toIso8601String(),
                ];
            });

        return Inertia::render('panel/seguridad/usuarios', [
            'usuarios' => $usuarios,
            'filtros' => $filtros,
            'opcionesPorPagina' => Paginacion::OPCIONES,
            'tipos' => collect(self::TIPOS)->map(fn (string $label, string $value): array => compact('value', 'label'))->values(),
            'estados' => collect(self::ESTADOS)->map(fn (array $e, string $value): array => ['value' => $value, 'label' => $e[0]])->values(),
        ]);
    }

    /**
     * Alta de funcionario — GET /panel/seguridad/usuarios/crear
     */
    public function create(): Response
    {
        return $this->formulario(null);
    }

    /**
     * Edición de funcionario — GET /panel/seguridad/usuarios/{usuario}/editar
     */
    public function edit(User $usuario): Response
    {
        // La cuenta del beneficiario se maneja desde su ficha (CuentaPortalController).
        abort_if($usuario->esBeneficiario(), 404);

        return $this->formulario($usuario->load('roles'));
    }

    /**
     * Edición de funcionario — PUT /panel/seguridad/usuarios/{usuario}
     */
    public function update(GuardarUsuarioRequest $request, User $usuario): RedirectResponse
    {
        abort_if($usuario->esBeneficiario(), 404);
        $datos = $request->validated();

        // Uno mismo no se desactiva ni se saca el administrador: se quedaría afuera.
        if ($usuario->is($request->user()) && (! ($datos['activo'] ?? true) || $usuario->hasRole(RolSistema::Administrador->value) && $datos['rol'] !== RolSistema::Administrador->value)) {
            return back()->with('aviso', 'No puede desactivar su propia cuenta ni quitarse el rol de administrador.');
        }

        DB::transaction(function () use ($usuario, $datos): void {
            $usuario->fill([
                'name' => $datos['nombre'],
                'mamore_id' => $datos['mamore_id'] ?? null,
                'activo' => $datos['activo'] ?? $usuario->activo,
            ]);
            // Sin la sección de emergencia (no es administrador) correo y clave no vienen: quedan como estaban.
            if (array_key_exists('email', $datos)) {
                $usuario->email = $datos['email'];
            }
            if (filled($datos['password'] ?? null)) {
                $usuario->password = $datos['password'];
            }
            $usuario->save();
            $usuario->syncRoles([$datos['rol']]);
        });

        return redirect()->route('usuarios.index')->with('exito', "Usuario «{$usuario->name}» actualizado.");
    }

    /** La misma pantalla para alta (sin usuario) y edición. */
    private function formulario(?User $usuario): Response
    {
        return Inertia::render('panel/seguridad/usuarios-formulario', [
            'usuario' => $usuario === null ? null : [
                'id' => $usuario->id,
                'nombre' => $usuario->name,
                'mamore_id' => $usuario->mamore_id,
                'rol' => $usuario->roles->first()?->name,
                'email' => $usuario->email,
                'activo' => $usuario->activo,
            ],
            'roles' => Rol::query()->orderBy('id')->get()
                ->map(fn (Rol $r): array => ['value' => $r->name, 'label' => $r->etiqueta()]),
            'ibareActivo' => (bool) config('jichi.ibare.activo'),
        ]);
    }

    /**
     * Alta de funcionario — POST /panel/seguridad/usuarios
     */
    public function store(GuardarUsuarioRequest $request): RedirectResponse
    {
        $datos = $request->validated();

        $usuario = DB::transaction(function () use ($datos): User {
            $usuario = User::create([
                'name' => $datos['nombre'],
                'email' => $datos['email'] ?? null,
                'mamore_id' => $datos['mamore_id'] ?? null,
                // Sin clave (entra solo por Ibare): una al azar que nadie conoce; la columna no admite null.
                'password' => $datos['password'] ?? Str::random(40),
            ]);
            $usuario->assignRole($datos['rol']);

            return $usuario;
        });

        return redirect()->route('usuarios.index')->with('exito', "Usuario «{$usuario->name}» creado.");
    }

    /** Funcionario: nombre, correo o C.I. de la cuenta. Beneficiario: la búsqueda del padrón. */
    private function buscar(Builder $q, string $termino): Builder
    {
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $termino).'%';
        $operador = Sql::like($q->getConnection());

        return $q->where(fn (Builder $w) => $w
            ->whereHas('beneficiario', fn (Builder $b) => $b->buscar($termino))
            ->orWhere(fn (Builder $f) => $f->whereNull('beneficiario_id')->where(fn (Builder $c) => $c
                ->where('name', $operador, $like)
                ->orWhere('email', $operador, $like)
                ->orWhere('ci', $operador, $like))));
    }

    private function estadoDe(User $usuario): string
    {
        return match (true) {
            ! $usuario->activo => 'desactivada',
            $usuario->debe_cambiar_password => 'temporal',
            default => 'activa',
        };
    }

    /** El mismo criterio que `estadoDe()`, en SQL. */
    private function filtrarEstado(Builder $q, string $estado): Builder
    {
        return match ($estado) {
            'desactivada' => $q->where('activo', false),
            'temporal' => $q->where('activo', true)->where('debe_cambiar_password', true),
            default => $q->where('activo', true)->where('debe_cambiar_password', false),
        };
    }
}
