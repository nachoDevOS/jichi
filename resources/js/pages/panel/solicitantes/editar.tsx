import { Head } from '@inertiajs/react';
import { FormularioSolicitante } from '@/components/panel/solicitantes/formulario-solicitante';
import LayoutPanel from '@/layouts/layout-panel';
import type { FormularioSolicitante as Valores, Opcion } from '@/types/solicitantes';

/**
 * ============================================================================
 *  EDICIÓN DE SOLICITANTE
 * ============================================================================
 *
 * Usa el MISMO componente de formulario que la pantalla de alta. Lo único que
 * cambia es que arranca con los datos ya cargados y que al guardar apunta a la
 * ruta de actualizar en vez de a la de crear.
 */

/** Lo que manda SolicitanteController@edit. */
interface SolicitanteEditable {
    id: number;
    ci_nit: string;
    complemento: string | null;
    expedido: string | null;
    primerNombre: string;
    segundoNombre: string | null;
    apellidoPaterno: string | null;
    apellidoMaterno: string | null;
    apellidoCasada: string | null;
    fechaNacimiento: string | null;
    genero: string | null;
    nacionalidad: string | null;
    direccion: string | null;
    ciudad: string | null;
    provincia: string | null;
    telefono: string | null;
    email: string | null;
    foto_url: string | null;
}

interface Props {
    solicitante: SolicitanteEditable;
    expedidos: Opcion[];
    provincias: string[];
}

export default function EditarSolicitante({ solicitante, expedidos, provincias }: Props) {
    /*
     * De la base de datos los campos vacíos llegan como null, pero un <input>
     * de HTML no sabe qué hacer con null: lo trataría como "sin valor" y React
     * perdería el control del campo.
     *
     * Por eso cada null se convierte en cadena vacía con `?? ''`. Al guardar,
     * el middleware ConvertEmptyStringsToNull de Laravel hace el camino
     * inverso y vuelve a dejarlos en null en la base.
     */
    const valoresIniciales: Valores = {
        ci_nit: solicitante.ci_nit,
        complemento: solicitante.complemento ?? '',
        expedido: solicitante.expedido ?? '',
        primerNombre: solicitante.primerNombre,
        segundoNombre: solicitante.segundoNombre ?? '',
        apellidoPaterno: solicitante.apellidoPaterno ?? '',
        apellidoMaterno: solicitante.apellidoMaterno ?? '',
        apellidoCasada: solicitante.apellidoCasada ?? '',
        fechaNacimiento: solicitante.fechaNacimiento ?? '',
        genero: solicitante.genero ?? '',
        nacionalidad: solicitante.nacionalidad ?? '',
        direccion: solicitante.direccion ?? '',
        ciudad: solicitante.ciudad ?? '',
        provincia: solicitante.provincia ?? '',
        telefono: solicitante.telefono ?? '',
        email: solicitante.email ?? '',
        // La foto nueva empieza vacía: la que ya está guardada se pasa aparte
        // en `fotoActual`, para poder distinguir "no cambió" de "la quitaron".
        foto: null,
        quitar_foto: false,
    };

    return (
        <LayoutPanel titulo="Editar solicitante" descripcion={solicitante.ci_nit}>
            <Head title="Editar solicitante" />

            <div className="mx-auto max-w-6xl">
                <FormularioSolicitante
                    modo="editar"
                    solicitanteId={solicitante.id}
                    valoresIniciales={valoresIniciales}
                    fotoActual={solicitante.foto_url}
                    expedidos={expedidos}
                    provincias={provincias}
                />
            </div>
        </LayoutPanel>
    );
}
