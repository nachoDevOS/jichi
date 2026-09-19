import type { EstadoCarnet } from '@/types';

/**
 * Tipos de la parte pública (verificación de carnets por QR).
 *
 * Lo arma App\Http\Controllers\Publico\VerificacionController.
 *
 * REGLA DE ORO DE ESTA PARTE DEL SISTEMA: acá solo puede aparecer lo mínimo
 * para constatar que un carnet es auténtico. Nunca el CI completo, ni la
 * dirección, ni el teléfono del titular. Cualquiera que levante un carnet del
 * suelo puede ver esta pantalla, así que cada campo que se agregue queda
 * expuesto al público.
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
     *
     * Es UNA, no una lista: cada actividad es un carnet propio, y quien hace
     * las dos tiene dos credenciales con dos códigos distintos.
     *
     * Viene en `null` cuando el carnet no está vigente, y no es un olvido:
     * mostrar la actividad —aunque fuera marcada en rojo— arriesga que el
     * inspector lea la fila y no el sello. Lo que no habilita, no aparece.
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
