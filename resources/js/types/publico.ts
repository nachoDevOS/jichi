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
     * El número impreso en el carnet: 000013. Es lo que el inspector compara
     * contra el plástico; la firma no se imprime, va solo dentro del QR.
     */
    registro: string;
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
     * Solo los rubros HABILITADOS, sin los suspendidos. Un rubro suspendido no
     * autoriza a trabajar, y mostrarlo —aunque fuera en rojo— arriesga que el
     * inspector lea la fila y no el color.
     */
    /**
     * La actividad que el carnet autoriza. UNA, no una lista: el carnet es de un
     * solo rubro.
     *
     * Viene en `null` cuando el carnet no está vigente —vencido, suspendido o
     * anulado— y no es un olvido: mostrar la actividad, aunque fuera marcada en
     * rojo, arriesga que el inspector lea la fila y no el color. Lo que no
     * habilita, no aparece.
     */
    rubro: string | null;
    /** El cupo autorizado: «600 KG». Null por lo mismo que `rubro`. */
    capacidad: string | null;
}

/** Datos institucionales que se muestran en el encabezado y el pie. */
export interface InstitucionPublica {
    municipio: string | null;
    sistema: string;
    pie_legal: string | null;
}
