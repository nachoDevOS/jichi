import { BadgeCheck, Ban, CalendarX } from 'lucide-react';
import { HojaOficial } from '@/components/publico/hoja-oficial';
import { fecha, fechaHora } from '@/lib/utils';
import type { CarnetPublico, InstitucionPublica } from '@/types/publico';

/**
 * ============================================================================
 *  EL ACTA DE VERIFICACIÓN DE UN CARNET
 * ============================================================================
 *
 * Lo que ve el inspector cuando escanea el QR. Va sobre la hoja blanca con
 * membrete, por lo explicado en `hoja-oficial.tsx`: el ciudadano tiene el papel
 * en la mano y compara, y si la pantalla se parece a otro papel oficial la
 * comparación la hace cualquiera sin que le expliquen.
 *
 * ----------------------------------------------------------------------------
 *  EL SELLO ES LO PRIMERO Y LO MÁS GRANDE
 * ----------------------------------------------------------------------------
 *
 * La escena es un muelle, con sol, y el inspector mira el teléfono dos segundos.
 * Todo lo demás —nombre, gestión, rubros— es la letra chica que se lee si hace
 * falta; lo que tiene que entenderse de un vistazo es si el carnet vale o no.
 * Por eso el estado va arriba, en un sello grande y con color propio, y no como
 * una etiqueta más en una lista de datos.
 *
 * ----------------------------------------------------------------------------
 *  LOS RUBROS SOLO APARECEN SI EL CARNET ESTÁ VIGENTE
 * ----------------------------------------------------------------------------
 *
 * Un carnet vencido o anulado no habilita nada, así que listar sus rubros
 * —aunque fuera en gris— es pedirle al inspector que lea el sello y la lista al
 * mismo tiempo y saque la conclusión correcta. Con un carnet caído, la lista
 * directamente no está.
 *
 * Lo mismo adentro de la lista: los rubros SUSPENDIDOS no vienen desde el
 * servidor. Lo que no habilita, no aparece.
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
                    El REGISTRO va primero porque es el único dato de esta pantalla
                    que también está impreso en el plástico: es lo que el inspector
                    cruza para confirmar que el acta corresponde a la credencial que
                    tiene en la mano, y no a otra.
                */}
                <Renglon etiqueta="Registro" valor={carnet.registro} mono />
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

            {carnet.vigente && (
                <div className="mt-6">
                    <p className="font-serif text-[11px] tracking-[0.14em] text-slate-500 uppercase">
                        Actividades habilitadas
                    </p>

                    {carnet.rubros.length === 0 ? (
                        <p className="mt-2 font-serif text-[13px] text-slate-600 italic">
                            El carnet es auténtico pero no tiene ninguna actividad habilitada.
                        </p>
                    ) : (
                        <ul className="mt-2 space-y-1.5">
                            {carnet.rubros.map((rubro) => (
                                <li
                                    key={rubro}
                                    className="flex items-center gap-2 font-serif text-[13px] text-slate-800"
                                >
                                    <BadgeCheck className="size-4 shrink-0 text-emerald-700" />
                                    {rubro}
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            )}
        </HojaOficial>
    );
}

/**
 * El sello de estado.
 *
 * Los colores NO se arman juntando textos (`bg-${color}-50`): Tailwind solo
 * incluye en el CSS final las clases que puede leer literalmente en el código, y
 * una clase compuesta nunca llega a la hoja de estilos. El sello saldría sin
 * fondo y sin ningún error que lo explique.
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

    if (carnet.estado === 'anulado') {
        return (
            <Marco
                clase="border-rose-700/40 bg-rose-50 text-rose-800"
                icono={<Ban className="size-7" />}
                texto="Anulado"
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
