import type { Config as ZiggyConfig } from 'ziggy-js';

/**
 * ============================================================================
 *  TIPOS COMPARTIDOS POR TODO EL SISTEMA
 * ============================================================================
 *
 * Acá va SOLO lo que usan varias pantallas a la vez. Lo que pertenece a un
 * módulo concreto vive en su propio archivo:
 *
 *   types/index.d.ts        <- este archivo: lo común
 *   types/dashboard.ts      <- tipos del panel principal
 *   types/beneficiarios.ts  <- tipos del módulo Beneficiarios
 *   types/tramites.ts       <- tipos del módulo Trámites
 *   types/carnets.ts        <- tipos del módulo Carnets
 *   types/rubros.ts         <- tipos del catálogo de rubros
 *   types/pagos.ts          <- tipos del libro de caja
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
    /** Nombres de rol. Por ahora el sistema tiene uno solo: 'administrador'. */
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
 *   Paginado<BeneficiarioFila>  -> data es BeneficiarioFila[]
 *   Paginado<PagoFila>          -> data es PagoFila[]
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
 *     pendiente ──▶ en_revision ──▶ aprobado
 *         │              │
 *         └──────────────┴─────────▶ rechazado
 *
 * Son CUATRO y no seis: «generado» y «entregado» no son estados sino hechos con
 * fecha, y viven en las columnas `fecha_generacion` y `fecha_entrega`. Un estado
 * obliga a mantener sincronizadas dos cosas que pueden discrepar; una fecha en
 * NULL dice «todavía no pasó» sin posibilidad de contradicción.
 *
 * Qué salto vale desde dónde NO se decide acá: lo dice el enum de PHP, y llega a
 * la pantalla como los campos `puede_*` de la ficha.
 */
export type EstadoTramite = 'pendiente' | 'en_revision' | 'aprobado' | 'rechazado';

/**
 * Espejo de App\Enums\TipoTramite.
 *
 * No lo elige el operador: lo decide el sistema según la persona ya tenga o no
 * carnet DE ESE RUBRO en la gestión en curso.
 */
export type TipoTramite = 'emision_inicial' | 'actualizacion';

/**
 * Espejo de App\Enums\EstadoCarnet.
 *
 * `vencido` lo escribe un comando que corre una vez al día, así que esta
 * columna puede estar desfasada: para saber si un carnet vale HOY, el servidor
 * mira además `fecha_vencimiento`. La pantalla recibe la respuesta ya
 * calculada y no la vuelve a deducir.
 */
export type EstadoCarnet = 'activo' | 'revocado' | 'vencido';

/**
 * Espejo de App\Enums\TipoActor.
 *
 * Es del DOCUMENTO, no de la persona: quien pesca y además comercializa tiene
 * una ficha y dos carnets. De acá cuelga qué puede emitir cada credencial
 * —faenas o guías— y si lleva cupo en kilos.
 */
export type TipoActor = 'pescador' | 'comercializador';

/**
 * Espejo de App\Enums\EstadoAprovechamiento.
 *
 *     PENDIENTE ──[enviar, con el monto cubierto]──▶ EN REVISIÓN
 *     (borrador)                                         │
 *          ▲                              ┌──────────────┴──────────────┐
 *          └──────────[rechazar]──────────┤                             │
 *                                    [aprobar]                          │
 *                                         │                             │
 *                                      ACTIVO ──▶ AGOTADO | VENCIDO ────┘
 *
 * `pendiente` es el BORRADOR: otorgado y sin cobrar del todo. Es el único estado
 * en que el cupo se edita, se elimina y admite depósitos.
 *
 * `en_revision` es el expediente PRESENTADO: la plata está y falta que alguien
 * firme. Tampoco autoriza a pescar — recién lo hace al aprobarse.
 *
 * `vencido` y `agotado` son distintos a propósito: se le acabó el tiempo o se
 * le acabaron los kilos, y al pescador se le explica distinto aunque los dos
 * terminen en un trámite nuevo.
 */
export type EstadoAprovechamiento =
    | 'pendiente'
    | 'en_revision'
    | 'activo'
    | 'vencido'
    | 'agotado';

/**
 * Espejo de App\Enums\ModalidadAprovechamiento.
 *
 * El RÉGIMEN bajo el que se autoriza el cupo:
 *
 *   - `escala_general`   — tramo de la escala progresiva: a más kilos, más valor.
 *   - `especie_especial` — paiche y lo que la resolución sume, con tasación fija.
 *
 * Vive en el TRAMO de la escala —la fija la resolución, no el operador— y se
 * COPIA al cupo al otorgarlo, por lo mismo que el volumen: reclasificar el
 * tramo no puede cambiarle la clasificación a lo ya otorgado.
 */
export type ModalidadAprovechamiento = 'escala_general' | 'especie_especial';

/** Espejo de App\Enums\EstadoFaena. */
export type EstadoFaena = 'activo' | 'completado' | 'vencido';

/** Espejo de App\Enums\EstadoGuia. */
export type EstadoGuia = 'activa' | 'cerrada' | 'anulada';

/**
 * Espejo de App\Enums\EstadoAsociacion.
 *
 * Una asociación NO se borra: se pone inactiva. Los carnets y las guías ya
 * emitidas apuntan a ella, y una asociación inactiva desaparece de los
 * desplegables de alta pero los documentos históricos la siguen mostrando — que
 * es lo correcto, porque la persona pertenecía a ella cuando se le emitió el
 * carnet.
 */
export type EstadoAsociacion = 'activo' | 'inactivo';

/**
 * Espejo de App\Enums\EstadoRubro.
 *
 * ⚠️ Los rubros ya no existen en la base. El tipo queda mientras las pantallas
 * del modelo anterior sigan compilando; se va con ellas.
 */
export type EstadoRubro = 'activo' | 'inactivo';

/**
 * Una opción de catálogo tal como la devuelven los `::opciones()` de los enums
 * de PHP. La usan los selectores de filtro de todos los listados.
 */
export interface OpcionEnum {
    value: string;
    label: string;
    color: string;
}

/* ==========================================================================
   DECLARACIONES GLOBALES
   ========================================================================== */

declare global {
    /**
     * route() convierte el nombre de una ruta de Laravel en su URL:
     *
     *   route('beneficiarios.show', 42)  ->  '/panel/beneficiarios/42'
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
