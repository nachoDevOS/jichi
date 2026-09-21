import { Head, useForm, usePage } from '@inertiajs/react';
import { Info, Paperclip, TriangleAlert } from 'lucide-react';
import { type FormEvent } from 'react';
import { Retrato } from '@/components/comunes/retrato';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Campo } from '@/components/ui/campo';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { SelectorArchivo } from '@/components/ui/selector-archivo';
import LayoutPanel from '@/layouts/layout-panel';
import { bs, fecha } from '@/lib/utils';
import type { PageProps, TipoActor } from '@/types';
import type { AsociacionElegible, CarnetEnCorreccion, CupoVigente, TipoElegible } from '@/types/carnets';

/**
 *  CORREGIR UN CARNET PENDIENTE
 *
 * Solo se llega acá con el borrador: el servidor lo comprueba con la fila
 * bloqueada. Lo que NO se corrige es de quién es el carnet ni qué actividad
 * habilita —eso sería otro carnet, y el código impreso lleva el prefijo de la
 * actividad—; para eso se elimina este y se registra el correcto.
 */
export default function EditarCarnet({
    carnet,
    asociaciones,
    tipos,
}: {
    carnet: CarnetEnCorreccion;
    asociaciones: AsociacionElegible[];
    tipos: TipoElegible[];
}) {
    const { institucion } = usePage<PageProps>().props;

    const form = useForm({
        asociacion_id: String(carnet.asociacion_id),
        tipo_carnet_id: String(carnet.tipo_carnet_id),
        aprovechamiento_id: carnet.aprovechamiento_id,
        fecha_solicitud: carnet.fecha_solicitud ?? new Date().toISOString().slice(0, 10),

        // Opcionales: lo normal es NO volver a subir lo que ya está cargado.
        archivo_ci: null as File | null,
        archivo_asociacion: null as File | null,
    });

    const tipo = tipos.find((t) => String(t.id) === String(form.data.tipo_carnet_id)) ?? null;

    // La actividad no se toca: solo se ofrecen los tipos de la que ya tiene.
    const tiposDelActor = tipos.filter((t) => t.tipo_actor === (carnet.tipo_actor as TipoActor));
    const esPescador = carnet.tipo_actor === 'pescador';

    const cupos: CupoVigente[] = carnet.cupos_elegibles;
    const cupo = cupos.find((c) => c.id === form.data.aprovechamiento_id) ?? null;

    function enviar(e: FormEvent) {
        e.preventDefault();

        /*
         * Va por POST con `_method: put` y `forceFormData`: un PUT de verdad no
         * lleva cuerpo multipart, así que los dos archivos no llegarían.
         */
        form.transform((datos) => ({ ...datos, _method: 'put' }));
        form.post(route('carnets.update', carnet.id), { forceFormData: true });
    }

    return (
        <LayoutPanel
            titulo="Corregir carnet"
            descripcion="Solo mientras está PENDIENTE y sin cobrar. El titular y la actividad no se corrigen."
        >
            <Head title="Corregir carnet" />

            <form onSubmit={enviar} className="grid gap-6 lg:grid-cols-3">
                <Card className="lg:col-span-2">
                    <CardHeader>
                        <CardTitle>Datos de la credencial</CardTitle>
                    </CardHeader>

                    <CardContent className="space-y-5">
                        {/* EL TITULAR NO SE CORRIGE: se muestra. Cambiarlo no
                            es una corrección, es otro carnet. */}
                        <div className="flex flex-wrap items-center gap-4 rounded-md border border-border bg-secondary/40 p-4">
                            <Retrato
                                url={carnet.foto_url}
                                nombre={carnet.beneficiario ?? 'Sin nombre'}
                                className="size-14"
                            />

                            <div className="min-w-0">
                                <p className="font-medium">{carnet.beneficiario ?? '—'}</p>
                                <p className="tabular-nums text-sm text-muted-foreground">
                                    C.I. {carnet.documento ?? '—'}
                                </p>
                                <p className="font-mono text-xs text-muted-foreground">
                                    {carnet.codigo} · {carnet.tipo_actor_etiqueta}
                                </p>
                            </div>
                        </div>

                        <Campo
                            etiqueta="Tipo de carnet"
                            htmlFor="tipo_carnet_id"
                            error={form.errors.tipo_carnet_id}
                            ayuda="Solo los de la misma actividad: el código impreso lleva su prefijo."
                            obligatorio
                        >
                            <Select
                                id="tipo_carnet_id"
                                value={form.data.tipo_carnet_id}
                                onChange={(e) => form.setData('tipo_carnet_id', e.target.value)}
                                aria-invalid={Boolean(form.errors.tipo_carnet_id)}
                            >
                                {tiposDelActor.map((t) => (
                                    <option key={t.id} value={t.id}>
                                        {t.nombre} — {bs(t.precio_bs, institucion.moneda)}
                                    </option>
                                ))}
                            </Select>
                        </Campo>

                        {esPescador && cupos.length > 0 && (
                            <Campo
                                etiqueta="Autorización de Pesca que respalda el carnet"
                                htmlFor="aprovechamiento_id"
                                error={form.errors.aprovechamiento_id}
                                ayuda={
                                    cupos.length > 1
                                        ? 'La persona tiene más de una en curso: elija cuál respalda este carnet.'
                                        : 'Es el volumen que se imprime en el carnet.'
                                }
                                obligatorio
                            >
                                {cupos.length > 1 ? (
                                    <Select
                                        id="aprovechamiento_id"
                                        value={form.data.aprovechamiento_id ?? ''}
                                        onChange={(e) =>
                                            form.setData(
                                                'aprovechamiento_id',
                                                e.target.value === '' ? null : Number(e.target.value),
                                            )
                                        }
                                    >
                                        {cupos.map((c) => (
                                            <option key={c.id} value={c.id}>
                                                {c.volumen_total_kg} kg ·{' '}
                                                {c.descripcion ?? 'sin tramo'} · {c.estado_etiqueta}
                                            </option>
                                        ))}
                                    </Select>
                                ) : null}

                                {cupo && (
                                    <div className="rounded-md border border-border bg-secondary/40 p-3 text-sm">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <p className="font-medium">
                                                {cupo.volumen_total_kg} kg autorizados
                                            </p>
                                            <Badge color={cupo.estado_color}>
                                                {cupo.estado_etiqueta}
                                            </Badge>
                                        </div>

                                        <p className="tabular-nums text-muted-foreground">
                                            {cupo.descripcion ?? '—'} · vence{' '}
                                            {fecha(cupo.fecha_vencimiento)}
                                        </p>
                                    </div>
                                )}
                            </Campo>
                        )}

                        {esPescador && cupos.length === 0 && (
                            <p className="flex items-start gap-2 rounded-md bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-500/10 dark:text-amber-200">
                                <TriangleAlert className="mt-0.5 size-4 shrink-0" />
                                <span>
                                    Esta persona ya <strong>no tiene una Autorización de Pesca en
                                    curso</strong>. El
                                    servidor va a rechazar la corrección: hay que otorgarle el
                                    aprovechamiento antes.
                                </span>
                            </p>
                        )}

                        {/* LOS ADJUNTOS SON OPCIONALES ACÁ: lo normal es no
                            volver a subir lo que ya está. Se muestra un enlace
                            al que hay, para poder mirarlo antes de reemplazarlo. */}
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Campo
                                etiqueta="Cédula del titular"
                                htmlFor="archivo_ci"
                                error={form.errors.archivo_ci}
                                ayuda="Solo si hay que reemplazarla. Foto o PDF, hasta 3 MB."
                            >
                                <div className="space-y-2">
                                    {carnet.archivo_ci_url && (
                                        <a
                                            href={carnet.archivo_ci_url}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="inline-flex items-center gap-1.5 text-sm text-primary hover:underline"
                                        >
                                            <Paperclip className="size-3.5" />
                                            Ver la cargada
                                        </a>
                                    )}

                                    <SelectorArchivo
                                        id="archivo_ci"
                                        archivo={form.data.archivo_ci}
                                        onElegir={(a) => form.setData('archivo_ci', a)}
                                        error={form.errors.archivo_ci}
                                    />
                                </div>
                            </Campo>

                            <Campo
                                etiqueta="Documento de la asociación"
                                htmlFor="archivo_asociacion"
                                error={form.errors.archivo_asociacion}
                                ayuda="Solo si hay que reemplazarlo. Foto o PDF, hasta 3 MB."
                            >
                                <div className="space-y-2">
                                    {carnet.archivo_asociacion_url && (
                                        <a
                                            href={carnet.archivo_asociacion_url}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="inline-flex items-center gap-1.5 text-sm text-primary hover:underline"
                                        >
                                            <Paperclip className="size-3.5" />
                                            Ver el cargado
                                        </a>
                                    )}

                                    <SelectorArchivo
                                        id="archivo_asociacion"
                                        archivo={form.data.archivo_asociacion}
                                        onElegir={(a) => form.setData('archivo_asociacion', a)}
                                        error={form.errors.archivo_asociacion}
                                    />
                                </div>
                            </Campo>
                        </div>

                        <Campo
                            etiqueta="Asociación"
                            htmlFor="asociacion_id"
                            error={form.errors.asociacion_id}
                            ayuda="La que certifica al titular. Se imprime en el carnet."
                            obligatorio
                        >
                            <Select
                                id="asociacion_id"
                                value={form.data.asociacion_id}
                                onChange={(e) => form.setData('asociacion_id', e.target.value)}
                                aria-invalid={Boolean(form.errors.asociacion_id)}
                            >
                                {asociaciones.map((a) => (
                                    <option key={a.id} value={a.id}>
                                        {a.nombre}
                                    </option>
                                ))}
                            </Select>
                        </Campo>

                        <Campo
                            etiqueta="Fecha de solicitud"
                            htmlFor="fecha_solicitud"
                            error={form.errors.fecha_solicitud}
                            ayuda="Al cambiarla se recalcula el vencimiento, que sale de ella."
                            obligatorio
                            className="max-w-xs"
                        >
                            <Input
                                id="fecha_solicitud"
                                type="date"
                                value={form.data.fecha_solicitud}
                                onChange={(e) => form.setData('fecha_solicitud', e.target.value)}
                                aria-invalid={Boolean(form.errors.fecha_solicitud)}
                            />
                        </Campo>
                    </CardContent>
                </Card>

                <Card className="h-fit">
                    <CardHeader>
                        <CardTitle>Lo que cambia</CardTitle>
                    </CardHeader>

                    <CardContent className="space-y-4">
                        <p className="flex items-start gap-2 text-sm text-muted-foreground">
                            <Info className="mt-0.5 size-4 shrink-0" />
                            El código del carnet no cambia: ya está asignado y podría estar dictado
                            por teléfono.
                        </p>

                        {tipo && (
                            <div>
                                <p className="text-xs uppercase tracking-wide text-muted-foreground">
                                    A cobrar
                                </p>
                                <p className="text-2xl font-semibold tabular-nums">
                                    {bs(tipo.precio_bs, institucion.moneda)}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    Cambiar el tipo cambia el arancel. Se puede porque todavía no
                                    entró ningún depósito.
                                </p>
                            </div>
                        )}

                        <Button type="submit" disabled={form.processing} className="w-full">
                            Guardar cambios
                        </Button>
                    </CardContent>
                </Card>
            </form>
        </LayoutPanel>
    );
}
