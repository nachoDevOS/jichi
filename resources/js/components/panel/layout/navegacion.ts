import {
    BadgeCheck,
    FileText,
    LayoutDashboard,
    Settings,
    ShieldCheck,
    Users,
    type LucideIcon,
} from 'lucide-react';

/**
 * Un ítem del menú lateral.
 */
export interface ItemNavegacion {
    /** Texto que ve el usuario. */
    titulo: string;
    /** Nombre de la ruta de Laravel, el mismo que se puso en ->name(). */
    ruta: string;
    icono: LucideIcon;
    /** Permiso necesario para verlo. Si se omite, lo ve cualquiera con sesión. */
    permiso?: string;
}

/**
 * EL MENÚ DEL PANEL DE ADMINISTRACIÓN.
 *
 * Está en su propio archivo (y no dentro de la barra lateral) porque es la
 * lista que más se toca: cada módulo nuevo agrega una línea acá y nada más.
 *
 * DOS FILTROS SE APLICAN SOBRE ESTA LISTA:
 *
 *   1. Por PERMISO. Si el usuario no tiene el permiso indicado, el ítem ni
 *      siquiera se dibuja. Los permisos salen del enum App\Enums\RolSistema
 *      y llegan a React dentro de auth.user.permisos (ver el middleware
 *      HandleInertiaRequests).
 *
 *   2. Por RUTA EXISTENTE. Si la ruta todavía no está declarada en
 *      routes/panel.php, el ítem se muestra en gris y no se puede pinchar.
 *      Así el menú refleja el sistema completo desde el primer día sin que
 *      nada explote al hacer clic.
 *
 * IMPORTANTE: la seguridad real la aplica el middleware de Laravel en
 * routes/panel.php. Esconder un botón en React es comodidad para el usuario,
 * NO protección: cualquiera puede escribir la URL a mano. Los dos filtros
 * tienen que existir.
 */
export const NAVEGACION: ItemNavegacion[] = [
    { titulo: 'Panel', ruta: 'dashboard', icono: LayoutDashboard, permiso: 'dashboard.ver' },
    { titulo: 'Solicitantes', ruta: 'solicitantes.index', icono: Users, permiso: 'solicitantes.ver' },
    { titulo: 'Trámites', ruta: 'tramites.index', icono: FileText, permiso: 'tramites.ver' },
    { titulo: 'Documentos', ruta: 'documentos.index', icono: BadgeCheck, permiso: 'documentos.ver' },
    { titulo: 'Reportes', ruta: 'reportes.index', icono: ShieldCheck, permiso: 'reportes.ver' },
    { titulo: 'Configuración', ruta: 'configuracion.index', icono: Settings, permiso: 'configuracion.gestionar' },
];
