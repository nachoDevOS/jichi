import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Plus, Trash2, TriangleAlert } from 'lucide-react';
import type { FormEvent } from 'react';
import { Button, buttonVariants } from '@/components/ui/button';
import { Campo } from '@/components/ui/campo';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import LayoutPanel from '@/layouts/layout-panel';
import { cn } from '@/lib/utils';
import type { TarifaSireb } from '@/types';
import type { TarifaDefinida } from '@/types/catalogos';

/**
 *  Definir tarifas — cada una con su etiqueta y la tarifa de SIREB que la cobra.
 *  Se guarda el id: si Recaudaciones renombra la tarifa, el vínculo sigue valiendo
 */
export default function ArancelesTarifas({
    arancel,
    fijas,
    filas,
    tarifasSireb,
}: {
    arancel: { id: number; nombre: string };
    /** true = las filas las fija el sistema (el carnet): no se agregan ni se quitan. */
    fijas: boolean;
    filas: TarifaDefinida[];
    /** Las tarifas del servicio; null = SIREB no respondió y no se puede guardar. */
    tarifasSireb: TarifaSireb[] | null;
}) {
    const form = useForm({
        tarifas: filas.map((f) => ({ ...f, sireb_tarifa_id: f.sireb_tarifa_id ?? '' })),
    });

    const errores = form.errors as Record<string, string>;

    function cambiar(i: number, campo: 'etiqueta' | 'sireb_tarifa_id', valor: string) {
        form.setData('tarifas', form.data.tarifas.map((f, j) => (j === i ? { ...f, [campo]: valor } : f)));
    }

    function enviar(e: FormEvent) {
        e.preventDefault();
        form.put(route('aranceles.tarifas.guardar', arancel.id));
    }

    return (
        <LayoutPanel
            titulo={`Tarifas: ${arancel.nombre}`}
            descripcion="Cada tarifa con su etiqueta y la de SIREB que la cobra. Se guarda su id: un cambio de nombre en SIREB no la afecta."
            acciones={
                <Link href={route('aranceles.index')} className={cn(buttonVariants({ variant: 'outline' }))}>
                    <ArrowLeft className="size-4" />
                    Volver a aranceles
                </Link>
            }
        >
            <Head title={`Tarifas: ${arancel.nombre}`} />

            <Card>
                <CardContent className="pt-6">
                    <form onSubmit={enviar} className="space-y-6">
                        {tarifasSireb === null && (
                            <p className="flex items-start gap-2 rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-200">
                                <TriangleAlert className="mt-0.5 size-4 shrink-0" />
                                Recaudaciones (SIREB) no responde: no se pueden elegir tarifas ahora.
                            </p>
                        )}

                        {errores.tarifas && <p className="text-sm text-destructive">{errores.tarifas}</p>}

                        {form.data.tarifas.length === 0 && (
                            <p className="text-sm text-muted-foreground">Todavía no hay tarifas. Agregue la primera.</p>
                        )}

                        {form.data.tarifas.map((f, i) => {
                            // La guardada se ofrece aunque SIREB ya no la mande: no se pierde sin avisar.
                            const ausente =
                                f.sireb_tarifa_id !== '' && !(tarifasSireb ?? []).some((t) => t.id === f.sireb_tarifa_id);

                            return (
                                <div key={f.id ?? `nueva-${i}`} className="grid gap-3 sm:grid-cols-[1fr_2fr_auto] sm:items-start">
                                    <Campo etiqueta="Etiqueta" htmlFor={`etiqueta-${i}`} error={errores[`tarifas.${i}.etiqueta`]} obligatorio>
                                        <Input
                                            id={`etiqueta-${i}`}
                                            value={f.etiqueta}
                                            onChange={(e) => cambiar(i, 'etiqueta', e.target.value)}
                                            placeholder="Alevines de tambaquí"
                                            maxLength={120}
                                        />
                                    </Campo>

                                    <Campo
                                        etiqueta="Tarifa de SIREB"
                                        htmlFor={`tarifa-${i}`}
                                        error={errores[`tarifas.${i}.sireb_tarifa_id`]}
                                        ayuda={ausente ? 'Esta tarifa ya no está en SIREB: elija otra.' : undefined}
                                    >
                                        <Select
                                            id={`tarifa-${i}`}
                                            value={f.sireb_tarifa_id}
                                            onChange={(e) => cambiar(i, 'sireb_tarifa_id', e.target.value)}
                                            disabled={tarifasSireb === null}
                                        >
                                            <option value="">{fijas ? 'Sin definir' : 'Elija una tarifa…'}</option>
                                            {ausente && <option value={f.sireb_tarifa_id}>Tarifa guardada (ya no está en SIREB)</option>}
                                            {(tarifasSireb ?? []).map((t) => (
                                                <option key={t.id} value={t.id} disabled={!t.activa}>
                                                    {t.etiqueta} — {t.monto} Bs{!t.activa ? ' (inactiva)' : ''}
                                                </option>
                                            ))}
                                        </Select>
                                    </Campo>

                                    {!fijas && (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            className="sm:mt-7"
                                            title="Quitar"
                                            onClick={() => form.setData('tarifas', form.data.tarifas.filter((_, j) => j !== i))}
                                        >
                                            <Trash2 className="size-4" />
                                        </Button>
                                    )}
                                </div>
                            );
                        })}

                        {!fijas && (
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() =>
                                    form.setData('tarifas', [
                                        ...form.data.tarifas,
                                        { id: null, clave: null, etiqueta: '', sireb_tarifa_id: '' },
                                    ])
                                }
                            >
                                <Plus className="size-4" />
                                Agregar tarifa
                            </Button>
                        )}

                        <div className="flex gap-2">
                            <Button type="submit" disabled={form.processing || tarifasSireb === null}>
                                Guardar tarifas
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
