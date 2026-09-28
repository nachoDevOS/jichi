import { ArrowRight, Fish, ScanLine, ShieldCheck, Truck } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import {
    Canoa,
    Cardumen,
    OlaDivisoria,
    Olas,
} from '@/components/publico/institucional/ilustraciones';
import type { InstitucionPortada } from '@/types/publico';

/** Las tres cosas a las que viene el público: una por tarjeta, con el ícono como pista para quien lee poco. */
const ACCESOS: {
    href: string;
    icono: LucideIcon;
    titulo: string;
    texto: string;
    destacado?: boolean;
}[] = [
    {
        href: '#pescador',
        icono: Fish,
        titulo: 'Soy pescador',
        texto: 'Autorización, carnet y permisos de faena',
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
        texto: '¿Es auténtico? ¿Está vigente?',
        destacado: true,
    },
];

/**
 *  Portada — lo primero que ve quien escribe el dominio
 */
export function Hero({
    portada,
    especies,
    provincias,
}: {
    portada: InstitucionPortada;
    especies: number;
    provincias: number;
}) {
    // Solo cifras que el sistema conoce: nada inventado en la cara pública.
    const cifras = [
        { valor: provincias, texto: 'provincias' },
        { valor: especies, texto: 'especies registradas' },
        { valor: 6, texto: 'documentos con QR' },
    ].filter((c) => c.valor > 0);

    return (
        <section
            className="relative overflow-hidden text-white"
            style={{
                background:
                    'radial-gradient(700px 380px at 85% 10%, color-mix(in oklch, var(--rio-claro) 35%, transparent) 0%, transparent 65%),' +
                    'linear-gradient(170deg, var(--rio) 0%, var(--rio-profundo) 70%)',
            }}
        >
            <Cardumen />

            <div className="relative mx-auto grid max-w-6xl items-center gap-10 px-4 pt-12 pb-32 sm:px-6 sm:pt-16 sm:pb-40 lg:grid-cols-[1.25fr_1fr]">
                <div className="min-w-0">
                    <span className="inline-flex items-center gap-2 rounded-full border border-white/25 bg-white/10 px-3 py-1 text-xs font-medium tracking-wide backdrop-blur">
                        <ShieldCheck className="size-3.5 text-institucional-dorado" />
                        Servicio oficial · SEDAG · Unidad de Pesca
                    </span>

                    <h1 className="mt-5 text-4xl leading-[1.05] font-extrabold tracking-tight text-balance sm:text-5xl lg:text-6xl">
                        Pesca responsable en los{' '}
                        <span className="bg-linear-to-r from-institucional-dorado to-rio-claro bg-clip-text text-transparent">
                            ríos del Beni
                        </span>
                    </h1>

                    <p className="mt-5 max-w-xl text-base leading-relaxed text-white/85 sm:text-lg">
                        Tramite su carnet y sus permisos de pesca y transporte con el{' '}
                        {portada.nombre}, y compruebe en segundos si un documento es auténtico.
                    </p>

                    {cifras.length > 0 && (
                        <dl className="mt-8 grid grid-cols-3 gap-4 sm:flex sm:gap-x-8">
                            {cifras.map((c) => (
                                <div key={c.texto}>
                                    <dt className="sr-only">{c.texto}</dt>
                                    <dd className="text-3xl font-extrabold text-institucional-dorado sm:text-4xl">
                                        {c.valor}
                                    </dd>
                                    <dd className="text-[11px] leading-tight font-medium tracking-wide text-white/70 uppercase sm:text-xs">
                                        {c.texto}
                                    </dd>
                                </div>
                            ))}
                        </dl>
                    )}
                </div>

                {/* El sol sobre el río, con la canoa. Solo en pantallas anchas: en el teléfono empuja las tarjetas abajo. */}
                <div
                    className="relative hidden aspect-square w-full max-w-sm justify-self-center lg:block"
                    aria-hidden
                >
                    <div className="absolute inset-0 overflow-hidden rounded-full border-4 border-white/15 bg-linear-to-b from-rio-claro/30 to-transparent shadow-2xl">
                        <div className="absolute top-[18%] left-1/2 size-1/2 -translate-x-1/2 rounded-full bg-linear-to-b from-institucional-dorado to-institucional-dorado-oscuro" />
                        <div className="absolute inset-x-0 bottom-0 h-[45%] bg-linear-to-b from-rio-claro to-rio-profundo" />
                        <OlaDivisoria className="absolute bottom-[45%] h-6! text-rio-claro sm:h-8!" />
                    </div>
                    <Canoa className="animar-flote absolute bottom-[41%] left-1/2 w-44 -translate-x-1/2 text-rio-profundo" />
                </div>

                <div className="grid gap-3 sm:grid-cols-3 lg:col-span-2">
                    {ACCESOS.map((a) => (
                        <a
                            key={a.href}
                            href={a.href}
                            className={`group flex items-center gap-4 rounded-2xl p-4 shadow-lg transition-all hover:-translate-y-0.5 ${
                                a.destacado
                                    ? 'bg-institucional-dorado text-rio-profundo hover:bg-institucional-dorado-oscuro'
                                    : 'border border-white/20 bg-white/10 text-white backdrop-blur hover:bg-white/20'
                            }`}
                        >
                            <span
                                className={`flex size-12 shrink-0 items-center justify-center rounded-xl ${
                                    a.destacado ? 'bg-rio-profundo/10' : 'bg-white/15'
                                }`}
                            >
                                <a.icono className="size-6" />
                            </span>

                            <span className="min-w-0 flex-1">
                                <span className="block text-base font-bold">{a.titulo}</span>
                                <span
                                    className={`mt-0.5 block text-sm leading-snug ${
                                        a.destacado ? 'text-rio-profundo/80' : 'text-white/75'
                                    }`}
                                >
                                    {a.texto}
                                </span>
                            </span>

                            <ArrowRight className="size-4 shrink-0 opacity-60 transition-transform group-hover:translate-x-1" />
                        </a>
                    ))}
                </div>
            </div>

            <Olas />
        </section>
    );
}
