import type { EstadoCarnet, EstadoHabilitacion, EstadoTramite, TipoTramite } from '@/types';

/**
 * Tipos del módulo Trámites.
 *
 * Lo arma App\Http\Controllers\Panel\TramiteController. El método `resumir()`
 * de ese controlador devuelve exactamente `TramiteFila`, y la ficha le suma los
 * campos de `TramiteFicha`.
 */

/** Una fila de la tabla de expedientes. También es la base de la ficha. */
export interface TramiteFila {
    id: number;
    carnet_id: number;
    /** El número de registro impreso en el carnet: 000013. */
    carnet_registro: string | null;
    gestion: number | null;
    beneficiario_id: number | null;
    beneficiario: string | null;
    /** Ya armado por el modelo: «1234567-1A BN». */
    documento_identidad: string | null;
    /** Ya pasada por Archivos::url(): puede ser ruta local o URL de s3. */
    foto_url: string | null;
    rubro: string | null;

    /**
     * Cupo autorizado en kilos. NO se imprime en el carnet: es dato de control
     * interno, para cruzar contra guías de transporte.
     *
     * `null` significa «todavía no se definió», que NO es lo mismo que cero.
     * Por eso viaja como number|null y no como cadena ya formateada.
     */
    capacidad_kg: number | null;

    tipo: TipoTramite;
    tipo_etiqueta: string;
    /** Nombre del color, no las clases: Tailwind no ve las clases armadas. */
    tipo_color: string;

    estado: EstadoTramite;
    estado_etiqueta: string;
    estado_color: string;

    /** Copia congelada de `rubros.costo` al momento de registrar. */
    monto_requerido: number;
    monto_pagado: number;
    saldo_pendiente: number;
    pagado: boolean;

    fecha_solicitud: string | null;

    /**
     * Cuándo se cargó el expediente en el sistema. Lo pone Eloquent y nadie lo
     * elige, a diferencia de `fecha_solicitud`, que es la fecha del papel y se
     * puede escribir hacia atrás. El listado ordena y muestra por este.
     */
    created_at: string | null;

    /**
     * ¿Se puede borrar el expediente? Ya viene resuelto del servidor.
     *
     * Es `true` solo en PENDIENTE, que es el borrador. Desde que se envía ya
     * hay un recibo oficial en manos del beneficiario, y lo que corresponde es
     * rechazarlo con su motivo. La pantalla NO recalcula la regla —ver
     * EstadoTramite::permiteEliminacion()—: escrita en los dos lados, algún día
     * dirían cosas distintas.
     */
    puede_eliminarse: boolean;
}

/**
 * La ficha del expediente.
 *
 * Los campos `puede_*` los calcula el SERVIDOR y no la pantalla. No es un
 * capricho: la regla de qué salto vale desde cada estado vive en
 * App\Enums\EstadoTramite, y escribirla otra vez en React garantiza que algún
 * día las dos versiones digan cosas distintas —y entonces el operador ve un
 * botón que el servidor rechaza, o peor, no ve uno que sí puede usar—.
 */
export interface TramiteFicha extends TramiteFila {
    observaciones: string | null;
    motivo_rechazo: string | null;
    /** Ya pasada por Archivos::url(): puede ser ruta local o URL de s3. */
    ci_file_url: string | null;
    cert_asociacion_file_url: string | null;

    /** Cuándo alguien lo abrió para revisarlo. Null si nadie lo tomó todavía. */
    fecha_revision: string | null;
    fecha_aprobacion: string | null;
    fecha_generacion: string | null;
    fecha_entrega: string | null;

    /** Qué le toca hacer al operador con este expediente. Sale del enum de PHP. */
    que_sigue: string;

    /**
     * ¿Se puede ENVIAR a revisión?
     *
     * Es el paso obligatorio del circuito: `puede_aprobar` no llega en true
     * hasta que el trámite pasó por acá. Ver App\Enums\EstadoTramite.
     */
    puede_enviar: boolean;

    /**
     * Qué le falta al expediente para poder enviarse, ya escrito en castellano
     * de ventanilla: «la fotocopia del carnet de identidad», etc.
     *
     * Vacío significa completo. Viene la LISTA y no un booleano porque con un
     * `puede_enviar` en false la pantalla sabría que no se puede pero no qué ir
     * a buscar.
     */
    faltantes: string[];

    puede_aprobar: boolean;

    /**
     * ¿Se puede rechazar?
     *
     * Solo desde EN REVISIÓN. Un trámite PENDIENTE es un borrador que todavía
     * no presentó nadie: no hay a quién responderle que no. Si no sirve, se
     * elimina —ver `puede_eliminarse`—.
     */
    puede_rechazar: boolean;

    /** ¿Se puede editar? Solo en PENDIENTE. Ver EstadoTramite::permiteEdicion(). */
    puede_editar: boolean;
    puede_generar: boolean;
    puede_entregar: boolean;
    admite_pagos: boolean;
}

/** El carnet sobre el que se apoya el trámite, tal como lo ve la ficha. */
export interface CarnetDelTramite {
    id: number;
    /** El número impreso en el carnet: 000013. */
    registro: string;
    gestion: number;
    estado_etiqueta: string;
    estado_color: string;
    vigente: boolean;
    fecha_vencimiento: string | null;
    /**
     * Si el plástico se puede sacar ya. Lo decide Carnet::puedeImprimirse():
     * el carnet no tiene que estar anulado y tiene que tener al menos un rubro
     * habilitado, cosa que nace al APROBAR el expediente. Por eso el botón de
     * imprimir no aparece antes, aunque el carnet exista desde PENDIENTE.
     */
    puede_imprimirse: boolean;
    /** Los rubros que el carnet YA tiene, para dar contexto al supervisor. */
    rubros: {
        nombre: string;
        estado: EstadoHabilitacion;
        estado_etiqueta: string;
        fecha_habilitacion: string | null;
    }[];
}

/**
 * El RECIBO OFICIAL del expediente.
 *
 * `null` mientras el trámite no pasó por revisión: el recibo nace ahí y no
 * antes, porque ese es el momento en que el pescador entrega los papeles y la
 * plata en el mostrador. Ver App\Services\ReciboTramiteService.
 */
export interface ReciboDelTramite {
    /**
     * El número impreso en el talonario, con sus ceros: «0016».
     *
     * `null` significa «todavía no se emitió, pero YA CORRESPONDE»: es el caso
     * de un expediente que pasó a revisión antes de que existiera este módulo.
     * El recibo se emite al imprimirlo y ahí toma su número.
     */
    numero: string | null;
    fecha_emision: string | null;
    monto: number | null;
}

/** Un depósito aplicado al trámite. */
export interface PagoDelTramite {
    id: number;
    nro_transaccion: string;
    monto: number;
    comprobante_url: string | null;
    fecha_pago: string | null;
    observaciones: string | null;
}

/** Un rubro disponible en el formulario de solicitud. */
export interface RubroOpcion {
    id: number;
    nombre: string;
    descripcion: string | null;
    costo: number;
}

/**
 * Una boleta que el pescador trae el mismo día del alta.
 *
 * Es opcional: puede llegar con una, con dos o con ninguna. Por eso el
 * formulario maneja una lista y no un solo juego de campos.
 */
export interface PagoInicial {
    nro_transaccion: string;
    monto: string;
    comprobante: File | null;
    fecha_pago: string;
    observaciones: string;
}

/** Lo que manda el formulario de alta de solicitud. */
export interface FormularioSolicitud {
    beneficiario_id: string;
    rubro_id: string;
    ciFile: File | null;
    certAsociacionFile: File | null;
    /**
     * A qué asociación pertenece el beneficiario.
     *
     * Va junto al certificado porque es lo que ese papel respalda. Texto libre y
     * no un id: hay decenas de asociaciones, nacen y se disuelven, y ninguna
     * oficina mantiene ese padrón.
     */
    asociacion: string;
    /**
     * Cupo autorizado en kilos. OBLIGATORIO — el servidor rechaza la solicitud
     * sin él.
     *
     * Es `string` y no `number` porque viene de un <input>, y un input vacío da
     * '' —no 0—. Tipar esto como number obligaría a convertir en cada tecla y
     * haría indistinguible «vacío» de «cero», que son cosas distintas: vacío es
     * el campo sin llenar, cero un cupo que no autoriza nada. Los dos se
     * rechazan, pero con mensajes distintos.
     */
    capacidad_kg: string;
    observaciones: string;
    pagos: PagoInicial[];
}

/**
 * Lo que manda el formulario de edición de un expediente abierto.
 *
 * NO lleva rubro ni beneficiario: cambiarlos no sería corregir este expediente
 * sino convertirlo en otro. El porqué está en
 * SolicitudCarnetService::actualizar().
 */
export interface FormularioEdicion {
    ciFile: File | null;
    certAsociacionFile: File | null;
    asociacion: string;
    /**
     * Cupo en kilos. `string` y no `number` porque viene de un <input>, y un
     * input vacío da '' —no 0—. Ver el comentario de FormularioSolicitud.
     */
    capacidad_kg: string;
    observaciones: string;
    _method: 'put';
}

/** Espejo del estado de un carnet, reexportado por comodidad de importación. */
export type { EstadoCarnet };
