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
    /** Con este código se paga en SIREB. Null si todavía no se registró allá. */
    codigo_pago: string | null;
    /** El PDF, solo si vale hoy; null si no se imprime. */
    descargar: string | null;
    /** El PDF con «NO VÁLIDO», solo de lo abierto: se ve, no se descarga. */
    vista_previa: string | null;
    estado_etiqueta: string;
    estado_color: string;
    vigente: boolean;
    /** Pendiente de pago: todavía no vale. */
    en_tramite: boolean;
    /** Qué le falta a un trámite abierto, ya redactado por el servidor. Null si no está abierto. */
    siguiente_paso: string | null;
    /** Dónde está en el circuito, para la línea de avance. Null si no está abierto. */
    etapa: 'pago' | null;
    /** Dónde va en «Mis papeles». Null si está abierto: eso va en «En curso». */
    situacion: 'vigente' | 'vencido' | 'revocado' | null;
    /** Hasta cuándo vale (o valió). La guía trae hora: vale por horas. */
    vence_el: string | null;
    /** Solo de lo vigente: para «faltan N días» y «vence pronto». */
    dias_restantes: number | null;
    /** Por qué ya no vale: revocado, anulado, sin efecto o agotado. */
    motivo_baja: string | null;
    /** Lo que falta pagar en SIREB. */
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
    numero_boleta: string | null;
    entidad_bancaria: string | null;
    /** Un DÍA, el de la boleta. */
    fecha_pago: string | null;
    emitido_en: string | null;
    /** El PDF del recibo, para bajarlo. */
    descargar: string | null;
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
