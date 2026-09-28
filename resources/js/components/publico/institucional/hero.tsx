import { ArrowRight, Fish, ScanLine, ShieldCheck, Truck } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { InstitucionPortada } from '@/types/publico';

/** Las tres cosas a las que viene el público: una por tarjeta, con el ícono como pista para quien lee poco. */
const ACCESOS: { href: string; icono: LucideIcon; titulo: string; texto: string; destacado?: boolean }[] = [
    {
        href: '#pescador',
        icono: Fish,
        titulo: 'Soy pescador',
        texto: 'Autorización de Pesca para Aprovechamiento Pesquero, carnet y permisos de faena',
    },
    {
        href: '#comercializador',
        icono: Truck,
        titulo: 'Soy comercializador',
        texto: 'Carnet y guías de transporte',
    },
    {
        href: '#verificacion',
        icono: ScanLine,
        titulo: 'Verificar un documento',
        texto: 'Consulte si un carnet, permiso o guía es válido',
        destacado: true,
    },
];

/**
 *  Portada — lo primero que ve quien escribe el dominio
 */
export function Hero({ portada }: { portada: InstitucionPortada }) {
    return (
        <section
            className="relative overflow-hidden text-white"
            style={{
                background:
                    'radial-gradient(900px 420px at 80% 0%, rgba(255,255,255,.12) 0%, transparent 60%),' +
                    'linear-gradient(160deg, var(--institucional-azul-claro) 0%, var(--institucional-azul) 55%, #16283c 100%)',
            }}
        >
            {/* Escudo muy tenue, como marca de agua de los documentos. */}
            <img
                src="/image/icon.png"
                alt=""
                aria-hidden
                className="pointer-events-none absolute -right-16 -bottom-20 w-96 opacity-[0.07] select-none"
            />

            <div className="relative mx-auto max-w-6xl px-4 pt-12 pb-10 sm:px-6 sm:pt-18 sm:pb-14">
                <span className="inline-flex items-center gap-2 rounded-full border border-white/25 bg-white/10 px-3 py-1 text-xs font-medium tracking-wide">
                    <ShieldCheck className="size-3.5 text-institucional-dorado" />
                    Servicio oficial · Unidad de Pesca del SEDAG · {portada.departamento}
                </span>

                <h1 className="mt-5 max-w-4xl text-3xl leading-tight font-bold text-balance sm:text-4xl lg:text-5xl">
                    Carnets y permisos para{' '}
                    <span className="text-institucional-dorado">pescar y comercializar</span> en el Beni
                </h1>

                <p className="mt-4 max-w-2xl text-base leading-relaxed text-white/85 sm:text-lg">
                    Aquí sabrá qué documentos necesita para trabajar en la pesca, qué llevar a
                    ventanilla y cómo comprobar que un documento es auténtico.
                </p>

                <div className="mt-8 grid gap-3 sm:grid-cols-3">
                    {ACCESOS.map((a) => (
                        <a
                            key={a.href}
                            href={a.href}
                            className={`group flex items-center gap-4 rounded-xl p-4 shadow-sm transition-colors ${
                                a.destacado
                                    ? 'bg-institucional-dorado text-institucional-azul hover:bg-institucional-dorado-oscuro'
                                    : 'border border-white/20 bg-white/10 text-white hover:bg-white/15'
                            }`}
                        >
                            <span
                                className={`flex size-12 shrink-0 items-center justify-center rounded-lg ${
                                    a.destacado ? 'bg-institucional-azul/10' : 'bg-white/10'
                                }`}
                            >
                                <a.icono className="size-6" />
                            </span>

                            <span className="min-w-0 flex-1">
                                <span className="block text-base font-semibold">{a.titulo}</span>
                                <span
                                    className={`mt-0.5 block text-sm leading-snug ${
                                        a.destacado ? 'text-institucional-azul/80' : 'text-white/75'
                                    }`}
                                >
                                    {a.texto}
                                </span>
                            </span>

                            <ArrowRight className="size-4 shrink-0 opacity-60 transition-transform group-hover:translate-x-0.5" />
                        </a>
                    ))}
                </div>
            </div>
        </section>
    );
}
