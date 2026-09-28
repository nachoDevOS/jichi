import { Banknote, ClipboardCheck, FileSignature, FileText, Fish, IdCard, Printer, Truck } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { Seccion } from '@/components/publico/institucional/seccion';

/** El mismo circuito para todos los documentos: se presenta, se paga, se firma y se retira. */
const PASOS: { icono: LucideIcon; titulo: string; texto: string }[] = [
    {
        icono: FileText,
        titulo: 'Presente su solicitud',
        texto: 'En ventanilla se cargan sus datos y los del documento que pide.',
    },
    {
        icono: Banknote,
        titulo: 'Deposite y entregue la boleta',
        texto: 'El pago es por depósito bancario. Al entregar la boleta recibe un recibo oficial numerado.',
    },
    {
        icono: FileSignature,
        titulo: 'La Unidad revisa y firma',
        texto: 'Se controla el depósito contra el banco y se aprueba el documento.',
    },
    {
        icono: Printer,
        titulo: 'Retire su documento',
        texto: 'Sale impreso con un código QR, que cualquiera puede usar para comprobar que es válido.',
    },
];

/** Lo que conviene traer. Los adjuntos del carnet son los que exige el formulario de ventanilla. */
const REQUISITOS: { icono: LucideIcon; titulo: string; items: string[] }[] = [
    {
        icono: IdCard,
        titulo: 'Para cualquier carnet',
        items: ['Cédula de identidad', 'Aval de su asociación', 'Boleta del depósito bancario'],
    },
    {
        icono: Fish,
        titulo: 'Para un permiso de faena',
        items: ['Su carnet de pescador vigente', 'Datos de la embarcación y del comandante', 'Kilos que va a extraer'],
    },
    {
        icono: Truck,
        titulo: 'Para una guía de transporte',
        items: ['Su carnet de comercializador vigente', 'Origen, destino y vehículo', 'Carga: producto y kilos'],
    },
];

export function Pasos() {
    return (
        <Seccion
            id="pasos"
            titulo="Cómo es el trámite en ventanilla"
            bajada="Todos los documentos siguen los mismos cuatro pasos."
            oscuro
        >
            <ol className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {PASOS.map((p, i) => (
                    <li key={p.titulo} className="relative rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <span className="absolute top-4 right-4 text-3xl font-bold text-slate-100 select-none" aria-hidden>
                            {i + 1}
                        </span>
                        <span className="flex size-11 items-center justify-center rounded-lg bg-institucional-azul/10 text-institucional-azul">
                            <p.icono className="size-5.5" />
                        </span>
                        <h3 className="mt-4 text-base font-semibold text-institucional-azul">
                            <span className="sr-only">Paso {i + 1}: </span>
                            {p.titulo}
                        </h3>
                        <p className="mt-1.5 text-sm leading-relaxed text-slate-600">{p.texto}</p>
                    </li>
                ))}
            </ol>

            <div className="mt-10 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <h3 className="flex items-center gap-2 text-lg font-bold text-institucional-azul">
                    <ClipboardCheck className="size-5 text-institucional-dorado-oscuro" />
                    Qué llevar a ventanilla
                </h3>

                <div className="mt-5 grid gap-6 md:grid-cols-3">
                    {REQUISITOS.map((r) => (
                        <div key={r.titulo}>
                            <p className="flex items-center gap-2 text-sm font-semibold text-slate-800">
                                <r.icono className="size-4 text-slate-500" />
                                {r.titulo}
                            </p>
                            <ul className="mt-2.5 space-y-2 text-sm text-slate-600">
                                {r.items.map((item) => (
                                    <li key={item} className="flex gap-2.5">
                                        <i className="mt-2 size-1.5 shrink-0 rounded-full bg-institucional-dorado-oscuro" />
                                        {item}
                                    </li>
                                ))}
                            </ul>
                        </div>
                    ))}
                </div>
            </div>
        </Seccion>
    );
}
