import { useEffect, useState } from 'react';

/**
 * ============================================================================
 *  LA PANTALLA DE "VERIFICANDO..."
 * ============================================================================
 *
 * Aparece un segundo y medio al abrir un código escaneado, y después se
 * desvanece dejando ver el resultado.
 *
 * ¿PARA QUÉ, SI EL DATO YA VINO CON LA PÁGINA?
 *
 * No es para disimular una espera: cuando esto se muestra, la respuesta ya
 * está. Es para que la verificación se SIENTA como un acto, y no como abrir
 * una página web cualquiera.
 *
 * El caso de uso real: el inspector escanea el QR delante del pescador y le
 * gira el teléfono para mostrárselo. Que aparezca el escudo con una línea de
 * escaneo y la palabra «Verificando» hace que los dos entiendan que el sistema
 * consultó algo. Si el resultado apareciera de golpe, parecería una imagen
 * guardada en el teléfono —justo lo que un documento falsificado querría
 * simular—.
 *
 * SE RESPETA `prefers-reduced-motion`: quien pidió menos animaciones en su
 * dispositivo va directo al resultado, sin splash y sin transición.
 */
export function SplashVerificacion() {
    const [oculto, setOculto] = useState(false);
    const [desmontado, setDesmontado] = useState(false);

    useEffect(() => {
        const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        if (reduce) {
            setDesmontado(true);

            return;
        }

        const aOcultar = window.setTimeout(() => setOculto(true), 1500);
        // Se quita del DOM recién cuando terminó de desvanecerse, para que no
        // quede una capa invisible tapando los clics del resultado.
        const aDesmontar = window.setTimeout(() => setDesmontado(true), 2100);

        return () => {
            window.clearTimeout(aOcultar);
            window.clearTimeout(aDesmontar);
        };
    }, []);

    if (desmontado) {
        return null;
    }

    return (
        <div
            // aria-hidden: para un lector de pantalla esto es decoración pura.
            // El resultado real ya está en el DOM debajo y se anuncia solo.
            aria-hidden
            className={`fixed inset-0 z-50 flex flex-col items-center justify-center gap-6 transition-opacity duration-500 ${
                oculto ? 'pointer-events-none opacity-0' : 'opacity-100'
            }`}
            style={{
                // El mismo verde del escritorio de layout-publico.tsx: al
                // desvanecerse, el splash tiene que fundirse con el fondo
                // sobre el que aparece la hoja, no cambiar de color a mitad
                // de la transición.
                background: 'linear-gradient(165deg, #0b5e2c 0%, #07401e 55%, #042a13 100%)',
            }}
        >
            <div className="relative flex size-44 items-center justify-center rounded-full bg-white/5">
                {/* Anillo que gira, con un solo tramo dorado para que se note. */}
                <span className="absolute -inset-2 animate-spin rounded-full border-[3px] border-white/20 border-t-[#f4c500]" />

                {/* Línea de escaneo que sube y baja sobre el escudo. */}
                <span className="animar-escaneo absolute right-[10%] left-[10%] h-[3px] rounded bg-gradient-to-r from-transparent via-[#f4c500] to-transparent shadow-[0_0_12px_#f4c500]" />

                <img
                    src="/image/icon.png"
                    alt=""
                    className="animar-latido h-32 w-auto max-w-[80%] object-contain"
                />
            </div>

            <p className="text-[13px] font-bold tracking-[0.2em] text-white uppercase">
                Verificando documento
            </p>
        </div>
    );
}
