import { Head, useForm, usePage } from '@inertiajs/react';
import { Info, TriangleAlert } from 'lucide-react';
import { type FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Campo } from '@/components/ui/campo';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import LayoutPanel from '@/layouts/layout-panel';
import { bs } from '@/lib/utils';
import type { PageProps } from '@/types';
import type { TramoElegible } from '@/types/aprovechamientos';

/**
 * ============================================================================
 *  CORREGIR UN APROVECHAMIENTO QUE TODAVÍA ES BORRADOR
 * ============================================================================
 *
 * Solo se llega acá con el cupo PENDIENTE DE PAGO y sin ningún abono encima. El
 * servidor lo comprueba dos veces —al abrir la pantalla y al guardar, esta
 * última con la fila bloqueada— porque entre una cosa y la otra otra ventanilla
 * puede cobrarlo.
 *
 * ----------------------------------------------------------------------------
 *  EL TITULAR NO SE CAMBIA, Y NO ES UN OLVIDO
 * ----------------------------------------------------------------------------
 *
 * Corregir es arreglar una carga equivocada; mover la autorización de una
 * persona a otra es otra cosa, y dejarlo hacer desde acá la volvería invisible:
 * la fila quedaría igual, con otro nombre, sin nada que lo delate. Si el cupo se
 * cargó a quien no era, se elimina —con el motivo escrito— y se otorga de nuevo.
 *
 * Por eso la persona se muestra fija arriba, no en el buscador.
 *
 * ----------------------------------------------------------------------------
 *  ES EL MISMO FORMULARIO QUE EL ALTA MENOS ESE CAMPO
 * ----------------------------------------------------------------------------
 *
 * Se mantiene como pantalla aparte —y no como un modo de `crear.tsx`— porque lo
 * que cambia no es un campo sino el significado: el alta elige a quién y esta
 * no, el alta manda a la caja y esta vuelve a la ficha. Un solo componente con
 * dos modos tendría un `if` en cada una de esas decisiones.
 */
export default function EditarCupo({
    cupo,
    escala,
}: {
    cupo: {
        id: number;
        beneficiario_id: number;
        beneficiario: string | null;
        documento: string | null;
        foto_url: string | null;
        categoria_aprov_id: number;
        tipo_embarcacion: string | null;
        fecha_emision: string | null;
    };
    escala: TramoElegible[];
}) {
    const { institucion } = usePage<PageProps>().props;

    const form = useForm({
        // Va aunque no se pueda cambiar: el Request lo exige, y mandarlo desde
        // acá evita que el servidor tenga que ir a buscarlo otra vez.
        beneficiario_id: cupo.beneficiario_id,
        categoria_aprov_id: String(cupo.categoria_aprov_id),
        tipo_embarcacion: cupo.tipo_embarcacion ?? '',
        fecha_emision: cupo.fecha_emision ?? '',
    });

    const tramo = escala.find((t) => String(t.id) === String(form.data.categoria_aprov_id)) ?? null;
    const original = escala.find((t) => t.id === cupo.categoria_aprov_id) ?? null;
    const cambioDeTramo = tramo !== null && tramo.id !== cupo.categoria_aprov_id;

    function enviar(e: FormEvent) {
        e.preventDefault();
        form.put(route('aprovechamientos.update', cupo.id));
    }

    return (
        <LayoutPanel
            titulo="Corregir aprovechamiento"
            descripcion={`${cupo.beneficiario ?? ''} · pendiente de pago`}
        >
            <Head title="Corregir aprovechamiento" />

            <form onSubmit={enviar} className="grid gap-6 lg:grid-cols-3">
                <Card className="lg:col-span-2">
                    <CardHeader>
                        <CardTitle>Datos del otorgamiento</CardTitle>
                    </CardHeader>

                    <CardContent className="space-y-5">
                        {/*
                            La persona, fija. Se muestra igual que en el alta para
                            que la pantalla se lea igual, pero sin buscador: acá
                            no hay nada que elegir.
                        */}
                        <Campo etiqueta="Pescador">
                            <div className="flex items-center gap-3 rounded-md border border-border bg-secondary/40 p-3">
                                {cupo.foto_url && (
                                    <img
                                        src={cupo.foto_url}
                                        alt=""
                                        className="size-10 shrink-0 rounded-full object-cover"
                                    />
                                )}

                                <div className="min-w-0">
                                    <p className="truncate font-medium">{cupo.beneficiario ?? '—'}</p>
                                    <p className="font-mono text-xs text-muted-foreground">
                                        {cupo.documento ?? '—'}
                                    </p>
                                </div>
                            </div>

                            <p className="mt-1 text-xs text-muted-foreground">
                                El titular no se cambia. Si el cupo se cargó a quien no era, elimínelo
                                y otórguelo de nuevo: así queda el motivo escrito.
                            </p>
                        </Campo>

                        <Campo
                            etiqueta="Tramo de la escala"
                            htmlFor="categoria_aprov_id"
                            error={form.errors.categoria_aprov_id}
                            ayuda="Cambiarlo vuelve a copiar los kilos y el monto del tramo nuevo."
                            obligatorio
                        >
                            <Select
                                id="categoria_aprov_id"
                                value={form.data.categoria_aprov_id}
                                onChange={(e) => form.setData('categoria_aprov_id', e.target.value)}
                                aria-invalid={Boolean(form.errors.categoria_aprov_id)}
                            >
                                {escala.map((t) => (
                                    <option key={t.id} value={t.id}>
                                        {t.nro_escala} · {t.descripcion_kg} —{' '}
                                        {bs(t.valor_bs, institucion.moneda)}
                                    </option>
                                ))}
                            </Select>
                        </Campo>

                        <Campo
                            etiqueta="Tipo de embarcación"
                            htmlFor="tipo_embarcacion"
                            error={form.errors.tipo_embarcacion}
                            ayuda="Como figura en el talonario. Si no la declara, déjelo vacío."
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
                            />

                            <datalist id="tipos-de-embarcacion">
                                {['Canoa', 'Peque-peque', 'Bote', 'Chalana', 'Deslizador', 'Balsa'].map(
                                    (t) => (
                                        <option key={t} value={t} />
                                    ),
                                )}
                            </datalist>
                        </Campo>

                        <Campo
                            etiqueta="Fecha de otorgamiento"
                            htmlFor="fecha_emision"
                            error={form.errors.fecha_emision}
                            ayuda="Al cambiarla se recalcula el vencimiento, que sale de ella."
                            obligatorio
                            className="max-w-xs"
                        >
                            <Input
                                id="fecha_emision"
                                type="date"
                                value={form.data.fecha_emision}
                                onChange={(e) => form.setData('fecha_emision', e.target.value)}
                                aria-invalid={Boolean(form.errors.fecha_emision)}
                            />
                        </Campo>
                    </CardContent>
                </Card>

                {/* ------------------------------------------------ Consecuencias */}
                <Card className="h-fit">
                    <CardHeader>
                        <CardTitle>Cómo queda</CardTitle>
                    </CardHeader>

                    <CardContent className="space-y-4">
                        {tramo === null ? (
                            <p className="flex items-start gap-2 text-sm text-muted-foreground">
                                <Info className="mt-0.5 size-4 shrink-0" />
                                Elija un tramo para ver cuántos kilos se autorizan y cuánto se cobra.
                            </p>
                        ) : (
                            <>
                                {/*
                                    CAMBIAR DE TRAMO CAMBIA LAS DOS COSAS que la
                                    persona ya escuchó en voz alta: los kilos y el
                                    precio. Se dice de dónde a dónde, porque
                                    mostrar solo el número nuevo esconde justo lo
                                    que el operador está por corregir.
                                */}
                                {cambioDeTramo && original && (
                                    <p className="flex items-start gap-2 rounded-md bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-500/10 dark:text-amber-200">
                                        <TriangleAlert className="mt-0.5 size-4 shrink-0" />
                                        <span>
                                            Cambia de la escala {original.nro_escala} a la{' '}
                                            {tramo.nro_escala}: de {original.kilos_max} a{' '}
                                            {tramo.kilos_max} kg, y de{' '}
                                            {bs(original.valor_bs, institucion.moneda)} a{' '}
                                            {bs(tramo.valor_bs, institucion.moneda)}.
                                        </span>
                                    </p>
                                )}

                                <div>
                                    <p className="text-xs uppercase tracking-wide text-muted-foreground">
                                        Volumen autorizado
                                    </p>
                                    <p className="text-3xl font-semibold tabular-nums">
                                        {tramo.kilos_max}
                                        <span className="ml-1 text-base font-normal text-muted-foreground">
                                            kg
                                        </span>
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        Es el techo del tramo ({tramo.kilos_min} – {tramo.kilos_max} kg).
                                    </p>
                                </div>

                                <div>
                                    <p className="text-xs uppercase tracking-wide text-muted-foreground">
                                        Régimen
                                    </p>
                                    <p className="font-medium">{tramo.modalidad_etiqueta}</p>
                                    <p className="text-xs text-muted-foreground">
                                        {tramo.modalidad_descripcion}
                                    </p>
                                </div>

                                <div>
                                    <p className="text-xs uppercase tracking-wide text-muted-foreground">
                                        A cobrar
                                    </p>
                                    <p className="text-2xl font-semibold tabular-nums">
                                        {bs(tramo.valor_bs, institucion.moneda)}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        Todavía no entró ningún pago: por eso este cupo se puede
                                        corregir.
                                    </p>
                                </div>
                            </>
                        )}

                        <div className="flex flex-col gap-2">
                            <Button type="submit" disabled={form.processing || tramo === null}>
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
