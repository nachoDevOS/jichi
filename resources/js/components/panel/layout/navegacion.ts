import {
    BadgeCheck,
    BarChart3,
    Building2,
    LayoutDashboard,
    ReceiptText,
    Ruler,
    Settings,
    Ship,
    Tags,
    Truck,
    Users,
    Wallet,
    Waves,
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
     * rótulo se escribe corto: bajo «CATÁLOGOS» alcanza con «Escala». Pero esa
     * palabra sola no sirve fuera del grupo —una miga que dijera
     * «Inicio / Escala / Editar» no se entiende— así que ahí va «Escala de
     * aprovechamiento».
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
 *
 * ============================================================================
 *  EL ORDEN ES EL DEL FLUJO DE TRABAJO, NO EL ABECEDARIO
 * ============================================================================
 *
 * VENTANILLA está en el orden EXACTO en que ocurren las cosas en el mostrador,
 * y esa es toda la idea del menú:
 *
 *     1. Beneficiarios  la persona se registra UNA vez
 *     2. Cupos de pesca se le asigna la bolsa madre según la escala oficial
 *     3. Carnets        se emite la credencial (y recién acá se puede imprimir)
 *     4. Faenas         cada salida, que descuenta kilos del cupo
 *     5. Guías          cada traslado del comercializador
 *
 * Leído de arriba hacia abajo, el menú ES el procedimiento. Un operador nuevo
 * no tiene que aprenderse el orden: lo tiene delante.
 *
 * ----------------------------------------------------------------------------
 *  LO QUE ESTE ORDEN NO MUESTRA, Y SE ACEPTÓ A PROPÓSITO
 * ----------------------------------------------------------------------------
 *
 * Que los pasos 4 y 5 se BIFURCAN: las faenas son del pescador y las guías del
 * comercializador, y nadie recorre los cinco renglones seguidos. Poner dos
 * grupos —«Pescador» y «Comercializador»— lo mostraría, pero obligaría a
 * repetir Carnets en los dos, porque la credencial es la misma tabla y la misma
 * pantalla.
 *
 * Se eligió la lista única porque la bifurcación se explica sola apenas se
 * entra: los dos formularios abren con un buscador de carnet que solo ofrece
 * los que pueden emitir ESE papel —lo dice `tipo_actor`—, así que quien tiene
 * carnet de pescador no encuentra a nadie en la lista de guías.
 *
 * ----------------------------------------------------------------------------
 *  POR QUÉ CAJA VA DESPUÉS Y NO INTERCALADA
 * ----------------------------------------------------------------------------
 *
 * Porque el cobro NO es un paso del flujo: es algo que puede pasar en
 * cualquiera de ellos y varias veces. Un carnet, un cupo o una guía se pagan en
 * cuotas, y un mismo recibo puede cubrir dos trámites distintos. Metido como
 * «paso 6» daría a entender que se cobra al final, que es justamente lo que el
 * pago fraccionado contradice.
 *
 * ----------------------------------------------------------------------------
 *  Y POR QUÉ CATÁLOGOS VA AL FINAL SI EL FLUJO EMPIEZA POR AHÍ
 * ----------------------------------------------------------------------------
 *
 * La escala de aprovechamiento es el paso 2 del diagrama —de ella sale el cupo
 * y el precio— así que por dependencia debería ir primero. Va al final porque
 * el menú se ordena por lo que se HACE, no por lo que se necesita: los tres
 * catálogos se cargan una vez, cuando sale la resolución, y no se vuelven a
 * abrir en meses. Arriba le robarían el primer lugar al trabajo diario.
 */
export const NAVEGACION: ItemNavegacion[] = [
    { titulo: 'Panel', ruta: 'dashboard', icono: LayoutDashboard, permiso: 'dashboard.ver' },

    // --- El flujo del mostrador, en orden.
    { titulo: 'Beneficiarios', ruta: 'beneficiarios.index', icono: Users, permiso: 'beneficiarios.ver', grupo: 'Ventanilla' },
    { titulo: 'Aprov. Pesquero', tituloCompleto: 'Cupos de pesca (aprovechamientos)', ruta: 'aprovechamientos.index', icono: Waves, permiso: 'aprovechamientos.ver', grupo: 'Ventanilla' },
    { titulo: 'Carnets', ruta: 'carnets.index', icono: BadgeCheck, permiso: 'carnets.ver', grupo: 'Ventanilla' },
    { titulo: 'Faenas', tituloCompleto: 'Permisos de faena', ruta: 'faenas.index', icono: Ship, permiso: 'faenas.ver', grupo: 'Ventanilla' },
    { titulo: 'Guías', tituloCompleto: 'Guías de movimiento', ruta: 'guias.index', icono: Truck, permiso: 'guias.ver', grupo: 'Ventanilla' },

    // --- El dinero, que atraviesa todo lo anterior.
    { titulo: 'Cobros', ruta: 'caja.index', icono: Wallet, permiso: 'caja.ver', grupo: 'Caja' },
    { titulo: 'Recibos', ruta: 'recibos.index', icono: ReceiptText, permiso: 'caja.ver', grupo: 'Caja' },

    // --- Lo que sale de una resolución y casi no se toca.
    { titulo: 'Asociaciones', ruta: 'asociaciones.index', icono: Building2, permiso: 'catalogos.ver', grupo: 'Catálogos' },
    { titulo: 'Escala', tituloCompleto: 'Escala de aprovechamiento', ruta: 'categorias-aprovechamiento.index', icono: Ruler, permiso: 'catalogos.ver', grupo: 'Catálogos' },
    { titulo: 'Tipos de carnet', ruta: 'tipos-carnet.index', icono: Tags, permiso: 'catalogos.ver', grupo: 'Catálogos' },

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
 * resaltar, y las migas de pan, para escribir «Inicio / Carnets / ...». Si cada
 * uno lo calculara por su cuenta, alcanzaría con que alguien tocara una de las
 * dos copias para que el menú marque un módulo y las migas digan otro —y eso no
 * rompe nada, así que nadie se entera hasta que lo nota un usuario—.
 *
 * Devuelve UNO SOLO, no una lista: con `find`, dos módulos no pueden quedar
 * marcados a la vez. Se compara contra el prefijo del nombre de la ruta
 * ('beneficiarios.index' -> 'beneficiarios') porque una ficha o un formulario
 * —/panel/beneficiarios/7/editar— pertenecen al mismo módulo que el listado y
 * tienen que resaltarlo igual.
 *
 * OJO CON LOS PREFIJOS QUE SE CONTIENEN ENTRE SÍ. Devuelve el PRIMERO que
 * coincide, así que el orden de NAVEGACION decide los empates. Hoy no hay
 * ninguno —'tipos-carnet' no contiene 'carnets', y 'categorias-aprovechamiento'
 * no contiene 'aprovechamientos'— pero es lo que hay que revisar al agregar un
 * módulo con nombre parecido a otro.
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
 * Dentro de un grupo el rótulo va corto —«Escala», porque arriba ya dice
 * «CATÁLOGOS»— y esa palabra sola no se entiende en una miga de pan ni en el
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
