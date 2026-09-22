import { Link } from '@inertiajs/react';
import { Mail, MapPin, Phone } from 'lucide-react';
import { FranjaTricolor } from '@/components/publico/institucional/franja-tricolor';
import type { InstitucionPortada } from '@/types/publico';

/**
 *  PIE INSTITUCIONAL
 */
export function Pie({ portada }: { portada: InstitucionPortada }) {
    return (
        <footer className="mt-auto">
            <FranjaTricolor />

            <div className="bg-institucional-azul text-white">
                <div className="mx-auto grid max-w-6xl gap-8 px-4 py-10 sm:px-6 md:grid-cols-3">
                    <div className="flex items-start gap-3">
                        <img
                            src="/image/icon.png"
                            alt=""
                            aria-hidden
                            className="size-12 shrink-0 object-contain"
                        />
                        <div className="min-w-0 text-sm leading-relaxed">
                            <p className="font-semibold">{portada.nombre}</p>
                            <p className="mt-1 text-white/70">
                                Servicio Departamental Agropecuario · Unidad de Pesca
                            </p>
                        </div>
                    </div>

                    <div className="text-sm">
                        <h3 className="mb-3 font-semibold tracking-wide text-institucional-dorado uppercase">
                            Contacto
                        </h3>
                        <ul className="space-y-2 text-white/80">
                            {portada.direccion && (
                                <li className="flex gap-2">
                                    <MapPin className="mt-0.5 size-4 shrink-0" />
                                    {portada.direccion}
                                </li>
                            )}
                            {portada.telefono && (
                                <li className="flex gap-2">
                                    <Phone className="mt-0.5 size-4 shrink-0" />
                                    {portada.telefono}
                                </li>
                            )}
                            {portada.email && (
                                <li className="flex gap-2">
                                    <Mail className="mt-0.5 size-4 shrink-0" />
                                    {portada.email}
                                </li>
                            )}
                        </ul>
                    </div>

                    <div className="text-sm">
                        <h3 className="mb-3 font-semibold tracking-wide text-institucional-dorado uppercase">
                            Enlaces
                        </h3>
                        <ul className="space-y-2 text-white/80">
                            <li>
                                <Link href={route('verificar.show')} className="hover:text-white">
                                    Verificar un carnet
                                </Link>
                            </li>
                            <li>
                                <a href="#servicios" className="hover:text-white">
                                    Servicios
                                </a>
                            </li>
                            <li>
                                <a href="#pasos" className="hover:text-white">
                                    Cómo tramitar
                                </a>
                            </li>
                            <li>
                                <Link href={route('login')} className="hover:text-white">
                                    Acceso para funcionarios
                                </Link>
                            </li>
                        </ul>
                    </div>
                </div>

                <div className="border-t border-white/15">
                    <p className="mx-auto max-w-6xl px-4 py-4 text-center text-xs text-white/60 sm:px-6">
                        © {new Date().getFullYear()} {portada.nombre} · Sistema{' '}
                        {portada.sistema}
                    </p>
                </div>
            </div>
        </footer>
    );
}
