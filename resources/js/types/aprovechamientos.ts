import type { EstadoAprovechamiento, EstadoFaena, ModalidadAprovechamiento } from '@/types';

/**
 * Tipos del módulo Aprovechamientos — la BOLSA MADRE del pescador.
 *
 * Describen, campo por campo, lo que arma
 * App\Http\Controllers\Panel\AprovechamientoController. Si allá se renombra una
 * clave y acá no, el editor lo marca en rojo al instante en vez de descubrirlo
 * con una pantalla en blanco.
 *
 * ============================================================================
 *  TODO LO CALCULADO LLEGA RESUELTO DEL SERVIDOR
 * ============================================================================
 *
 * `saldo_kg`, `vigente`, `puede_emitir_faena`, `saldo_pendiente`: ninguno se
 * deduce en la pantalla, y no es comodidad. Son REGLAS:
 *
 *   - `vigente` mira el estado Y la fecha, porque la columna de estado la
 *     escribe un comando que corre una vez al día y entre corrida y corrida
 *     miente.
 *   - `saldo_kg` se corta en cero: un cupo excedido no es un saldo negativo.
 *   - `saldo_pendiente` también, porque pagar de más no genera saldo a favor.
 *
 * Deducirlas en React sería una segunda copia de cada una, y las copias se
 * desincronizan sin que nada falle.
 */

/** Un cupo, tal como lo pintan el listado y la ficha. */
export interface CupoFila {
    id: number;
    beneficiario_id: number;
    beneficiario: string | null;
    documento: string | null;
    /** Puede faltar: mucha gente del padrón todavía no tiene foto cargada. */
    foto_url: string | null;

    /** El tramo de la escala bajo el que se otorgó: 1 a 7. */
    escala: number | null;
    /** El texto literal de la resolución: «201 Kg Hasta 500 Kg». */
    descripcion: string | null;

    /**
     * Los kilos OTORGADOS, copiados del techo del tramo al otorgar.
     *
     * Están congelados a propósito: la escala cambia por resolución, y un cupo
     * dado en marzo bajo un tramo de 500 kg no puede pasar a valer 800 porque
     * alguien editó el catálogo. La única cosa que los mueve es una AMPLIACIÓN.
     */
    volumen_total_kg: number;

    /**
     * Lo que el pescador declaró que navega: «canoa», «peque-peque», «bote»…
     *
     * Es el renglón «Tipo de Embarcación» del talonario verde, y va en texto
     * libre porque no hay padrón de embarcaciones ni nomenclatura fija. NULL
     * cuando no se declaró —que el papel también admite—, y por eso la pantalla
     * distingue «no declarada» de una cadena vacía.
     */
    tipo_embarcacion: string | null;

    /** Lo comprometido por las faenas que consumen cupo (todas menos las vencidas). */
    kilos_consumidos: number;
    saldo_kg: number;
    porcentaje_usado: number;

    /**
     * El régimen, COPIADO del tramo al otorgar.
     *
     * No se lee de la escala en vivo a propósito: reclasificar un tramo en el
     * catálogo no puede cambiarle la clasificación a un cupo ya otorgado.
     */
    modalidad: ModalidadAprovechamiento;
    modalidad_etiqueta: string;
    modalidad_color: string;

    estado: EstadoAprovechamiento;
    estado_etiqueta: string;
    estado_color: string;
    vigente: boolean;

    /**
     * Los kilos que se PASARON del volumen otorgado.
     *
     * En modo estricto siempre es 0 —la emisión no deja pasar una faena que no
     * entre—, así que solo aparece en pantalla cuando hay algo que mostrar.
     * `saldo_kg` no puede decirlo: se corta en cero.
     */
    kilos_excedidos: number;
    excedido: boolean;
    /** Vigente, con saldo y sin agotar: las tres condiciones juntas. */
    puede_emitir_faena: boolean;
    /**
     * Si todavía se puede corregir, y si se puede borrar la fila entera.
     *
     * NO son «el estado es pendiente»: son eso Y que no haya entrado plata —y
     * para eliminar, además, que no tenga faenas emitidas—. Llegan resueltas
     * del servidor porque deducirlas acá sería una segunda copia de tres reglas.
     */
    puede_editarse: boolean;
    puede_eliminarse: boolean;

    /**
     * Las tres del circuito de revisión, resueltas en el servidor.
     *
     * `puede_enviarse` NO es «el estado es pendiente»: es eso Y que los
     * depósitos cubran el monto entero. Deducirlo acá sería una segunda copia
     * de la regla, y con un saldo que la pantalla puede tener viejo.
     */
    admite_pagos: boolean;
    puede_enviarse: boolean;
    puede_revisarse: boolean;

    monto: number;
    saldo_pendiente: number;
    pagado: boolean;

    /** Un DÍA, no un instante: llega como 'AAAA-MM-DD' y se muestra con fecha(). */
    fecha_emision: string | null;
    fecha_vencimiento: string | null;
}

/** El cupo con el detalle que solo pinta la ficha. */
export interface CupoFicha extends CupoFila {
    escala_descripcion: string | null;
    /** [piso, techo] del tramo, para contrastarlo con lo otorgado. */
    escala_rango: [number, number] | null;
}

/**
 * Un depósito que pagó este cupo, en la ficha.
 *
 * Son VARIOS a propósito: un cupo se puede pagar en cuotas, y cada depósito
 * bancario llega con su propia boleta. No hay efectivo ni QR, así que las tres
 * columnas de la boleta están siempre.
 */
export interface PagoDelCupo {
    id: number;
    monto_parcial: number;
    nro_transaccion: string;
    comprobante_url: string | null;
    /** Un DÍA —lo que dice la boleta—: se muestra con fecha(). */
    fecha_deposito: string | null;
    numero_recibo: string | null;
    recibo_id: number;
    /** Un MOMENTO —cuándo entró la plata—: se muestra con fechaHora(). */
    cobrado_en: string | null;
}

/** Una faena colgada del cupo, en la ficha. */
export interface FaenaDelCupo {
    id: number;
    numero_faena: number;
    kilos_extraidos: number;
    estado: EstadoFaena;
    estado_etiqueta: string;
    estado_color: string;
    /**
     * Si sus kilos pesan contra el saldo.
     *
     * Una faena VENCIDA libera su volumen —la salida no ocurrió— así que la
     * pantalla la marca aparte: sin eso, la suma de la lista no cuadra con el
     * saldo y parece un error del sistema.
     */
    consume_cupo: boolean;
    fecha_salida: string | null;
    fecha_limite: string | null;
}

/** Un tramo elegible en el formulario de otorgamiento. */
export interface TramoElegible {
    id: number;
    nro_escala: number;
    descripcion_kg: string;
    kilos_min: number;
    /**
     * El techo del rango, que es EL VOLUMEN QUE SE VA A OTORGAR.
     *
     * La escala dice «201 kg Hasta 500 Kg»: lo que se autoriza es el máximo, no
     * un número que el operador elija adentro. Por eso el formulario lo muestra
     * al elegir el tramo: las dos consecuencias —kilos y precio— se ven antes
     * de guardar, no después.
     */
    kilos_max: number;
    valor_bs: number;
    /** El régimen del tramo, visible antes de otorgar. */
    modalidad: ModalidadAprovechamiento;
    modalidad_etiqueta: string;
    modalidad_descripcion: string;
}

/** Lo que el formulario de otorgamiento manda de vuelta. */
export interface FormularioCupo {
    beneficiario_id: number | null;
    categoria_aprov_id: number | string;
    fecha_emision: string;
    /** Opcional: el renglón del talonario tampoco es obligatorio. */
    tipo_embarcacion: string;
}
