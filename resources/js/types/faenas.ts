import type { EstadoFaena } from '@/types';
import type { VentaSireb } from '@/types/aprovechamientos';

/**
 * Tipos del módulo Faenas — el permiso de UNA salida de pesca.
 */

/** Una faena, tal como la pintan el listado y la ficha. */
export interface FaenaFila {
    id: number;
    /** Correlativo GLOBAL y continuo del talonario. Lo genera el sistema. */
    numero_faena: number;
    /** El mismo, con los seis ceros del papel: «002190». */
    numero_legible: string;
    /** «Faena N° 002190», armado por el servidor. */
    etiqueta: string;
    /** El código de verificación de la faena, en grupos de cuatro. */
    codigo: string | null;

    carnet_id: number;
    /** En grupos de cuatro, con guion: «EFGT-96R4-CJ42-AHYJ». */
    carnet_codigo: string | null;
    /** El número del libro, con cinco dígitos: «00001». */
    carnet_registro: string | null;
    beneficiario_id: number | null;
    beneficiario: string | null;
    documento: string | null;
    foto_url: string | null;

    /**
     * Los kilos que esta salida compromete contra la bolsa madre.
     *
     * Lo declarado al salir es una previsión; al COMPLETAR se puede corregir
     * contra lo que dijo la balanza.
     */
    kilos_extraidos: number;

    /**
     * Los renglones del talonario de papel. Null porque el formulario se llena
     * a mano y llega incompleto: no hay padrón de embarcaciones ni de
     * comandantes, así que son texto libre.
     */
    embarcacion: string | null;
    propietario: string | null;
    comandante_barco: string | null;
    matricula_naval: string | null;
    nro_kardex: string | null;
    /** La región amparada: «desde» y «hasta» del papel. */
    region_desde: string | null;
    region_hasta: string | null;

    estado: EstadoFaena;
    estado_etiqueta: string;
    estado_color: string;
    vigente: boolean;
    consume_cupo: boolean;
    /** Se pasó de fecha y sigue activa: trabajo sin cerrar, no una previsión. */
    caducada: boolean;
    puede_editarse: boolean;
    puede_eliminarse: boolean;
    /** Por qué todavía no autoriza a salir. `null` cuando sí autoriza. */
    motivo_sin_autorizar: string | null;
    /** Si pasó por la firma. Hasta entonces el permiso no vale. */
    ya_fue_aprobada: boolean;
    /** Firmada y no revocada: recién ahí se imprime el permiso. */
    puede_imprimirse: boolean;

    /** El arancel de la salida. */
    monto: number;
    /** El cobro está en SIREB: su liquidación, y si se puede preguntar por el pago. */
    sireb: VentaSireb | null;
    puede_verificar_pago: boolean;

    /** El recibo. Existe desde que SIREB confirmó el pago; null mientras está pendiente. */
    recibo_id: number | null;
    recibo_numero: string | null;

    /** Cuándo se cargó la fila. Un MOMENTO: se muestra con fechaHora() y hace(). */
    registrado_en: string | null;

    /** Un DÍA, no un instante: llega como 'AAAA-MM-DD' y se muestra con fecha(). */
    fecha_solicitud: string | null;
    /** Las dos las escribe la aprobación: sale el día de la firma, vuelve a los 30 días. */
    fecha_salida: string | null;
    fecha_desembarque: string | null;
}

/** La faena con el detalle que solo pinta la ficha. */
export interface FaenaFicha extends FaenaFila {
    asociacion: string | null;


    /**
     * El cupo del que salieron los kilos.
     *
     * Va en la ficha porque es la pregunta que sigue: «¿le queda para otra
     * salida?». Sin esto habría que ir al módulo de cupos a buscarlo.
     */
    cupo: {
        id: number;
        escala: number | null;
        volumen_total_kg: number;
        saldo_kg: number;
        porcentaje_usado: number;
    } | null;
}

/**
 * Lo que necesita el formulario de CORRECCIÓN. El titular y el carnet vienen
 * fijos, para mostrar: ver EmitirFaenaService::editar().
 */
export type FaenaEnCorreccion = {
    id: number;
    numero_legible: string;
    beneficiario: string | null;
    documento: string | null;
    foto_url: string | null;
    carnet_codigo: string | null;
    /** El número del libro, con cinco dígitos: «00001». */
    carnet_registro: string | null;
    /** Con los kilos propios sumados de vuelta solo si esta faena descontaba. */
    saldo_kg: number | null;
    /** Lo libre más lo que esta faena ya reservaba: lo que puede pedir en modo estricto. */
    libre_kg: number | null;
    kilos_extraidos: number;
    embarcacion: string | null;
    propietario: string | null;
    comandante_barco: string | null;
    matricula_naval: string | null;
    nro_kardex: string | null;
    region_desde: string | null;
    region_hasta: string | null;
};
