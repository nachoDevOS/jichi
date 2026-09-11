import { Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    Banknote,
    ExternalLink,
    FileText,
    IdCard,
    Paperclip,
    PencilLine,
    QrCode,
    TriangleAlert,
    UserRound,
} from 'lucide-react';
import { AccionesTramite } from '@/components/panel/tramites/acciones-tramite';
import { LineaTiempoTramite } from '@/components/panel/tramites/linea-tiempo-tramite';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { usePermisos } from '@/hooks/use-permisos';
import LayoutPanel from '@/layouts/layout-panel';
import { bs, fechaHora } from '@/lib/utils';
import type { TramiteDetalle } from '@/types/tramites';

/**
 * ============================================================================
 *  FICHA DEL TRÁMITE — revisar, aprobar, emitir y entregar
 * ============================================================================
 *
 * Registrar el trámite en ventanilla es la mitad del trabajo. La otra mitad la
 * hace otra persona, en otro momento: alguien tiene que abrir los papeles que
 * se adjuntaron, comprobar que el pago está, y recién entonces aprobar y
 * emitir la credencial.
 *
 * ----------------------------------------------------------------------------
 *  CÓMO ESTÁ REPARTIDA LA PANTALLA
 * ----------------------------------------------------------------------------
 *
 * A la izquierda, TODO lo que hay que mirar para decidir: quién es, qué pidió,
 * qué adjuntó y cuánto pagó. A la derecha, fija, la columna donde se decide:
 * por dónde va el trámite y los botones.
 *
 * Está así y no al revés porque revisar es una lectura larga —hay que abrir
 * archivos— y decidir es un clic. La columna de decisión no puede quedar abajo
 * de todo, obligando a scrollear de vuelta después de leer.
 */
interface Props {
    tramite: TramiteDetalle;
}

export default function VerTramite({ tramite }: Props) {
    const { puede } = usePermisos();

    return (
        <LayoutPanel
            titulo={`Trámite N° ${tramite.id}`}
            descripcion={`${tramite.tipo.nombre} · ${tramite.tipo.area}`}
            acciones={
                <div className="flex flex-wrap gap-2">
                    {/* Corregir solo mientras el expediente está en curso. La
                        condición sale del servidor (`puede_editarse`), no de
                        una lista de estados escrita acá. */}
                    {tramite.puede_editarse && puede('tramites.editar') && (
                        <Link href={route('tramites.edit', tramite.id)}>
                            <Button variant="outline">
                                <PencilLine className="size-4" />
                                Corregir
                            </Button>
                        </Link>
                    )}

                    <Link href={route('tramites.index')}>
                        <Button variant="outline">
                            <ArrowLeft className="size-4" />
                            Volver al listado
                        </Button>
                    </Link>
                </div>
            }
        >
            <Head title={`Trámite N° ${tramite.id}`} />

            <div className="grid gap-6 lg:grid-cols-[1fr_20rem] lg:items-start">
                {/* ===========================================================
                    COLUMNA IZQUIERDA — lo que hay que revisar
                    =========================================================== */}
                <div className="space-y-4">
                    <Card>
                        <CardContent className="space-y-4 pt-6">
                            <p className="flex items-center gap-1.5 text-sm font-semibold">
                                <UserRound className="size-4" />
                                Solicitante
                            </p>

                            <div className="flex flex-wrap items-start justify-between gap-4">
                                <div className="flex min-w-0 items-start gap-3">
                                    <span className="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-md border border-border bg-muted">
                                        {tramite.solicitante.foto_url ? (
                                            <img
                                                src={tramite.solicitante.foto_url}
                                                alt=""
                                                aria-hidden
                                                className="size-full object-cover"
                                            />
                                        ) : (
                                            <UserRound className="size-6 text-muted-foreground" />
                                        )}
                                    </span>

                                    <div className="min-w-0 space-y-1">
                                        <p className="font-semibold">
                                            {tramite.solicitante.nombreCompleto}
                                        </p>
                                        <p className="font-mono text-xs text-muted-foreground">
                                            C.I. {tramite.solicitante.documento_identidad}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {[
                                                tramite.solicitante.direccion,
                                                tramite.solicitante.ciudad,
                                                tramite.solicitante.provincia,
                                            ]
                                                .filter(Boolean)
                                                .join(' · ') || 'Sin domicilio cargado'}
                                        </p>
                                    </div>
                                </div>

                                <Link href={route('solicitantes.show', tramite.solicitante.id)}>
                                    <Button type="button" variant="ghost" size="sm">
                                        <ExternalLink className="size-4" />
                                        Ver ficha
                                    </Button>
                                </Link>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Los campos propios del servicio: asociación y cupo en la
                        cédula, embarcación y especies en los otros. Se recorren
                        genéricamente porque cada servicio guarda los suyos. */}
                    <Card>
                        <CardContent className="space-y-4 pt-6">
                            <p className="flex items-center gap-1.5 text-sm font-semibold">
                                <FileText className="size-4" />
                                Datos del servicio
                            </p>

                            <dl className="grid gap-x-6 gap-y-3 sm:grid-cols-2">
                                <Dato etiqueta="Servicio" valor={tramite.tipo.nombre} />
                                <Dato etiqueta="Vigencia" valor={tramite.tipo.vigencia} />

                                {Object.entries(tramite.datos_adicionales).map(
                                    ([clave, valor]) => (
                                        <Dato
                                            key={clave}
                                            etiqueta={etiquetar(clave)}
                                            valor={textoDe(valor)}
                                        />
                                    ),
                                )}
                            </dl>

                            {tramite.observaciones && (
                                <div className="rounded-lg border border-dashed border-border bg-muted/30 p-3">
                                    <p className="text-xs text-muted-foreground">Observaciones</p>
                                    <p className="text-sm">{tramite.observaciones}</p>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="space-y-4 pt-6">
                            <p className="flex items-center gap-1.5 text-sm font-semibold">
                                <Paperclip className="size-4" />
                                Requisitos adjuntos
                            </p>

                            {/* El enlace abre el archivo en otra pestaña: aprobar
                                mirando solo el nombre del archivo no es revisar
                                nada. Hay que poder leer la certificación. */}
                            {tramite.requisitos.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    Este trámite no lleva papeles adjuntos.
                                </p>
                            ) : (
                                <ul className="space-y-2">
                                    {tramite.requisitos.map((r) => (
                                        <li key={r.campo}>
                                            <a
                                                href={r.url}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="flex items-center justify-between gap-3 rounded-lg border border-border p-3 hover:bg-accent/40"
                                            >
                                                <span className="min-w-0">
                                                    <span className="block text-sm font-medium">
                                                        {r.etiqueta}
                                                    </span>
                                                    <span className="block truncate font-mono text-xs text-muted-foreground">
                                                        {r.archivo}
                                                    </span>
                                                </span>
                                                <ExternalLink className="size-4 shrink-0 text-muted-foreground" />
                                            </a>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="space-y-4 pt-6">
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <p className="flex items-center gap-1.5 text-sm font-semibold">
                                    <Banknote className="size-4" />
                                    Pagos
                                </p>

                                <p className="text-xs text-muted-foreground">
                                    Cobrado{' '}
                                    <span className="font-mono font-semibold text-foreground">
                                        {bs(tramite.monto_pagado)}
                                    </span>{' '}
                                    de {bs(tramite.monto_total)}
                                </p>
                            </div>

                            {/* El saldo se avisa acá y apaga el botón de emitir:
                                una credencial emitida ya está en la calle, y el
                                saldo se vuelve incobrable. */}
                            {!tramite.esta_pagado && (
                                <p className="flex items-center gap-1.5 rounded-lg bg-amber-500/10 px-3 py-2 text-xs text-amber-700 dark:text-amber-400">
                                    <TriangleAlert className="size-3.5 shrink-0" />
                                    Queda un saldo pendiente de {bs(tramite.saldo_pendiente)}.
                                </p>
                            )}

                            {tramite.pagos.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No hay pagos registrados.
                                </p>
                            ) : (
                                <ul className="space-y-2">
                                    {tramite.pagos.map((p, i) => (
                                        <li
                                            key={i}
                                            className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-border p-3"
                                        >
                                            <div className="min-w-0">
                                                <p className="text-sm font-medium">
                                                    {p.forma_etiqueta}
                                                    {p.banco && ` · ${p.banco}`}
                                                </p>
                                                <p className="font-mono text-xs text-muted-foreground">
                                                    {p.nro_transaccion ?? 'Sin número de transacción'}
                                                </p>
                                            </div>

                                            <div className="flex items-center gap-3">
                                                <span className="font-mono text-sm font-semibold">
                                                    {bs(p.monto)}
                                                </span>

                                                {p.url && (
                                                    <a
                                                        href={p.url}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        className="text-muted-foreground hover:text-foreground"
                                                        aria-label={`Ver comprobante del pago ${i + 1}`}
                                                    >
                                                        <ExternalLink className="size-4" />
                                                    </a>
                                                )}
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* ===========================================================
                    COLUMNA DERECHA — dónde se decide
                    =========================================================== */}
                <aside className="space-y-4 lg:sticky lg:top-6">
                    <Card>
                        <CardContent className="space-y-4 pt-6">
                            <div className="flex items-center justify-between gap-2">
                                <p className="text-sm font-semibold">Estado</p>
                                <Badge color={tramite.estado_color}>
                                    {tramite.estado_etiqueta}
                                </Badge>
                            </div>

                            <LineaTiempoTramite tramite={tramite} />
                        </CardContent>
                    </Card>

                    {/* El documento emitido, con su código de verificación. Es
                        lo que el inspector comprueba en la orilla del río. */}
                    {tramite.documento && (
                        <Card>
                            <CardContent className="space-y-3 pt-6">
                                <div className="flex items-center justify-between gap-2">
                                    <p className="flex items-center gap-1.5 text-sm font-semibold">
                                        <IdCard className="size-4" />
                                        Documento
                                    </p>
                                    <Badge color={tramite.documento.estado_color}>
                                        {tramite.documento.estado_etiqueta}
                                    </Badge>
                                </div>

                                <dl className="space-y-2">
                                    <Dato
                                        etiqueta="Código de verificación"
                                        valor={tramite.documento.codigo_verificacion}
                                        mono
                                    />
                                    <Dato
                                        etiqueta="Emitido"
                                        valor={tramite.documento.fecha_emision ?? '—'}
                                    />
                                    <Dato
                                        etiqueta="Vence"
                                        valor={tramite.documento.fecha_vencimiento ?? 'Sin vencimiento'}
                                    />
                                </dl>

                                <a
                                    href={tramite.documento.url_verificacion}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    <Button type="button" variant="outline" className="w-full">
                                        <QrCode className="size-4" />
                                        Ver verificación pública
                                    </Button>
                                </a>
                            </CardContent>
                        </Card>
                    )}

                    <Card>
                        <CardContent className="space-y-4 pt-6">
                            <p className="text-sm font-semibold">Acciones</p>
                            <AccionesTramite tramite={tramite} />
                        </CardContent>
                    </Card>

                    {tramite.modo_entrega && (
                        <p className="px-1 text-xs text-muted-foreground">
                            Entregado de forma{' '}
                            {tramite.modo_entrega === 'fisica' ? 'física, en mano' : 'digital'}
                            {tramite.hitos.entrega && ` el ${fechaHora(tramite.hitos.entrega.fecha)}`}
                            .
                        </p>
                    )}
                </aside>
            </div>
        </LayoutPanel>
    );
}

/** Un dato con su etiqueta chica arriba. */
function Dato({
    etiqueta,
    valor,
    mono = false,
}: {
    etiqueta: string;
    valor: string;
    mono?: boolean;
}) {
    return (
        <div>
            <dt className="text-xs text-muted-foreground">{etiqueta}</dt>
            <dd className={mono ? 'font-mono text-sm break-all' : 'text-sm'}>{valor}</dd>
        </div>
    );
}

/**
 * Convierte la clave de la columna jsonb en algo legible.
 *
 * Los campos propios de cada servicio se guardan con el nombre del input
 * —`capacidad_kg`, `region_desde`—, y esta pantalla los recorre sin saber
 * cuáles son: cada servicio guarda los suyos y la lista crece. Traducir cada
 * clave a mano en un mapa obligaría a tocar este archivo cada vez que un
 * formulario suma un campo, y el día que alguien se olvidara, el dato
 * simplemente no aparecería.
 */
function etiquetar(clave: string): string {
    const texto = clave.replaceAll('_', ' ');

    return texto.charAt(0).toUpperCase() + texto.slice(1);
}

/** Lo que venga de la columna jsonb, dicho como texto. */
function textoDe(valor: unknown): string {
    if (valor === null || valor === undefined || valor === '') {
        return '—';
    }

    if (Array.isArray(valor)) {
        return valor.length === 0 ? '—' : `${valor.length} ítem(s)`;
    }

    if (typeof valor === 'object') {
        return 'Ver expediente';
    }

    return String(valor);
}
