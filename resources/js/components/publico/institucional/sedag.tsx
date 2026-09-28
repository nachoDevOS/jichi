import { ClipboardList, Leaf, MapPinned, Scale, Truck } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { Aparecer } from '@/components/publico/institucional/aparecer';
import { Seccion } from '@/components/publico/institucional/seccion';

/**
 * Qué hace la Unidad de Pesca. Texto BASE, sin cifras ni fechas: la unidad lo
 * tiene que confirmar (ver docs/PENDIENTES.md).
 */
const TAREAS: { icono: LucideIcon; titulo: string; texto: string }[] = [
    {
        icono: ClipboardList,
        titulo: 'Registra',
        texto: 'Lleva el padrón de pescadores y comercializadores del departamento, con el aval de sus asociaciones.',
    },
    {
        icono: Scale,
        titulo: 'Autoriza',
        texto: 'Otorga a cada pescador los kilos que puede extraer en la gestión, según la escala oficial.',
    },
    {
        icono: Truck,
        titulo: 'Controla',
        texto: 'Cada salida de pesca y cada traslado de producto lleva su permiso, con código verificable.',
    },
    {
        icono: Leaf,
        titulo: 'Cuida',
        texto: 'Un recurso bien administrado hoy es pesca para las familias del Beni mañana.',
    },
];

export function Sedag({ provincias }: { provincias: string[] }) {
    return (
        <Seccion
            id="sedag"
            titulo="Sobre el SEDAG"
            bajada="El Servicio Departamental Agropecuario es la entidad del Gobierno Autónomo Departamental del Beni que acompaña la producción del campo y del agua. Su Unidad de Pesca administra la actividad pesquera del departamento."
        >
            <div className="grid items-center gap-10 lg:grid-cols-[1fr_1.4fr]">
                <Aparecer>
                    <div className="relative mx-auto max-w-sm">
                        <div
                            className="absolute -inset-4 rotate-3 rounded-3xl bg-linear-to-br from-rio-claro/40 to-selva/30"
                            aria-hidden
                        />
                        <div className="relative rounded-3xl bg-white p-8 shadow-xl ring-1 ring-slate-200">
                            <img
                                src="/image/sedag.png"
                                alt="Logo del SEDAG Beni"
                                className="mx-auto h-40 w-auto object-contain"
                            />
                            <p className="mt-6 text-center text-sm font-semibold tracking-wide text-rio-profundo uppercase">
                                Servicio Departamental Agropecuario
                            </p>
                            <p className="text-center text-xs text-slate-500">Unidad de Pesca</p>
                        </div>
                    </div>
                </Aparecer>

                <div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        {TAREAS.map((t, i) => (
                            <Aparecer key={t.titulo} retraso={i * 90}>
                                <article className="group h-full rounded-2xl border border-slate-200 bg-white p-5 transition-all hover:-translate-y-1 hover:border-rio-claro hover:shadow-lg">
                                    <span className="flex size-11 items-center justify-center rounded-xl bg-rio-espuma text-rio transition-colors group-hover:bg-rio group-hover:text-white">
                                        <t.icono className="size-5.5" />
                                    </span>
                                    <h3 className="mt-4 text-lg font-bold text-rio-profundo">
                                        {t.titulo}
                                    </h3>
                                    <p className="mt-1 text-sm leading-relaxed text-slate-600">
                                        {t.texto}
                                    </p>
                                </article>
                            </Aparecer>
                        ))}
                    </div>

                    {provincias.length > 0 && (
                        <Aparecer retraso={200} className="mt-6">
                            <div className="rounded-2xl bg-rio-espuma p-5">
                                <p className="flex items-center gap-2 text-sm font-semibold text-rio-profundo">
                                    <MapPinned className="size-4.5 text-rio" />
                                    Las {provincias.length} provincias del Beni
                                </p>
                                <ul className="mt-3 flex flex-wrap gap-2">
                                    {provincias.map((p) => (
                                        <li
                                            key={p}
                                            className="rounded-full bg-white px-3 py-1 text-xs font-medium text-rio-profundo ring-1 ring-rio-claro/40"
                                        >
                                            {p}
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        </Aparecer>
                    )}
                </div>
            </div>
        </Seccion>
    );
}
