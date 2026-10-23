import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    Ban,
    Banknote,
    BadgeCheck,
    CalendarClock,
    Hourglass,
    Eye,
    Fish,
    Pencil,
    Plus,
    Printer,
    Receipt,
    RefreshCw,
    Scale,
    Ship,
    Trash2,
    Truck,
    Wallet,
} from 'lucide-react';
import { useState } from 'react';
import { GraficoDisponible } from '@/components/panel/aprovechamientos/grafico-disponible';
import {
    Aviso,
    avisoVencimiento,
    BandaTitular,
    BotonPestana,
    Chip,
    Cifra,
    Conteo,
    EnlacePdf,
    EnlaceVer,
    kg,
    LineaTiempo,
    nivelDisponible,
    porcentaje,
    Tabla,
    TarjetaCodigo,
    Td,
} from '@/components/panel/comunes/ficha';
import { EnlacePermitido } from '@/components/panel/comunes/enlace-permitido';
import { TarjetaRecaudaciones } from '@/components/panel/pagos/tarjeta-recaudaciones';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ConfirmarConMotivo } from '@/components/ui/confirmar-con-motivo';
import { EstadoVacio } from '@/components/ui/estado-vacio';
import { useMontado } from '@/hooks/use-movimiento';
import { usePermisos } from '@/hooks/use-permisos';
import LayoutPanel from '@/layouts/layout-panel';
import { bs, cn, fecha } from '@/lib/utils';
import type { PageProps } from '@/types';
import type {
    CarnetDelCupo,
    CupoFicha,
    FaenaDelCupo,
    GuiaDelCupo,
    PuntoDisponible,
    ReciboDelCupo,
} from '@/types/aprovechamientos';

const NOMBRE = 'Autorización de Pesca para Aprovechamiento Pesquero';

// Los tramos de la barra, en orden. Mismos colores que los puntos del gráfico.
const TRAMOS = [
    { clave: 'kilos_vendidos', etiqueta: 'Vendido', color: 'bg-primary' },
    // Reservado en gris: el amarillo queda para avisar que el disponible baja.
    { clave: 'kilos_reservados', etiqueta: 'Reservado', color: 'bg-slate-400 dark:bg-slate-500' },
    { clave: 'kilos_disponibles', etiqueta: 'Disponible', color: 'bg-rio-claro dark:bg-rio' },
] as const;

type Pestana = 'carnets' | 'faenas' | 'guias';

export default function VerCupo({
    cupo,
    carnets,
    faenas,
    guias,
    evolucion,
    recibo,
    carnetParaFaena,
}: {
    cupo: CupoFicha;
    carnets: CarnetDelCupo[];
    faenas: FaenaDelCupo[];
    /** Las guías que le compraron pescado: cada una descontó sus kilos. */
    guias: GuiaDelCupo[];
    evolucion: PuntoDisponible[];
    /** Null mientras está pendiente: se emite cuando SIREB confirma el pago. */
    recibo: ReciboDelCupo | null;
    /** El carnet aprobado con el que se emite la próxima faena; null si ninguno puede. */
    carnetParaFaena: number | null;
}) {
    const { puede } = usePermisos();
    const { institucion } = usePage<PageProps>().props;

    // Abre en la primera pestaña con algo para mostrar.
    const [pestana, setPestana] = useState<Pestana>(() =>
        carnets.length > 0 || (faenas.length === 0 && guias.length === 0)
            ? 'carnets'
            : faenas.length > 0
              ? 'faenas'
              : 'guias',
    );
    const [eliminando, setEliminando] = useState(false);
    const [revocando, setRevocando] = useState(false);
    const [reponiendo, setReponiendo] = useState<CarnetDelCupo | null>(null);
    const borrado = useForm({ motivo: '' });
    const revocacion = useForm({ motivo: '' });
    const reposicion = useForm({ motivo: '' });

    const montado = useMontado();
    const pctDisponible = porcentaje(cupo.kilos_disponibles, cupo.volumen_total_kg);
    const nivel = nivelDisponible(pctDisponible, cupo.ya_fue_aprobado);
    const colorTramo = (clave: (typeof TRAMOS)[number]['clave'], color: string) =>
        clave === 'kilos_disponibles' ? nivel.barra : color;
    const vence = avisoVencimiento(cupo.dias_para_vencer, cupo.ya_fue_aprobado);

    const conHijos = cupo.ya_fue_aprobado || carnets.length + faenas.length + guias.length > 0;

    return (
        <LayoutPanel
            titulo={NOMBRE}
            acciones={
                <div className="flex flex-wrap gap-2">
                    {puede('beneficiarios.ver') && (
                        <Button
                            variant="ver"
                            onClick={() => router.visit(route('beneficiarios.show', cupo.beneficiario_id))}
                        >
                            <Eye className="size-4" />
                            Beneficiario
                        </Button>
                    )}
                    {puede('aprovechamientos.imprimir') && cupo.puede_imprimirse && (
                        <EnlacePdf href={route('aprovechamientos.autorizacion', cupo.id)} icono={Printer}>
                            Autorización
                        </EnlacePdf>
                    )}
                    {puede('recibos.imprimir') && recibo && (
                        <EnlacePdf href={route('recibos.imprimir', recibo.id)} icono={Receipt}>
                            Recibo
                        </EnlacePdf>
                    )}
                    {puede('aprovechamientos.editar') && cupo.puede_editarse && (
                        <Button variant="editar" onClick={() => router.visit(route('aprovechamientos.edit', cupo.id))}>
                            <Pencil className="size-4" />
                            Editar
                        </Button>
                    )}
                    {puede('aprovechamientos.eliminar') && cupo.puede_eliminarse && (
                        <Button variant="eliminar" onClick={() => setEliminando(true)}>
                            <Trash2 className="size-4" />
                            Eliminar
                        </Button>
                    )}
                    {puede('aprovechamientos.revocar') && cupo.puede_revocarse && (
                        <Button variant="eliminar" onClick={() => setRevocando(true)}>
                            <Ban className="size-4" />
                            Revocar
                        </Button>
                    )}
                </div>
            }
        >
            <Head title={`${NOMBRE} · ${cupo.beneficiario ?? ''}`} />

            <BandaTitular
                foto={cupo.foto_url}
                nombre={cupo.beneficiario}
                beneficiarioId={cupo.beneficiario_id}
                linea={`C.I. ${cupo.documento ?? '—'} · Autorización N° ${cupo.numero}`}
                estado={{ color: cupo.estado_color, etiqueta: cupo.estado_etiqueta }}
            >
                <Chip icono={Scale}>{cupo.descripcion ?? 'Sin escala'}</Chip>
                <Chip icono={Ship}>{cupo.tipo_embarcacion || 'Embarcación no declarada'}</Chip>
                <Chip icono={CalendarClock} tono={vence.tono}>
                    Vence el {fecha(cupo.fecha_vencimiento)}
                    {vence.texto && <span className="font-semibold">· {vence.texto}</span>}
                </Chip>
            </BandaTitular>

            {cupo.estado === 'pendiente' && (
                <p className="mt-4 flex items-start gap-2 rounded-xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-900 dark:border-sky-500/30 dark:bg-sky-500/10 dark:text-sky-200">
                    <Banknote className="mt-0.5 size-4 shrink-0" />
                    <span>
                        <strong>Pendiente de pago.</strong> Todavía no autoriza a pescar: falta que se pague y se valide
                        en Recaudaciones. Mientras tanto se puede corregir o eliminar.
                    </span>
                </p>
            )}

            <div className="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
                <Cifra
                    icono={Fish}
                    etiqueta="Disponible"
                    valor={<Conteo valor={cupo.kilos_disponibles} formato={kg} />}
                    pie={
                        cupo.ya_fue_aprobado && cupo.kilos_disponibles <= 0
                            ? 'Sin kilos: las guías nuevas se frenan'
                            : `${Math.round(pctDisponible)}% de ${kg(cupo.volumen_total_kg)}${nivel.texto ? ` · ${nivel.texto}` : ''}`
                    }
                    destacada
                    tono={nivel.tono}
                />
                <Cifra
                    icono={Truck}
                    etiqueta="Vendido en guías"
                    valor={<Conteo valor={cupo.kilos_vendidos} formato={kg} />}
                    pie="Guías aprobadas: no vuelve"
                />
                <Cifra
                    icono={Hourglass}
                    etiqueta="Reservado"
                    valor={<Conteo valor={cupo.kilos_reservados} formato={kg} />}
                    pie="Guías esperando el pago"
                />
                <Cifra
                    icono={Wallet}
                    etiqueta="Monto"
                    valor={<Conteo valor={cupo.monto} formato={(n) => bs(n, institucion.moneda)} />}
                    pie={recibo ? `Recibo N° ${recibo.numero_recibo}` : 'Sin recibo todavía'}
                />
            </div>

            <div className="mt-6 grid gap-6 lg:grid-cols-3">
                <Card className="min-w-0 lg:col-span-2">
                    <CardHeader>
                        <CardTitle>Volumen</CardTitle>
                        <p className="text-sm text-muted-foreground">
                            Lo bajan las guías de quien le compra; cada faena sale con lo disponible y no descuenta.
                        </p>
                    </CardHeader>

                    <CardContent className="space-y-5">
                        <div className="space-y-2">
                            <div className="flex h-3 gap-0.5 overflow-hidden rounded-full bg-secondary">
                                {TRAMOS.map((t) => (
                                    <div
                                        key={t.clave}
                                        className={cn(
                                            'transition-[width] duration-1000 ease-out motion-reduce:transition-none',
                                            colorTramo(t.clave, t.color),
                                            t.clave === 'kilos_disponibles' && 'animar-brillo',
                                        )}
                                        style={{
                                            width: montado
                                                ? `${porcentaje(cupo[t.clave], cupo.volumen_total_kg)}%`
                                                : '0%',
                                        }}
                                        title={`${t.etiqueta}: ${kg(cupo[t.clave])}`}
                                    />
                                ))}
                            </div>
                            <ul className="flex flex-wrap gap-x-5 gap-y-1 text-xs text-muted-foreground">
                                {TRAMOS.map((t) => (
                                    <li key={t.clave} className="flex items-center gap-1.5">
                                        <i
                                            className={cn('size-2.5 rounded-full', colorTramo(t.clave, t.color))}
                                            aria-hidden
                                        />
                                        {t.etiqueta}{' '}
                                        <span className="font-medium text-foreground tabular-nums">
                                            {kg(cupo[t.clave])}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </div>

                        {cupo.ya_fue_aprobado && <GraficoDisponible puntos={evolucion} total={cupo.volumen_total_kg} />}
                    </CardContent>
                </Card>

                <Card className="h-fit">
                    <CardHeader>
                        <CardTitle>El documento</CardTitle>
                    </CardHeader>

                    <CardContent className="space-y-5 text-sm">
                        <TarjetaCodigo
                            titulo="Código de autorización"
                            codigo={cupo.codigo}
                            numero={cupo.numero}
                            ayuda="Va en el QR del papel. Con él se verifica la autorización y el comercializador declara a quién le compró."
                        />

                        <LineaTiempo
                            hitos={[
                                { etiqueta: 'Solicitada', fecha: cupo.fecha_solicitud, hecho: true },
                                { etiqueta: 'Otorgada', fecha: cupo.fecha_emision, hecho: cupo.fecha_emision !== null },
                                {
                                    etiqueta: 'Vence',
                                    fecha: cupo.fecha_vencimiento,
                                    hecho: !cupo.vigente && cupo.ya_fue_aprobado,
                                },
                            ]}
                        />

                        <Aviso tono={cupo.puede_emitir_faena ? 'ok' : 'aviso'} icono={Ship}>
                            {cupo.puede_emitir_faena ? 'Habilitada para emitir faenas.' : cupo.motivo_sin_faena}
                        </Aviso>
                    </CardContent>
                </Card>
            </div>

            <TarjetaRecaudaciones
                className="mt-6"
                monto={cupo.monto}
                pago={{
                    concepto: NOMBRE,
                    numero: cupo.numero,
                    titular: cupo.beneficiario,
                    documento: cupo.documento,
                    detalle: kg(cupo.volumen_total_kg),
                }}
                sireb={cupo.sireb}
                recibo={recibo}
                puedeVerificar={cupo.puede_verificar_pago}
                rutaVerificar={route('aprovechamientos.verificar-pago', cupo.id)}
                puedeCargar={cupo.puede_cargar_pago}
                rutaCargar={route('aprovechamientos.cargar-pago', cupo.id)}
                rutaConsultarQr={route('aprovechamientos.consultar-qr', cupo.id)}
                rutaRenovar={route('aprovechamientos.renovar-liquidacion', cupo.id)}
                documento="aprovechamientos"
            />

            {/* Lo que cuelga de la autorización, en pestañas: antes eran tres tablas una debajo de otra. */}
            {conHijos && (
                <Card className="mt-6 min-w-0 overflow-hidden">
                    <div className="flex flex-wrap items-center justify-between gap-3 border-b border-border px-4">
                        <div
                            role="tablist"
                            aria-label="Papeles de la autorización"
                            className="flex gap-1 overflow-x-auto"
                        >
                            <BotonPestana
                                activa={pestana === 'carnets'}
                                onClick={() => setPestana('carnets')}
                                icono={BadgeCheck}
                                cantidad={carnets.length}
                            >
                                Carnets
                            </BotonPestana>
                            <BotonPestana
                                activa={pestana === 'faenas'}
                                onClick={() => setPestana('faenas')}
                                icono={Ship}
                                cantidad={faenas.length}
                            >
                                Faenas
                            </BotonPestana>
                            <BotonPestana
                                activa={pestana === 'guias'}
                                onClick={() => setPestana('guias')}
                                icono={Truck}
                                cantidad={guias.length}
                            >
                                Guías que le compraron
                            </BotonPestana>
                        </div>

                        {pestana === 'carnets' && puede('carnets.crear') && cupo.puede_emitir_carnet && (
                            <Button
                                size="sm"
                                onClick={() => router.visit(route('carnets.create', { aprovechamiento: cupo.id }))}
                            >
                                <Plus className="size-4" />
                                Emitir carnet
                            </Button>
                        )}
                        {pestana === 'faenas' && puede('faenas.crear') && carnetParaFaena !== null && (
                            <Button
                                size="sm"
                                onClick={() => router.visit(route('faenas.create', { carnet: carnetParaFaena }))}
                            >
                                <Plus className="size-4" />
                                Emitir faena
                            </Button>
                        )}
                    </div>

                    <div role="tabpanel">
                        {pestana === 'carnets' &&
                            (carnets.length === 0 ? (
                                <EstadoVacio
                                    icono={BadgeCheck}
                                    titulo="Sin carnets"
                                    descripcion="Todavía no se emitió ningún carnet con esta autorización."
                                />
                            ) : (
                                <Tabla columnas={['Registro', 'Emitido', 'Vence', 'Estado', '']}>
                                    {carnets.map((c) => (
                                        <tr key={c.id} className="hover:bg-secondary/50">
                                            <Td>
                                                <span className="font-mono font-medium">{c.registro ?? c.codigo}</span>
                                                <p className="text-xs text-muted-foreground">{c.tipo_actor_etiqueta}</p>
                                            </Td>
                                            <Td apagada>{c.fecha_emision ? fecha(c.fecha_emision) : '—'}</Td>
                                            <Td apagada>{fecha(c.fecha_vencimiento)}</Td>
                                            <Td>
                                                <Badge color={c.estado_color}>{c.estado_etiqueta}</Badge>
                                            </Td>
                                            <Td>
                                                <div className="flex justify-end gap-2">
                                                    {puede('carnets.crear') && c.puede_reponerse && (
                                                        <Button
                                                            size="sm"
                                                            variant="eliminar"
                                                            onClick={() => setReponiendo(c)}
                                                        >
                                                            <RefreshCw className="size-4" />
                                                            Reponer
                                                        </Button>
                                                    )}
                                                    {puede('carnets.imprimir') && c.puede_imprimirse && (
                                                        <EnlacePdf
                                                            href={route('carnets.imprimir', c.id)}
                                                            icono={Printer}
                                                            chico
                                                        >
                                                            Imprimir
                                                        </EnlacePdf>
                                                    )}
                                                    {puede('carnets.ver') && (
                                                        <EnlaceVer href={route('carnets.show', c.id)} />
                                                    )}
                                                </div>
                                            </Td>
                                        </tr>
                                    ))}
                                </Tabla>
                            ))}

                        {pestana === 'faenas' &&
                            (faenas.length === 0 ? (
                                <EstadoVacio
                                    icono={Ship}
                                    titulo="Sin faenas"
                                    descripcion="Todavía no se emitió ninguna salida de pesca con esta autorización."
                                />
                            ) : (
                                <Tabla columnas={['N°', 'Carnet', 'Kilos', 'Salida', 'Desembarque', 'Estado', '']}>
                                    {faenas.map((f) => (
                                        <tr key={f.id} className="hover:bg-secondary/50">
                                            <Td>
                                                <span className="font-mono font-medium">{f.numero_legible}</span>
                                            </Td>
                                            <Td apagada>
                                                {f.carnet_id !== null ? (
                                                    <EnlacePermitido
                                                        permiso="carnets.ver"
                                                        href={route('carnets.show', f.carnet_id)}
                                                        className="font-mono text-primary hover:underline"
                                                    >
                                                        N° {f.carnet_registro ?? '—'}
                                                    </EnlacePermitido>
                                                ) : (
                                                    '—'
                                                )}
                                            </Td>
                                            <Td>
                                                <span className="tabular-nums">{kg(f.kilos_extraidos)}</span>
                                            </Td>
                                            <Td apagada>{fecha(f.fecha_salida)}</Td>
                                            <Td apagada>{fecha(f.fecha_desembarque)}</Td>
                                            <Td>
                                                <Badge color={f.estado_color}>{f.estado_etiqueta}</Badge>
                                            </Td>
                                            <Td>
                                                <div className="flex justify-end gap-2">
                                                    {puede('faenas.imprimir') && f.puede_imprimirse && (
                                                        <EnlacePdf
                                                            href={route('faenas.imprimir', f.id)}
                                                            icono={Printer}
                                                            chico
                                                        >
                                                            Imprimir
                                                        </EnlacePdf>
                                                    )}
                                                    {puede('faenas.ver') && (
                                                        <EnlaceVer href={route('faenas.show', f.id)} />
                                                    )}
                                                </div>
                                            </Td>
                                        </tr>
                                    ))}
                                </Tabla>
                            ))}

                        {pestana === 'guias' &&
                            (guias.length === 0 ? (
                                <EstadoVacio
                                    icono={Truck}
                                    titulo="Sin guías"
                                    descripcion="Ningún comercializador declaró todavía pescado de esta autorización."
                                />
                            ) : (
                                <Tabla columnas={['N°', 'Comercializador', 'Kilos', 'Solicitada', 'Estado', '']}>
                                    {guias.map((g) => (
                                        <tr key={g.id} className="hover:bg-secondary/50">
                                            <Td>
                                                <span className="font-mono font-medium">{g.numero_legible}</span>
                                            </Td>
                                            <Td>{g.comercializador ?? '—'}</Td>
                                            <Td>
                                                <span className="tabular-nums">{kg(g.peso_total_kg)}</span>
                                            </Td>
                                            <Td apagada>{fecha(g.fecha_solicitud)}</Td>
                                            <Td>
                                                <Badge color={g.estado_color}>{g.estado_etiqueta}</Badge>
                                            </Td>
                                            <Td>
                                                <div className="flex justify-end">
                                                    {puede('guias.ver') && (
                                                        <EnlaceVer href={route('guias.show', g.id)} />
                                                    )}
                                                </div>
                                            </Td>
                                        </tr>
                                    ))}
                                </Tabla>
                            ))}
                    </div>
                </Card>
            )}

            {/* Reponer revoca el carnet y abre el formulario del nuevo con esta autorización. */}
            <ConfirmarConMotivo
                abierto={reponiendo !== null}
                titulo={`Reponer el carnet N° ${reponiendo?.registro ?? ''}`}
                descripcion={
                    <div className="space-y-2">
                        <p>
                            El carnet <strong>{reponiendo?.codigo}</strong> de{' '}
                            <strong>{cupo.beneficiario ?? 'el titular'}</strong> deja de valer, y al escanearlo figura
                            como revocado. <strong>No se revierte.</strong>
                        </p>
                        <p>
                            Las faenas ya emitidas con él <strong>siguen vigentes</strong>. Después se abre el
                            formulario del carnet nuevo con esta misma autorización.
                        </p>
                    </div>
                }
                etiquetaMotivo="Motivo de la reposición"
                ayuda="Queda en la auditoría con su nombre."
                placeholder="Extravío del carnet: el titular tramita la reposición."
                confirmacion="Entiendo que el carnet deja de valer y que la revocación no se revierte."
                textoConfirmar="Reponer carnet"
                valor={reposicion.data.motivo}
                onCambiar={(v) => reposicion.setData('motivo', v)}
                error={reposicion.errors.motivo}
                procesando={reposicion.processing}
                onCancelar={() => {
                    setReponiendo(null);
                    reposicion.reset();
                }}
                onConfirmar={() => reponiendo && reposicion.patch(route('carnets.reponer', reponiendo.id))}
            />

            <ConfirmarConMotivo
                abierto={revocando}
                titulo={`Revocar la ${NOMBRE}`}
                descripcion={
                    <div className="space-y-2">
                        <p>
                            La autorización de <strong>{cupo.beneficiario ?? 'el pescador'}</strong> queda REVOCADA
                            aunque siga en fecha.
                        </p>
                        <ul className="list-disc space-y-1 pl-5">
                            <li>No se emiten más carnets ni faenas con ella.</li>
                            <li>
                                Sus carnets y faenas aprobados <strong>quedan sin efecto</strong>: ya no están vigentes,
                                el QR lo informa y no se imprimen.
                            </li>
                            <li>
                                Los carnets, faenas y guías pendientes con ella ya no se aprueban, aunque se paguen.
                            </li>
                            <li>La persona puede tramitar una autorización nueva.</li>
                        </ul>
                        <p>
                            <strong>No se puede deshacer.</strong>
                        </p>
                    </div>
                }
                etiquetaMotivo="Motivo de la revocación"
                ayuda="Queda en la auditoría con su nombre. Es lo que explica la sanción más adelante."
                placeholder="Resolución SEDAG N° 045/2026: pesca en época de veda."
                confirmacion="Entiendo que la autorización deja de valer y que esto no se deshace desde el panel."
                textoConfirmar="Revocar"
                valor={revocacion.data.motivo}
                onCambiar={(v) => revocacion.setData('motivo', v)}
                error={revocacion.errors.motivo}
                procesando={revocacion.processing}
                onCancelar={() => {
                    setRevocando(false);
                    revocacion.reset();
                }}
                onConfirmar={() =>
                    revocacion.patch(route('aprovechamientos.revocar', cupo.id), {
                        preserveScroll: true,
                        onSuccess: () => {
                            setRevocando(false);
                            revocacion.reset();
                        },
                    })
                }
            />

            <ConfirmarConMotivo
                abierto={eliminando}
                titulo="Eliminar esta autorización"
                descripcion={
                    <div className="space-y-2">
                        <p>
                            Se da de baja la autorización de <strong>{cupo.beneficiario ?? 'el pescador'}</strong>:{' '}
                            {kg(cupo.volumen_total_kg)}.
                        </p>
                        <p>
                            Solo se puede porque está <strong>pendiente de pago</strong>. No se deshace.
                        </p>
                    </div>
                }
                etiquetaMotivo="Motivo de la eliminación"
                ayuda="Queda en la auditoría con su nombre."
                placeholder="Cargada por error: la escala corresponde a otro pescador."
                textoConfirmar="Eliminar autorización"
                confirmacion="Entiendo que la autorización desaparece del sistema y que esto no se deshace desde el panel."
                valor={borrado.data.motivo}
                onCambiar={(v) => borrado.setData('motivo', v)}
                error={borrado.errors.motivo}
                procesando={borrado.processing}
                onCancelar={() => {
                    setEliminando(false);
                    borrado.reset();
                }}
                onConfirmar={() =>
                    borrado.delete(route('aprovechamientos.destroy', cupo.id), {
                        preserveScroll: true,
                        // Borrada va al listado; con el pago ya validado vuelve acá aprobada.
                        onSuccess: () => {
                            setEliminando(false);
                            borrado.reset();
                        },
                        onError: () => setEliminando(true),
                    })
                }
            />
        </LayoutPanel>
    );
}
