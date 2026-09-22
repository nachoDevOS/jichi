import { QrCode, ScanLine } from 'lucide-react';
import { BuscadorCodigo } from '@/components/publico/buscador-codigo';

/**
 *  VERIFICAR UN CARNET — el único bloque de la portada que consulta la base
 *
 *  Reusa el mismo formulario de /verificar: escribe en la misma ruta y hereda
 *  sus límites de peticiones. Un buscador propio acá sería una segunda puerta
 *  a la misma consulta, con otro tope.
 */
export function Verificacion() {
    return (
        <section
            id="verificacion"
            className="scroll-mt-24 px-4 py-14 text-white sm:px-6 sm:py-18"
            style={{
                background:
                    'linear-gradient(160deg, #0b5e2c 0%, #07401e 55%, #042a13 100%)',
            }}
        >
            <div className="mx-auto max-w-2xl text-center">
                <span className="mx-auto flex size-12 items-center justify-center rounded-full border border-white/25 bg-white/10">
                    <ScanLine className="size-6" />
                </span>

                <h2 className="mt-4 text-2xl font-bold sm:text-3xl">
                    Verifique un carnet
                </h2>

                <p className="mx-auto mt-3 max-w-lg text-sm leading-relaxed text-white/80 sm:text-base">
                    Escanee el código QR impreso en el carnet, o escriba acá el código que figura
                    debajo. La consulta es pública y no muestra datos personales completos.
                </p>

                {/* El buscador va sobre una tarjeta blanca: el Input y el Button
                    son los del panel y están pensados para fondo claro. */}
                <div className="mt-7 rounded-xl bg-white p-5 shadow-lg sm:p-6">
                    <BuscadorCodigo />

                    <p className="mt-4 flex items-center justify-center gap-2 text-xs text-slate-500">
                        <QrCode className="size-3.5" />
                        El código tiene letras y números, y va impreso en grupos de cuatro.
                    </p>
                </div>
            </div>
        </section>
    );
}
