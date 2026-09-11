import type { Config as ZiggyConfig } from 'ziggy-js';

/**
 * ============================================================================
 *  TIPOS COMPARTIDOS POR TODO EL SISTEMA
 * ============================================================================
 *
 * Acá va SOLO lo que usan varias pantallas a la vez. Lo que pertenece a un
 * módulo concreto vive en su propio archivo:
 *
 *   types/index.d.ts       <- este archivo: lo común
 *   types/dashboard.ts     <- tipos del panel principal
 *   types/solicitantes.ts  <- tipos del módulo Solicitantes
 *
 * ¿Para qué sirven los tipos? Describen la forma exacta de los datos que
 * manda Laravel. Si en PHP se renombra una clave y acá no, el editor lo marca
 * en rojo al instante, en vez de descubrirlo con una pantalla en blanco.
 */

/* ==========================================================================
   PROPS COMPARTIDAS — llegan en TODAS las páginas
   ========================================================================== */

/**
 * El usuario que tiene la sesión abierta.
 * Lo arma App\Http\Middleware\HandleInertiaRequests.
 */
export interface Usuario {
    id: number;
    name: string;
    email: string;
    cargo: string | null;
    /** Nombres de rol: 'administrador', 'supervisor', 'operador', 'solo_lectura'. */
    roles: string[];
    /** Permisos ya expandidos: 'solicitantes.crear', 'pagos.registrar'... */
    permisos: string[];
}

/** Datos institucionales que se leen de la tabla `configuraciones`. */
export interface Institucion {
    sistema: string;
    municipio: string | null;
    sigla: string | null;
    /** Símbolo de la moneda: 'Bs'. */
    moneda: string;
}

/**
 * Qué archivos acepta el sistema y hasta cuánto pesan.
 *
 * Sale de `config/jichi.php`, no de un número escrito en React: es el mismo que
 * usan las reglas de validación del servidor. Se lee con el hook
 * `useArchivos()`.
 */
export interface LimitesArchivo {
    /** Kilobytes de 1024 bytes, como los cuenta la regla `max` de Laravel. */
    max_kb: number;
    /** Tipos MIME aceptados, listos para el atributo `accept`. */
    mimes: string;
    /** Los mismos, pero solo imagen: las fotos se imprimen y un PDF no sirve. */
    mimes_imagen: string;
}

/**
 * Mensajes de una sola vez, los que en Laravel se mandan con
 * ->with('exito', '...'). El hook useFlash() los convierte en avisos flotantes.
 */
export interface Flash {
    exito: string | null;
    error: string | null;
    info: string | null;
}

/**
 * TODO lo que llega a cualquier página.
 *
 * Se accede con usePage<PageProps>().props desde cualquier componente, sin
 * tener que ir pasando las props de padre a hijo.
 */
export interface PageProps {
    auth: { user: Usuario | null };
    institucion: Institucion;
    archivos: LimitesArchivo;
    flash: Flash;
    apariencia: 'light' | 'dark' | 'system';
    /** Lista de rutas de Laravel que Ziggy publica para poder usar route(). */
    ziggy: ZiggyConfig & { location: string };
    // Inertia exige que las props de página sean indexables por clave.
    [key: string]: unknown;
}

/* ==========================================================================
   PAGINACIÓN
   ========================================================================== */

/**
 * Lo que devuelve ->paginate() de Laravel, ya convertido a JSON.
 *
 * <T> es un "genérico": el tipo de las filas se indica al usarlo.
 *
 *   Paginado<SolicitanteFila>  -> data es SolicitanteFila[]
 *   Paginado<PagoFila>         -> data es PagoFila[]
 *
 * Así el componente de paginación sirve para cualquier listado sin perder la
 * verificación de tipos de las filas.
 */
export interface Paginado<T> {
    data: T[];
    /** Botones ya calculados por Laravel: anterior, números, siguiente. */
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    /** Primer y último número de fila de esta página. Null si está vacía. */
    from: number | null;
    to: number | null;
}

/* ==========================================================================
   ESTADOS DEL DOMINIO

   Copian exactamente los enums de PHP en app/Enums/. Si allá se agrega un
   estado nuevo, hay que agregarlo acá también: son las dos mitades de la
   misma definición.
   ========================================================================== */

/**
 * Espejo de App\Enums\EstadoTramite.
 *
 * Son cuatro y no seis: «recibido» y «emitido» se retiraron. Todo trámite nace
 * en revisión, y que el documento esté impreso se sabe mirando si existe el
 * documento, no por el estado del trámite.
 */
export type EstadoTramite = 'en_revision' | 'aprobado' | 'entregado' | 'rechazado';

/** Espejo de App\Enums\EstadoDocumento. */
export type EstadoDocumento = 'vigente' | 'vencido' | 'anulado';

/** Espejo de App\Enums\FormaPago. */
export type FormaPago = 'efectivo' | 'qr' | 'transferencia';

/* ==========================================================================
   DECLARACIONES GLOBALES
   ========================================================================== */

declare global {
    /**
     * route() convierte el nombre de una ruta de Laravel en su URL:
     *
     *   route('solicitantes.show', 42)  ->  '/solicitantes/42'
     *
     * No hace falta importarla: la inyecta la directiva @routes de Ziggy en
     * resources/views/app.blade.php, y está disponible en cualquier archivo.
     */
    const route: typeof import('ziggy-js').route;

    interface Window {
        route: typeof import('ziggy-js').route;
    }
}

// Le enseña a Inertia la forma de nuestras props compartidas, para que
// usePage() devuelva los tipos correctos sin anotarlo en cada llamada.
declare module '@inertiajs/core' {
    interface PageProps extends import('./index').PageProps {}
}
