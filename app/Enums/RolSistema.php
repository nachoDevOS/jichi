<?php

namespace App\Enums;

/**
 * El catálogo de permisos y los roles fijos del sistema. Los demás roles los
 * arma la unidad en Seguridad › Roles.
 */
enum RolSistema: string
{
    case Administrador = 'administrador';

    /**
     * El catálogo, módulo por módulo en el orden del menú: [nombre, sección, acciones].
     * Las acciones van en el orden del trámite. Un permiso nuevo va acá y en su ruta.
     */
    public const CATALOGO = [
        'dashboard' => ['Panel', 'General', ['ver']],

        'beneficiarios' => ['Beneficiarios', 'Ventanilla', ['ver', 'crear', 'editar', 'portal', 'eliminar']],
        'aprovechamientos' => ['Autorizaciones de pesca', 'Ventanilla', ['ver', 'crear', 'editar', 'verificar-pago', 'cargar-pago', 'renovar-liquidacion', 'imprimir', 'eliminar', 'revocar']],
        // Reponer no lleva permiso propio: es parte de registrar (`carnets.crear`).
        'carnets' => ['Carnets', 'Ventanilla', ['ver', 'crear', 'editar', 'verificar-pago', 'cargar-pago', 'renovar-liquidacion', 'imprimir', 'eliminar', 'revocar']],
        'faenas' => ['Permisos de faena', 'Ventanilla', ['ver', 'crear', 'editar', 'verificar-pago', 'cargar-pago', 'renovar-liquidacion', 'imprimir', 'eliminar', 'revocar']],
        'guias' => ['Guías de transporte', 'Ventanilla', ['ver', 'crear', 'editar', 'verificar-pago', 'cargar-pago', 'renovar-liquidacion', 'imprimir', 'eliminar', 'revocar']],

        'recibos' => ['Recibos', 'Pagos', ['ver', 'imprimir']],

        'asociaciones' => ['Asociaciones', 'Parámetros', ['ver', 'crear', 'editar']],
        // Tipos de carnet, productos, escala y aranceles: cambian por la misma vía, una resolución.
        'catalogos' => ['Catálogos', 'Catálogos', ['ver', 'crear', 'editar']],

        // Sin pantalla todavía: se declaran para que el rol ya los pueda llevar.
        'reportes' => ['Reportes', 'Administración', ['ver', 'exportar']],
        'auditoria' => ['Auditoría', 'Administración', ['ver']],
        'configuracion' => ['Configuración', 'Administración', ['ver', 'editar']],

        'usuarios' => ['Usuarios', 'Seguridad', ['ver', 'crear', 'editar']],
        'roles' => ['Roles', 'Seguridad', ['ver', 'crear', 'editar', 'eliminar']],
        // Lo que se habló con SIREB, leído del log. `exportar` = descargar el archivo del día.
        'sireb' => ['Registro SIREB', 'Seguridad', ['ver', 'exportar']],
    ];

    /** Cómo se lee la segunda mitad de un permiso. */
    public const ACCIONES = [
        'ver' => 'Ver',
        'crear' => 'Registrar',
        'editar' => 'Editar',
        'verificar-pago' => 'Verificar pago',
        'cargar-pago' => 'Cargar pago',
        'renovar-liquidacion' => 'Generar nueva liquidación',
        'imprimir' => 'Imprimir',
        'portal' => 'Acceso al portal',
        'eliminar' => 'Eliminar',
        'revocar' => 'Revocar',
        'exportar' => 'Exportar',
    ];

    public function etiqueta(): string
    {
        return match ($this) {
            self::Administrador => 'Administrador',
        };
    }

    public function descripcion(): string
    {
        return match ($this) {
            self::Administrador => 'Control total del sistema: beneficiarios, aprovechamientos, '.
                'carnets, faenas, guías, recibos y configuración.',
        };
    }

    /**
     * Permisos que el seeder le da al rol. Aprobar no es de nadie: lo hace el
     * pago confirmado en SIREB.
     *
     * @return array<int, string>
     */
    public function permisos(): array
    {
        return match ($this) {
            self::Administrador => self::todosLosPermisos(),
        };
    }

    /**
     * Qué otros permisos necesita uno para no terminar en un 403: toda acción
     * vuelve a la ficha o al listado, así que arrastra el `ver` de su módulo.
     *
     * @return list<string>
     */
    public static function requiere(string $permiso): array
    {
        $ver = strstr($permiso, '.', true).'.ver';

        // No todo módulo tiene `ver` (Usuarios sí, pero un catálogo futuro podría no tenerlo).
        return $ver !== $permiso && in_array($ver, self::todosLosPermisos(), true) ? [$ver] : [];
    }

    /**
     * La lista con todo lo que sus permisos arrastran.
     *
     * @param  list<string>  $permisos
     * @return list<string>
     */
    public static function conDependencias(array $permisos): array
    {
        return array_values(array_unique(array_merge($permisos, ...array_map(self::requiere(...), $permisos))));
    }

    /**
     * Catálogo completo de permisos del sistema, en el orden del menú.
     *
     * @return array<int, string>
     */
    public static function todosLosPermisos(): array
    {
        $todos = [];

        foreach (self::CATALOGO as $modulo => [, , $acciones]) {
            foreach ($acciones as $accion) {
                $todos[] = "{$modulo}.{$accion}";
            }
        }

        return $todos;
    }
}
