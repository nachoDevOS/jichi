import type { ControlDePago } from '@/types/pagos';
import type { EstadoCarnet, EstadoTramite, TipoTramite } from '@/types';

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
    /**
     * Cuántos depósitos siguen frenando la aprobación, por estado.
     *
     * Separados porque las dos salidas son distintas: un pendiente se valida, un
     * observado hay que corregirlo antes. Lo calcula
     * `Tramite::pagosPorControlar()`.
     */
    pagos_por_controlar: { pendientes: number; observados: number };
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

    /**
     * ¿Se puede REABRIR? Solo desde RECHAZADO.
     *
     * Es el camino de vuelta: el expediente regresa al borrador con sus
     * depósitos y sus papeles, en vez de obligar a presentar uno nuevo —que
     * nacería con cero cobrado mientras el dinero se queda colgado del
     * rechazado—. Ver `SolicitudCarnetService::reabrir()`.
     */
    puede_reabrir: boolean;

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
     * el carnet no tiene que estar anulado y tiene que tener al menos un trámite
     * APROBADO. Por eso el botón de imprimir no aparece antes, aunque el carnet
     * exista desde PENDIENTE.
     */
    puede_imprimirse: boolean;
    /** La actividad que habilita este carnet, y su cupo autorizado. */
    rubro: string | null;
    capacidad: string | null;
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
     * Es el id del EXPEDIENTE, no una serie propia: la tabla `recibos` se
     * retiró y el comprobante se arma al vuelo, así que no hay dónde guardar un
     * correlativo. Ver App\Support\ReciboArmado::numeroImpreso().
     *
     * Consecuencia a tener presente: la serie tiene huecos, porque no todo
     * trámite emite recibo.
     */
    numero: string;
    /** El día en que se entregó: `tramites.fecha_revision`. */
    fecha_emision: string | null;
    monto: number;
}

/** Un depósito aplicado al trámite. */
export interface PagoDelTramite extends ControlDePago {
    id: number;
    nro_transaccion: string;
    monto: number;
    comprobante_url: string | null;
    fecha_pago: string | null;
    observaciones: string | null;

    /**
     * Si este depósito todavía se puede CORREGIR.
     *
     * Opcional porque solo lo manda «Editar trámite», que es la pantalla donde
     * se corrige: la ficha los muestra para controlarlos, no para tocarlos.
     *
     * Lo decide el servidor —`Pago::admiteCorreccion()`— y dice que no cuando
     * el depósito ya fue validado: alguien firmó con su nombre que cuadraba
     * contra el extracto, y cambiarle el monto después dejaría esa firma puesta
     * sobre otro número.
     */
    puede_corregirse?: boolean;

    /** Por qué no se puede corregir, ya escrito para mostrar. */
    motivo_sin_correccion?: string | null;

    /**
     * Si este depósito se puede QUITAR del expediente. Es OTRA pregunta que
     * `puede_corregirse`, y más estricta.
     *
     * Corregir deja la fila y su historial; quitar la hace desaparecer, y con
     * ella el número de transacción y su boleta. Por eso solo se puede mientras
     * el expediente sea un BORRADOR: una vez enviado salió el recibo oficial, y
     * hacer desaparecer un depósito dejaría ese papel cobrando más de lo que el
     * expediente puede mostrar. Ver `Pago::admiteEliminacion()`.
     *
     * Es solo la mitad: la otra es el permiso `pagos.eliminar`, que se consulta
     * con usePermisos().
     */
    puede_eliminarse?: boolean;

    /** Por qué no se puede quitar, ya escrito para mostrar. */
    motivo_sin_eliminacion?: string | null;
}

/**
 * Lo que manda el formulario de CORRECCIÓN de un depósito.
 *
 * Es el de alta con dos diferencias: la boleta es opcional —se conserva la que
 * está si no se elige otra— y `_method`, que convierte el POST en un PUT del
 * lado de Laravel. Hace falta porque el formulario lleva archivos, y
 * router.put() no los manda.
 */
export interface FormularioCorreccionPago {
    nro_transaccion: string;
    monto: string;
    comprobante: File | null;
    fecha_pago: string;
    _method: 'put';
}

/** Un rubro disponible en el formulario de solicitud. */
export interface RubroOpcion {
    id: number;
    nombre: string;
    descripcion: string | null;
    costo: number;
    /**
     * ¿Esta actividad se autoriza por volumen (kilos)?
     *
     * La pesca sí —el carnet lleva el cupo impreso y se contrasta contra las
     * guías de transporte—; la comercialización no. Sale del catálogo y no de
     * una lista de nombres en React: el mismo rubro figura como «Pescador» o
     * como «Faena» según quién lo cargó.
     *
     * De esto depende que el formulario pida el cupo y que la ficha y el
     * plástico lo muestren. El servidor lo vuelve a comprobar al guardar:
     * esconder un campo es comodidad, no regla.
     */
    requiere_capacidad: boolean;
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
