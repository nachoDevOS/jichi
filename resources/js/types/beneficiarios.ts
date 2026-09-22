import type { CupoVigente } from '@/types/carnets';
import type { EstadoAprovechamiento, EstadoCarnet, TipoActor } from '@/types';

/**
 * Tipos del módulo Beneficiarios.
 */

/**
 * Una fila de la tabla del padrón.
 */
export interface BeneficiarioFila {
    id: number;

    // --- Columna «Beneficiario»
    foto_url: string | null;
    nombreCompleto: string;
    /** Ya armado por el modelo: «1234567-1A BN». */
    documento_identidad: string;

    // --- Columna «Datos»
    genero: string | null;
    telefono: string | null;
    fechaNacimiento: string | null;
    /**
     * Años CUMPLIDOS, calculados por el servidor.
     */
    edad: number | null;
}

/**
 * La ficha completa.
 */
export interface BeneficiarioFicha {
    id: number;
    ci: string;
    complemento: string | null;
    /**
     * El departamento donde se expidió la cédula. Es lo ÚNICO que se guarda: el
     * código —«BN»— llega ya armado dentro de `documento_identidad`.
     */
    departamento_id: number | null;
    primerNombre: string;
    segundoNombre: string | null;
    apellidoPaterno: string;
    apellidoMaterno: string | null;
    /** Se guarda SIN el «de»: lo agrega el modelo al armar el nombre. */
    apellidoCasado: string | null;
    nombreCompleto: string;
    documento_identidad: string;
    fechaNacimiento: string | null;
    genero: string | null;
    nacionalidad: string | null;
    direccion: string | null;
    ciudad: string | null;
    provincia: string | null;
    telefono: string | null;
    email: string | null;
    foto_url: string | null;
    registrado: string | null;
}

/** Lo que el formulario de alta y edición manda de vuelta. */
export interface FormularioBeneficiario {
    ci: string;
    complemento: string;
    /** El id del departamento. Vacío cuando no se declaró. */
    departamento_id: number | '';
    primerNombre: string;
    segundoNombre: string;
    apellidoPaterno: string;
    apellidoMaterno: string;
    apellidoCasado: string;
    fechaNacimiento: string;
    genero: string;
    nacionalidad: string;
    direccion: string;
    ciudad: string;
    provincia: string;
    telefono: string;
    email: string;
    /** El archivo elegido. Null mientras no se toque el campo. */
    foto: File | null;
    /** Marca que la foto guardada hay que borrarla. Solo en edición. */
    quitar_foto: boolean;
    /** Inertia lo usa para simular el PUT desde un formulario con archivos. */
    _method?: 'put';
}


/**
 * El resumen de un carnet que muestra la ficha del beneficiario.
 */
export interface CarnetResumen {
    id: number;
    /** En grupos de cuatro, con guion: «EFGT-96R4-CJ42-AHYJ». Se guarda sin separadores. */
    codigo: string;
    /** El nombre del catálogo: «Carnet de Pescador». Es texto, no una regla. */
    tipo: string | null;
    /** La regla: de acá cuelga qué puede emitir y si lleva cupo. */
    tipo_actor: TipoActor;
    tipo_actor_etiqueta: string;
    tipo_actor_color: string;
    /** La que certificó al beneficiario. Se imprime en la tarjeta. */
    asociacion: string | null;
    /**
     * Los kilos impresos en el plástico, o null si es comercializador.
     */
    cupo_kg: number | null;
    estado: EstadoCarnet;
    estado_etiqueta: string;
    estado_color: string;
    vigente: boolean;
    monto: number;
    /** Lo que falta cobrar. Se corta en cero: pagar de más no da saldo a favor. */
    saldo_pendiente: number;
    fecha_emision: string | null;
    fecha_vencimiento: string | null;
}

/**
 * Una BOLSA MADRE de la persona: el cupo anual en kilos.
 */
export interface CupoResumen {
    id: number;
    /** El tramo de la escala oficial: 1 a 7. */
    escala: number | null;
    /** El texto literal de la resolución: «201 Kg Hasta 500 Kg». */
    descripcion: string | null;
    volumen_total_kg: number;
    /** Lo comprometido por las faenas que consumen cupo (todas menos las vencidas). */
    kilos_consumidos: number;
    saldo_kg: number;
    porcentaje_usado: number;
    estado: EstadoAprovechamiento;
    estado_etiqueta: string;
    estado_color: string;
    vigente: boolean;
    /** Si pasó por la firma. En `false` todavía no hay saldo que mostrar. */
    ya_fue_aprobado: boolean;
    saldo_pendiente: number;
    fecha_solicitud: string | null;
    /** El día que lo firmaron. `null` mientras no esté aprobado. */
    fecha_emision: string | null;
    fecha_vencimiento: string | null;
}

/**
 * Un carnet vigente, tal como lo devuelve el autocompletado.
 */
export interface CarnetVigenteSugerido {
    id: number;
    codigo: string;
    tipo: string | null;
    tipo_actor: TipoActor;
    tipo_actor_etiqueta: string;
    /** Exige además una bolsa madre con saldo, no solo que sea de pescador. */
    puede_emitir_faenas: boolean;
    puede_emitir_guias: boolean;

    /**
     * Kilos que quedan en la bolsa madre. Null si el carnet no lleva cupo.
     */
    saldo_kg: number | null;
}

/** Una coincidencia del autocompletado de los formularios de emisión. */
export interface BeneficiarioSugerido {
    id: number;
    nombreCompleto: string;
    documento_identidad: string;
    foto_url: string | null;

    /*
     * El domicilio, que se imprime en el carnet debajo de la asociación.
     *
     * Sale de la FICHA del beneficiario y no del formulario de emisión: son
     * datos del padrón. Si están mal, se corrigen editando a la persona.
     */
    ciudad: string | null;
    provincia: string | null;
    direccion: string | null;

    /**
     * Sus credenciales vigentes. Vacío si no tiene ninguna, y en ese caso lo
     * que corresponde es emitirle una antes de cualquier otra cosa.
     */
    carnets_vigentes: CarnetVigenteSugerido[];

    /**
     * Sus bolsas madre EN CURSO, las que pueden respaldar un carnet nuevo.
     * Solo las mandan las pantallas que las necesitan —emitir un carnet—; en
     * las demás llega `undefined`. Normalmente es una: la regla deja una sola
     * en curso por persona.
     */
    cupos_elegibles?: CupoVigente[];
}
