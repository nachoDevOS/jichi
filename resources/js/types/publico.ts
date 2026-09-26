/**
 * Tipos de la parte pública (verificación de carnets por QR).
 */

/** Un renglón del acta, ya resuelto por el servidor. */
export interface RenglonPublico {
    etiqueta: string;
    valor: string;
    /** Monoespaciada: en un número se confunden el 0 con la O. */
    mono: boolean;
}

/**
 * Lo que se muestra de CUALQUIER documento verificado.
 *
 * Misma forma para los cinco —carnet, aprovechamiento, faena, guía y recibo—:
 * lo que cambia de uno a otro son los `renglones`, que arma
 * VerificacionController::renglones(). Sumar un tipo nuevo no toca este
 * archivo.
 */
/** El tramo de validez, ya calculado por el servidor: el navegador no parsea fechas. */
export interface VigenciaPublica {
    desde_etiqueta: string;
    desde: string;
    hasta_etiqueta: string;
    hasta: string;
    /** Negativo si ya venció. */
    dias_restantes: number;
    /** 0 a 100: cuánto del tramo ya pasó. */
    avance: number;
}

export interface DocumentoPublico {
    tipo: 'carnet' | 'aprovechamiento' | 'faena' | 'guia' | 'recibo';
    /** «Carnet de Pescador», «Permiso de Faena»… */
    tipo_etiqueta: string;
    /** En grupos de cuatro, con guion: «EFGT-96R4-CJ42-AHYJ». */
    codigo: string | null;
    titular: string | null;
    /** Enmascarado: solo los últimos 3 dígitos ('••••779'). */
    documento_titular: string;
    estado_etiqueta: string;
    estado_color: string;
    /**
     * NO es lo mismo que el estado guardado. Se calcula además contra la
     * fecha, porque `vencido` lo escribe un comando que corre una vez al día.
     */
    vigente: boolean;
    /** Frase para el inspector: «Documento auténtico y vigente…». */
    mensaje: string;
    renglones: RenglonPublico[];
    /** Null en la guía y el recibo: sus fechas van como renglones. */
    vigencia: VigenciaPublica | null;
}

/** Datos institucionales que se muestran en el encabezado y el pie. */
export interface InstitucionPublica {
    municipio: string | null;
    sistema: string;
    pie_legal: string | null;
}

/** Datos institucionales de la portada. Todos salen de `configuraciones`. */
export interface InstitucionPortada {
    nombre: string;
    sigla: string;
    departamento: string;
    direccion: string | null;
    telefono: string | null;
    email: string | null;
    horario: string;
    sistema: string;
    descripcion: string;
}
