import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import type { FormEvent } from 'react';
import { Button, buttonVariants } from '@/components/ui/button';
import { Campo } from '@/components/ui/campo';
import { Card, CardContent } from '@/components/ui/card';
import { Select } from '@/components/ui/select';
import LayoutPanel from '@/layouts/layout-panel';
import { cn } from '@/lib/utils';
import type { ServicioSireb } from '@/types/catalogos';

/**
 *  Elegir el servicio de SIREB de un arancel. Solo el servicio: la tarifa se
 *  elige al emitir. El concepto no se edita
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
    };
    /** Los servicios activos; null = SIREB no respondió y el select queda deshabilitado. */
    serviciosSireb: ServicioSireb[] | null;
}) {
    const form = useForm({ servicio_sireb: arancel.servicio_sireb ?? '' });

    // El guardado se ofrece aunque SIREB ya no lo mande: editar no lo borra sin avisar.
    const guardadoAusente =
        arancel.servicio_sireb !== null && !(serviciosSireb ?? []).some((s) => s.id === arancel.servicio_sireb);

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
                        <Campo
                            etiqueta="Servicio SIREB"
                            htmlFor="servicio_sireb"
                            error={form.errors.servicio_sireb}
                            ayuda={
                                serviciosSireb === null
                                    ? 'Recaudaciones (SIREB) no responde: no se puede elegir el servicio ahora.'
                                    : 'Ventanilla elige entre sus tarifas al emitir el trámite.'
                            }
                            obligatorio
                        >
                            <Select
                                id="servicio_sireb"
                                value={form.data.servicio_sireb}
                                onChange={(e) => form.setData('servicio_sireb', e.target.value)}
                                disabled={serviciosSireb === null}
                                aria-invalid={Boolean(form.errors.servicio_sireb)}
                            >
                                <option value="">Elija un servicio…</option>
                                {guardadoAusente && <option value={arancel.servicio_sireb!}>Servicio actual</option>}
                                {(serviciosSireb ?? []).map((s) => (
                                    <option key={s.id} value={s.id}>
                                        {s.nombre}
                                        {s.codigo ? ` (${s.codigo})` : ''} — {s.tarifas} tarifas
                                    </option>
                                ))}
                            </Select>
                        </Campo>

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
