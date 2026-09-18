/**
 * Tipos del módulo Faenas — el permiso por salida de pesca.
 *
 * Lo arma App\Http\Controllers\Panel\FaenaController.
 */

/**
 * Espejo de App\Enums\EstadoPermiso.
 *
 * Lo comparten faenas y guías, igual que el enum de PHP: su ciclo de vida es
 * idéntico y dos tipos iguales se separan con el tiempo sin que nadie lo decida.
 */
export type EstadoPermiso = 'emitido' | 'anulado';

/** El carnet tal como lo devuelve el autocompletado del formulario. */
export interface CarnetElegible {
    id: number;
    /** El número impreso en el plástico: 000013. */
    registro: string;
    gestion: number;
    rubro: string | null;
    beneficiario: string | null;
    documento_identidad: string | null;
    /** El cupo anual del carnet, ya escrito: «600 KG». No es el de la faena. */
    capacidad: string | null;
}

/** Una fila del listado de faenas. También es la base de la ficha. */
export interface FaenaFila {
    id: number;
    /** El número del talonario de papel. Lo tipea el operador. */
    nro_permiso: string;
    estado: EstadoPermiso;
    estado_etiqueta: string;
    estado_color: string;
    embarcacion: string | null;
    comandante_barco: string | null;
    /** Las dos fechas definen la VENTANA del permiso. */
    fecha_salida: string | null;
    fecha_desembarque: string | null;
    /** El tope de ESTA salida, en kilos. No es el cupo anual del carnet. */
    cantidad_autorizada_kg: number;
    /** El mismo dato ya escrito: «450 KG». */
    cantidad: string | null;
    monto: number;
    monto_pagado: number;
    saldo: number;
    pagada: boolean;
    carnet_id: number | null;
    carnet_registro: string | null;
    beneficiario: string | null;
}

/** La ficha de una faena: la fila más todo lo que solo se mira de a una. */
export interface FaenaFicha extends FaenaFila {
    propietario: string | null;
    matricula_naval: string | null;
    nro_kardex: string | null;
    nro_recibo: string | null;
    region_desde: string | null;
    region_hasta: string | null;
    observaciones: string | null;
    /** Contando los dos extremos: salir y desembarcar el mismo día es 1. */
    dias_autorizados: number | null;
    /**
     * Si autoriza a pescar HOY. Lo decide el servidor —Faena::estaVigente()— y
     * no esta pantalla: mira el estado, la ventana de fechas Y el carnet, y esa
     * última condición es la que se olvida al reescribirla en React.
     */
    vigente: boolean;
    puede_anularse: boolean;
}

/** El carnet del que cuelga la faena, en la ficha. */
export interface CarnetDeFaena {
    id: number | null;
    registro: string | null;
    rubro: string | null;
    gestion: number | null;
    vigente: boolean;
}

/** Un depósito aplicado a la faena. */
export interface PagoDePermiso {
    id: number;
    nro_transaccion: string;
    monto: number;
    fecha_pago: string | null;
    comprobante_url: string | null;
}

/**
 * Lo que manda el formulario de emisión.
 *
 * Los números van como texto porque el <input> devuelve texto; la conversión la
 * hace Laravel al validar con la regla `numeric`.
 */
export interface FormularioFaena {
    carnet_id: number | null;
    nro_permiso: string;
    nro_recibo: string;
    monto: string;
    embarcacion: string;
    propietario: string;
    comandante_barco: string;
    matricula_naval: string;
    nro_kardex: string;
    region_desde: string;
    region_hasta: string;
    fecha_salida: string;
    fecha_desembarque: string;
    cantidad_autorizada_kg: string;
    observaciones: string;
}
