import { BadgeCheck, Ban, CalendarX } from 'lucide-react';
import { HojaOficial } from '@/components/publico/hoja-oficial';
import { fecha, fechaHora } from '@/lib/utils';
import type { CarnetPublico, InstitucionPublica } from '@/types/publico';

/**
 *  EL ACTA DE VERIFICACIÓN DE UN CARNET
 */
export function FichaCarnet({
    carnet,
    institucion,
}: {
    carnet: CarnetPublico;
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

            <Sello carnet={carnet} />

            <div className="mt-6 space-y-3 font-serif text-[13px] leading-relaxed text-slate-700">
                <p className="text-justify">{carnet.mensaje}</p>
            </div>

            <dl className="mt-6 divide-y divide-slate-200 border-y border-slate-200">
                {/*
                    EL CÓDIGO va primero porque es el único dato de esta pantalla
                    que también está impreso en el plástico: es lo que el inspector
                    cruza para confirmar que el acta corresponde a la credencial que
                    tiene en la mano, y no a otra.
                */}
                <Renglon etiqueta="Código" valor={carnet.codigo} mono />
                <Renglon etiqueta="Titular" valor={carnet.titular ?? '—'} />
                {/*
                    La cédula llega ENMASCARADA desde PHP: solo los últimos tres
                    dígitos. Alcanza para que el inspector confirme contra el
                    documento que la persona le está mostrando, y no alcanza para
                    que alguien que encuentre un carnet tirado se haga con el
                    número completo.
                */}
                <Renglon etiqueta="Documento" valor={carnet.documento_titular} mono />
                <Renglon etiqueta="Gestión" valor={String(carnet.gestion)} />
                <Renglon etiqueta="Emitido" valor={fecha(carnet.fecha_emision)} />
                <Renglon etiqueta="Vence" valor={fecha(carnet.fecha_vencimiento)} />
            </dl>

            {/*
                LA ACTIVIDAD QUE EL CARNET AUTORIZA. Es UNA, no una lista: cada
                carnet habilita una sola actividad, y quien hace las dos tiene dos
                carnets con dos códigos distintos.
            */}
            {carnet.vigente && carnet.actividad && (
                <div className="mt-6">
                    <p className="font-serif text-[11px] tracking-[0.14em] text-slate-500 uppercase">
                        Actividad habilitada
                    </p>

                    <p className="mt-2 flex items-center gap-2 font-serif text-[13px] text-slate-800">
                        <BadgeCheck className="size-4 shrink-0 text-emerald-700" />
                        {carnet.actividad}
                    </p>

                    {/*
                        El cupo autorizado. Va debajo de la actividad y no en su
                        misma línea porque es el dato que un control contrasta
                        contra la guía de transporte: tiene que poder leerse solo.
                    */}
                    {carnet.cupo_kg !== null && (
                        <p className="mt-1.5 font-serif text-[13px] text-slate-600">
                            Cupo autorizado: <strong>{carnet.cupo_kg} kg</strong>
                        </p>
                    )}
                </div>
            )}
        </HojaOficial>
    );
}

/**
 * El sello de estado.
 */
function Sello({ carnet }: { carnet: CarnetPublico }) {
    if (carnet.vigente) {
        return (
            <Marco
                clase="border-emerald-700/40 bg-emerald-50 text-emerald-800"
                icono={<BadgeCheck className="size-7" />}
                texto="Vigente"
            />
        );
    }

    /*
     * REVOCADO TIENE SELLO PROPIO, y no es un detalle estético.
     */
    if (carnet.estado === 'revocado') {
        return (
            <Marco
                clase="border-rose-700/40 bg-rose-50 text-rose-800"
                icono={<Ban className="size-7" />}
                texto="Revocado"
            />
        );
    }

    return (
        <Marco
            clase="border-amber-700/40 bg-amber-50 text-amber-800"
            icono={<CalendarX className="size-7" />}
            texto="Vencido"
        />
    );
}

function Marco({
    clase,
    icono,
    texto,
}: {
    clase: string;
    icono: React.ReactNode;
    texto: string;
}) {
    return (
        <div className={`mt-5 flex items-center justify-center gap-3 border-2 py-4 ${clase}`}>
            {icono}
            <span className="font-serif text-2xl font-bold tracking-[0.12em] uppercase">{texto}</span>
        </div>
    );
}

function Renglon({ etiqueta, valor, mono = false }: { etiqueta: string; valor: string; mono?: boolean }) {
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
