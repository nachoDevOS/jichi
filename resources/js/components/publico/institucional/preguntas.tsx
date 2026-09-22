import { ChevronDown } from 'lucide-react';
import { useState } from 'react';
import { Seccion } from '@/components/publico/institucional/seccion';

/** Las respuestas salen de docs/REGLAS-NEGOCIO.md, escritas sin tecnicismos. */
const PREGUNTAS = [
    {
        p: '¿Quién necesita el carnet?',
        r: 'Toda persona que extraiga producto pesquero en el departamento del Beni, y toda persona que lo acopie, traslade o venda. Son dos carnets distintos: el de pescador y el de comercializador.',
    },
    {
        p: '¿Cuánto dura el carnet?',
        r: 'Una gestión. Vence al cerrar el año y hay que tramitar el de la gestión siguiente. La fecha de vencimiento va impresa en el plástico.',
    },
    {
        p: '¿Qué es el cupo de aprovechamiento?',
        r: 'Es el volumen máximo, en kilos, que la Gobernación le autoriza a extraer durante la gestión. Se otorga según una escala oficial, que también fija cuánto cuesta. De ese total se va descontando lo que declara cada faena.',
    },
    {
        p: '¿El comercializador también necesita cupo?',
        r: 'No. El cupo autoriza extracción, y el comercializador no extrae: acopia, traslada y vende. Su carnet se emite sin cupo y no lleva ese dato impreso.',
    },
    {
        p: '¿Cuánto dura un permiso de faena o una guía?',
        r: 'El permiso de faena ampara una salida, con un máximo de 30 días. La guía de movimiento ampara un traslado, con un máximo de 5 días. No son anuales: se piden cada vez.',
    },
    {
        p: '¿Cómo se paga?',
        r: 'Por depósito bancario. La boleta se presenta en ventanilla, donde se controla contra el extracto del banco. Contra esa entrega se emite el recibo oficial, que es el comprobante del trámite.',
    },
    {
        p: '¿Cómo sé si un carnet es auténtico?',
        r: 'Escaneando el código QR del plástico, o escribiendo su código en esta misma página. El sistema responde si el carnet existe, si está vigente y qué actividad autoriza.',
    },
    {
        p: 'Perdí mi carnet, ¿qué hago?',
        r: 'Acérquese a la Unidad de Pesca del SEDAG con su cédula de identidad. El carnet se reimprime con el mismo código: el documento no cambia de identificador.',
    },
];

export function Preguntas() {
    // Una sola abierta a la vez: con ocho desplegadas la sección mide tres
    // pantallas y el contacto de abajo deja de encontrarse.
    const [abierta, setAbierta] = useState<number | null>(0);

    return (
        <Seccion id="preguntas" titulo="Preguntas frecuentes" oscuro>
            <div className="mx-auto max-w-3xl divide-y divide-slate-200 overflow-hidden rounded-xl border border-slate-200 bg-white">
                {PREGUNTAS.map((item, i) => (
                    <div key={item.p}>
                        <button
                            type="button"
                            onClick={() => setAbierta(abierta === i ? null : i)}
                            aria-expanded={abierta === i}
                            className="flex w-full items-center gap-3 px-5 py-4 text-left transition-colors hover:bg-slate-50"
                        >
                            <span className="flex-1 text-sm font-semibold text-institucional-azul">
                                {item.p}
                            </span>
                            <ChevronDown
                                className={`size-4 shrink-0 text-slate-400 transition-transform ${
                                    abierta === i ? 'rotate-180' : ''
                                }`}
                            />
                        </button>

                        {abierta === i && (
                            <p className="px-5 pb-4 text-sm leading-relaxed text-slate-600">
                                {item.r}
                            </p>
                        )}
                    </div>
                ))}
            </div>
        </Seccion>
    );
}
