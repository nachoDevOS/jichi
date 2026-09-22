import { Head } from '@inertiajs/react';
import { Printer, ScanLine } from 'lucide-react';
import { BuscadorCodigo } from '@/components/publico/buscador-codigo';
import { FichaDocumento } from '@/components/publico/ficha-documento';
import { HojaOficial } from '@/components/publico/hoja-oficial';
import { SplashVerificacion } from '@/components/publico/splash-verificacion';
import LayoutPublico from '@/layouts/layout-publico';
import { fechaHora } from '@/lib/utils';
import type { DocumentoPublico, InstitucionPublica } from '@/types/publico';

/**
 *  VERIFICACIÓN PÚBLICA DE CARNETS
 */
interface Props {
    /** El código que venía en la URL, ya normalizado. Null si se entró sin nada. */
    codigo: string | null;
    /** El documento hallado, sea del tipo que sea. Null si no apareció. */
    documento: DocumentoPublico | null;
    /**
     * Tres estados posibles, y hay que distinguirlos:
     *   null   -> todavía no se buscó nada (se entró a /verificar pelado)
     *   false  -> se buscó y no apareció: ningún carnet tiene ese código
     *   true   -> se encontró
     * Con solo `carnet` no se podría separar «aún no buscaste» de «buscaste y no
     * existe», que son mensajes muy distintos.
     */
    encontrado: boolean | null;
    institucion: InstitucionPublica;
}

export default function Verificar({ codigo, documento, encontrado, institucion }: Props) {
    // El splash solo tiene sentido cuando de verdad se verificó algo. Entrar a
    // /verificar sin código es buscar el formulario, no escanear un QR.
    const seVerifico = encontrado !== null;

    return (
        <LayoutPublico>
            <Head title="Verificación de documentos" />

            {seVerifico && <SplashVerificacion />}

            {/* Sin código todavía: se explica qué es esto y se ofrece el
                formulario para tipear el código a mano. */}
            {!seVerifico && (
                <HojaOficial institucion={institucion} esConstancia={false}>
                    <div className="mt-6 text-center">
                        <span className="mx-auto flex size-12 items-center justify-center rounded-full border border-emerald-800/25 bg-emerald-50 text-emerald-800">
                            <ScanLine className="size-6" />
                        </span>

                        <h1 className="mt-4 font-serif text-[17px] leading-tight font-bold tracking-[0.08em] text-slate-800 uppercase">
                            Verificación de autenticidad
                        </h1>

                        <p className="mx-auto mt-2.5 max-w-sm font-serif text-[13px] leading-relaxed text-slate-600">
                            Escanee el código QR impreso en el documento, o ingrese aquí el
                            código que figura debajo del QR.
                        </p>
                    </div>

                    <div className="mt-6">
                        <BuscadorCodigo codigoInicial={codigo} />
                    </div>
                </HojaOficial>
            )}

            {/* Se buscó y no apareció. */}
            {encontrado === false && <NoEncontrado codigo={codigo} institucion={institucion} />}

            {/* Se encontró: el acta con el resultado. */}
            {documento && <FichaDocumento documento={documento} institucion={institucion} />}

            {/* Todo lo que sigue es de la pantalla y no del acta: por eso lleva
                `solo-pantalla`, la clase que lo saca de la impresión. */}
            {seVerifico && (
                <div className="solo-pantalla mt-6">
                    <div className="flex justify-center">
                        <button
                            type="button"
                            onClick={() => window.print()}
                            className="flex items-center gap-2 rounded-full border border-white/25 px-4 py-2 text-[12px] font-semibold text-white/85 transition hover:bg-white/10"
                        >
                            <Printer className="size-4" />
                            Imprimir o guardar en PDF
                        </button>
                    </div>

                    <div className="mt-5 rounded-xl bg-white/10 p-4 backdrop-blur-sm">
                        <p className="mb-3 text-center text-[11px] font-semibold tracking-wide text-white/70 uppercase">
                            Verificar otro documento
                        </p>
                        <BuscadorCodigo codigoInicial={null} />
                    </div>
                </div>
            )}
        </LayoutPublico>
    );
}

/**
 * Acta de carnet no hallado.
 */
function NoEncontrado({
    codigo,
    institucion,
}: {
    codigo: string | null;
    institucion: InstitucionPublica;
}) {
    return (
        <HojaOficial
            institucion={institucion}
            pie={
                <p className="text-center font-serif text-[11px] text-slate-500 italic">
                    Consulta realizada el {fechaHora(new Date())}
                </p>
            }
        >
            <h1 className="mt-6 text-center font-serif text-[17px] leading-tight font-bold tracking-[0.08em] text-slate-800 uppercase">
                Constancia de consulta
            </h1>

            <div className="mt-4">
                <p className="text-justify font-serif text-[13px] leading-relaxed text-slate-700">
                    Se deja constancia de que, consultado el registro electrónico de carnets
                    emitidos por esta institución, el código{' '}
                    <b className="font-mono text-[12px] font-bold tracking-wider break-all text-slate-900">
                        {codigo}
                    </b>{' '}
                    NO corresponde a ningún documento emitido.
                </p>
            </div>

            <div className="mt-6 border-l-4 border-slate-400/50 bg-slate-50 py-2.5 pr-3 pl-3.5">
                <p className="text-[12px] leading-snug text-slate-700">
                    <b className="block font-semibold">Antes de dar por falso el documento</b>
                    Revise que el código esté bien escrito —es fácil confundir el 0 con la O y el 1
                    con la I— o vuelva a escanear el código QR. Si el código es correcto, acérquese a
                    las oficinas del SEDAG antes de dar por válido el documento.
                </p>
            </div>
        </HojaOficial>
    );
}
