import { router, useForm, usePage } from '@inertiajs/react';
import { AlertTriangle, Banknote, FilePlus, Printer, QrCode, RefreshCw } from 'lucide-react';
import { useCallback, useState, type FormEvent } from 'react';
import { TextoCopiable } from '@/components/comunes/texto-copiable';
import { Badge } from '@/components/ui/badge';
import { Button, buttonVariants } from '@/components/ui/button';
import { Campo } from '@/components/ui/campo';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { SelectorArchivo } from '@/components/ui/selector-archivo';
import { ConfirmarNuevaLiquidacion } from '@/components/panel/pagos/confirmar-nueva-liquidacion';
import { ModalPagoQr, type DetallePago } from '@/components/panel/pagos/modal-pago-qr';
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
    const ofreceRenovar = puede(`${documento}.renovar-liquidacion`) && Boolean(sireb?.puede_renovar) && Boolean(rutaRenovar);
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
    const carga = useForm<{ numero_transaccion: string; banco: string; comprobante: File | null }>({
        numero_transaccion: '',
        banco: '',
        comprobante: null,
    });
    // Cómo se ofrece pagar: con el QR o cargando la transacción de un depósito.
    const [modo, setModo] = useState<'qr' | 'carga' | null>(null);
    const cargando = modo === 'carga';
    const setCargando = (abierto: boolean) => setModo(abierto ? 'carga' : null);
    const cerrarModal = useCallback(() => setModo(null), []);

    function cargar(e: FormEvent) {
        e.preventDefault();
        if (!rutaCargar) return;
        carga.post(rutaCargar, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                setCargando(false);
                carga.reset();
            },
        });
    }

    return (
        <Card className={cn('@container h-fit min-w-0', className)}>
            <CardHeader>
                <CardTitle>Pago en Recaudaciones</CardTitle>
            </CardHeader>

            <CardContent className="grid gap-6 text-sm @3xl:grid-cols-2">
                <div className="space-y-3">
                    <div className="flex items-baseline justify-between gap-3">
                        <span className="text-muted-foreground">Monto</span>
                        <span className="text-xl font-semibold tabular-nums">{bs(monto)}</span>
                    </div>

                    {sireb && (
                        <div className="flex items-center justify-between gap-2">
                            <span className="text-muted-foreground">Recaudaciones</span>
                            <Badge color={sireb.estado_color}>{sireb.estado_etiqueta}</Badge>
                        </div>
                    )}

                    {/* Con este código el titular paga en SIREB. */}
                    {sireb?.codigo_publico && (
                        <div className="flex items-center justify-between gap-3">
                            <span className="text-muted-foreground">Código de pago</span>
                            <TextoCopiable texto={sireb.codigo_publico} className="-mr-1.5" />
                        </div>
                    )}

                    {sireb?.caida && (
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
                                <p className="text-base font-bold">{sireb.caida === 'anulada' ? 'Cobro anulado' : 'Plazo de pago vencido'}</p>
                                <p className="font-medium">
                                    {sireb.caida === 'anulada' ? 'Recaudaciones anuló el cobro.' : 'Venció el plazo de pago.'} El trámite sigue
                                    pendiente: genere una nueva liquidación para cobrarlo.
                                </p>
                            </div>
                        </div>
                    )}

                    {/* Sin pago todavía: lo que se ve antes de cobrar. */}
                    {!recibo && !sireb?.pago && (
                        <>
                            {sireb?.codigo_publico && (
                                <div className="flex items-center justify-between gap-2">
                                    <span className="text-muted-foreground">Pago cargado</span>
                                    {sireb.pago_consultado ? (
                                        <Badge color="slate">No</Badge>
                                    ) : (
                                        <span className="text-muted-foreground">Sin verificar</span>
                                    )}
                                </div>
                            )}
                            <p className="text-muted-foreground">
                                Se paga en Recaudaciones. Cuando lo validen allá, queda aprobado y se emite el recibo.
                            </p>
                        </>
                    )}
                </div>

                <div className="space-y-3">
                    {/* El pago, cuando ya hay uno: a la derecha, junto a «Verificar pago». */}
                    {recibo ? (
                        <div className="space-y-2 rounded-md border p-3">
                            <div className="flex justify-between gap-3">
                                <span className="text-muted-foreground">Recibo</span>
                                <span className="font-mono font-medium">{recibo.numero_recibo}</span>
                            </div>
                            {recibo.numero_boleta && (
                                <div className="flex justify-between gap-3">
                                    <span className="text-muted-foreground">N° de transacción</span>
                                    <span className="text-right">
                                        {recibo.numero_boleta}
                                        {recibo.entidad_bancaria && <> · {recibo.entidad_bancaria}</>}
                                    </span>
                                </div>
                            )}
                            <div className="flex justify-between gap-3">
                                <span className="text-muted-foreground">Pagado</span>
                                <span>{recibo.fecha_pago ? fecha(recibo.fecha_pago) : fechaHora(recibo.emitido_en)}</span>
                            </div>
                            {sireb?.pago?.fecha_validacion && (
                                <div className="flex justify-between gap-3">
                                    <span className="text-muted-foreground">Validado el</span>
                                    <span>{fechaHora(sireb.pago.fecha_validacion)}</span>
                                </div>
                            )}
                            {puede('recibos.imprimir') && (
                                <a
                                    href={route('recibos.imprimir', recibo.id)}
                                    target="_blank"
                                    rel="noreferrer"
                                    className={cn(
                                        buttonVariants({
                                            variant: 'outline',
                                            size: 'sm',
                                        }),
                                        'w-full',
                                    )}
                                >
                                    <Printer className="size-4" />
                                    Imprimir recibo
                                </a>
                            )}
                        </div>
                    ) : sireb?.pago ? (
                        <div className="space-y-2 rounded-md border p-3">
                            <div className="flex items-center justify-between gap-2">
                                <span className="font-medium">Pago informado</span>
                                <Badge color={sireb.pago.estado === 'confirmado' ? 'emerald' : 'amber'}>
                                    {sireb.pago.estado === 'confirmado' ? 'Validado' : 'Por validar'}
                                </Badge>
                            </div>
                            <div className="flex justify-between gap-3">
                                <span className="text-muted-foreground">N° de transacción</span>
                                <span className="font-mono">{sireb.pago.numero_boleta ?? '—'}</span>
                            </div>
                            <div className="flex justify-between gap-3">
                                <span className="text-muted-foreground">Banco</span>
                                <span className="text-right">{sireb.pago.entidad_bancaria ?? '—'}</span>
                            </div>
                            <div className="flex justify-between gap-3">
                                <span className="text-muted-foreground">Fecha de pago</span>
                                <span>{sireb.pago.fecha_pago ? fecha(sireb.pago.fecha_pago) : '—'}</span>
                            </div>
                            <div className="flex justify-between gap-3">
                                <span className="text-muted-foreground">Monto pagado</span>
                                <span className="text-base tabular-nums">{bs(sireb.pago.monto_pagado)}</span>
                            </div>
                            {sireb.pago.fecha_validacion && (
                                <div className="flex justify-between gap-3">
                                    <span className="text-muted-foreground">Validado el</span>
                                    <span>{fechaHora(sireb.pago.fecha_validacion)}</span>
                                </div>
                            )}
                            <p className="text-xs text-muted-foreground">
                                Lo cargaron en Recaudaciones. Cuando lo validen allá, queda aprobado y se emite el recibo.
                            </p>
                        </div>
                    ) : null}
                    {/* Solo CARGA el pago en SIREB: validarlo sigue siendo de Recaudaciones. */}
                    {ofreceCarga && rutaCargar && (
                        <div className="space-y-2">
                            <p className="font-medium">¿Cómo paga?</p>
                            <div className="grid grid-cols-2 gap-2">
                                <OpcionPago
                                    key="opcion-qr"
                                    activa={modo === 'qr'}
                                    onClick={() => setModo(modo === 'qr' ? null : 'qr')}
                                    icono={<QrCode className="size-4" />}
                                    titulo="Pagar por QR"
                                    detalle="Desde la app del banco"
                                    tono="sky"
                                />
                                <OpcionPago
                                    key="opcion-carga"
                                    activa={cargando}
                                    onClick={() => setCargando(!cargando)}
                                    icono={<Banknote className="size-4" />}
                                    titulo="Cargar pago"
                                    detalle="Ya depositó en el banco"
                                    tono="emerald"
                                />
                            </div>
                        </div>
                    )}

                    {cargando && (
                        <form onSubmit={cargar} className="space-y-3 rounded-md border p-3">
                            <p className="font-medium">Cargar pago en Recaudaciones</p>
                            <Campo etiqueta="N° de transacción" htmlFor="numero_transaccion" error={carga.errors.numero_transaccion} obligatorio>
                                <Input
                                    id="numero_transaccion"
                                    value={carga.data.numero_transaccion}
                                    onChange={(e) => carga.setData('numero_transaccion', e.target.value)}
                                    aria-invalid={Boolean(carga.errors.numero_transaccion)}
                                    maxLength={50}
                                    autoFocus
                                />
                            </Campo>
                            <Campo etiqueta="Banco" htmlFor="banco" error={carga.errors.banco} obligatorio>
                                <Input
                                    id="banco"
                                    list="bancos-bolivia"
                                    value={carga.data.banco}
                                    onChange={(e) => carga.setData('banco', e.target.value)}
                                    aria-invalid={Boolean(carga.errors.banco)}
                                    maxLength={100}
                                />
                                <datalist id="bancos-bolivia">
                                    {BANCOS.map((b) => (
                                        <option key={b} value={b} />
                                    ))}
                                </datalist>
                            </Campo>
                            <Campo etiqueta="Comprobante (PDF o imagen, hasta 3 MB)" htmlFor="comprobante">
                                <SelectorArchivo
                                    id="comprobante"
                                    archivo={carga.data.comprobante}
                                    onElegir={(archivo) => carga.setData('comprobante', archivo)}
                                    error={carga.errors.comprobante}
                                />
                            </Campo>
                            <p className="text-xs text-muted-foreground">
                                Queda por validar en Recaudaciones. Cuando lo validen, el trámite se aprueba solo.
                            </p>
                            <div className="flex gap-2">
                                <Button key="enviar-carga" type="submit" className="flex-1" disabled={carga.processing}>
                                    Cargar
                                </Button>
                                <Button
                                    key="cancelar-carga"
                                    type="button"
                                    variant="outline"
                                    disabled={carga.processing}
                                    onClick={() => {
                                        setCargando(false);
                                        carga.reset();
                                        carga.clearErrors();
                                    }}
                                >
                                    Cancelar
                                </Button>
                            </div>
                        </form>
                    )}

                    {ofreceRenovar && rutaRenovar && (
                        <Button key="renovar" type="button" className="w-full" onClick={abrirRenovar}>
                            <FilePlus className="size-4" />
                            Generar nueva liquidación
                        </Button>
                    )}

                    {ofreceVerificar && (
                        <Button
                            variant="outline"
                            className="w-full"
                            disabled={verificacion.processing}
                            onClick={() =>
                                verificacion.post(rutaVerificar, {
                                    preserveScroll: true,
                                })
                            }
                        >
                            <RefreshCw className={cn('size-4', verificacion.processing && 'animate-spin')} />
                            Verificar pago
                        </Button>
                    )}
                </div>

                {sireb && sireb.historial.length > 0 && <HistorialLiquidaciones historial={sireb.historial} />}
            </CardContent>

            <ConfirmarNuevaLiquidacion
                abierto={confirmandoRenovar}
                cotizando={cotizando}
                cotizacion={cotizacion}
                procesando={renovacion.processing}
                onCerrar={cerrarRenovar}
                onConfirmar={() => rutaRenovar && renovacion.post(rutaRenovar, { preserveScroll: true, onFinish: cerrarRenovar })}
            />

            <ModalPagoQr
                abierto={modo === 'qr'}
                monto={monto}
                codigoPago={sireb?.codigo_publico ?? null}
                rutaConsultar={rutaConsultarQr}
                pago={pago}
                puedeVerificar={ofreceVerificar}
                verificando={verificacion.processing}
                onVerificar={() => verificacion.post(rutaVerificar, { preserveScroll: true, onSuccess: () => setModo(null) })}
                onCerrar={cerrarModal}
            />
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
            <summary className="cursor-pointer text-muted-foreground">Liquidaciones anteriores ({historial.length})</summary>
            <ul className="mt-3 space-y-2">
                {[...historial].reverse().map((l) => (
                    <li key={l.liquidacion_id} className="space-y-1 rounded-md border p-3">
                        <div className="flex items-center justify-between gap-2">
                            <span className="font-mono">{l.codigo_publico ?? '—'}</span>
                            <Badge color={ESTADO_LIQUIDACION[l.estado].color}>{ESTADO_LIQUIDACION[l.estado].etiqueta}</Badge>
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
                            {l.items.map((i) => `${i.producto ? i.producto + ': ' : ''}tarifa ${i.tarifa_id} a ${bs(i.precio)}`).join(' · ')}
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
            <span className={cn('flex size-8 shrink-0 items-center justify-center rounded-full', estilo.icono)}>{icono}</span>
            <span className="min-w-0 leading-tight">
                <span className={cn('block text-sm font-semibold', estilo.titulo)}>{titulo}</span>
                <span className="block text-xs text-muted-foreground">{detalle}</span>
            </span>
        </button>
    );
}

/** Sugerencias para el campo Banco: se puede escribir otro. */
const BANCOS = [
    'Banco Unión',
    'Banco Nacional de Bolivia (BNB)',
    'Banco Mercantil Santa Cruz',
    'Banco de Crédito de Bolivia (BCP)',
    'Banco Bisa',
    'Banco Ganadero',
    'Banco Económico',
    'Banco Sol',
    'Banco FIE',
    'Banco Fortaleza',
    'Banco Prodem',
    'Banco PYME de la Comunidad',
];
