/**
 * Tipos de la parte pública (verificación de documentos por QR).
 *
 * Lo arma App\Http\Controllers\Publico\VerificacionController.
 *
 * REGLA DE ORO DE ESTA PARTE DEL SISTEMA: acá solo puede aparecer lo mínimo
 * para constatar que un documento es auténtico. Nunca el CI completo, ni la
 * dirección, ni el teléfono del titular. Cualquiera con el código puede ver
 * esta pantalla, así que cada campo que se agregue queda expuesto al público.
 */

import type { EstadoDocumento } from '@/types';

/** Lo que se muestra de un documento en la pantalla pública. */
export interface DocumentoPublico {
    codigo_verificacion: string;
    titular: string;
    /** Enmascarado: solo los últimos 3 dígitos ('••••779'). */
    documento_titular: string;
    tipo_documento: string;
    categoria: string;
    area: string;
    icono_area: string | null;
    fecha_emision: string;
    fecha_vencimiento: string | null;
    /** Estado REAL, ya recalculado contra la fecha de vencimiento. */
    estado: EstadoDocumento;
    estado_etiqueta: string;
    estado_color: string;
    /** Frase para el ciudadano: "Documento válido y vigente." */
    mensaje: string;
    es_valido: boolean;
}

/** Datos institucionales que se muestran en el encabezado y el pie. */
export interface InstitucionPublica {
    municipio: string | null;
    sistema: string;
    pie_legal: string | null;
}
