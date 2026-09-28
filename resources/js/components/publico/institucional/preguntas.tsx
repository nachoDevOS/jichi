import { ChevronDown } from 'lucide-react';
import { useState } from 'react';
import { Seccion } from '@/components/publico/institucional/seccion';

/** Las respuestas salen de docs/REGLAS-NEGOCIO.md, escritas sin tecnicismos. */
const PREGUNTAS = [
    {
        p: '¿Quién necesita carnet?',
        r: 'Toda persona que pesque en el departamento del Beni, y toda persona que acopie, traslade o venda producto pesquero. Son dos carnets distintos: el de pescador y el de comercializador. Quien hace las dos cosas necesita los dos.',
    },
    {
        p: '¿Cuánto dura el carnet?',
        r: 'Una gestión. La fecha de vencimiento va impresa en el carnet; al vencer hay que tramitar el de la gestión siguiente.',
    },
    {
        p: '¿Qué es la Autorización de Pesca para Aprovechamiento Pesquero?',
        r: 'Es la cantidad máxima de kilos que la Gobernación le autoriza a extraer en la gestión. Se otorga según una escala oficial, que también fija su costo. Cada permiso de faena descuenta sus kilos de ese total. Es requisito para sacar el carnet de pescador.',
    },
    {
        p: '¿El comercializador necesita esa autorización?',
        r: 'No. La autorización es para quien extrae, y el comercializador no extrae: acopia, traslada y vende. Su carnet se emite sin ella.',
    },
    {
        p: '¿Cuánto dura un permiso de faena o una guía?',
        r: 'El permiso de faena ampara una salida de pesca, con un máximo de 30 días desde que se firma. La Guía Única de Transporte ampara un traslado, con un máximo de 5 días. No son anuales: se piden cada vez.',
    },
    {
        p: '¿Cuánto cuesta la guía de transporte?',
        r: 'Depende de la carga: se paga un monto por cada kilo, según el producto que se traslada. Si el producto viene de piscicultura (criadero), se paga la mitad.',
    },
    {
        p: '¿Cómo se paga?',
        r: 'Por depósito bancario. La boleta se entrega en ventanilla y a cambio recibe un recibo oficial numerado. La Unidad controla el depósito contra el banco antes de aprobar el documento.',
    },
    {
        p: '¿Cómo sé si un documento es auténtico?',
        r: 'Escaneando su código QR con la cámara del celular, o escribiendo en esta página el código que figura junto al QR. El sistema responde si el documento existe, a nombre de quién está y si vale hoy. Un documento revocado o vencido aparece como no vigente.',
    },
    {
        p: 'Perdí mi carnet, ¿qué hago?',
        r: 'Acérquese a la Unidad de Pesca con su cédula de identidad. El carnet perdido se da de baja —así nadie más puede usarlo— y se emite uno nuevo, con código nuevo. Si es pescador, se emite con la misma autorización: los kilos que ya usó siguen descontados.',
    },
];

export function Preguntas() {
    // Una sola abierta a la vez: con nueve desplegadas la sección mide tres
    // pantallas y el contacto de abajo deja de encontrarse.
    const [abierta, setAbierta] = useState<number | null>(0);

    return (
        <Seccion id="preguntas" titulo="Preguntas frecuentes" oscuro>
            <div className="mx-auto max-w-3xl divide-y divide-slate-200 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-md">
                {PREGUNTAS.map((item, i) => (
                    <div key={item.p}>
                        <button
                            type="button"
                            onClick={() => setAbierta(abierta === i ? null : i)}
                            aria-expanded={abierta === i}
                            className="flex w-full items-center gap-3 px-5 py-4 text-left transition-colors hover:bg-slate-50 focus-visible:bg-slate-50 focus-visible:outline-none"
                        >
                            <span className="flex-1 text-sm font-semibold text-rio-profundo sm:text-base">
                                {item.p}
                            </span>
                            <ChevronDown
                                className={`size-4 shrink-0 text-slate-400 transition-transform ${
                                    abierta === i ? 'rotate-180 text-rio' : ''
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
