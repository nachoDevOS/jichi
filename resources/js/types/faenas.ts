import type { EstadoFaena } from '@/types';

/**
 * Tipos del módulo Faenas — el permiso de UNA salida de pesca.
 *
 * Describen, campo por campo, lo que arma
 * App\Http\Controllers\Panel\FaenaController.
 *
 * ============================================================================
 *  TRES BANDERAS LLEGAN RESUELTAS, Y NINGUNA SE DEDUCE EN LA PANTALLA
 * ============================================================================
 *
 *   - `vigente` mira el estado Y la fecha límite. La columna de estado la
 *     escribe un comando diario y entre corrida y corrida miente.
 *   - `consume_cupo` sale del enum: una faena VENCIDA libera su volumen, porque
 *     la salida no ocurrió.
 *   - `puede_completarse` exige que esté EN CURSO. Sobre una vencida no se
 *     puede: al vencer ya devolvió los kilos, y completarla los volvería a
 *     descontar de un cupo que se repuso.
 */

/** Una faena, tal como la pintan el listado y la ficha. */
export interface FaenaFila {
    id: number;
    numero_faena: number;
    /** «Faena N° 0003», armado por el servidor. */
    etiqueta: string;

    carnet_id: number;
    /** En grupos de cuatro: «PES2 6K7R J2M». */
    carnet_codigo: string | null;
    beneficiario_id: number | null;
    beneficiario: string | null;

    /**
     * Los kilos que esta salida compromete contra la bolsa madre.
     *
     * Lo declarado al salir es una previsión; al COMPLETAR se puede corregir
     * contra lo que dijo la balanza.
     */
    kilos_extraidos: number;

    estado: EstadoFaena;
    estado_etiqueta: string;
    estado_color: string;
    vigente: boolean;
    consume_cupo: boolean;
    /** Se pasó de fecha y sigue activa: trabajo sin cerrar, no una previsión. */
    caducada: boolean;
    puede_completarse: boolean;

    /** Un DÍA, no un instante: llega como 'AAAA-MM-DD' y se muestra con fecha(). */
    fecha_salida: string | null;
    fecha_limite: string | null;
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

/** Lo que el formulario de emisión manda de vuelta. */
export interface FormularioFaena {
    carnet_id: number | null;
    numero_faena: number | string;
    kilos_extraidos: number | string;
    fecha_salida: string;
}
