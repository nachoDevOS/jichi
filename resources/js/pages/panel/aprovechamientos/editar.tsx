import { Head, useForm, usePage } from '@inertiajs/react';
import { Lock } from 'lucide-react';
import { type FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Campo } from '@/components/ui/campo';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import LayoutPanel from '@/layouts/layout-panel';
import { bs, fecha } from '@/lib/utils';
import type { PageProps } from '@/types';

/**
 *  Corregir una autorización: solo la embarcación
 *
 * El tramo, los kilos, el monto y las fechas quedan como se otorgaron. Para otro
 * tramo se elimina y se otorga de nuevo, así queda el motivo escrito.
 */
export default function EditarCupo({
    cupo,
}: {
    cupo: {
        id: number;
        beneficiario: string | null;
        documento: string | null;
        foto_url: string | null;
        tramo: string | null;
        volumen_total_kg: number;
        modalidad_etiqueta: string | null;
        /** Lo que se congeló de SIREB al otorgar. */
        monto: number;
        tipo_embarcacion: string | null;
        fecha_solicitud: string | null;
        fecha_vencimiento: string | null;
    };
}) {
    const { institucion } = usePage<PageProps>().props;

    const form = useForm({ tipo_embarcacion: cupo.tipo_embarcacion ?? '' });

    function enviar(e: FormEvent) {
        e.preventDefault();
        form.put(route('aprovechamientos.update', cupo.id));
    }

    return (
        <LayoutPanel titulo="Corregir aprovechamiento" descripcion={`${cupo.beneficiario ?? ''} · pendiente`}>
            <Head title="Corregir aprovechamiento" />

            <form onSubmit={enviar} className="grid gap-6 lg:grid-cols-3">
                <Card className="lg:col-span-2">
                    <CardHeader>
                        <CardTitle>Datos del otorgamiento</CardTitle>
                    </CardHeader>

                    <CardContent className="space-y-5">
                        <Campo etiqueta="Pescador">
                            <div className="flex items-center gap-3 rounded-md border border-border bg-secondary/40 p-3">
                                {cupo.foto_url && (
                                    <img src={cupo.foto_url} alt="" className="size-10 shrink-0 rounded-full object-cover" />
                                )}

                                <div className="min-w-0">
                                    <p className="truncate font-medium">{cupo.beneficiario ?? '—'}</p>
                                    <p className="font-mono text-xs text-muted-foreground">{cupo.documento ?? '—'}</p>
                                </div>
                            </div>
                        </Campo>

                        <Campo
                            etiqueta="Tipo de embarcación"
                            htmlFor="tipo_embarcacion"
                            error={form.errors.tipo_embarcacion}
                            ayuda="Como figura en el talonario. Va impreso en la Autorización de Pesca para Aprovechamiento Pesquero."
                            obligatorio
                            className="max-w-sm"
                        >
                            <Input
                                id="tipo_embarcacion"
                                list="tipos-de-embarcacion"
                                value={form.data.tipo_embarcacion}
                                onChange={(e) => form.setData('tipo_embarcacion', e.target.value)}
                                aria-invalid={Boolean(form.errors.tipo_embarcacion)}
                                placeholder="Canoa, peque-peque, bote…"
                                maxLength={120}
                                autoFocus
                            />

                            <datalist id="tipos-de-embarcacion">
                                {['Canoa', 'Peque-peque', 'Bote', 'Chalana', 'Deslizador', 'Balsa'].map((t) => (
                                    <option key={t} value={t} />
                                ))}
                            </datalist>
                        </Campo>
                    </CardContent>
                </Card>

                <Card className="h-fit">
                    <CardHeader>
                        <CardTitle>Lo que no cambia</CardTitle>
                    </CardHeader>

                    <CardContent className="space-y-4 text-sm">
                        <Fijo etiqueta="Tramo de la escala" valor={cupo.tramo ?? '—'} />
                        <Fijo etiqueta="Volumen autorizado" valor={`${cupo.volumen_total_kg} kg`} />
                        <Fijo etiqueta="Régimen" valor={cupo.modalidad_etiqueta ?? '—'} />
                        <Fijo etiqueta="Monto" valor={bs(cupo.monto, institucion.moneda)} />
                        <Fijo etiqueta="Solicitado el" valor={cupo.fecha_solicitud ? fecha(cupo.fecha_solicitud) : '—'} />
                        <Fijo etiqueta="Vence el" valor={cupo.fecha_vencimiento ? fecha(cupo.fecha_vencimiento) : '—'} />

                        <p className="flex items-start gap-2 rounded-md bg-secondary/60 p-3 text-xs text-muted-foreground">
                            <Lock className="mt-0.5 size-3.5 shrink-0" />
                            El titular y el tramo no se cambian. Si se cargó mal, elimine la autorización y
                            otórguela de nuevo: así queda el motivo escrito.
                        </p>

                        <div className="flex flex-col gap-2">
                            <Button type="submit" disabled={form.processing}>
                                Guardar cambios
                            </Button>

                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => window.history.back()}
                                disabled={form.processing}
                            >
                                Cancelar
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </form>
        </LayoutPanel>
    );
}

function Fijo({ etiqueta, valor }: { etiqueta: string; valor: string }) {
    return (
        <div className="flex justify-between gap-3">
            <span className="text-muted-foreground">{etiqueta}</span>
            <span className="text-right font-medium tabular-nums">{valor}</span>
        </div>
    );
}
