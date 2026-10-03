<?php

namespace App\Enums;

/**
 * Los roles del sistema y qué puede hacer cada uno.
 */
enum RolSistema: string
{
    case Administrador = 'administrador';

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
     * Permisos asignados al rol durante el seeding.
     *
     * El reparto, en una línea: VENTANILLA arma, SUPERVISIÓN deshace. Aprobar ya no
     * es de nadie: lo hace el pago confirmado en SIREB. Ver docs/ARQUITECTURA.md.
     *
     * @return array<int, string>
     */
    public function permisos(): array
    {
        $lectura = [
            'dashboard.ver',
            'beneficiarios.ver',
            'aprovechamientos.ver',
            'carnets.ver',
            'faenas.ver',
            'guias.ver',
            'recibos.ver',
            'catalogos.ver',
            'reportes.ver',
        ];

        // VENTANILLA: cargar, corregir el borrador, verificar el pago en SIREB
        // (el permiso `crear` de cada documento) y entregar los papeles.
        $operacion = [
            'beneficiarios.crear',
            'beneficiarios.editar',
            // Dar acceso al portal /mi-cuenta y resetear su clave, en ventanilla.
            'beneficiarios.portal',

            'aprovechamientos.crear',
            'aprovechamientos.editar',

            'carnets.crear',
            'carnets.editar',

            'faenas.crear',
            'faenas.editar',

            'guias.crear',
            'guias.editar',

            // Entregar un papel es un acto distinto de consultar la ficha, así
            // que cada documento lleva su propio permiso.
            'aprovechamientos.imprimir',
            'carnets.imprimir',
            'faenas.imprimir',
            'guias.imprimir',
            'recibos.imprimir',
        ];

        // SUPERVISIÓN: deshacer lo que ya no se puede corregir.
        $supervision = [
            // Eliminar deja la fila fuera de los listados y solo queda la
            // auditoría con el motivo. Solo sobre borradores; anula el cobro en SIREB.
            'aprovechamientos.eliminar',
            'carnets.eliminar',
            'faenas.eliminar',
            'guias.eliminar',

            // Revocar es una sanción y anular quema un número del talonario:
            // ninguna de las dos se revierte.
            'carnets.revocar',
            'aprovechamientos.revocar',
            'guias.anular',

            'reportes.exportar',
            'auditoria.ver',
        ];

        $administracion = [
            'beneficiarios.eliminar',

            // Los TRES catálogos con un solo permiso: cambian por la misma vía
            // —una resolución— y se otorgarían siempre juntos.
            'catalogos.gestionar',

            'usuarios.gestionar',
            'roles.gestionar',
            'configuracion.gestionar',
        ];

        return match ($this) {
            self::Administrador => [...$lectura, ...$operacion, ...$supervision, ...$administracion],
        };
    }

    /**
     * Catálogo completo de permisos del sistema.
     *
     * @return array<int, string>
     */
    public static function todosLosPermisos(): array
    {
        return array_values(array_unique(self::Administrador->permisos()));
    }
}
