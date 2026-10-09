import type { Config as ZiggyConfig } from 'ziggy-js';

/**
 *  Tipos compartidos por todo el sistema
 */

// Props compartidas: llegan en todas las páginas.

/**
 * El usuario que tiene la sesión abierta.
 * Lo arma App\Http\Middleware\HandleInertiaRequests.
 */
export interface Usuario {
    id: number;
    name: string;
    /** Null en las cuentas del portal: el beneficiario entra con su C.I. */
    email: string | null;
    /** Cuenta del portal /mi-cuenta: nunca entra al panel. */
    es_beneficiario: boolean;
    /** La foto por defecto del panel (no se cargan fotos). Null en las cuentas del portal. */
    foto_url: string | null;
    /** Nombres de rol; los arma la unidad en Seguridad › Roles. */
    roles: string[];
    /** Permisos ya expandidos: 'beneficiarios.crear', 'pagos.registrar'... */
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
    aviso: string | null;
    error: string | null;
    info: string | null;
    /** La clave temporal del portal, recién creada o reseteada. Llega UNA vez. */
    cuenta_portal: CredencialPortal | null;
}

export interface CredencialPortal {
    nombre: string;
    usuario: string;
    clave: string;
    url: string;
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

// Paginación.

/**
 * Lo que devuelve ->paginate() de Laravel, ya convertido a JSON.
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

// Estados del dominio.

/**
 * Espejo de App\Enums\EstadoCarnet.
 */
export type EstadoCarnet =
    | 'pendiente'
    | 'aprobado'
    | 'revocado';

/**
 * Espejo de App\Enums\TipoActor.
 */
export type TipoActor = 'pescador' | 'comercializador';

/**
 * Espejo de App\Enums\EstadoAprovechamiento.
 */
export type EstadoAprovechamiento =
    | 'pendiente'
    | 'aprobado'
    | 'agotado'
    | 'revocado';

/**
 * Espejo de App\Enums\ModalidadAprovechamiento.
 */
export type ModalidadAprovechamiento = 'escala_general' | 'especie_especial';

/** Espejo de App\Enums\EstadoFaena. */
export type EstadoFaena = 'pendiente' | 'aprobado' | 'revocado';

/** Espejo de App\Enums\EstadoGuia. */
export type EstadoGuia = 'pendiente' | 'aprobado' | 'revocado';

/**
 * Espejo de App\Enums\EstadoAsociacion.
 */
export type EstadoAsociacion = 'activo' | 'inactivo';

/**
 * Una opción de catálogo tal como la devuelven los `::opciones()` de los enums
 * de PHP. La usan los selectores de filtro de todos los listados.
 */
export interface OpcionEnum {
    value: string;
    label: string;
    color: string;
}

// Declaraciones globales.

declare global {
    /**
     * route() convierte el nombre de una ruta de Laravel en su URL:
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
