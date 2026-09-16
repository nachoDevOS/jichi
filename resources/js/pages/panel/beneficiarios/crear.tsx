import { Head } from '@inertiajs/react';
import { FormularioBeneficiarioComponente } from '@/components/panel/beneficiarios/formulario-beneficiario';
import LayoutPanel from '@/layouts/layout-panel';

/**
 * Alta de un beneficiario.
 *
 * La pantalla es casi solo el envoltorio: el formulario vive en su propio
 * componente porque es el MISMO que usa la edición. Ver
 * components/panel/beneficiarios/formulario-beneficiario.tsx.
 */
export default function CrearBeneficiario({
    expedidos,
    provincias,
}: {
    expedidos: { value: string; label: string }[];
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

                El formulario es de DOS columnas —campos a la izquierda, vista
                previa fija a la derecha— y cuanto más angosto, peor: con 4xl la
                columna de la derecha quedaba tan apretada que el nombre completo
                se partía en tres renglones.

                7xl y no «sin tope»: en un monitor muy ancho, un formulario sin
                límite estira los renglones hasta que leerlos obliga a barrer la
                cabeza de lado a lado.
            */}
            <div className="mx-auto max-w-7xl">
                <FormularioBeneficiarioComponente expedidos={expedidos} provincias={provincias} />
            </div>
        </LayoutPanel>
    );
}
