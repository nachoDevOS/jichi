import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import type { FormEvent } from 'react';
import { Button, buttonVariants } from '@/components/ui/button';
import { Campo } from '@/components/ui/campo';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import LayoutPanel from '@/layouts/layout-panel';
import { cn } from '@/lib/utils';
import type { ServicioSireb } from '@/types/catalogos';

/**
 *  Registrar un arancel o corregirlo: nombre y servicio de SIREB. La tarifa no se
 *  guarda: se elige al emitir
 */
export default function ArancelesFormulario({
    arancel,
    serviciosSireb,
}: {
    /** null = registrar. */
    arancel: {
        id: number;
        nombre: string;
        /** null en los registrados en el panel: no los usa ningún trámite. */
        descripcion: string | null;
        servicio_sireb: string | null;
    } | null;
    /** Los servicios activos; null = SIREB no respondió y el select queda deshabilitado. */
    serviciosSireb: ServicioSireb[] | null;
}) {
    const form = useForm({
        nombre: arancel?.nombre ?? '',
        servicio_sireb: arancel?.servicio_sireb ?? '',
    });

    // El guardado se ofrece aunque SIREB ya no lo mande: editar no lo borra sin avisar.
    const guardadoAusente =
        arancel !== null &&
        arancel.servicio_sireb !== null &&
        !(serviciosSireb ?? []).some((s) => s.id === arancel.servicio_sireb);

    const titulo = arancel ? `Arancel: ${arancel.nombre}` : 'Registrar arancel';

    function enviar(e: FormEvent) {
        e.preventDefault();

        if (arancel) {
            form.put(route('aranceles.update', arancel.id));
        } else {
            form.post(route('aranceles.store'));
        }
    }

    return (
        <LayoutPanel
            titulo={titulo}
            descripcion={arancel?.descripcion ?? undefined}
            acciones={
                <Link href={route('aranceles.index')} className={cn(buttonVariants({ variant: 'outline' }))}>
                    <ArrowLeft className="size-4" />
                    Volver a aranceles
                </Link>
            }
        >
            <Head title={titulo} />

            <Card>
                <CardContent className="pt-6">
                    <form onSubmit={enviar} className="space-y-6">
                        <Campo etiqueta="Nombre" htmlFor="nombre" error={form.errors.nombre} obligatorio>
                            <Input
                                id="nombre"
                                value={form.data.nombre}
                                onChange={(e) => form.setData('nombre', e.target.value)}
                                aria-invalid={Boolean(form.errors.nombre)}
                                placeholder="Solicitud de importe de alevines"
                                maxLength={120}
                            />
                        </Campo>

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
                                {arancel ? 'Guardar cambios' : 'Registrar'}
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
