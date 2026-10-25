import { router, useForm, usePage } from '@inertiajs/react';
import { AlertTriangle, Banknote, Check, FilePlus, Hourglass, Printer, QrCode, RefreshCw } from 'lucide-react';
import { useCallback, useState, type ReactNode } from 'react';
import { TextoCopiable } from '@/components/comunes/texto-copiable';
import { EnlacePdf, type EnlaceDirecto } from '@/components/panel/comunes/ficha';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ConfirmarNuevaLiquidacion } from '@/components/panel/pagos/confirmar-nueva-liquidacion';
import { ModalCargarPago } from '@/components/panel/pagos/modal-cargar-pago';
import { ModalPagoQr } from '@/components/panel/pagos/modal-pago-qr';
import type { DetallePago } from '@/components/panel/pagos/ventana-pago';
import { usePermisos } from '@/hooks/use-permisos';
import { bs, cn, fecha, fechaHora } from '@/lib/utils';
import type { CotizacionRenovacion, LiquidacionHistorial, ReciboDelCupo, VentaSireb } from '@/types/aprovechamientos';

/**
 * El cobro de un documento, que se paga en SIREB. La comparten las cuatro fichas:
 * el estado de la liquidación, el código con el que se paga, el botón que pregunta
 * por el pago (si está pagado, lo aprueba) y el recibo una vez emitido.
 */
export function TarjetaRecaudaciones({
    monto,
    sireb,
    recibo,
    reciboDirecto = null,
    puedeVerificar,
    rutaVerificar,
    puedeCargar = false,
    rutaCargar,
    rutaConsultarQr,
    rutaRenovar,
    documento,
    pago,
    className,
}: {
    monto: number;
    sireb: VentaSireb | null;
    recibo: ReciboDelCupo | null;
    /** El enlace firmado del PDF guardado del recibo: lo abre directo de S3. */
    reciboDirecto?: EnlaceDirecto | null;
    puedeVerificar: boolean;
    /** La ruta ya resuelta: `route('carnets.verificar-pago', id)`. */
    rutaVerificar: string;
    /** Pendiente y sin pago cargado: se ofrece cargarlo en SIREB desde acá. */
    puedeCargar?: boolean;
    /** `route('carnets.cargar-pago', id)`. */
    rutaCargar?: string;
    /** `route('carnets.consultar-qr', id)`: la ventana del QR pregunta a SIREB al abrirse. */
    rutaConsultarQr?: string;
    /** `route('carnets.renovar-liquidacion', id)`: se ofrece si la liquidación venció sin pago. */
    rutaRenovar?: string;
    /** Prefijo de los permisos `<documento>.verificar-pago` y `.cargar-pago`. */
    documento: 'aprovechamientos' | 'carnets' | 'faenas' | 'guias';
    /** Qué se paga, para el detalle de la ventana del QR. */
    pago?: DetallePago;
    className?: string;
}) {
    const { puede } = usePermisos();
    const ofreceVerificar = puede(`${documento}.verificar-pago`) && puedeVerificar;
    const ofreceCarga = puede(`${documento}.cargar-pago`) && puedeCargar;
    const ofreceRenovar =
        puede(`${documento}.renovar-liquidacion`) && Boolean(sireb?.puede_renovar) && Boolean(rutaRenovar);
    const verificacion = useForm({});
    const renovacion = useForm({});
    const [confirmandoRenovar, setConfirmandoRenovar] = useState(false);
    const cerrarRenovar = useCallback(() => setConfirmandoRenovar(false), []);
    // El monto con la tarifa vigente: se pide a SIREB recién al abrir la ventana.
    const cotizacion = usePage<{ cotizacion_renovacion?: CotizacionRenovacion }>().props.cotizacion_renovacion ?? null;
    const [cotizando, setCotizando] = useState(false);

    function abrirRenovar() {
        setConfirmandoRenovar(true);
        router.reload({
            only: ['cotizacion_renovacion'],
            onStart: () => setCotizando(true),
            onFinish: () => setCotizando(false),
        });
    }
    // Cómo se ofrece pagar: con el QR o cargando la boleta de un depósito, cada uno en su ventana.
    const [modo, setModo] = useState<'qr' | 'carga' | null>(null);
    const cerrarModal = useCallback(() => setModo(null), []);

    // Solo para pintar en qué tramo va el cobro; quien aprueba es ConfirmarPagoService.
    const paso: 1 | 2 | 3 = recibo ? 3 : sireb?.pago ? 2 : 1;
    const caido = Boolean(sireb?.caida) && !recibo;
    const verificar = () => verificacion.post(rutaVerificar, { preserveScroll: true });

    return (
        <Card className={cn('@container h-fit min-w-0', className)}>
            <CardHeader className="gap-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <CardTitle>Pago en Recaudaciones</CardTitle>
                    {/* Registrada es lo normal: el badge queda solo para lo que hay que mirar. */}
                    {sireb && sireb.estado !== 'registrada' && (
                        <Badge color={sireb.estado_color}>{sireb.estado_etiqueta}</Badge>
                    )}
                </div>
                <PasosCobro paso={paso} caido={caido} />
            </CardHeader>

            <CardContent className="grid gap-6 text-sm @3xl:grid-cols-2">
                <div className="space-y-4">
                    <div
                        className={cn(
                            'space-y-3 rounded-xl p-4',
                            paso === 3 ? 'bg-emerald-50 dark:bg-emerald-500/10' : 'bg-secondary/60',
                        )}
                    >
                        <div>
                            <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                {paso === 3 ? 'Pagado' : 'Monto a pagar'}
                            </p>
                            <p className="text-3xl font-semibold tabular-nums">{bs(monto)}</p>
                        </div>

                        {/* Con este código el titular paga en el banco; pagado ya no hace falta. */}
                        {paso !== 3 && !caido && sireb?.codigo_publico && (
                            <div className="border-t border-foreground/10 pt-3">
                                <p className="text-xs text-muted-foreground">Código para pagar en el banco</p>
                                <TextoCopiable
                                    texto={sireb.codigo_publico}
                                    className="-ml-1.5 text-lg tracking-wider"
                                />
                            </div>
                        )}

                        {sireb?.estado === 'por_enviar' && (
                            <p className="border-t border-foreground/10 pt-3 text-xs text-muted-foreground">
                                Recaudaciones todavía no respondió. Se reintenta solo y el código aparece cuando
                                responda.
                            </p>
                        )}
                    </div>

                    {caido && sireb && (
                        // Un cobro vencido no avanza solo: el aviso tiene que verse antes que todo lo demás.
                        <div
                            role="alert"
                            className="flex gap-3 rounded-lg border-2 border-amber-500 bg-amber-100 p-4 text-amber-950 shadow-md dark:border-amber-400 dark:bg-amber-500/20 dark:text-amber-100"
                        >
                            <span className="relative flex size-9 shrink-0 items-center justify-center rounded-full bg-amber-500 text-white dark:text-amber-950">
                                <span className="absolute inline-flex size-full animate-ping rounded-full bg-amber-400 opacity-60 motion-reduce:hidden" />
                                <AlertTriangle className="relative size-5" />
                            </span>
                            <div className="space-y-1">
                                <p className="text-base font-bold">
                                    {sireb.caida === 'anulada' ? 'Cobro anulado' : 'Plazo de pago vencido'}
                                </p>
                                <p className="font-medium">
                                    {sireb.caida === 'anulada'
                                        ? 'Recaudaciones anuló el cobro.'
                                        : 'Venció el plazo de pago.'}{' '}
                                    El trámite sigue pendiente: genere una nueva liquidación para cobrarlo.
                                </p>
                            </div>
                        </div>
                    )}
                </div>

                <div className="space-y-3">
                    {paso === 3 && recibo && (
                        <div className="space-y-2 rounded-md border p-3">
                            <Dato etiqueta="Recibo">
                                <span className="font-mono font-medium">{recibo.numero_recibo}</span>
                            </Dato>
                            {recibo.numero_boleta && (
                                <Dato etiqueta="N° de transacción">
                                    {recibo.numero_boleta}
                                    {recibo.entidad_bancaria && <> · {recibo.entidad_bancaria}</>}
                                </Dato>
                            )}
                            <Dato etiqueta="Pagado">
                                {recibo.fecha_pago ? fecha(recibo.fecha_pago) : fechaHora(recibo.emitido_en)}
                            </Dato>
                            {sireb?.pago?.fecha_validacion && (
                                <Dato etiqueta="Validado el">{fechaHora(sireb.pago.fecha_validacion)}</Dato>
                            )}
                            {puede('recibos.imprimir') && (
                                <EnlacePdf
                                    href={route('recibos.imprimir', recibo.id)}
                                    directo={reciboDirecto}
                                    icono={Printer}
                                    className="mt-1 w-full"
                                >
                                    Imprimir recibo
                                </EnlacePdf>
                            )}
                        </div>
                    )}

                    {paso === 2 && sireb?.pago && (
                        <div className="space-y-2 rounded-md border p-3">
                            <div className="flex items-center justify-between gap-2">
                                <span className="font-medium">Boleta cargada</span>
                                <Badge color={sireb.pago.estado === 'confirmado' ? 'emerald' : 'amber'}>
                                    {sireb.pago.estado === 'confirmado' ? 'Validado' : 'Por validar'}
                                </Badge>
                            </div>
                            <Dato etiqueta="N° de transacción">
                                <span className="font-mono">{sireb.pago.numero_boleta ?? '—'}</span>
                            </Dato>
                            <Dato etiqueta="Banco">{sireb.pago.entidad_bancaria ?? '—'}</Dato>
                            <Dato etiqueta="Fecha de pago">
                                {sireb.pago.fecha_pago ? fecha(sireb.pago.fecha_pago) : '—'}
                            </Dato>
                            <Dato etiqueta="Monto pagado">
                                <span className="text-base tabular-nums">{bs(sireb.pago.monto_pagado)}</span>
                            </Dato>
                            {sireb.pago.fecha_validacion && (
                                <Dato etiqueta="Validado el">{fechaHora(sireb.pago.fecha_validacion)}</Dato>
                            )}
                            <p className="flex items-start gap-2 rounded-md bg-amber-50 p-2 text-xs text-amber-900 dark:bg-amber-500/10 dark:text-amber-100">
                                <Hourglass className="mt-0.5 size-3.5 shrink-0" />
                                Esperando que Recaudaciones lo valide. Cuando lo validen, el trámite se aprueba y sale
                                el recibo.
                            </p>
                        </div>
                    )}

                    {/* Solo CARGA el pago en SIREB: validarlo sigue siendo de Recaudaciones. */}
                    {paso === 1 && !caido && ofreceCarga && rutaCargar && (
                        <div className="space-y-2">
                            <p className="font-medium">¿Cómo va a pagar el titular?</p>
                            <div className="grid gap-2 @md:grid-cols-2">
                                <OpcionPago
                                    key="opcion-qr"
                                    activa={modo === 'qr'}
                                    onClick={() => setModo('qr')}
                                    icono={<QrCode className="size-4" />}
                                    titulo="Pagar por QR"
                                    detalle="Desde la app del banco"
                                    tono="sky"
                                />
                                <OpcionPago
                                    key="opcion-carga"
                                    activa={modo === 'carga'}
                                    onClick={() => setModo('carga')}
                                    icono={<Banknote className="size-4" />}
                                    titulo="Ya depositó"
                                    detalle="Cargar la boleta del banco"
                                    tono="emerald"
                                />
                            </div>
                        </div>
                    )}

                    {paso === 1 && !ofreceCarga && !caido && (
                        <p className="flex items-start gap-2 rounded-md border border-dashed p-3 text-muted-foreground">
                            <Hourglass className="mt-0.5 size-4 shrink-0" />
                            Esperando que el titular pague con el código. Cuando Recaudaciones valide el pago, el
                            trámite queda aprobado y sale el recibo.
                        </p>
                    )}

                    {ofreceRenovar && rutaRenovar && (
                        <Button key="renovar" type="button" className="w-full" onClick={abrirRenovar}>
                            <FilePlus className="size-4" />
                            Generar nueva liquidación
                        </Button>
                    )}

                    {/* jichi:verificar-pagos ya pregunta cada 10 min: el botón es para no esperar. */}
                    {ofreceVerificar &&
                        (paso === 2 ? (
                            <Button
                                key="verificar-grande"
                                variant="outline"
                                className="w-full"
                                disabled={verificacion.processing}
                                onClick={verificar}
                            >
                                <RefreshCw className={cn('size-4', verificacion.processing && 'animate-spin')} />
                                Revisar si ya lo validaron
                            </Button>
                        ) : (
                            <p
                                key="verificar-chico"
                                className="flex flex-wrap items-center gap-x-1.5 gap-y-1 text-xs text-muted-foreground"
                            >
                                <RefreshCw className={cn('size-3.5', verificacion.processing && 'animate-spin')} />
                                Se revisa solo cada 10 minutos.
                                <button
                                    type="button"
                                    className="font-medium text-primary underline-offset-2 hover:underline disabled:opacity-50"
                                    disabled={verificacion.processing}
                                    onClick={verificar}
                                >
                                    {verificacion.processing ? 'Revisando…' : '¿Ya pagó? Revisar ahora'}
                                </button>
                            </p>
                        ))}
                </div>

                {sireb && sireb.historial.length > 0 && <HistorialLiquidaciones historial={sireb.historial} />}
            </CardContent>

            <ConfirmarNuevaLiquidacion
                abierto={confirmandoRenovar}
                cotizando={cotizando}
                cotizacion={cotizacion}
                procesando={renovacion.processing}
                onCerrar={cerrarRenovar}
                onConfirmar={() =>
                    rutaRenovar &&
                    renovacion.post(rutaRenovar, {
                        preserveScroll: true,
                        onFinish: cerrarRenovar,
                    })
                }
            />

            <ModalPagoQr
                abierto={modo === 'qr'}
                monto={monto}
                codigoPago={sireb?.codigo_publico ?? null}
                rutaConsultar={rutaConsultarQr}
                pago={pago}
                puedeVerificar={ofreceVerificar}
                verificando={verificacion.processing}
                onVerificar={() =>
                    verificacion.post(rutaVerificar, {
                        preserveScroll: true,
                        onSuccess: () => setModo(null),
                    })
                }
                onCerrar={cerrarModal}
            />

            {rutaCargar && (
                <ModalCargarPago
                    abierto={modo === 'carga'}
                    monto={monto}
                    codigoPago={sireb?.codigo_publico ?? null}
                    pago={pago}
                    rutaCargar={rutaCargar}
                    onCerrar={cerrarModal}
                />
            )}
        </Card>
    );
}

const ESTADO_LIQUIDACION: Record<LiquidacionHistorial['estado'], { etiqueta: string; color: string }> = {
    registrada: { etiqueta: 'Registrada', color: 'sky' },
    vencida: { etiqueta: 'Vencida', color: 'amber' },
    anulada: { etiqueta: 'Anulada', color: 'slate' },
    pagada: { etiqueta: 'Pagada', color: 'emerald' },
};

/** Las liquidaciones ya cerradas: con qué tarifa y monto se pidieron y cómo terminaron. */
function HistorialLiquidaciones({ historial }: { historial: LiquidacionHistorial[] }) {
    return (
        <details className="@3xl:col-span-2">
            <summary className="cursor-pointer text-muted-foreground">
                Liquidaciones anteriores ({historial.length})
            </summary>
            <ul className="mt-3 space-y-2">
                {[...historial].reverse().map((l) => (
                    <li key={l.liquidacion_id} className="space-y-1 rounded-md border p-3">
                        <div className="flex items-center justify-between gap-2">
                            <span className="font-mono">{l.codigo_publico ?? '—'}</span>
                            <Badge color={ESTADO_LIQUIDACION[l.estado].color}>
                                {ESTADO_LIQUIDACION[l.estado].etiqueta}
                            </Badge>
                        </div>
                        <div className="flex justify-between gap-3">
                            <span className="text-muted-foreground">Monto</span>
                            <span className="text-base tabular-nums">{bs(l.monto)}</span>
                        </div>
                        <div className="flex justify-between gap-3">
                            <span className="text-muted-foreground">Solicitada</span>
                            <span>{fechaHora(l.solicitada_en)}</span>
                        </div>
                        {l.cerrada_en && (
                            <div className="flex justify-between gap-3">
                                <span className="text-muted-foreground">Cerrada</span>
                                <span>{fechaHora(l.cerrada_en)}</span>
                            </div>
                        )}
                        <p className="text-xs break-all text-muted-foreground">
                            {l.items
                                .map(
                                    (i) =>
                                        `${i.producto ? i.producto + ': ' : ''}tarifa ${i.tarifa_id} a ${bs(i.precio)}`,
                                )
                                .join(' · ')}
                        </p>
                    </li>
                ))}
            </ul>
        </details>
    );
}

/** Cada forma de pago con su color, para distinguirlas de un vistazo. Clases completas: ver badge.tsx. */
const TONO_OPCION = {
    sky: {
        caja: 'border-sky-200 bg-sky-50/60 hover:border-sky-400 hover:bg-sky-50 dark:border-sky-500/30 dark:bg-sky-500/5 dark:hover:bg-sky-500/10',
        activa: 'border-sky-500 bg-sky-50 ring-1 ring-sky-500 dark:bg-sky-500/15',
        icono: 'bg-sky-100 text-sky-700 dark:bg-sky-500/20 dark:text-sky-300',
        titulo: 'text-sky-900 dark:text-sky-100',
    },
    emerald: {
        caja: 'border-emerald-200 bg-emerald-50/60 hover:border-emerald-400 hover:bg-emerald-50 dark:border-emerald-500/30 dark:bg-emerald-500/5 dark:hover:bg-emerald-500/10',
        activa: 'border-emerald-500 bg-emerald-50 ring-1 ring-emerald-500 dark:bg-emerald-500/15',
        icono: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300',
        titulo: 'text-emerald-900 dark:text-emerald-100',
    },
} as const;

function OpcionPago({
    activa,
    onClick,
    icono,
    titulo,
    detalle,
    tono,
}: {
    activa: boolean;
    onClick: () => void;
    icono: React.ReactNode;
    titulo: string;
    detalle: string;
    tono: keyof typeof TONO_OPCION;
}) {
    const estilo = TONO_OPCION[tono];

    return (
        <button
            type="button"
            onClick={onClick}
            aria-pressed={activa}
            className={cn(
                'flex items-center gap-2.5 rounded-lg border px-3 py-2 text-left transition-all',
                estilo.caja,
                activa && estilo.activa,
            )}
        >
            <span className={cn('flex size-8 shrink-0 items-center justify-center rounded-full', estilo.icono)}>
                {icono}
            </span>
            <span className="min-w-0 leading-tight">
                <span className={cn('block text-sm font-semibold', estilo.titulo)}>{titulo}</span>
                <span className="block text-xs text-muted-foreground">{detalle}</span>
            </span>
        </button>
    );
}

/** Un renglón rótulo–valor de los datos del pago. */
function Dato({ etiqueta, children }: { etiqueta: string; children: ReactNode }) {
    return (
        <div className="flex justify-between gap-3">
            <span className="text-muted-foreground">{etiqueta}</span>
            <span className="text-right">{children}</span>
        </div>
    );
}

const PASOS_COBRO = [
    { titulo: 'Por pagar', detalle: 'El titular paga con el código' },
    { titulo: 'Por validar', detalle: 'Recaudaciones revisa la boleta' },
    { titulo: 'Aprobado', detalle: 'Sale el recibo' },
] as const;

/** Los tres tramos del cobro, para que se vea de un vistazo qué falta. */
function PasosCobro({ paso, caido }: { paso: 1 | 2 | 3; caido: boolean }) {
    return (
        <ol className="grid grid-cols-3 gap-2">
            {PASOS_COBRO.map((p, i) => {
                const numero = i + 1;
                const hecho = numero < paso || paso === 3;
                const actual = numero === paso && paso !== 3;
                const alerta = actual && caido;

                return (
                    <li key={p.titulo} aria-current={actual ? 'step' : undefined} className="min-w-0 space-y-2">
                        <span
                            className={cn(
                                'block h-1.5 rounded-full',
                                hecho ? 'bg-emerald-500' : alerta ? 'bg-amber-500' : actual ? 'bg-primary' : 'bg-muted',
                            )}
                        />
                        <span className="flex items-start gap-2">
                            <span
                                className={cn(
                                    'flex size-5 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold',
                                    hecho
                                        ? 'bg-emerald-500 text-white'
                                        : alerta
                                          ? 'bg-amber-500 text-white'
                                          : actual
                                            ? 'bg-primary text-primary-foreground'
                                            : 'bg-muted text-muted-foreground',
                                )}
                            >
                                {hecho ? <Check className="size-3" /> : alerta ? '!' : numero}
                            </span>
                            <span className="min-w-0 leading-tight">
                                <span
                                    className={cn(
                                        'block text-sm font-medium',
                                        !hecho && !actual && 'text-muted-foreground',
                                    )}
                                >
                                    {alerta ? 'Plazo vencido' : p.titulo}
                                </span>
                                <span className="hidden text-xs text-muted-foreground @md:block">
                                    {alerta ? 'Hay que generar otra liquidación' : p.detalle}
                                </span>
                            </span>
                        </span>
                    </li>
                );
            })}
        </ol>
    );
}
