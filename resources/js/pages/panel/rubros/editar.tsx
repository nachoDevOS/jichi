import { Head } from '@inertiajs/react';
import { FormularioRubroComponente } from '@/components/panel/rubros/formulario-rubro';
import type { OpcionEnum } from '@/types';
import type { RubroFila } from '@/types/rubros';

/** Edición de un rubro. Mismo formulario que el alta. */
export default function EditarRubro({
    rubro,
    estados,
    tramitesAbiertos,
}: {
    rubro: Partial<RubroFila> & { id: number };
    estados: OpcionEnum[];
    tramitesAbiertos: number;
}) {
    return (
        <>
            <Head title={`Editar ${rubro.nombre ?? 'rubro'}`} />
            <FormularioRubroComponente
                rubro={rubro}
                estados={estados}
                tramitesAbiertos={tramitesAbiertos}
            />
        </>
    );
}
