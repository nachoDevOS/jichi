import { Head } from '@inertiajs/react';
import { FormularioSolicitante } from '@/components/panel/solicitantes/formulario-solicitante';
import LayoutPanel from '@/layouts/layout-panel';
import type { FormularioSolicitante as Valores, Opcion } from '@/types/solicitantes';

/**
 * ============================================================================
 *  ALTA DE SOLICITANTE
 * ============================================================================
 *
 * Esta pantalla casi no tiene código propio: arma los valores iniciales del
 * formulario y deja que <FormularioSolicitante> haga el resto. El mismo
 * componente lo usa la pantalla de edición.
 */

/**
 * El formulario arranca con todos los campos en blanco.
 *
 * IMPORTANTE: hay que declarar TODOS los campos, aunque estén vacíos.
 *
 * Si un campo empieza como `undefined` y después se le escribe algo, React
 * avisa en consola de que el input "pasó de no controlado a controlado". Ese
 * error significa que React perdió el control de ese campo, y en la práctica
 * se traduce en texto que desaparece solo al escribir. Con la cadena vacía
 * desde el principio no pasa.
 */
const VALORES_INICIALES: Valores = {
    ci_nit: '',
    // El Beni, que es de donde viene casi todo el que se acerca a ventanilla.
    complemento: '',
    expedido: 'BN',
    primerNombre: '',
    segundoNombre: '',
    apellidoPaterno: '',
    apellidoMaterno: '',
    apellidoCasada: '',
    fechaNacimiento: '',
    genero: '',
    nacionalidad: 'Boliviana',
    direccion: '',
    ciudad: 'Trinidad',
    provincia: 'Cercado',
    telefono: '',
    email: '',
    foto: null,
    quitar_foto: false,
};

interface Props {
    expedidos: Opcion[];
    provincias: string[];
}

export default function CrearSolicitante({ expedidos, provincias }: Props) {
    return (
        <LayoutPanel
            titulo="Nuevo solicitante"
            descripcion="Registro del pescador que realiza el trámite"
        >
            <Head title="Nuevo solicitante" />

            <div className="mx-auto max-w-6xl">
                <FormularioSolicitante
                    modo="crear"
                    valoresIniciales={VALORES_INICIALES}
                    expedidos={expedidos}
                    provincias={provincias}
                />
            </div>
        </LayoutPanel>
    );
}
