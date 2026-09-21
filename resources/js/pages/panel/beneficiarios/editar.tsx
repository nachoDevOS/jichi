import { Head } from '@inertiajs/react';
import { FormularioBeneficiarioComponente } from '@/components/panel/beneficiarios/formulario-beneficiario';
import LayoutPanel from '@/layouts/layout-panel';
import type { BeneficiarioFicha } from '@/types/beneficiarios';

/**
 * Edición de un beneficiario.
 */
export default function EditarBeneficiario({
    beneficiario,
    expedidos,
    provincias,
}: {
    beneficiario: Partial<BeneficiarioFicha> & { id: number };
    expedidos: { value: number; label: string }[];
    provincias: string[];
}) {
    return (
        <LayoutPanel
            titulo="Editar beneficiario"
            descripcion="Corregir los datos de la ficha. El historial de carnets y trámites no se toca."
        >
            <Head title="Editar beneficiario" />

            {/* Mismo ancho que el alta y que el resto de los formularios del
                panel. Ver el comentario de pages/panel/beneficiarios/crear.tsx. */}
            <div className="mx-auto max-w-7xl">
                <FormularioBeneficiarioComponente
                    beneficiario={beneficiario}
                    expedidos={expedidos}
                    provincias={provincias}
                />
            </div>
        </LayoutPanel>
    );
}
