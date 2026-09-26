import {
    Anchor,
    BadgeCheck,
    CalendarClock,
    CalendarX,
    Fish,
    IdCard,
    QrCode,
    Receipt,
    Truck,
    type LucideIcon,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { HojaOficial } from '@/components/publico/hoja-oficial';
import { cn, fechaHora, iniciales } from '@/lib/utils';
import type {
    DocumentoPublico,
    InstitucionPublica,
    RenglonPublico,
    VigenciaPublica,
} from '@/types/publico';

/**
 *  EL ACTA DE VERIFICACIÓN DE CUALQUIER DOCUMENTO
 *
 *  El servidor manda renglones ya resueltos, así que sumar un tipo de documento
 *  no toca este archivo. Ver VerificacionController::datosPublicos().
 */
const ICONOS: Record<DocumentoPublico['tipo'], LucideIcon> = {
    carnet: IdCard,
    aprovechamiento: Fish,
    faena: Anchor,
    guia: Truck,
    recibo: Receipt,
};

export function FichaDocumento({
    documento,
    institucion,
}: {
    documento: DocumentoPublico;
    institucion: InstitucionPublica;
}) {
    const Icono = ICONOS[documento.tipo] ?? IdCard;

    return (
        <HojaOficial
            institucion={institucion}
            pie={
                <p className="text-center font-serif text-[11px] text-slate-500 italic">
                    Consulta realizada el {fechaHora(new Date())}
                </p>
            }
        >
            <h1 className="mt-6 text-center font-serif text-[17px] leading-tight font-bold tracking-[0.08em] text-slate-800 uppercase">
                Constancia de verificación
            </h1>

            {/* Qué documento es va antes del sello: «vigente» sin saber de qué no dice nada. */}
            <p className="mt-2 flex items-center justify-center gap-1.5 font-serif text-[12px] tracking-[0.1em] text-slate-500 uppercase">
                <Icono className="size-4 text-emerald-800" />
                {documento.tipo_etiqueta}
            </p>

            <Sello documento={documento} />

            <p className="mt-4 text-center font-serif text-[13px] leading-relaxed text-slate-700">
                {documento.mensaje}
            </p>

            <Titular documento={documento} />

            {documento.vigencia && (
                <Vigencia vigencia={documento.vigencia} vigente={documento.vigente} />
            )}

            {documento.renglones.length > 0 && <Datos renglones={documento.renglones} />}

            <Codigo codigo={documento.codigo} />
        </HojaOficial>
    );
}

/** El sello de estado: el servidor ya resolvió si el documento habilita algo hoy. */
function Sello({ documento }: { documento: DocumentoPublico }) {
    const vigente = documento.vigente;

    return (
        <div
            className={cn(
                'mt-4 flex items-center justify-center gap-1.5 rounded-sm border px-3 py-1.5',
                vigente
                    ? 'border-emerald-700/30 bg-emerald-50 text-emerald-800'
                    : 'border-amber-700/30 bg-amber-50 text-amber-800',
            )}
        >
            {vigente ? <BadgeCheck className="size-4" /> : <CalendarX className="size-4" />}
            <span className="text-[12px] font-bold tracking-[0.1em] uppercase">
                {vigente ? 'Vigente' : documento.estado_etiqueta}
            </span>
        </div>
    );
}

// La cédula llega ENMASCARADA desde PHP: alcanza para cotejar con el documento, no para copiarla.
function Titular({ documento }: { documento: DocumentoPublico }) {
    if (!documento.titular) {
        return null;
    }

    return (
        <section className="mt-4 flex items-center gap-2.5 rounded-sm border border-slate-200 px-3 py-2">
            <span
                aria-hidden
                className="flex size-7 shrink-0 items-center justify-center rounded-full bg-emerald-900 text-[11px] font-bold text-white"
            >
                {iniciales(documento.titular)}
            </span>
            <div className="min-w-0 leading-tight">
                                <p className="font-serif text-[13px] font-bold text-slate-900">
                    {documento.titular}
                </p>
                <p className="font-mono text-[10px] text-slate-600">C.I. {documento.documento_titular}</p>
            </div>
        </section>
    );
}

// Es el único dato que también está impreso en el papel: con él se confirma que el acta es de ESE documento.
function Codigo({ codigo }: { codigo: string | null }) {
    return (
        <section className="mt-4 rounded-sm border border-dashed border-emerald-900/30 px-4 py-3 text-center">
            <p className="flex items-center justify-center gap-1.5 text-[10px] tracking-[0.14em] text-slate-500 uppercase">
                <QrCode className="size-3.5" />
                Código de verificación
            </p>
            <p className="mt-1 font-mono text-[17px] font-bold tracking-[0.12em] break-all text-slate-900">
                {codigo ?? '—'}
            </p>
            <p className="mt-1 text-[11px] text-slate-500">
                Debe coincidir con el impreso debajo del QR del documento.
            </p>
        </section>
    );
}

function Vigencia({ vigencia, vigente }: { vigencia: VigenciaPublica; vigente: boolean }) {
    const dias = vigencia.dias_restantes;
    // Sin vigencia —en revisión, anulado— contar días que «quedan» engañaría.
    const leyenda = !vigente && dias >= 0
        ? 'No habilitado'
        : dias > 1
            ? `Quedan ${dias} días`
            : dias === 1
              ? 'Queda 1 día'
              : dias === 0
                ? 'Vence hoy'
                : `Venció hace ${Math.abs(dias)} ${Math.abs(dias) === 1 ? 'día' : 'días'}`;

    return (
        <section className="mt-4 rounded-sm border border-slate-200 p-4">
            <div className="flex items-center justify-between gap-3">
                <p className="flex items-center gap-1.5 text-[10px] tracking-[0.14em] text-slate-500 uppercase">
                    <CalendarClock className="size-3.5" />
                    Vigencia
                </p>
                <span
                    className={cn(
                        'rounded-full px-2.5 py-0.5 text-[11px] font-semibold',
                        vigente && dias >= 0
                            ? 'bg-emerald-100 text-emerald-800'
                            : 'bg-amber-100 text-amber-800',
                    )}
                >
                    {leyenda}
                </span>
            </div>

            <div className="mt-3 h-2 overflow-hidden rounded-full bg-slate-200">
                <div
                    className={cn('h-full rounded-full', vigente ? 'bg-emerald-700' : 'bg-amber-600')}
                    style={{ width: `${Math.max(vigencia.avance, 2)}%` }}
                />
            </div>

            <div className="mt-2 flex justify-between gap-4">
                <Fecha etiqueta={vigencia.desde_etiqueta} valor={vigencia.desde} />
                <Fecha etiqueta={vigencia.hasta_etiqueta} valor={vigencia.hasta} derecha />
            </div>
        </section>
    );
}

function Fecha({ etiqueta, valor, derecha = false }: { etiqueta: string; valor: string; derecha?: boolean }) {
    return (
        <div className={derecha ? 'text-right' : ''}>
            <p className="text-[10px] tracking-[0.12em] text-slate-500 uppercase">{etiqueta}</p>
            <p className="font-serif text-[14px] font-bold text-slate-900">{valor}</p>
        </div>
    );
}

// Recuadros de dos columnas; un valor largo —un concepto, un destino— ocupa la fila entera.
function Datos({ renglones }: { renglones: RenglonPublico[] }) {
    return (
        <dl className="mt-4 grid grid-cols-2 gap-px overflow-hidden rounded-sm border border-slate-200 bg-slate-200">
            {renglones.map((r) => (
                <Recuadro key={r.etiqueta} renglon={r} />
            ))}
        </dl>
    );
}

function Recuadro({ renglon }: { renglon: RenglonPublico }): ReactNode {
    return (
        <div className={cn('bg-white px-3.5 py-3', renglon.valor.length > 18 && 'col-span-2')}>
            <dt className="text-[10px] tracking-[0.12em] text-slate-500 uppercase">{renglon.etiqueta}</dt>
            <dd
                className={cn(
                    'mt-0.5 font-bold break-words text-slate-900',
                    renglon.mono
                        ? 'font-mono text-[14px] tracking-wider'
                        : 'font-serif text-[15px]',
                )}
            >
                {renglon.valor}
            </dd>
        </div>
    );
}
