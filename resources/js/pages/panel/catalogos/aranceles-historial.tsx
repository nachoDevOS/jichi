import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { HistorialSireb } from '@/components/panel/catalogos/historial-sireb';
import { buttonVariants } from '@/components/ui/button';
import LayoutPanel from '@/layouts/layout-panel';
import { cn } from '@/lib/utils';
import type { TarifaAnterior, TarifaHistorial } from '@/types/catalogos';

/**
 *  Qué servicio y tarifa de SIREB tuvo un arancel, la actual arriba
 */
export default function ArancelesHistorial({
    arancel,
    actual,
    anteriores,
    sirebDisponible,
}: {
    arancel: { etiqueta: string };
    actual: TarifaHistorial;
    anteriores: TarifaAnterior[];
    sirebDisponible: boolean;
}) {
    return (
        <LayoutPanel
            titulo="Historial de SIREB"
            descripcion={arancel.etiqueta}
            acciones={
                <Link href={route('aranceles.index')} className={cn(buttonVariants({ variant: 'outline' }))}>
                    <ArrowLeft className="size-4" />
                    Volver a aranceles
                </Link>
            }
        >
            <Head title={`Historial de SIREB — ${arancel.etiqueta}`} />

            <HistorialSireb actual={actual} anteriores={anteriores} sirebDisponible={sirebDisponible} />
        </LayoutPanel>
    );
}
