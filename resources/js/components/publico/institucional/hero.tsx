import { ScanLine, ShieldCheck } from 'lucide-react';
import type { InstitucionPortada } from '@/types/publico';

/**
 *  PORTADA — lo primero que ve quien escribe el dominio
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

            <div className="relative mx-auto max-w-6xl px-4 py-14 sm:px-6 sm:py-20">
                <span className="inline-flex items-center gap-2 rounded-full border border-white/25 bg-white/10 px-3 py-1 text-xs font-medium tracking-wide">
                    <ShieldCheck className="size-3.5 text-institucional-dorado" />
                    Servicio oficial · {portada.departamento}
                </span>

                <h1 className="mt-5 max-w-3xl text-3xl leading-tight font-bold sm:text-4xl lg:text-5xl">
                    Carnets y permisos del{' '}
                    <span className="text-institucional-dorado">sector pesquero</span> del Beni
                </h1>

                <p className="mt-4 max-w-2xl text-base leading-relaxed text-white/80 sm:text-lg">
                    {portada.sistema} es el sistema con el que la Gobernación registra a
                    pescadores y comercializadores, autoriza sus cupos de extracción y emite los
                    permisos de faena y las guías de transporte.
                </p>

                <div className="mt-8 flex flex-col gap-3 sm:flex-row">
                    <a
                        href="#verificacion"
                        className="inline-flex items-center justify-center gap-2 rounded-lg bg-institucional-dorado px-5 py-3 text-sm font-semibold text-institucional-azul shadow-sm transition-colors hover:bg-institucional-dorado-oscuro"
                    >
                        <ScanLine className="size-4" />
                        Verificar un carnet
                    </a>

                    <a
                        href="#pasos"
                        className="inline-flex items-center justify-center gap-2 rounded-lg border border-white/30 px-5 py-3 text-sm font-semibold text-white transition-colors hover:bg-white/10"
                    >
                        Cómo tramitar mi carnet
                    </a>
                </div>
            </div>
        </section>
    );
}
