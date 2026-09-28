import { Camera, Keyboard, QrCode, ScanLine } from 'lucide-react';
import { BuscadorCodigo } from '@/components/publico/buscador-codigo';
import { Cardumen, OlaDivisoria } from '@/components/publico/institucional/ilustraciones';

/** Los cinco documentos que llevan código de verificación (REGLAS-NEGOCIO, paso 7). */
const DOCUMENTOS = [
    'Carnet de Pescador',
    'Carnet de Comercializador',
    'Autorización de Pesca para Aprovechamiento Pesquero',
    'Permiso de Faena',
    'Guía Única de Transporte',
    'Recibo oficial',
];

/**
 *  Verificar un documento — el único bloque de la portada que consulta la base
 *
 *  Reusa el formulario de /verificar: misma ruta y mismo límite de peticiones.
 */
export function Verificacion() {
    return (
        <section id="verificacion" className="scroll-mt-24 bg-rio-espuma">
            <OlaDivisoria className="text-rio" />
            <div
                className="relative overflow-hidden px-4 pt-8 pb-14 text-white sm:px-6 sm:pb-18"
                style={{
                    background: 'linear-gradient(180deg, var(--rio) 0%, var(--rio-profundo) 100%)',
                }}
            >
                <Cardumen />
                <div className="relative mx-auto max-w-3xl text-center">
                    <span className="animar-latido mx-auto flex size-14 items-center justify-center rounded-full bg-institucional-dorado text-rio-profundo shadow-lg">
                        <ScanLine className="size-6" />
                    </span>

                    <h2 className="mt-5 text-3xl font-extrabold tracking-tight sm:text-4xl">
                        ¿Es válido este documento?
                    </h2>

                    <p className="mx-auto mt-3 max-w-xl text-sm leading-relaxed text-white/80 sm:text-base">
                        Cualquier persona puede comprobar si un documento es auténtico y si está
                        vigente hoy. La consulta es gratuita y no muestra datos personales
                        completos.
                    </p>

                    <ul className="mx-auto mt-5 flex max-w-2xl flex-wrap justify-center gap-2">
                        {DOCUMENTOS.map((d) => (
                            <li
                                key={d}
                                className="rounded-full border border-white/20 bg-white/10 px-3 py-1 text-xs font-medium text-white/90"
                            >
                                {d}
                            </li>
                        ))}
                    </ul>

                    <div className="mt-8 grid gap-4 text-left sm:grid-cols-2">
                        <div className="min-w-0 rounded-2xl border border-white/15 bg-white/10 p-5 backdrop-blur">
                            <p className="flex items-center gap-2 font-semibold">
                                <Camera className="size-5 text-institucional-dorado" />
                                Con el celular
                            </p>
                            <p className="mt-2 text-sm leading-relaxed text-white/75">
                                Abra la cámara y apunte al código QR impreso en el documento. Se
                                abre solo la página con el resultado.
                            </p>
                        </div>

                        {/* Tarjeta blanca y buscador en modo `claro`: tiene que verse igual con el teléfono en modo oscuro. */}
                        <div className="min-w-0 rounded-2xl bg-white p-5 text-slate-700 shadow-2xl">
                            <p className="flex items-center gap-2 font-semibold text-rio-profundo">
                                <Keyboard className="size-5 text-rio" />
                                Escribiendo el código
                            </p>
                            <div className="mt-3">
                                <BuscadorCodigo claro />
                            </div>
                            <p className="mt-3 flex items-center gap-2 text-xs text-slate-500">
                                <QrCode className="size-3.5 shrink-0" />
                                Está impreso junto al QR: 16 letras y números, en grupos de cuatro.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <OlaDivisoria invertida className="text-rio-profundo" />
        </section>
    );
}
