import type { EstadoCarnet, TipoActor } from '@/types';

/**
 * Tipos del módulo Carnets — la credencial anual.
 *
 * Describen, campo por campo, lo que arma
 * App\Http\Controllers\Panel\CarnetController.
 *
 * ============================================================================
 *  LAS TRES BANDERAS LLEGAN RESUELTAS, Y NINGUNA SE DEDUCE EN LA PANTALLA
 * ============================================================================
 *
 *   - `vigente` mira el estado Y la fecha, porque la columna de estado la
 *     escribe un comando diario y entre corrida y corrida miente.
 *   - `cupo_kg` depende del TIPO DE ACTOR, que es un enum del servidor, nunca
 *     del nombre del tipo de carnet — ese nombre es un catálogo editable.
 *   - `puede_emitir_faenas` exige además una bolsa madre con saldo.
 *
 * Un `if` sobre el nombre del tipo en React sería una segunda copia de esas
 * reglas, y se rompería en silencio en cuanto alguien renombre una fila.
 */

/** Un carnet, tal como lo pintan el listado y la ficha. */
export interface CarnetFila {
    id: number;
    beneficiario_id: number;
    beneficiario: string | null;

    /** En grupos de cuatro: «PES2 6K7R J2M». Se guarda sin separadores. */
    codigo: string;

    /** El nombre del catálogo: «Carnet de Pescador». Es texto, no una regla. */
    tipo: string | null;
    /** La regla: de acá cuelga qué puede emitir y si lleva cupo. */
    tipo_actor: TipoActor;
    tipo_actor_etiqueta: string;
    tipo_actor_color: string;
    asociacion: string | null;

    /** Los kilos impresos en el plástico. Null en un comercializador. */
    cupo_kg: number | null;

    estado: EstadoCarnet;
    estado_etiqueta: string;
    estado_color: string;
    vigente: boolean;
    /** Negativo si ya venció. Null si no tiene fecha. */
    dias_para_vencer: number | null;

    puede_emitir_faenas: boolean;
    puede_emitir_guias: boolean;

    monto: number;
    saldo_pendiente: number;
    pagado: boolean;

    /** Un DÍA, no un instante: llega como 'AAAA-MM-DD' y se muestra con fecha(). */
    fecha_emision: string | null;
    fecha_vencimiento: string | null;
}

/** El carnet con el detalle que solo pinta la ficha. */
export interface CarnetFicha extends CarnetFila {
    foto_url: string | null;
    documento_identidad: string | null;
    ciudad: string | null;
    provincia: string | null;
    /** El nombre completo de la asociación; en la tira del carnet va la sigla. */
    asociacion_nombre: string | null;

    /**
     * La bolsa madre que respalda el cupo impreso. Null en un comercializador.
     *
     * Trae el SALDO y no solo el volumen porque es lo que decide si hoy se le
     * puede emitir una faena, que es la pregunta que trae a alguien a esta
     * ficha.
     */
    cupo: {
        id: number;
        volumen_total_kg: number;
        saldo_kg: number;
        porcentaje_usado: number;
        vigente: boolean;
    } | null;
}

/** Una asociación elegible en el formulario de emisión. */
export interface AsociacionElegible {
    id: number;
    nombre: string;
    sigla: string | null;
}

/** Un tipo de carnet elegible, con su arancel. */
export interface TipoElegible {
    id: number;
    nombre: string;
    precio_bs: number;
}

/**
 * El cupo vigente de la persona elegida, si lo tiene.
 *
 * La pantalla lo usa para avisar ANTES de guardar que un carnet de pescador sin
 * cupo va a ser rechazado. El servidor lo comprueba igual dentro de la
 * transacción; esto evita el viaje en falso.
 */
export interface CupoVigente {
    volumen_total_kg: number;
    saldo_kg: number;
}

/** Lo que el formulario de emisión manda de vuelta. */
export interface FormularioCarnet {
    beneficiario_id: number | null;
    asociacion_id: number | string;
    tipo_carnet_id: number | string;
    tipo_actor: TipoActor | '';
    fecha_emision: string;
}
