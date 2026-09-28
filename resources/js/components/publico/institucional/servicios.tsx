import { Fish, Info, Truck } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { Aparecer } from '@/components/publico/institucional/aparecer';
import { Seccion } from '@/components/publico/institucional/seccion';

type Documento = { nombre: string; texto: string; vigencia: string };

/**
 * Los dos caminos, en el orden en que se sacan los documentos. Los nombres,
 * plazos y condiciones salen de docs/REGLAS-NEGOCIO.md: si la regla cambia, esto cambia con ella.
 */
const CAMINOS: {
    id: string;
    icono: LucideIcon;
    titulo: string;
    bajada: string;
    tono: { cabecera: string; numero: string; chip: string };
    documentos: Documento[];
}[] = [
    {
        id: 'pescador',
        icono: Fish,
        titulo: 'Si usted pesca',
        bajada: 'Para extraer producto de ríos y lagunas del departamento.',
        tono: {
            cabecera: 'bg-linear-to-r from-rio to-selva',
            numero: 'bg-rio text-white',
            chip: 'bg-rio-espuma text-rio-profundo ring-rio/20',
        },
        documentos: [
            {
                nombre: 'Registro de beneficiario',
                texto: 'Se hace una sola vez, con su cédula de identidad. De este registro nacen todos sus trámites.',
                vigencia: 'Una sola vez',
            },
            {
                nombre: 'Autorización de Pesca para Aprovechamiento Pesquero',
                texto: 'Fija cuántos kilos puede extraer en la gestión, según la escala oficial, que también fija el costo. Se tiene una a la vez.',
                vigencia: 'Por gestión',
            },
            {
                nombre: 'Carnet de Pescador',
                texto: 'Su credencial, con el aval de su asociación. Lleva impresos los kilos autorizados y un código QR.',
                vigencia: 'Por gestión',
            },
            {
                nombre: 'Permiso de Faena',
                texto: 'Uno por cada salida de pesca. Los kilos del permiso se descuentan de su autorización.',
                vigencia: 'Hasta 30 días',
            },
        ],
    },
    {
        id: 'comercializador',
        icono: Truck,
        titulo: 'Si usted comercializa',
        bajada: 'Para acopiar, trasladar y vender producto pesquero.',
        tono: {
            cabecera: 'bg-linear-to-r from-rio-profundo to-institucional-azul-claro',
            numero: 'bg-rio-profundo text-white',
            chip: 'bg-sky-50 text-sky-800 ring-sky-700/15',
        },
        documentos: [
            {
                nombre: 'Registro de beneficiario',
                texto: 'Se hace una sola vez, con su cédula de identidad. Si ya está registrado como pescador, no se repite.',
                vigencia: 'Una sola vez',
            },
            {
                nombre: 'Carnet de Comercializador',
                texto: 'Su credencial, con el aval de su asociación. No necesita la Autorización de Pesca para Aprovechamiento Pesquero: usted no extrae.',
                vigencia: 'Por gestión',
            },
            {
                nombre: 'Guía Única de Transporte',
                texto: 'Una por cada traslado: de dónde a dónde, en qué vehículo y qué carga. Se paga por kilo según el producto; si viene de piscicultura, la mitad.',
                vigencia: 'Hasta 5 días',
            },
        ],
    },
];

export function Servicios() {
    return (
        <Seccion
            id="tramites"
            titulo="¿Qué trámite necesita?"
            bajada="Depende de su actividad. Los documentos se sacan en este orden, y cada uno es requisito del siguiente."
        >
            <div className="grid gap-6 lg:grid-cols-2">
                {CAMINOS.map((c, indice) => (
                    <Aparecer key={c.id} retraso={indice * 120}>
                        <article
                            id={c.id}
                            className="h-full scroll-mt-28 overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-md transition-shadow hover:shadow-xl"
                        >
                            <header
                                className={`flex items-center gap-4 px-5 py-4 text-white sm:px-6 ${c.tono.cabecera}`}
                            >
                                <span className="flex size-11 shrink-0 items-center justify-center rounded-lg bg-white/15">
                                    <c.icono className="size-6" />
                                </span>
                                <div>
                                    <h3 className="text-lg font-bold">{c.titulo}</h3>
                                    <p className="text-sm text-white/80">{c.bajada}</p>
                                </div>
                            </header>

                            <ol className="px-5 py-5 sm:px-6">
                                {c.documentos.map((d, i) => (
                                    <li key={d.nombre} className="flex gap-4">
                                        {/* El número y la línea que lo une con el siguiente. */}
                                        <div className="flex flex-col items-center">
                                            <span
                                                className={`flex size-8 shrink-0 items-center justify-center rounded-full text-sm font-bold ${c.tono.numero}`}
                                            >
                                                {i + 1}
                                            </span>
                                            {i < c.documentos.length - 1 && (
                                                <i
                                                    className="w-px flex-1 bg-slate-200"
                                                    aria-hidden
                                                />
                                            )}
                                        </div>

                                        <div
                                            className={`min-w-0 flex-1 ${i < c.documentos.length - 1 ? 'pb-6' : ''}`}
                                        >
                                            <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                                                <h4 className="text-base font-semibold text-rio-profundo">
                                                    {d.nombre}
                                                </h4>
                                                <span
                                                    className={`rounded-full px-2 py-0.5 text-xs font-medium ring-1 ${c.tono.chip}`}
                                                >
                                                    {d.vigencia}
                                                </span>
                                            </div>
                                            <p className="mt-1 text-sm leading-relaxed text-slate-600">
                                                {d.texto}
                                            </p>
                                        </div>
                                    </li>
                                ))}
                            </ol>
                        </article>
                    </Aparecer>
                ))}
            </div>

            <p className="mx-auto mt-6 flex max-w-3xl gap-3 rounded-lg border border-amber-600/20 bg-amber-50 p-4 text-sm leading-relaxed text-amber-900">
                <Info className="mt-0.5 size-4.5 shrink-0" />
                <span>
                    Si usted pesca <strong>y también</strong> comercializa, necesita los{' '}
                    <strong>dos carnets</strong>: son documentos distintos y cada uno habilita su
                    propia actividad.
                </span>
            </p>
        </Seccion>
    );
}
