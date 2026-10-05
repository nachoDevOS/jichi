import { useForm } from '@inertiajs/react';
import { Banknote, Printer, QrCode, RefreshCw } from 'lucide-react';
import { useCallback, useState, type FormEvent } from 'react';
import { TextoCopiable } from '@/components/comunes/texto-copiable';
import { Badge } from '@/components/ui/badge';
import { Button, buttonVariants } from '@/components/ui/button';
import { Campo } from '@/components/ui/campo';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { ModalPagoQr, type DetallePago } from '@/components/panel/pagos/modal-pago-qr';
import { usePermisos } from '@/hooks/use-permisos';
import { bs, cn, fecha, fechaHora } from '@/lib/utils';
import type { ReciboDelCupo, VentaSireb } from '@/types/aprovechamientos';

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
    permiso,
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
    /** El permiso de los botones: el `crear` del documento. */
    permiso: string;
    /** Qué se paga, para el detalle de la ventana del QR. */
    pago?: DetallePago;
    className?: string;
}) {
    const { puede } = usePermisos();
    const verificacion = useForm({});
    const carga = useForm({ numero_transaccion: '', banco: '' });
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
                        <span className="text-lg font-semibold tabular-nums">{bs(monto)}</span>
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
                                <span className="tabular-nums">{bs(sireb.pago.monto_pagado)}</span>
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
                    {puede(permiso) && puedeCargar && rutaCargar && (
                        <div className="space-y-2">
                            <p className="font-medium">¿Cómo paga?</p>
                            <div className="grid grid-cols-2 gap-2">
                                <OpcionPago
                                    key="opcion-qr"
                                    activa={modo === 'qr'}
                                    onClick={() => setModo(modo === 'qr' ? null : 'qr')}
                                    icono={<QrCode className="size-5" />}
                                    titulo="Pagar por QR"
                                    detalle="Desde la app del banco"
                                />
                                <OpcionPago
                                    key="opcion-carga"
                                    activa={cargando}
                                    onClick={() => setCargando(!cargando)}
                                    icono={<Banknote className="size-5" />}
                                    titulo="Cargar pago"
                                    detalle="Ya depositó en el banco"
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

                    {puede(permiso) && puedeVerificar && (
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
            </CardContent>

            <ModalPagoQr
                abierto={modo === 'qr'}
                monto={monto}
                codigoPago={sireb?.codigo_publico ?? null}
                pago={pago}
                puedeVerificar={puede(permiso) && puedeVerificar}
                verificando={verificacion.processing}
                onVerificar={() => verificacion.post(rutaVerificar, { preserveScroll: true, onSuccess: () => setModo(null) })}
                onCerrar={cerrarModal}
            />
        </Card>
    );
}

function OpcionPago({
    activa,
    onClick,
    icono,
    titulo,
    detalle,
}: {
    activa: boolean;
    onClick: () => void;
    icono: React.ReactNode;
    titulo: string;
    detalle: string;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-pressed={activa}
            className={cn(
                'flex flex-col items-center gap-1 rounded-lg border p-3 text-center transition-colors hover:bg-muted/50',
                activa && 'border-primary bg-primary/5 ring-1 ring-primary',
            )}
        >
            <span className={cn('text-muted-foreground', activa && 'text-primary')}>{icono}</span>
            <span className="font-medium">{titulo}</span>
            <span className="text-xs text-muted-foreground">{detalle}</span>
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
