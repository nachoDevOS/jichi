/**
 * Tipos del módulo Solicitantes.
 *
 * Cada interfaz corresponde a lo que arma
 * App\Http\Controllers\Panel\SolicitanteController.
 *
 * Se declaran varias formas del mismo solicitante en vez de una sola gigante,
 * porque cada pantalla recibe distinto: el listado necesita pocos campos, la
 * ficha necesita todos, y el formulario necesita los que se pueden editar.
 * Un tipo por uso hace que TypeScript avise si se pide un campo que en esa
 * pantalla nunca llegó.
 *
 * OJO CON LOS NOMBRES: de `primerNombre` en adelante van en camelCase, igual
 * que las columnas de la base. `ci_nit` y `complemento` conservan el guión
 * bajo. No es un descuido: así están escritas del otro lado y las claves
 * tienen que coincidir exactamente, porque Inertia manda el array de PHP tal
 * cual, sin convertir nada.
 */

/** Una fila de la tabla del listado. */
export interface SolicitanteFila {
    id: number;
    ci_nit: string;
    /** CI con su complemento, listo para imprimir: '1234567-1A'. */
    documento_identidad: string;
    nombreCompleto: string;
    telefono: string | null;
    email: string | null;
    /** Cuántos trámites tiene. Lo calcula withCount() en el controlador. */
    tramites_count: number;
}

/** La ficha completa. */
export interface SolicitanteFicha {
    id: number;
    ci_nit: string;
    complemento: string | null;
    expedido: string | null;
    documento_identidad: string;
    primerNombre: string;
    segundoNombre: string | null;
    apellidoPaterno: string | null;
    apellidoMaterno: string | null;
    apellidoCasada: string | null;
    nombreCompleto: string;
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

/** Un trámite en el historial de la ficha. */
export interface TramiteDelSolicitante {
    id: number;
    codigo: string;
    tipo: string;
    area: string;
    icono: string | null;
    estado: string;
    estado_etiqueta: string;
    estado_color: string;
    monto_total: number;
    saldo_pendiente: number;
    creado: string | null;
}

/**
 * Los campos tal como los maneja el formulario.
 *
 * Todos son texto porque un <input> siempre devuelve texto, incluso el de
 * fecha. Las conversiones a número o a fecha las hace Laravel al validar.
 * `foto` es la excepción: es el archivo que eligió el usuario, o null.
 */
export interface FormularioSolicitante {
    ci_nit: string;
    complemento: string;
    expedido: string;
    primerNombre: string;
    segundoNombre: string;
    apellidoPaterno: string;
    apellidoMaterno: string;
    apellidoCasada: string;
    fechaNacimiento: string;
    genero: string;
    nacionalidad: string;
    direccion: string;
    ciudad: string;
    provincia: string;
    telefono: string;
    email: string;
    foto: File | null;
    quitar_foto: boolean;
}

/** Opción de una lista desplegable, tal como la arman los enums de PHP. */
export interface Opcion {
    value: string;
    label: string;
}

/** Lo que el usuario escribió en la barra de búsqueda. */
export interface FiltrosSolicitantes {
    buscar: string | null;
    /** Filas por página elegidas en la pantalla. El servidor ya la validó. */
    por_pagina: number;
}
