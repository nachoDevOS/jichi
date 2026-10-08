<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Rol;
use App\Models\User;
use App\Support\Paginacion;
use App\Support\Sql;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
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
                    'detalle' => $beneficiario ? 'C.I. '.$beneficiario->documento_identidad : ($u->email ?? $u->cargo),
                    'foto_url' => $beneficiario?->foto_url,
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
