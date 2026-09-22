import { Fish, IdCard, Scale, Truck } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { Seccion } from '@/components/publico/institucional/seccion';

/**
 * Los cuatro servicios, tal como los define docs/REGLAS-NEGOCIO.md. Los plazos
 * y las condiciones que dicen las tarjetas salen de ahí: si la regla cambia,
 * este texto cambia con ella.
 */
const SERVICIOS: {
    icono: LucideIcon;
    titulo: string;
    texto: string;
    detalles: string[];
}[] = [
    {
        icono: Fish,
        titulo: 'Carnet de Pescador',
        texto: 'El documento que habilita a extraer producto de los ríos y lagunas del departamento.',
        detalles: [
            'Exige un cupo de aprovechamiento vigente',
            'Requiere el aval de una asociación',
            'Lleva impreso el cupo autorizado en kilos',
        ],
    },
    {
        icono: IdCard,
        titulo: 'Carnet de Comercializador',
        texto: 'Habilita el acopio, el traslado y la venta del producto pesquero.',
        detalles: [
            'No lleva cupo: no realiza extracción',
            'Requiere el aval de una asociación',
            'Es la condición para pedir guías de transporte',
        ],
    },
    {
        icono: Scale,
        titulo: 'Aprovechamiento Pesquero',
        texto: 'La autorización de extracción, otorgada en kilos según una escala oficial.',
        detalles: [
            'Solo para pescadores',
            'La escala fija el techo de kilos y el costo',
            'De ella se descuenta cada faena',
        ],
    },
    {
        icono: Truck,
        titulo: 'Permisos de Faena y Guías',
        texto: 'Los permisos del día a día, que cuelgan del carnet y son varios por gestión.',
        detalles: [
            'Permiso de faena: una salida, hasta 30 días',
            'Guía de movimiento: un traslado, hasta 5 días',
            'La guía declara origen, destino y peso',
        ],
    },
];

export function Servicios() {
    return (
        <Seccion
            id="servicios"
            titulo="Servicios que brinda el sistema"
            bajada="Cuatro trámites, que se atienden en ventanilla de la Unidad de Pesca del SEDAG."
        >
            <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                {SERVICIOS.map((s) => (
                    <article
                        key={s.titulo}
                        className="flex flex-col rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition-shadow hover:border-emerald-700/30 hover:shadow-md"
                    >
                        <span className="flex size-11 items-center justify-center rounded-lg bg-emerald-50 text-emerald-800">
                            <s.icono className="size-5.5" />
                        </span>

                        <h3 className="mt-4 text-base font-semibold text-institucional-azul">
                            {s.titulo}
                        </h3>

                        <p className="mt-2 text-sm leading-relaxed text-slate-600">{s.texto}</p>

                        <ul className="mt-4 space-y-1.5 border-t border-slate-100 pt-4 text-xs leading-relaxed text-slate-500">
                            {s.detalles.map((d) => (
                                <li key={d} className="flex gap-2">
                                    <i className="mt-1.5 size-1.5 shrink-0 rounded-full bg-emerald-600" />
                                    {d}
                                </li>
                            ))}
                        </ul>
                    </article>
                ))}
            </div>
        </Seccion>
    );
}
