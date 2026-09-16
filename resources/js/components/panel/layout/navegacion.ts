import {
    BadgeCheck,
    BarChart3,
    FileText,
    LayoutDashboard,
    Receipt,
    Settings,
    Tags,
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
    /**
     * Encabezado de sección bajo el que se agrupa el ítem.
     *
     * Es puramente visual: la barra lateral dibuja el rótulo la primera vez que
     * aparece un grupo nuevo. No se declara una lista de grupos aparte porque
     * entonces habría DOS lugares que mantener y se podrían contradecir —un
     * grupo declarado sin ítems, o un ítem apuntando a un grupo que ya no
     * existe—. Acá el orden de esta lista es el orden de la pantalla.
     *
     * Sin `grupo` el ítem va suelto arriba de todo, antes del primer rótulo.
     */
    grupo?: string;
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

    /*
     * LOS GRUPOS SIGUEN EL RECORRIDO DEL EXPEDIENTE, no el abecedario.
     *
     * Ventanilla es lo que se toca todos los días y en ese orden: llega una
     * persona (beneficiario), presenta una solicitud (trámite) y deposita
     * (pago). Registro es el resultado que queda. Administración es lo que se
     * configura una vez y casi no se vuelve a abrir, por eso va al final aunque
     * Rubros sea el catálogo del que dependen los trámites.
     */
    { titulo: 'Beneficiarios', ruta: 'beneficiarios.index', icono: Users, permiso: 'beneficiarios.ver', grupo: 'Ventanilla' },
    { titulo: 'Trámites', ruta: 'tramites.index', icono: FileText, permiso: 'tramites.ver', grupo: 'Ventanilla' },
    { titulo: 'Pagos', ruta: 'pagos.index', icono: Receipt, permiso: 'pagos.ver', grupo: 'Ventanilla' },

    { titulo: 'Carnets', ruta: 'carnets.index', icono: BadgeCheck, permiso: 'carnets.ver', grupo: 'Registro' },

    { titulo: 'Rubros', ruta: 'rubros.index', icono: Tags, permiso: 'rubros.ver', grupo: 'Administración' },
    { titulo: 'Reportes', ruta: 'reportes.index', icono: BarChart3, permiso: 'reportes.ver', grupo: 'Administración' },
    { titulo: 'Configuración', ruta: 'configuracion.index', icono: Settings, permiso: 'configuracion.gestionar', grupo: 'Administración' },
];

/**
 * En qué módulo está parado el usuario, según la URL.
 *
 * ----------------------------------------------------------------------------
 *  POR QUÉ ESTÁ ACÁ Y NO ADENTRO DE LA BARRA LATERAL
 * ----------------------------------------------------------------------------
 *
 * Lo necesitan DOS componentes: la barra lateral, para saber qué renglón
 * resaltar, y las migas de pan, para escribir «Inicio / Trámites / ...». Si cada
 * uno lo calculara por su cuenta, alcanzaría con que alguien tocara una de las
 * dos copias para que el menú marque un módulo y las migas digan otro —y eso no
 * rompe nada, así que nadie se entera hasta que lo nota un usuario—.
 *
 * Devuelve UNO SOLO, no una lista: con `find`, dos módulos no pueden quedar
 * marcados a la vez. Se compara contra el prefijo del nombre de la ruta
 * ('beneficiarios.index' -> 'beneficiarios') porque una ficha o un formulario
 * —/panel/beneficiarios/7/editar— pertenecen al mismo módulo que el listado y
 * tienen que resaltarlo igual.
 */
export function moduloActual(ubicacion: string | undefined): ItemNavegacion | undefined {
    if (!ubicacion) {
        return undefined;
    }

    return NAVEGACION.find((item) => ubicacion.includes(item.ruta.split('.')[0] ?? ''));
}
