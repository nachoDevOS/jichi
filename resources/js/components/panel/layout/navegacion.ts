import {
    BadgeCheck,
    BarChart3,
    FileText,
    LayoutDashboard,
    Receipt,
    Settings,
    Ship,
    Tags,
    Truck,
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
     * El nombre ENTERO, para las migas de pan y para el globito de la barra
     * angosta.
     *
     * Existe porque el renglón de la barra mide 256 px y dentro de un grupo el
     * rótulo se escribe corto: bajo «TRÁMITES» alcanza con «De faena». Pero esa
     * palabra sola no sirve fuera del grupo —una miga que dijera
     * «Inicio / De faena / Nueva faena» no se entiende— así que ahí va
     * «Trámites de faena».
     *
     * Si se omite, se usa `titulo`, que es lo que pasa con los ítems sueltos.
     */
    tituloCompleto?: string;

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
     * LOS GRUPOS RESPONDEN «¿A QUÉ VINO LA PERSONA?», no el abecedario.
     *
     * Ventanilla es a quién se atiende y qué se cobra. Trámites es lo que se
     * viene a pedir. Registro es lo que quedó emitido. Administración se
     * configura una vez y casi no se vuelve a abrir, por eso va al final aunque
     * Rubros sea el catálogo del que dependen los trámites.
     */
    { titulo: 'Beneficiarios', ruta: 'beneficiarios.index', icono: Users, permiso: 'beneficiarios.ver', grupo: 'Ventanilla' },
    { titulo: 'Pagos', ruta: 'pagos.index', icono: Receipt, permiso: 'pagos.ver', grupo: 'Ventanilla' },

    /*
     * ========================================================================
     *  LOS TRES TRÁMITES, JUNTOS Y EN UN SOLO GRUPO
     * ========================================================================
     *
     * Antes «Trámites» era UNA opción más, al lado de Faenas y de Guías, y el
     * rótulo no distinguía nada: sacar un carnet es un trámite, emitir una
     * faena también, y emitir una guía también. La palabra nombra el ACTO, y el
     * acto es el mismo en los tres.
     *
     * La salida fue subirla a TÍTULO DEL GRUPO y que cada opción diga DE QUÉ es
     * el trámite. Así el operador piensa «vengo a hacer un trámite», entra al
     * grupo y elige, en vez de tener que adivinar cuál de las tres opciones era
     * «la de los trámites».
     *
     * ------------------------------------------------------------------------
     *  LO QUE ESTE AGRUPAMIENTO NO MUESTRA
     * ------------------------------------------------------------------------
     *
     * Que los tres NO están al mismo nivel: sin un carnet vigente no se puede
     * emitir ni una faena ni una guía. El menú los pone uno al lado del otro
     * como si se pudiera empezar por cualquiera.
     *
     * Se aceptó a propósito, porque la dependencia se explica sola apenas se
     * entra: los dos formularios abren con un buscador de carnet, y ese buscador
     * solo ofrece carnets vigentes que puedan emitir ese papel. El que no tiene
     * carnet no encuentra a nadie en la lista.
     *
     * ------------------------------------------------------------------------
     *  DOS NOMBRES POR ÍTEM
     * ------------------------------------------------------------------------
     *
     * En la barra van cortos —«De faena»— porque el renglón mide 256 px y el
     * grupo ya puso la palabra «Trámites» arriba. Fuera del grupo esa palabra
     * sola no dice nada, así que las migas y el globito usan `tituloCompleto`.
     */
    { titulo: 'De carnet', tituloCompleto: 'Trámites de carnet', ruta: 'tramites.index', icono: FileText, permiso: 'tramites.ver', grupo: 'Trámites' },
    { titulo: 'De faena', tituloCompleto: 'Trámites de faena', ruta: 'faenas.index', icono: Ship, permiso: 'faenas.ver', grupo: 'Trámites' },
    { titulo: 'De guía', tituloCompleto: 'Trámites de guía', ruta: 'guias.index', icono: Truck, permiso: 'guias.ver', grupo: 'Trámites' },

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

/**
 * El nombre de un ítem fuera de la barra lateral.
 *
 * Dentro de un grupo el rótulo va corto —«De faena», porque arriba ya dice
 * «TRÁMITES»— y esa palabra sola no se entiende en una miga de pan ni en el
 * globito de la barra angosta. Los ítems sueltos no declaran `tituloCompleto` y
 * caen al `titulo`, que ya es el nombre entero.
 *
 * Vive acá y no en cada componente porque lo usan los dos, y escrito dos veces
 * alcanzaría con tocar uno para que la miga y el globito dijeran cosas
 * distintas.
 */
export function nombreDe(item: ItemNavegacion): string {
    return item.tituloCompleto ?? item.titulo;
}
