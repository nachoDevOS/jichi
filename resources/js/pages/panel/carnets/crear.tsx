import { Head, useForm, usePage } from '@inertiajs/react';
import { Info, TriangleAlert } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { BuscadorBeneficiario } from '@/components/panel/comunes/buscador-beneficiario';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Campo } from '@/components/ui/campo';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { SelectorArchivo } from '@/components/ui/selector-archivo';
import LayoutPanel from '@/layouts/layout-panel';
import { bs, fecha } from '@/lib/utils';
import type { PageProps, TipoActor } from '@/types';
import type { BeneficiarioSugerido } from '@/types/beneficiarios';
import type { AsociacionElegible, CupoVigente, TipoElegible } from '@/types/carnets';

/**
 *  EMITIR UNA CREDENCIAL — paso 3 del flujo
 */
export default function CrearCarnet({
    beneficiario,
    asociaciones,
    tipos,
}: {
    beneficiario: BeneficiarioSugerido | null;
    asociaciones: AsociacionElegible[];
    tipos: TipoElegible[];
}) {
    const { institucion } = usePage<PageProps>().props;
    const [persona, setPersona] = useState<BeneficiarioSugerido | null>(beneficiario);

    const form = useForm({
        beneficiario_id: beneficiario?.id ?? null,
        asociacion_id: '',
        tipo_carnet_id: '',
        // De qué bolsa madre cuelga. Se manda explícito en vez de dejar que el
        // servidor adivine: así la pantalla y lo guardado dicen lo mismo.
        aprovechamiento_id: (beneficiario?.cupos_elegibles?.[0]?.id ?? null) as number | null,
        fecha_solicitud: new Date().toISOString().slice(0, 10),

        // LOS DOS PAPELES QUE RESPALDAN LA EMISIÓN. Suben con el formulario,
        // así que el post va con `forceFormData`.
        archivo_ci: null as File | null,
        archivo_asociacion: null as File | null,
    });

    const tipo = tipos.find((t) => String(t.id) === String(form.data.tipo_carnet_id)) ?? null;

    /*
     * LA ACTIVIDAD NO SE PREGUNTA: la dice el tipo elegido.
     *
     * Eran dos campos y decían lo mismo —cada tipo del catálogo ya declara para
     * qué actividad sirve—, así que el operador tenía que acertar dos veces la
     * misma respuesta. El `tipo_actor` se sigue guardando en el carnet: es la
     * regla congelada, y no se vuelve a leer del catálogo.
     */
    const actor: TipoActor | '' = tipo?.tipo_actor ?? '';
    const esPescador = actor === 'pescador';

    /*
     * SUS BOLSAS MADRE EN CURSO. Son las que el servidor acepta para respaldar
     * el carnet —pendiente, en revisión o aprobada—, no solo las que ya
     * autorizan a pescar: el plástico se emite con el cupo sin cobrar y los dos
     * se pagan juntos.
     */
    const cupos: CupoVigente[] = persona?.cupos_elegibles ?? [];
    const cupo = cupos.find((c) => c.id === form.data.aprovechamiento_id) ?? null;
    const faltaCupo = esPescador && persona !== null && cupos.length === 0;

    function elegirPersona(elegida: BeneficiarioSugerido | null) {
        setPersona(elegida);

        form.setData((datos) => ({
            ...datos,
            beneficiario_id: elegida?.id ?? null,
            /*
             * El cupo es de la persona: cambiarla cambia de qué bolsa cuelga.
             * Se propone la primera —la más reciente— y si hay más de una, el
             * operador elige abajo. En un comercializador queda en null: no
             * lleva volumen, y mandarlo hace que el servidor rechace.
             */
            aprovechamiento_id: esPescador ? (elegida?.cupos_elegibles?.[0]?.id ?? null) : null,
        }));
        // Se limpia el error anterior: si venía de «ya tiene carnet vigente»,
        // dejarlo colgado bajo otra persona diría una mentira.
        form.clearErrors('beneficiario_id');
    }

    function enviar(e: FormEvent) {
        e.preventDefault();
        /*
         * `forceFormData` es obligatorio: sin él Inertia manda el cuerpo como
         * JSON y los dos archivos se pierden en el camino, sin ningún error.
         */
        /*
         * La red por si algo quedó colgado del estado: el cupo solo viaja si
         * el tipo elegido lo lleva. Es lo mismo que exige el servidor.
         */
        form.transform((datos) => ({
            ...datos,
            aprovechamiento_id: esPescador ? datos.aprovechamiento_id : null,
        }));

        form.post(route('carnets.store'), { forceFormData: true });
    }

    return (
        <LayoutPanel
            titulo="Registrar carnet"
            // descripcion="Paso 3 del flujo: queda PENDIENTE de cobro. Se imprime recién cuando esté aprobado."
        >
            <Head title="Registrar carnet" />

            <form onSubmit={enviar} className="grid gap-6 lg:grid-cols-3">
                <Card className="lg:col-span-2">
                    <CardHeader>
                        <CardTitle>Datos de la credencial</CardTitle>
                    </CardHeader>

                    <CardContent className="space-y-5">
                        <Campo etiqueta="Titular" obligatorio error={form.errors.beneficiario_id}>
                            <BuscadorBeneficiario
                                seleccionado={persona}
                                onSeleccionar={elegirPersona}
                                ayuda="Tiene que estar en el padrón. Si es la primera vez que viene, regístrelo antes."
                            />
                        </Campo>

                        {/*
                            UN SOLO CAMPO, no dos. El tipo del catálogo ya
                            declara su actividad, así que preguntarla aparte era
                            pedir dos veces la misma respuesta. Van agrupados
                            para que se vea qué habilita cada uno.
                        */}
                        <Campo
                            etiqueta="Tipo de carnet"
                            htmlFor="tipo_carnet_id"
                            error={form.errors.tipo_carnet_id}
                            ayuda="Decide qué habilita el documento y cuánto sale: el de pescador saca faenas y lleva volumen autorizado; el de comercializador saca guías."
                            obligatorio
                        >
                            <Select
                                id="tipo_carnet_id"
                                value={form.data.tipo_carnet_id}
                                onChange={(e) => {
                                    /*
                                     * EL CUPO SE LIMPIA AL PASAR A COMERCIALIZADOR.
                                     * Se proponía solo, del cupo de la persona,
                                     * y seguía viajando aunque el tipo elegido
                                     * no llevara volumen: el servidor lo
                                     * rechazaba —«no lleva cupo de pesca»— y el
                                     * error caía en un campo que en ese caso no
                                     * se dibuja, así que el formulario no
                                     * guardaba y no decía por qué.
                                     */
                                    const elegido = tipos.find(
                                        (t) => String(t.id) === String(e.target.value),
                                    );

                                    form.setData((datos) => ({
                                        ...datos,
                                        tipo_carnet_id: e.target.value,
                                        aprovechamiento_id:
                                            elegido?.tipo_actor === 'comercializador'
                                                ? null
                                                : (datos.aprovechamiento_id ??
                                                  persona?.cupos_elegibles?.[0]?.id ??
                                                  null),
                                    }));
                                }}
                                aria-invalid={Boolean(form.errors.tipo_carnet_id)}
                            >
                                <option value="">Elija un tipo…</option>

                                {(['pescador', 'comercializador'] as const).map((act) => {
                                    const delActor = tipos.filter((t) => t.tipo_actor === act);

                                    return delActor.length === 0 ? null : (
                                        <optgroup
                                            key={act}
                                            label={act === 'pescador' ? 'Pescador' : 'Comercializador'}
                                        >
                                            {delActor.map((t) => (
                                                <option key={t.id} value={t.id}>
                                                    {t.nombre} — {bs(t.precio_bs, institucion.moneda)}
                                                </option>
                                            ))}
                                        </optgroup>
                                    );
                                })}
                            </Select>
                        </Campo>

                        {/*
                            DE QUÉ BOLSA MADRE CUELGA. El operador tiene que
                            poder VERLO antes de emitir: el plástico imprime ese
                            volumen, y hasta ahora el servidor elegía el cupo
                            solo y la pantalla no decía cuál.
                        */}
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
                                {/* CON UNA SOLA NO SE PREGUNTA: se muestra. Un
                                    desplegable de una opción es un clic que no
                                    decide nada. Con dos o más, elige el
                                    operador y no el servidor. */}
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
                                        aria-invalid={Boolean(form.errors.aprovechamiento_id)}
                                    >
                                        {cupos.map((c) => (
                                            <option key={c.id} value={c.id}>
                                                {c.volumen_total_kg} kg ·{' '}
                                                {c.descripcion ?? 'sin tramo'} · {c.estado_etiqueta} ·
                                                vence {fecha(c.fecha_vencimiento)}
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

                                        {/* Se puede emitir igual, y conviene
                                            decirlo: el carnet y el cupo se
                                            cobran juntos, pero hasta que el
                                            cupo se firme no habilita faenas. */}
                                        {!cupo.habilita_faenas && (
                                            <p className="mt-1 text-xs text-amber-700 dark:text-amber-400">
                                                Este cupo todavía no autoriza a pescar. El carnet se
                                                emite igual; las faenas salen recién cuando el
                                                aprovechamiento quede aprobado.
                                            </p>
                                        )}
                                    </div>
                                )}
                            </Campo>
                        )}

                        {/*
                            EL ERROR DEL CUPO, incluso cuando su campo no se
                            dibuja: si no, el formulario se queda quieto sin
                            decir nada y parece que el botón no anda.
                        */}
                        {form.errors.aprovechamiento_id && !esPescador && (
                            <p className="rounded-md bg-destructive/10 p-3 text-sm text-destructive">
                                {form.errors.aprovechamiento_id}
                            </p>
                        )}

                        {faltaCupo && (
                            <p className="flex items-start gap-2 rounded-md bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-500/10 dark:text-amber-200">
                                <TriangleAlert className="mt-0.5 size-4 shrink-0" />
                                <span>
                                    Esta persona <strong>no tiene una Autorización de Pesca en
                                    curso</strong>. El carnet de pescador imprime el volumen
                                    autorizado, así que hay que registrarla antes. El servidor va a
                                    rechazar la emisión.
                                </span>
                            </p>
                        )}

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
                                <option value="">Elija una asociación…</option>
                                {/* Solo el nombre: la sigla se sigue guardando
                                    y se sigue imprimiendo, pero acá alargaba
                                    cada renglón sin ayudar a elegir. */}
                                {asociaciones.map((a) => (
                                    <option key={a.id} value={a.id}>
                                        {a.nombre}
                                    </option>
                                ))}
                            </Select>
                        </Campo>

                        {/*
                            LOS RESPALDOS. Son los papeles que la persona trae
                            al mostrador y que después nadie encuentra: quedan
                            adjuntos al carnet. El tope de 3 MB lo pone
                            StorageController, que es el único que escribe.
                        */}
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Campo
                                etiqueta="Cédula del titular"
                                htmlFor="archivo_ci"
                                error={form.errors.archivo_ci}
                                ayuda="Foto o PDF, hasta 3 MB."
                                obligatorio
                            >
                                <SelectorArchivo
                                    id="archivo_ci"
                                    archivo={form.data.archivo_ci}
                                    onElegir={(a) => form.setData('archivo_ci', a)}
                                    error={form.errors.archivo_ci}
                                />
                            </Campo>

                            <Campo
                                etiqueta="Documento de la asociación"
                                htmlFor="archivo_asociacion"
                                error={form.errors.archivo_asociacion}
                                ayuda="La carta o certificación del gremio. Foto o PDF, hasta 3 MB."
                                obligatorio
                            >
                                <SelectorArchivo
                                    id="archivo_asociacion"
                                    archivo={form.data.archivo_asociacion}
                                    onElegir={(a) => form.setData('archivo_asociacion', a)}
                                    error={form.errors.archivo_asociacion}
                                />
                            </Campo>
                        </div>

                        <Campo
                            etiqueta="Fecha de solicitud"
                            htmlFor="fecha_solicitud"
                            error={form.errors.fecha_solicitud}
                            ayuda="El día que la persona lo pidió. La fecha de emisión la escribe el sistema al aprobarlo."
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

                {/* ------------------------------------------------ Consecuencias */}
                <Card className="h-fit">
                    <CardHeader>
                        <CardTitle>Lo que se va a emitir</CardTitle>
                    </CardHeader>

                    <CardContent className="space-y-4">
                        {actor === '' ? (
                            <p className="flex items-start gap-2 text-sm text-muted-foreground">
                                <Info className="mt-0.5 size-4 shrink-0" />
                                Elija el tipo de carnet para ver qué va a habilitar la credencial.
                            </p>
                        ) : (
                            <div className="space-y-1 text-sm">
                                <p className="text-xs uppercase tracking-wide text-muted-foreground">
                                    Habilita
                                </p>
                                <p className="font-medium">
                                    {esPescador ? 'Emitir permisos de faena' : 'Emitir guías de movimiento'}
                                </p>

                                {/* LA CAPACIDAD, no el número de la escala: el
                                    tramo es un dato del catálogo interno, y lo
                                    que dice cuánto autoriza el carnet son los
                                    kilos. */}
                                <p className="text-muted-foreground">
                                    {esPescador
                                        ? cupo
                                            ? `Autoriza ${cupo.volumen_total_kg} kg${cupo.descripcion ? ` (${cupo.descripcion})` : ''}.`
                                            : 'Necesita una Autorización de Pesca vigente: de ahí salen los kilos de cada faena.'
                                        : 'No lleva volumen: la comercialización no se autoriza por kilos.'}
                                </p>
                            </div>
                        )}

                        {tipo && (
                            <div>
                                <p className="text-xs uppercase tracking-wide text-muted-foreground">
                                    A cobrar
                                </p>
                                <p className="text-2xl font-semibold tabular-nums">
                                    {bs(tipo.precio_bs, institucion.moneda)}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    Se cobra después, en la ficha: el carnet queda PENDIENTE hasta
                                    que el arancel esté cubierto y alguien lo apruebe.
                                </p>
                            </div>
                        )}

                        <Button
                            type="submit"
                            disabled={
                                form.processing ||
                                persona === null ||
                                form.data.tipo_carnet_id === '' ||
                                form.data.archivo_ci === null ||
                                form.data.archivo_asociacion === null ||
                                form.data.asociacion_id === ''
                            }
                            className="w-full"
                        >
                            {/* REGISTRAR y no «emitir»: lo que se crea es un
                                expediente PENDIENTE, y el plástico sale al
                                final del circuito. */}
                            Registrar
                        </Button>
                    </CardContent>
                </Card>
            </form>
        </LayoutPanel>
    );
}
