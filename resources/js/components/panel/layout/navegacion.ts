import { BadgeCheck, BarChart3, Building2, Coins, Fish, LayoutDashboard, ReceiptText, Ruler, Ship, Tags, Truck, Users, Waves, type LucideIcon } from 'lucide-react';

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
    /**
     * El nombre ENTERO, para las migas de pan y para el globito de la barra
     * angosta.
     */
    tituloCompleto?: string;

    /**
     * Encabezado de sección bajo el que se agrupa el ítem.
     */
    grupo?: string;
}

/**
 * El menú del panel de administración.
 */
export const NAVEGACION: ItemNavegacion[] = [
    { titulo: 'Panel', ruta: 'dashboard', icono: LayoutDashboard, permiso: 'dashboard.ver' },

    // --- El flujo del mostrador, en orden.
    { titulo: 'Beneficiarios', ruta: 'beneficiarios.index', icono: Users, permiso: 'beneficiarios.ver', grupo: 'Ventanilla' },
    { titulo: 'Aprov. Pesquero', tituloCompleto: 'Autorización de Pesca para Aprovechamiento Pesquero', ruta: 'aprovechamientos.index', icono: Waves, permiso: 'aprovechamientos.ver', grupo: 'Ventanilla' },
    { titulo: 'Carnets', ruta: 'carnets.index', icono: BadgeCheck, permiso: 'carnets.ver', grupo: 'Ventanilla' },
    { titulo: 'Faenas', tituloCompleto: 'Permisos de faena', ruta: 'faenas.index', icono: Ship, permiso: 'faenas.ver', grupo: 'Ventanilla' },
    { titulo: 'Guías', tituloCompleto: 'Guías de movimiento', ruta: 'guias.index', icono: Truck, permiso: 'guias.ver', grupo: 'Ventanilla' },

    // --- Lo pagado en Recaudaciones (SIREB): un recibo por documento.
    { titulo: 'Recibos', ruta: 'recibos.index', icono: ReceiptText, permiso: 'recibos.ver', grupo: 'Pagos' },

    // --- Las asociaciones de pescadores, aparte de los catálogos.
    { titulo: 'Asociaciones', ruta: 'asociaciones.index', icono: Building2, permiso: 'catalogos.ver', grupo: 'Parámetros' },

    // --- Lo que sale de una resolución y casi no se toca.
    { titulo: 'Tipos de carnet', ruta: 'tipos-carnet.index', icono: Tags, permiso: 'catalogos.ver', grupo: 'Catálogos' },
    { titulo: 'Productos', tituloCompleto: 'Productos hidrobiológicos', ruta: 'productos.index', icono: Fish, permiso: 'catalogos.ver', grupo: 'Catálogos' },
    { titulo: 'Escala', tituloCompleto: 'Escala de aprovechamiento', ruta: 'categorias-aprovechamiento.index', icono: Ruler, permiso: 'catalogos.ver', grupo: 'Catálogos' },
    { titulo: 'Aranceles', tituloCompleto: 'Aranceles de SIREB', ruta: 'aranceles.index', icono: Coins, permiso: 'catalogos.ver', grupo: 'Catálogos' },

    { titulo: 'Reportes', ruta: 'reportes.index', icono: BarChart3, permiso: 'reportes.ver', grupo: 'Administración' },
];

/**
 * En qué módulo está parado el usuario, según la URL.
 */
export function moduloActual(ubicacion: string | undefined): ItemNavegacion | undefined {
    if (!ubicacion) {
        return undefined;
    }

    return NAVEGACION.find((item) => ubicacion.includes(item.ruta.split('.')[0] ?? ''));
}

/**
 * El nombre de un ítem fuera de la barra lateral.
 */
export function nombreDe(item: ItemNavegacion): string {
    return item.tituloCompleto ?? item.titulo;
}
