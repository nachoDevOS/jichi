import { Clock, Mail, MapPin, Phone } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { Seccion } from '@/components/publico/institucional/seccion';
import type { InstitucionPortada } from '@/types/publico';

/**
 *  DÓNDE ATIENDEN — todo sale de `configuraciones`, editable sin tocar código
 */
export function Contacto({ portada }: { portada: InstitucionPortada }) {
    const datos: { icono: LucideIcon; rotulo: string; valor: string | null; href?: string }[] = [
        {
            icono: MapPin,
            rotulo: 'Dirección',
            valor: portada.direccion,
        },
        {
            icono: Phone,
            rotulo: 'Teléfono',
            valor: portada.telefono,
            href: portada.telefono ? `tel:${portada.telefono.replace(/[^+\d]/g, '')}` : undefined,
        },
        {
            icono: Mail,
            rotulo: 'Correo',
            valor: portada.email,
            href: portada.email ? `mailto:${portada.email}` : undefined,
        },
        {
            icono: Clock,
            rotulo: 'Horario de atención',
            valor: portada.horario,
        },
    ];

    return (
        <Seccion
            id="contacto"
            titulo="Dónde realizar el trámite"
            bajada="Unidad de Pesca del Servicio Departamental Agropecuario (SEDAG)."
        >
            <div className="mx-auto grid max-w-4xl gap-4 sm:grid-cols-2">
                {datos
                    .filter((d) => d.valor)
                    .map((d) => (
                        <div
                            key={d.rotulo}
                            className="flex gap-4 rounded-xl border border-slate-200 bg-white p-5"
                        >
                            <span className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-institucional-azul/8 text-institucional-azul">
                                <d.icono className="size-5" />
                            </span>

                            <div className="min-w-0">
                                <p className="text-xs font-semibold tracking-wide text-slate-500 uppercase">
                                    {d.rotulo}
                                </p>
                                {d.href ? (
                                    <a
                                        href={d.href}
                                        className="mt-1 block text-sm leading-relaxed text-institucional-azul hover:underline"
                                    >
                                        {d.valor}
                                    </a>
                                ) : (
                                    <p className="mt-1 text-sm leading-relaxed text-slate-700">
                                        {d.valor}
                                    </p>
                                )}
                            </div>
                        </div>
                    ))}
            </div>
        </Seccion>
    );
}
