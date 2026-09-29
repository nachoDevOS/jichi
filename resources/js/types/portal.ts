/**
 * Tipos del portal del beneficiario (/mi-cuenta). Los arma App\Support\ResumenPortal.
 */

/** Lo común a los cuatro documentos, tal como lo ve su titular. */
interface PapelBase {
    tipo: string;
    /** «EFGT-96R4-CJ42-AHYJ», o null si todavía no se emitió. */
    codigo: string | null;
    /** Ruta de /verificar para ese código: la misma página que abre el QR. */
    verificar: string | null;
    /** El PNG del QR del «Pagar» simulado; null si no falta plata. */
    qr_pago: string | null;
    /** El PDF, solo si vale hoy; null si no se imprime. */
    descargar: string | null;
    /** El PDF con «NO VÁLIDO», solo de lo abierto: se ve, no se descarga. */
    vista_previa: string | null;
    estado_etiqueta: string;
    estado_color: string;
    vigente: boolean;
    /** Pendiente o en revisión: todavía no vale. */
    en_tramite: boolean;
    /** Presentado: la Unidad está controlando el depósito. */
    en_revision: boolean;
    /** Qué le falta a un trámite abierto, ya redactado por el servidor. Null si no está abierto. */
    siguiente_paso: string | null;
    /** Dónde está en el circuito, para la línea de avance. Null si no está abierto. */
    etapa: 'pago' | 'envio' | 'revision' | null;
    /** Dónde va en «Mis papeles». Null si está abierto: eso va en «En curso». */
    situacion: 'vigente' | 'vencido' | 'revocado' | null;
    /** Hasta cuándo vale (o valió). La guía trae hora: vale por horas. */
    vence_el: string | null;
    /** Solo de lo vigente: para «faltan N días» y «vence pronto». */
    dias_restantes: number | null;
    /** Por qué ya no vale: revocado, anulado, sin efecto o agotado. */
    motivo_baja: string | null;
    /** Lo que falta depositar, si todavía admite depósitos. */
    debe: number;
}

export interface AprovechamientoPortal extends PapelBase {
    clase: 'aprovechamiento';
    escala: string | null;
    volumen_total_kg: number;
    ya_fue_aprobado: boolean;
    kilos_consumidos: number;
    saldo_kg: number;
    porcentaje_usado: number;
    fecha_solicitud: string | null;
    fecha_vencimiento: string | null;
}

export interface CarnetPortal extends PapelBase {
    clase: 'carnet';
    tipo_actor: 'pescador' | 'comercializador';
    asociacion: string | null;
    cupo_kg: number | null;
    fecha_emision: string | null;
    fecha_vencimiento: string | null;
}

export interface FaenaPortal extends PapelBase {
    clase: 'faena';
    numero: string;
    kilos: number;
    embarcacion: string | null;
    region: string | null;
    fecha_salida: string | null;
    fecha_desembarque: string | null;
}

export interface GuiaPortal extends PapelBase {
    clase: 'guia';
    numero: string;
    ruta: string;
    kilos: number;
    /** Un MOMENTO, con hora: la guía vale por horas. */
    fecha_vencimiento: string | null;
}

export type PapelPortal = AprovechamientoPortal | CarnetPortal | FaenaPortal | GuiaPortal;

/** «Puede tramitar» del inicio (`App\Support\TramitesDisponibles`). */
export interface TramiteDisponible {
    clase: PapelPortal['clase'];
    titulo: string;
    disponible: boolean;
    detalle: string;
}

export interface ReciboPortal {
    numero: string;
    concepto: string;
    monto_total: number;
    depositos: number;
    emitido_en: string | null;
    /** El PDF del recibo, para bajarlo. */
    descargar: string | null;
}

/** Una boleta de depósito y el estado de su control, tal como la ve el titular. */
export interface DepositoPortal {
    nro_transaccion: string;
    /** Un DÍA, el de la boleta. */
    fecha_deposito: string | null;
    monto: number;
    control_etiqueta: string;
    control_color: string;
    /** Por qué se observó; solo en los observados. */
    observacion: string | null;
    /** La boleta que subió (imagen o PDF). */
    comprobante: string | null;
}

/** Un trámite pagado, con todas sus boletas: se puede pagar con una o con varias. */
export interface TramitePagado {
    /** Para qué trámite fue: «Permiso de Faena». */
    concepto: string;
    total: number;
    /** Los recibos que cubren sus boletas. */
    recibos: { numero: string; descargar: string | null }[];
    /** El peor estado de sus boletas. */
    control: 'pendiente' | 'validado' | 'observado';
    control_etiqueta: string;
    control_color: string;
    depositos: DepositoPortal[];
}

export interface DatosPortal {
    nombre: string;
    nombres: string;
    apellido_paterno: string;
    apellido_materno: string | null;
    apellido_casado: string | null;
    documento_identidad: string;
    /** El departamento donde se expidió la cédula, con nombre: «Beni». */
    expedido: string | null;
    foto_url: string | null;
    fecha_nacimiento: string | null;
    edad: number | null;
    genero: string | null;
    nacionalidad: string | null;
    telefono: string | null;
    email: string | null;
    direccion: string | null;
    ciudad: string | null;
    provincia: string | null;
    /** Un MOMENTO: cuándo entró al padrón. */
    registrado: string | null;
}

export interface ActividadPortal {
    actividad: string;
    asociacion: string | null;
    vence: string | null;
}

export interface CuentaPortal {
    creada: string | null;
    ultimo_acceso: string | null;
}
