import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { HistorialSireb } from '@/components/panel/catalogos/historial-sireb';
import { buttonVariants } from '@/components/ui/button';
import LayoutPanel from '@/layouts/layout-panel';
import { cn } from '@/lib/utils';
import type { TarifaAnterior, TarifaHistorial } from '@/types/catalogos';

/**
 *  Qué servicio y tarifa de SIREB tuvo un producto, la actual arriba
 */
export default function ProductosHistorial({
    producto,
    actual,
    anteriores,
    sirebDisponible,
}: {
    producto: { nombre: string };
    actual: TarifaHistorial;
    anteriores: TarifaAnterior[];
    sirebDisponible: boolean;
}) {
    return (
        <LayoutPanel
            titulo="Historial de SIREB"
            descripcion={producto.nombre}
            acciones={
                <Link href={route('productos.index')} className={cn(buttonVariants({ variant: 'outline' }))}>
                    <ArrowLeft className="size-4" />
                    Volver a productos
                </Link>
            }
        >
            <Head title={`Historial de SIREB — ${producto.nombre}`} />

            <HistorialSireb actual={actual} anteriores={anteriores} sirebDisponible={sirebDisponible} />
        </LayoutPanel>
    );
}
