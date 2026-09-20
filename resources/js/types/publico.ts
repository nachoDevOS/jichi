import type { EstadoCarnet } from '@/types';

/**
 * Tipos de la parte pública (verificación de carnets por QR).
 */

/** Lo que se muestra de un carnet en la pantalla pública. */
export interface CarnetPublico {
    /**
     * El código impreso en el carnet, en grupos de cuatro: «PES2 6000 0017».
     * Es el único dato de esta pantalla que también está en el plástico, así
     * que es lo que el inspector cruza para confirmar que el acta corresponde
     * a la credencial que tiene en la mano.
     */
    codigo: string;
    titular: string | null;
    /** Enmascarado: solo los últimos 3 dígitos ('••••779'). */
    documento_titular: string;
    gestion: number;
    fecha_emision: string | null;
    fecha_vencimiento: string | null;
    estado: EstadoCarnet;
    estado_etiqueta: string;
    estado_color: string;
    /**
     * NO es lo mismo que estado === 'vigente'. Se calcula además contra la
     * fecha, porque el estado lo escribe un comando programado que corre una vez
     * al día. Ver Carnet::estaVigente().
     */
    vigente: boolean;
    /** Frase para el inspector: «Carnet auténtico y vigente...». */
    mensaje: string;
    /**
     * La actividad que el carnet autoriza: «Pescador» o «Comercializador».
     */
    actividad: string | null;
    /** El nombre del catálogo: «Carnet de Pescador». Null por lo mismo. */
    tipo_carnet: string | null;
    /** Los kilos autorizados. Null si es comercializador: no lleva cupo. */
    cupo_kg: number | null;
}

/** Datos institucionales que se muestran en el encabezado y el pie. */
export interface InstitucionPublica {
    municipio: string | null;
    sistema: string;
    pie_legal: string | null;
}
