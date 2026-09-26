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
                'carnets, faenas, guías, caja y configuración.',
        };
    }

    /**
     * Permisos asignados al rol durante el seeding.
     *
     * El reparto, en una línea: VENTANILLA arma y presenta, SUPERVISIÓN firma y
     * deshace. Son dos personas distintas a propósito. Ver docs/ARQUITECTURA.md.
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
            'caja.ver',
            'catalogos.ver',
            'reportes.ver',
        ];

        // VENTANILLA: cargar, corregir el borrador, cobrar, presentar a
        // revisión, cerrar lo que volvió y entregar los papeles.
        $operacion = [
            'beneficiarios.crear',
            'beneficiarios.editar',

            'aprovechamientos.crear',
            'aprovechamientos.editar',
            'aprovechamientos.enviar',

            'carnets.crear',
            'carnets.editar',
            'carnets.enviar',

            'faenas.crear',
            'faenas.editar',
            'faenas.enviar',

            'guias.crear',
            'guias.editar',
            'guias.enviar',
            'guias.cerrar',

            // Entregar un papel es un acto distinto de consultar la ficha, así
            // que cada documento lleva su propio permiso.
            'aprovechamientos.imprimir',
            'carnets.imprimir',
            'faenas.imprimir',
            'guias.imprimir',
            'recibos.imprimir',

            'caja.cobrar',
            // Corregir un depósito es lo único que levanta una observación.
            'pagos.corregir',
        ];

        // SUPERVISIÓN: firmar, y deshacer lo que ya no se puede corregir.
        $supervision = [
            'aprovechamientos.aprobar',
            'carnets.aprobar',
            'faenas.aprobar',
            'guias.aprobar',

            // Controlar las boletas es del mismo lado que aprobar: sin eso, la
            // validación sería decorativa.
            'pagos.controlar',

            // Eliminar deja la fila fuera de los listados y solo queda la
            // auditoría con el motivo. Solo sobre borradores sin plata encima.
            'aprovechamientos.eliminar',
            'carnets.eliminar',
            'faenas.eliminar',
            'guias.eliminar',

            // Revocar es una sanción y anular quema un número del talonario:
            // ninguna de las dos se revierte.
            'carnets.revocar',
            'guias.anular',
            'caja.anular',

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
