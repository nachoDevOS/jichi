import { BadgeCheck, CalendarX } from 'lucide-react';
import type { ReactNode } from 'react';
import { HojaOficial } from '@/components/publico/hoja-oficial';
import { fechaHora } from '@/lib/utils';
import type { DocumentoPublico, InstitucionPublica } from '@/types/publico';

/**
 *  EL ACTA DE VERIFICACIÓN DE CUALQUIER DOCUMENTO
 *
 *  Era `ficha-carnet.tsx` y solo sabía de carnets. Desde el 22/09/2026 el
 *  servidor manda RENGLONES ya resueltos —etiqueta, valor y si va en
 *  monoespaciada— así que sumar un tipo de documento nuevo no toca este
 *  archivo. Ver VerificacionController::renglones().
 */
export function FichaDocumento({
    documento,
    institucion,
}: {
    documento: DocumentoPublico;
    institucion: InstitucionPublica;
}) {
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

            {/* QUÉ documento es. Va antes del sello porque «vigente» sin saber
                de qué no dice nada: el acta puede ser de un carnet, de un cupo,
                de una faena, de una guía o de un recibo. */}
            <p className="mt-1.5 text-center font-serif text-[12px] tracking-[0.1em] text-slate-500 uppercase">
                {documento.tipo_etiqueta}
            </p>

            <Sello documento={documento} />

            <div className="mt-6 space-y-3 font-serif text-[13px] leading-relaxed text-slate-700">
                <p className="text-justify">{documento.mensaje}</p>
            </div>

            <dl className="mt-6 divide-y divide-slate-200 border-y border-slate-200">
                {/*
                    EL CÓDIGO va primero porque es el único dato de esta pantalla
                    que también está impreso en el papel: es lo que el inspector
                    cruza para confirmar que el acta corresponde al documento que
                    tiene en la mano, y no a otro.
                */}
                <Renglon etiqueta="Código" valor={documento.codigo ?? '—'} mono />
                <Renglon etiqueta="Titular" valor={documento.titular ?? '—'} />
                {/*
                    La cédula llega ENMASCARADA desde PHP: solo los últimos tres
                    dígitos. Alcanza para que el inspector confirme contra el
                    documento que la persona le muestra, y no alcanza para que
                    alguien que encuentre un papel tirado se haga con el número.
                */}
                <Renglon etiqueta="Documento" valor={documento.documento_titular} mono />

                {documento.renglones.map((r) => (
                    <Renglon key={r.etiqueta} etiqueta={r.etiqueta} valor={r.valor} mono={r.mono} />
                ))}
            </dl>
        </HojaOficial>
    );
}

/**
 * El sello de estado. No distingue por tipo: el servidor ya resolvió si el
 * documento habilita algo hoy.
 */
function Sello({ documento }: { documento: DocumentoPublico }) {
    if (documento.vigente) {
        return (
            <Marco
                clase="border-emerald-700/40 bg-emerald-50 text-emerald-800"
                icono={<BadgeCheck className="size-7" />}
                texto="Vigente"
            />
        );
    }

    return (
        <Marco
            clase="border-amber-700/40 bg-amber-50 text-amber-800"
            icono={<CalendarX className="size-7" />}
            texto={documento.estado_etiqueta}
        />
    );
}

function Marco({ clase, icono, texto }: { clase: string; icono: ReactNode; texto: string }) {
    return (
        <div className={`mt-5 flex items-center justify-center gap-3 border-2 py-4 ${clase}`}>
            {icono}
            <span className="font-serif text-2xl font-bold tracking-[0.12em] uppercase">{texto}</span>
        </div>
    );
}

function Renglon({
    etiqueta,
    valor,
    mono = false,
}: {
    etiqueta: string;
    valor: string;
    mono?: boolean;
}) {
    return (
        <div className="flex items-baseline justify-between gap-4 py-2">
            <dt className="shrink-0 font-serif text-[11px] tracking-[0.12em] text-slate-500 uppercase">
                {etiqueta}
            </dt>
            <dd
                className={
                    mono
                        ? 'text-right font-mono text-[12px] font-semibold tracking-wider break-all text-slate-900'
                        : 'text-right font-serif text-[13px] font-semibold text-slate-900'
                }
            >
                {valor}
            </dd>
        </div>
    );
}
