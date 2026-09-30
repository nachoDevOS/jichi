import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import type { FormEvent } from 'react';
import { SelectorTarifaSireb } from '@/components/panel/catalogos/selector-tarifa-sireb';
import { Button, buttonVariants } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import LayoutPanel from '@/layouts/layout-panel';
import { cn } from '@/lib/utils';
import type { ServicioSireb } from '@/types/catalogos';

/**
 *  Elegir la tarifa de SIREB de un arancel. El concepto no se edita
 */
export default function ArancelesFormulario({
    arancel,
    serviciosSireb,
}: {
    arancel: {
        id: number;
        etiqueta: string;
        descripcion: string;
        servicio_sireb: string | null;
        tarifa_sireb: string | null;
    };
    /** null = SIREB no respondió: el select de tarifa queda deshabilitado. */
    serviciosSireb: ServicioSireb[] | null;
}) {
    const form = useForm({
        servicio_sireb: arancel.servicio_sireb ?? '',
        tarifa_sireb: arancel.tarifa_sireb ?? '',
    });

    function enviar(e: FormEvent) {
        e.preventDefault();
        form.put(route('aranceles.update', arancel.id));
    }

    return (
        <LayoutPanel
            titulo={`Arancel: ${arancel.etiqueta}`}
            descripcion={arancel.descripcion}
            acciones={
                <Link href={route('aranceles.index')} className={cn(buttonVariants({ variant: 'outline' }))}>
                    <ArrowLeft className="size-4" />
                    Volver a aranceles
                </Link>
            }
        >
            <Head title={`Arancel: ${arancel.etiqueta}`} />

            <Card>
                <CardContent className="pt-6">
                    <form onSubmit={enviar} className="space-y-6">
                        <SelectorTarifaSireb
                            tarifa={form.data.tarifa_sireb}
                            tarifaGuardada={arancel.tarifa_sireb}
                            servicioGuardado={arancel.servicio_sireb}
                            serviciosSireb={serviciosSireb}
                            tarifasUsadas={{}}
                            error={form.errors.tarifa_sireb ?? form.errors.servicio_sireb}
                            onElegir={(tarifa, servicio) =>
                                form.setData((d) => ({ ...d, tarifa_sireb: tarifa, servicio_sireb: servicio }))
                            }
                        />

                        <div className="flex gap-2">
                            <Button type="submit" disabled={form.processing}>
                                Guardar cambios
                            </Button>

                            <Link href={route('aranceles.index')} className={cn(buttonVariants({ variant: 'outline' }))}>
                                Cancelar
                            </Link>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </LayoutPanel>
    );
}
