<?php

namespace App\Enums;

enum RolSistema: string
{
    case Administrador = 'administrador';
    case Supervisor = 'supervisor';
    case Operador = 'operador';
    case SoloLectura = 'solo_lectura';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Administrador => 'Administrador',
            self::Supervisor => 'Supervisor',
            self::Operador => 'Operador de Ventanilla',
            self::SoloLectura => 'Solo Lectura',
        };
    }

    public function descripcion(): string
    {
        return match ($this) {
            self::Administrador => 'Control total del sistema, configuración y usuarios.',
            self::Supervisor => 'Aprueba o rechaza trámites y supervisa la recaudación.',
            self::Operador => 'Recepciona trámites, registra pagos y entrega documentos.',
            self::SoloLectura => 'Consulta información y reportes sin modificar datos.',
        };
    }

    /**
     * Permisos asignados al rol durante el seeding.
     *
     * @return array<int, string>
     */
    public function permisos(): array
    {
        $lectura = [
            'dashboard.ver',
            'solicitantes.ver',
            'tramites.ver',
            'pagos.ver',
            'documentos.ver',
            'reportes.ver',
        ];

        $operacion = [
            'solicitantes.crear',
            'solicitantes.editar',
            'tramites.crear',
            'tramites.editar',
            // No hay 'tramites.revisar': el paso de «tomar para revisar» se
            // retiró junto con el estado «recibido». Todo trámite nace en
            // revisión, así que no queda nada que ese permiso proteja.
            'pagos.registrar',
            'documentos.emitir',
            'documentos.entregar',
        ];

        $supervision = [
            'tramites.aprobar',
            'tramites.rechazar',
            'pagos.anular',
            'documentos.anular',
            'reportes.exportar',
            'auditoria.ver',
        ];

        $administracion = [
            'solicitantes.eliminar',
            'tramites.eliminar',
            'usuarios.gestionar',
            'roles.gestionar',
            'areas.gestionar',
            'tipos_tramite.gestionar',
            'configuracion.gestionar',
        ];

        return match ($this) {
            self::SoloLectura => $lectura,
            self::Operador => [...$lectura, ...$operacion],
            self::Supervisor => [...$lectura, ...$operacion, ...$supervision],
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
