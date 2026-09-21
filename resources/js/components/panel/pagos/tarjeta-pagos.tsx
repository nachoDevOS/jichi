import { useForm, usePage } from '@inertiajs/react';
import { Banknote, Check, Paperclip, Pencil, Plus, Printer, Trash2, TriangleAlert, X } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { DialogoCorregirPago } from '@/components/panel/pagos/dialogo-corregir-pago';
import { Badge } from '@/components/ui/badge';
import { Button, buttonVariants } from '@/components/ui/button';
import { Campo } from '@/components/ui/campo';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ConfirmarAccion } from '@/components/ui/confirmar-accion';
import { ConfirmarConMotivo } from '@/components/ui/confirmar-con-motivo';
import { EstadoVacio } from '@/components/ui/estado-vacio';
import { Input } from '@/components/ui/input';
import { SelectorArchivo } from '@/components/ui/selector-archivo';
import { usePermisos } from '@/hooks/use-permisos';
import { bs, cn, fecha, fechaHora } from '@/lib/utils';
import type { PageProps } from '@/types';
import type { PagoDelCupo, ReciboDelCupo } from '@/types/aprovechamientos';

/**
 * LA TARJETA DE PAGOS de un trámite que se cobra con depósitos.
 *
 * La usan el aprovechamiento y el carnet: los dos se cobran igual —una sección
 * por boleta, cubriendo el monto entero, y el recibo sale al presentar— así que
 * el formulario es UNO. Escrito dos veces, el día que cambie una regla se
 * corrige en uno y el otro sigue cobrando con la vieja.
 *
 * Lo que cambia entre trámites llega por props: a qué ruta se manda y qué
 * permiso habilita el envío.
 */
export function TarjetaPagos({
    pagos,
    recibo,
    saldoPendiente,
    titular,
    admitePagos,
    rutaPagar,
    permisoEnviar,
    textoAlEnviar,
}: {
    pagos: PagoDelCupo[];
    recibo: ReciboDelCupo | null;
    /** Lo que falta cobrar. Es lo que las secciones tienen que cubrir. */
    saldoPendiente: number;
    /** A nombre de quién sale: se muestra en la confirmación. */
    titular: string;
    admitePagos: boolean;
    /** La ruta ya resuelta: `route('carnets.pagar', id)`. */
    rutaPagar: string;
    /** Con este permiso, registrar y presentar son un solo acto. */
    permisoEnviar: string;
    /** Qué pasa al presentar. Cada trámite lo dice a su manera. */
    textoAlEnviar: string;
}) {
    const { puede } = usePermisos();
    const { institucion } = usePage<PageProps>().props;

    const [corrigiendo, setCorrigiendo] = useState<PagoDelCupo | null>(null);
    const [observando, setObservando] = useState<PagoDelCupo | null>(null);
    const [confirmando, setConfirmando] = useState(false);

    const control = useForm({});
    const observacion = useForm({ motivo: '' });

    /** Una sección del formulario: una boleta del banco. */
    const seccionNueva = () => ({
        // `key` estable: sin ella, quitar la del medio remonta las de abajo y
        // les vacía el archivo elegido.
        key: `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
        // VACÍO, no el saldo propuesto: el monto es el que dice la BOLETA, y un
        // campo ya lleno se confirma sin leerlo.
        monto: '',
        nro_transaccion: '',
        fecha_deposito: new Date().toISOString().slice(0, 10),
        comprobante: null as File | null,
    });

    const pago = useForm({
        pagos: [] as ReturnType<typeof seccionNueva>[],
        enviar: false,
    });

    const cobrando = pago.data.pagos.length > 0;

    // `Number('')` da 0 y no NaN: una sección recién agregada no rompe la
    // cuenta mientras el operador todavía no escribió el monto.
    const suma = pago.data.pagos.reduce((s, x) => s + Number(x.monto || 0), 0);
    const falta = Math.round((saldoPendiente - suma) * 100) / 100;

    // Cubrir habilita REGISTRAR; enviar pide además el permiso.
    const cubierto = falta <= 0;
    const cubre = cubierto && puede(permisoEnviar);

    function cambiar(key: string, campo: string, valor: string | File | null) {
        pago.setData(
            'pagos',
            pago.data.pagos.map((x) => (x.key === key ? { ...x, [campo]: valor } : x)),
        );
    }

    const errorDe = (i: number, campo: string): string | undefined =>
        (pago.errors as Record<string, string | undefined>)[`pagos.${i}.${campo}`];

    function registrar() {
        // `transform` y no `setData`: setData es asincrónico y el post saldría
        // con el valor anterior.
        pago.transform((datos) => ({ ...datos, enviar: cubre }));

        // `forceFormData`: sin él Inertia manda JSON y las boletas se pierden.
        pago.post(rutaPagar, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                pago.reset();
                setConfirmando(false);
            },
            // Se cierra también al fallar: los errores se pintan sobre el
            // formulario y con la ventana encima no se ven.
            onError: () => setConfirmando(false),
        });
    }

    return (
        <Card className="min-w-0 lg:col-span-3">
            <CardHeader className="flex-row items-center justify-between gap-2 space-y-0">
                <CardTitle>Pagos</CardTitle>

                {admitePagos && puede('caja.cobrar') && (
                    <div className="flex gap-2">
                        {cobrando && (
                            <Button variant="outline" size="sm" onClick={() => pago.reset()}>
                                <X className="size-4" />
                                Cancelar
                            </Button>
                        )}

                        <Button
                            size="sm"
                            onClick={() =>
                                pago.setData('pagos', [...pago.data.pagos, seccionNueva()])
                            }
                        >
                            <Plus className="size-4" />
                            Agregar pago
                        </Button>
                    </div>
                )}
            </CardHeader>

            <CardContent className="space-y-5 p-0">
                {cobrando && (
                    <form
                        onSubmit={(e: FormEvent) => {
                            e.preventDefault();
                            // El botón no guarda: abre la confirmación.
                            setConfirmando(true);
                        }}
                        className="mx-5 space-y-4"
                    >
                        {pago.errors.pagos && (
                            <p className="text-sm text-destructive">{pago.errors.pagos}</p>
                        )}

                        {pago.data.pagos.map((s, i) => (
                            <div
                                key={s.key}
                                className="space-y-4 rounded-md border border-border bg-secondary/30 p-4"
                            >
                                <div className="flex items-center justify-between gap-2">
                                    <p className="text-sm font-medium">Depósito {i + 1}</p>

                                    <Button
                                        type="button"
                                        variant="eliminar"
                                        size="sm"
                                        title="Quitar este depósito"
                                        aria-label={`Quitar el depósito ${i + 1}`}
                                        onClick={() =>
                                            pago.setData(
                                                'pagos',
                                                pago.data.pagos.filter((x) => x.key !== s.key),
                                            )
                                        }
                                    >
                                        <Trash2 className="size-4" />
                                    </Button>
                                </div>

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <Campo
                                        etiqueta="Monto del depósito"
                                        htmlFor={`monto-${s.key}`}
                                        error={errorDe(i, 'monto')}
                                        obligatorio
                                    >
                                        <Input
                                            id={`monto-${s.key}`}
                                            type="number"
                                            step="0.01"
                                            min={0}
                                            placeholder="0.00"
                                            value={s.monto}
                                            onChange={(e) => cambiar(s.key, 'monto', e.target.value)}
                                            aria-invalid={Boolean(errorDe(i, 'monto'))}
                                        />
                                    </Campo>

                                    <Campo
                                        etiqueta="Fecha del depósito"
                                        htmlFor={`fecha-${s.key}`}
                                        error={errorDe(i, 'fecha_deposito')}
                                        ayuda="La que dice la boleta, no la de hoy."
                                        obligatorio
                                    >
                                        <Input
                                            id={`fecha-${s.key}`}
                                            type="date"
                                            value={s.fecha_deposito}
                                            onChange={(e) =>
                                                cambiar(s.key, 'fecha_deposito', e.target.value)
                                            }
                                            aria-invalid={Boolean(errorDe(i, 'fecha_deposito'))}
                                        />
                                    </Campo>

                                    <Campo
                                        etiqueta="N° de transacción"
                                        htmlFor={`nro-${s.key}`}
                                        error={errorDe(i, 'nro_transaccion')}
                                        ayuda="No se puede repetir: una misma boleta no respalda dos pagos."
                                        obligatorio
                                    >
                                        {/* Solo dígitos, y de TEXTO: un campo
                                            numérico se come los ceros de
                                            adelante de la boleta. */}
                                        <Input
                                            id={`nro-${s.key}`}
                                            inputMode="numeric"
                                            value={s.nro_transaccion}
                                            onChange={(e) =>
                                                cambiar(
                                                    s.key,
                                                    'nro_transaccion',
                                                    e.target.value.replace(/\D/g, ''),
                                                )
                                            }
                                            aria-invalid={Boolean(errorDe(i, 'nro_transaccion'))}
                                            placeholder="0012345678"
                                            className="font-mono"
                                            maxLength={60}
                                        />
                                    </Campo>

                                    <Campo
                                        etiqueta="Boleta del depósito"
                                        htmlFor={`comprobante-${s.key}`}
                                        error={errorDe(i, 'comprobante')}
                                        ayuda="Foto o PDF, hasta 3 MB."
                                        obligatorio
                                    >
                                        <SelectorArchivo
                                            id={`comprobante-${s.key}`}
                                            archivo={s.comprobante}
                                            onElegir={(a) => cambiar(s.key, 'comprobante', a)}
                                            error={errorDe(i, 'comprobante')}
                                        />
                                    </Campo>
                                </div>
                            </div>
                        ))}

                        {/* LA CUENTA A LA VISTA: de MÁS se admite —la boleta
                            dice lo que dice— y de menos no. */}
                        <div className="flex flex-wrap items-center justify-between gap-3 rounded-md bg-secondary/50 p-3 text-sm">
                            <span>
                                Suman{' '}
                                <strong className="tabular-nums">
                                    {bs(suma, institucion.moneda)}
                                </strong>{' '}
                                de {bs(saldoPendiente, institucion.moneda)} pendientes
                            </span>

                            <span
                                className={
                                    falta > 0
                                        ? 'font-medium text-amber-700 dark:text-amber-400'
                                        : 'font-medium text-emerald-700 dark:text-emerald-400'
                                }
                            >
                                {falta > 0
                                    ? `Faltan ${bs(falta, institucion.moneda)}`
                                    : falta < 0
                                      ? `Cubre el monto · ${bs(-falta, institucion.moneda)} de más`
                                      : 'Cubre el monto'}
                            </span>
                        </div>

                        <Button
                            type="submit"
                            disabled={
                                pago.processing ||
                                !cubierto ||
                                pago.data.pagos.some(
                                    (s) =>
                                        Number(s.monto) <= 0 ||
                                        s.fecha_deposito === '' ||
                                        s.nro_transaccion.trim() === '' ||
                                        s.comprobante === null,
                                )
                            }
                        >
                            <Banknote className="size-4" />
                            {cubre
                                ? `Registrar ${pago.data.pagos.length} depósito(s) y enviar a revisión`
                                : `Registrar ${pago.data.pagos.length} depósito(s)`}
                        </Button>

                        {!cubierto ? (
                            <p className="text-xs text-amber-700 dark:text-amber-400">
                                Faltan {bs(falta, institucion.moneda)} para cubrir el monto. El
                                trámite se cobra entero: agregue las boletas que falten y
                                regístrelas todas juntas.
                            </p>
                        ) : (
                            cubre && <p className="text-xs text-muted-foreground">{textoAlEnviar}</p>
                        )}
                    </form>
                )}

                {/* EL RECIBO VA UNA VEZ, arriba del detalle que ampara: en cada
                    fila se leía como «un recibo por depósito». */}
                {pagos.length > 0 && (
                    <div className="mx-5 mb-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-border bg-secondary/40 px-4 py-3">
                        {recibo ? (
                            <>
                                <div className="min-w-0">
                                    <p className="text-xs uppercase tracking-wide text-muted-foreground">
                                        Recibo del trámite
                                    </p>

                                    <span className="font-mono font-medium">
                                        {recibo.numero_recibo}
                                    </span>

                                    <span className="ml-2 text-xs text-muted-foreground">
                                        {recibo.pagos_count} depósito(s) · emitido el{' '}
                                        {fechaHora(recibo.emitido_en)}
                                    </span>
                                </div>

                                <div className="flex items-center gap-3">
                                    <span className="font-semibold tabular-nums">
                                        {bs(recibo.monto_total, institucion.moneda)}
                                    </span>

                                    {puede('recibos.imprimir') && (
                                        <a
                                            href={route('recibos.imprimir', recibo.id)}
                                            target="_blank"
                                            rel="noreferrer"
                                            className={cn(buttonVariants({ variant: 'outline' }))}
                                        >
                                            <Printer className="size-4" />
                                            Imprimir
                                        </a>
                                    )}
                                </div>
                            </>
                        ) : (
                            <p className="text-xs text-muted-foreground">
                                Todavía no se emitió el recibo. Sale uno solo, con el total de todos
                                los depósitos, al enviar el trámite a revisión.
                            </p>
                        )}
                    </div>
                )}

                {pagos.length === 0 ? (
                    // El vacío se calla mientras se está cargando un depósito:
                    // decir «sin pagos» abajo del formulario abierto se lee
                    // como que lo tipeado no entró.
                    !cobrando && (
                        <EstadoVacio
                            icono={Banknote}
                            titulo="Sin pagos registrados"
                            descripcion="El trámite no queda habilitado hasta que el arancel esté cobrado."
                        />
                    )
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="border-y border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                                <tr>
                                    <th className="px-5 py-2.5 font-medium">Boleta</th>
                                    <th className="px-5 py-2.5 font-medium">Depositado</th>
                                    <th className="px-5 py-2.5 text-right font-medium">Monto</th>
                                    <th className="px-5 py-2.5 font-medium">Cargado</th>
                                    <th className="px-5 py-2.5 font-medium">Validación</th>
                                    <th className="px-5 py-2.5" />
                                </tr>
                            </thead>

                            <tbody className="divide-y divide-border">
                                {pagos.map((p) => (
                                    <tr key={p.id} className="hover:bg-secondary/50">
                                        <td className="px-5 py-2.5">
                                            {p.comprobante_url ? (
                                                <a
                                                    href={p.comprobante_url}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="inline-flex items-center gap-1.5 font-mono text-primary hover:underline"
                                                >
                                                    <Paperclip className="size-3.5" />
                                                    {p.nro_transaccion}
                                                </a>
                                            ) : (
                                                <span className="font-mono">{p.nro_transaccion}</span>
                                            )}
                                        </td>

                                        <td className="px-5 py-2.5 text-muted-foreground">
                                            {fecha(p.fecha_deposito)}
                                        </td>

                                        <td className="px-5 py-2.5 text-right font-medium tabular-nums">
                                            {bs(p.monto_parcial, institucion.moneda)}
                                        </td>

                                        <td className="px-5 py-2.5 text-xs text-muted-foreground">
                                            {fechaHora(p.cobrado_en)}
                                            <span className="block">por {p.registrado_por ?? '—'}</span>
                                        </td>

                                        <td className="px-5 py-2.5">
                                            <Badge color={p.estado_validacion_color}>
                                                {p.estado_validacion_etiqueta}
                                            </Badge>

                                            {p.validado_por && (
                                                <span className="block text-xs text-muted-foreground">
                                                    {p.validado_por} · {fechaHora(p.validado_en)}
                                                </span>
                                            )}

                                            {p.observacion && (
                                                <span className="block text-xs text-amber-700 dark:text-amber-400">
                                                    {p.observacion}
                                                </span>
                                            )}
                                        </td>

                                        {/* Validar y observar son de SUPERVISIÓN;
                                            corregir, de ventanilla. Sobre un
                                            observado no aparece «Validar»: se
                                            corrige. */}
                                        <td className="px-5 py-2.5">
                                            <div className="flex justify-end gap-1">
                                                {puede('pagos.controlar') && p.puede_validarse && (
                                                    <>
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            onClick={() =>
                                                                control.patch(
                                                                    route('pagos.validar', p.id),
                                                                    { preserveScroll: true },
                                                                )
                                                            }
                                                            disabled={control.processing}
                                                            title="La boleta cuadra con el extracto del banco"
                                                            className="border-emerald-300 text-emerald-700 hover:bg-emerald-50 hover:text-emerald-800 dark:border-emerald-500/40 dark:text-emerald-300 dark:hover:bg-emerald-500/10"
                                                        >
                                                            <Check className="size-4" />
                                                            Validar
                                                        </Button>

                                                        <Button
                                                            variant="eliminar"
                                                            size="sm"
                                                            onClick={() => setObservando(p)}
                                                            title="No cuadra: hay que escribir por qué"
                                                        >
                                                            <TriangleAlert className="size-4" />
                                                            Observar
                                                        </Button>
                                                    </>
                                                )}

                                                {puede('pagos.corregir') && p.puede_corregirse && (
                                                    <Button
                                                        variant="editar"
                                                        size="sm"
                                                        onClick={() => setCorrigiendo(p)}
                                                        title="Corregir el monto, la boleta o la fecha"
                                                    >
                                                        <Pencil className="size-4" />
                                                        Corregir
                                                    </Button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </CardContent>

            {/* REGISTRAR SE CONFIRMA: cuando cubre el monto, el botón hace DOS
                cosas y la segunda cierra la puerta —sale el recibo numerado—. */}
            <ConfirmarAccion
                abierto={confirmando}
                tono="afirmativo"
                titulo={cubre ? 'Registrar y enviar a revisión' : 'Registrar los depósitos'}
                descripcion={
                    <div className="space-y-2">
                        <p>
                            Se cargan{' '}
                            <strong>
                                {pago.data.pagos.length} depósito(s) por {bs(suma, institucion.moneda)}
                            </strong>{' '}
                            al trámite de <strong>{titular}</strong>.
                        </p>

                        {cubre && <p>{textoAlEnviar}</p>}
                    </div>
                }
                confirmacion={
                    cubre
                        ? 'Los montos y los números de boleta coinciden con los comprobantes del banco.'
                        : undefined
                }
                textoConfirmar={cubre ? 'Registrar y enviar' : 'Registrar'}
                procesando={pago.processing}
                onCancelar={() => setConfirmando(false)}
                onConfirmar={registrar}
            />

            {/* OBSERVAR PIDE MOTIVO: es lo único que le dice a ventanilla qué
                corregir. */}
            <ConfirmarConMotivo
                abierto={observando !== null}
                titulo="Observar este depósito"
                descripcion={
                    <p>
                        El depósito <strong>sigue sumando</strong>: lo que queda en duda es si la
                        boleta respalda lo que dice. Para levantarlo hay que CORREGIRLO, y ahí vuelve
                        a quedar pendiente de control.
                    </p>
                }
                etiquetaMotivo="Qué no cuadra"
                ayuda="Lo lee quien tenga que corregirlo. Queda en la auditoría con su nombre."
                placeholder="El monto de la boleta no coincide con el extracto del banco."
                textoConfirmar="Observar"
                valor={observacion.data.motivo}
                onCambiar={(v) => observacion.setData('motivo', v)}
                error={observacion.errors.motivo}
                procesando={observacion.processing}
                onCancelar={() => {
                    setObservando(null);
                    observacion.reset();
                }}
                onConfirmar={() =>
                    observando &&
                    observacion.patch(route('pagos.observar', observando.id), {
                        preserveScroll: true,
                        onSuccess: () => {
                            setObservando(null);
                            observacion.reset();
                        },
                    })
                }
            />

            <DialogoCorregirPago pago={corrigiendo} onCerrar={() => setCorrigiendo(null)} />
        </Card>
    );
}
