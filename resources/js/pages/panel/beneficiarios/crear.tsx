import { Head } from '@inertiajs/react';
import { FormularioBeneficiarioComponente } from '@/components/panel/beneficiarios/formulario-beneficiario';
import LayoutPanel from '@/layouts/layout-panel';

/**
 * Alta de un beneficiario.
 */
export default function CrearBeneficiario({
    expedidos,
    provincias,
}: {
    expedidos: { value: number; label: string }[];
    provincias: string[];
}) {
    return (
        <LayoutPanel
            titulo="Nuevo beneficiario"
            descripcion="Los datos se toman de la cédula de identidad, tal como figuran en ella."
        >
            <Head title="Nuevo beneficiario" />

            {/*
                EL ANCHO ES EL MISMO EN TODOS LOS FORMULARIOS DEL PANEL: max-w-7xl.
            */}
            <div className="mx-auto max-w-7xl">
                <FormularioBeneficiarioComponente expedidos={expedidos} provincias={provincias} />
            </div>
        </LayoutPanel>
    );
}
