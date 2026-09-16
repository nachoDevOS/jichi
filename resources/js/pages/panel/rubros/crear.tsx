import { Head } from '@inertiajs/react';
import { FormularioRubroComponente } from '@/components/panel/rubros/formulario-rubro';
import type { OpcionEnum } from '@/types';

/** Alta de un rubro. El formulario es el mismo que usa la edición. */
export default function CrearRubro({ estados }: { estados: OpcionEnum[] }) {
    return (
        <>
            <Head title="Nuevo rubro" />
            <FormularioRubroComponente estados={estados} />
        </>
    );
}
