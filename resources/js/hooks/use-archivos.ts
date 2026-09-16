import { usePage } from '@inertiajs/react';
import type { PageProps } from '@/types';

/**
 * ============================================================================
 *  EL LÍMITE DE LOS ARCHIVOS, UNO SOLO PARA TODO EL SISTEMA
 * ============================================================================
 *
 * ¿POR QUÉ VALIDAR EL PESO EN EL NAVEGADOR SI EL SERVIDOR YA LO VALIDA?
 *
 * Por lo mismo que se esconde un botón por permiso: no es seguridad, es no
 * hacerle perder el tiempo al operador. Sin esto, adjuntar un escaneo de 8 MB
 * significa esperar a que suba entero por la conexión de la Gobernación —que no
 * es rápida— para que recién ahí el servidor lo rechace. Con el control acá, el
 * aviso es instantáneo y no se sube ni un byte.
 *
 * La regla de verdad sigue estando en el servidor. Ver
 * `RegistrarSolicitudRequest` y `GuardarBeneficiarioRequest`.
 *
 * ----------------------------------------------------------------------------
 *  EL NÚMERO NO ESTÁ ESCRITO ACÁ
 * ----------------------------------------------------------------------------
 *
 * Sale de `config/jichi.php` y llega por Inertia, igual que el símbolo de la
 * moneda. Escrito a mano en este archivo, el día que el límite cambie en el
 * servidor esta pantalla seguiría diciendo el número viejo: el operador leería
 * «hasta 4 MB» y el sistema le rechazaría un archivo de 3,5 MB sin que nadie
 * entienda por qué.
 */
export function useArchivos() {
    const { archivos } = usePage<PageProps>().props;

    /*
     * Los KB de Laravel son de 1024 bytes, igual que los que cuenta el
     * navegador en `archivo.size`. Por eso la conversión es exacta y un archivo
     * que pasa acá pasa también en el servidor, sin casos de borde en el límite.
     */
    const maxBytes = archivos.max_kb * 1024;
    const maxMb = Math.round((archivos.max_kb / 1024) * 10) / 10;

    /** El texto de ayuda que va debajo del campo, ya armado. */
    const ayudaPeso = `hasta ${maxMb} MB`;

    /**
     * Devuelve el mensaje de error, o NULL si el archivo sirve.
     *
     * Se comprueban las dos cosas que el servidor va a comprobar: el tipo y el
     * peso. El tipo también, porque el atributo `accept` del input es una
     * sugerencia —el usuario puede elegir «Todos los archivos» en el diálogo
     * del sistema y mandar lo que quiera—.
     */
    function validar(archivo: File, opciones?: { soloImagen?: boolean }): string | null {
        const permitidos = (
            opciones?.soloImagen ? archivos.mimes_imagen : archivos.mimes
        ).split(',');

        if (!permitidos.includes(archivo.type)) {
            return opciones?.soloImagen
                ? 'La fotografía tiene que ser JPG, PNG o WEBP.'
                : 'El archivo tiene que ser PDF, JPG, PNG o WEBP.';
        }

        if (archivo.size > maxBytes) {
            // Se dice cuánto pesa el que eligió, no solo cuál es el tope: así
            // el operador sabe si le falta poco —y conviene volver a escanear
            // en menor calidad— o si se equivocó de archivo.
            const pesa = Math.round((archivo.size / 1024 / 1024) * 10) / 10;

            return `El archivo pesa ${pesa} MB y el máximo es ${maxMb} MB.`;
        }

        return null;
    }

    return {
        maxKb: archivos.max_kb,
        maxBytes,
        maxMb,
        ayudaPeso,
        /** Para el atributo `accept` del <input type="file">. */
        acepta: archivos.mimes,
        aceptaImagen: archivos.mimes_imagen,
        validar,
    };
}
