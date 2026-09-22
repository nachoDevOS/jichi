import { Info } from 'lucide-react';
import { Seccion } from '@/components/publico/institucional/seccion';

/** El circuito del beneficiario, en el orden de docs/REGLAS-NEGOCIO.md. */
const PASOS = [
    {
        titulo: 'Regístrese como beneficiario',
        texto: 'Preséntese en ventanilla con su cédula de identidad. Se cargan sus datos personales y de contacto. Todo trámite nace de este registro.',
    },
    {
        titulo: 'Solicite su cupo de aprovechamiento',
        texto: 'Elija la escala que corresponde al volumen que va a extraer. La escala fija el techo en kilos y el costo en bolivianos.',
        soloPescador: true,
    },
    {
        titulo: 'Pague y presente el depósito',
        texto: 'El pago se hace por depósito bancario. Entregue la boleta en ventanilla: se le devuelve un recibo oficial con el número de trámite.',
    },
    {
        titulo: 'Retire su carnet',
        texto: 'Aprobado el trámite, se imprime el carnet con su código único y su código QR. Es anual: vence al cerrar la gestión.',
    },
    {
        titulo: 'Pida sus permisos cuando salga a trabajar',
        texto: 'Con el carnet en mano se emite un permiso de faena por cada salida, o una guía de movimiento por cada traslado.',
    },
];

export function Pasos() {
    return (
        <Seccion
            id="pasos"
            titulo="Cómo tramitar su carnet"
            bajada="El circuito completo, del registro al permiso de trabajo."
            oscuro
        >
            <ol className="mx-auto max-w-3xl">
                {PASOS.map((paso, i) => (
                    <li key={paso.titulo} className="flex gap-4 sm:gap-5">
                        {/* La columna del número: el círculo y la línea que lo
                            une con el siguiente. El último no lleva línea. */}
                        <div className="flex flex-col items-center">
                            <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-institucional-azul text-sm font-bold text-white">
                                {i + 1}
                            </span>
                            {i < PASOS.length - 1 && (
                                <i className="w-px flex-1 bg-slate-300" aria-hidden />
                            )}
                        </div>

                        <div className={i < PASOS.length - 1 ? 'pb-8' : ''}>
                            <h3 className="text-base font-semibold text-institucional-azul">
                                {paso.titulo}
                                {paso.soloPescador && (
                                    <span className="ml-2 rounded bg-emerald-100 px-1.5 py-0.5 align-middle text-[10px] font-semibold tracking-wide text-emerald-800 uppercase">
                                        Solo pescador
                                    </span>
                                )}
                            </h3>
                            <p className="mt-1.5 text-sm leading-relaxed text-slate-600">
                                {paso.texto}
                            </p>
                        </div>
                    </li>
                ))}
            </ol>

            <p className="mx-auto mt-8 flex max-w-3xl gap-3 rounded-lg border border-emerald-700/20 bg-emerald-50 p-4 text-sm leading-relaxed text-emerald-900">
                <Info className="mt-0.5 size-4.5 shrink-0" />
                <span>
                    Quien pesca y además comercializa necesita los{' '}
                    <strong>dos carnets</strong> en la misma gestión. El de comercializador se
                    tramita sin paso 2: no lleva cupo de extracción.
                </span>
            </p>
        </Seccion>
    );
}
