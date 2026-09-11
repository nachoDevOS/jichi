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
 * ============================================================================
 *  VERIFICACIÓN PÚBLICA DE DOCUMENTOS
 * ============================================================================
 *
 * La ÚNICA pantalla del sistema que se ve sin iniciar sesión, y la más
 * importante de todas: es la que sostiene el valor de cada documento que emite
 * la Gobernación.
 *
 * Es la dirección codificada dentro del QR impreso en cada permiso, guía y
 * credencial. Un inspector escanea el QR de un pescador con su teléfono, en el
 * muelle, y cae acá.
 *
 * ----------------------------------------------------------------------------
 *  EL DISEÑO: UN ACTA, NO UNA PANTALLA
 * ----------------------------------------------------------------------------
 *
 * Todo lo que se muestra va sobre una hoja blanca con membrete y guarda. El
 * motivo está explicado largo en `hoja-oficial.tsx`, pero en corto:
 * el ciudadano tiene el papel en la mano y compara. Si la pantalla se parece a
 * una aplicación, no hay nada que comparar; si se parece a otro papel oficial,
 * la comparación la hace cualquiera sin que le expliquen.
 *
 * ----------------------------------------------------------------------------
 *  LOS CUATRO RESULTADOS POSIBLES
 * ----------------------------------------------------------------------------
 *
 *   VIGENTE   verde  — auténtico y habilita la actividad
 *   VENCIDO   ámbar  — auténtico pero caducó: NO habilita
 *   ANULADO   rojo   — la institución lo dio de baja
 *   NO EXISTE gris   — ningún documento emitido tiene ese código
 *
 * Los tres primeros los resuelve `Documento::estadoEfectivo()` en PHP, que
 * recalcula el vencimiento contra la fecha de hoy en vez de confiar en la
 * columna `estado` —que puede quedar desactualizada entre corridas del comando
 * de vencimiento—.
 *
 * ----------------------------------------------------------------------------
 *  LO QUE ESTA PANTALLA NO MUESTRA
 * ----------------------------------------------------------------------------
 *
 * Cualquiera con el código puede abrirla, así que solo aparece lo mínimo para
 * constatar autenticidad: nunca el CI completo (llega enmascarado desde PHP),
 * ni la dirección, ni el teléfono del titular. Ver
 * `VerificacionController::datosPublicos()`.
 */
interface Props {
    /** El código que venía en la URL. Null si se entró sin código. */
    codigo: string | null;
    /** El documento hallado, o null. */
    documento: DocumentoPublico | null;
    /**
     * Tres estados posibles, y hay que distinguirlos:
     *   null   -> todavía no se buscó nada (se entró a /verificar pelado)
     *   false  -> se buscó y NO existe
     *   true   -> se encontró
     * Con solo `documento` no se podría separar "aún no buscaste" de
     * "buscaste y no existe", que son mensajes muy distintos.
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
                            Escanee el código QR impreso en el documento, o ingrese aquí el código
                            de verificación que figura debajo del QR.
                        </p>
                    </div>

                    <div className="mt-6">
                        <BuscadorCodigo codigoInicial={codigo} />
                    </div>
                </HojaOficial>
            )}

            {/* Se buscó y no existe. */}
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
 * Acta de código inexistente.
 *
 * Sale en la misma hoja que las demás, y no en una pantalla de error. Es
 * deliberado: que no figure un documento es un RESULTADO de la consulta, tan
 * válido como los otros tres, y merece la misma constancia. Una pantalla de
 * error haría dudar de si el sistema falló o si el documento es falso.
 *
 * El texto evita acusar a nadie: puede ser un documento falso, pero también un
 * código mal tipeado o un QR borroso. Se informa el hecho y se dice qué hacer.
 * Acusar de falsificación a quien tipeó mal una letra sería un problema real
 * en una ventanilla pública.
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
                    Se deja constancia de que, consultado el registro electrónico de documentos
                    emitidos por esta institución, el código de verificación{' '}
                    <b className="font-mono text-[12px] font-bold tracking-wider break-all text-slate-900">
                        {codigo}
                    </b>{' '}
                    NO corresponde a ningún documento emitido.
                </p>

            </div>

            <div className="mt-6 border-l-4 border-slate-400/50 bg-slate-50 py-2.5 pr-3 pl-3.5">
                <p className="text-[12px] leading-snug text-slate-700">
                    <b className="block font-semibold">Antes de dar por falso el documento</b>
                    Revise que el código esté bien escrito —conviene confundir el 0 con la O— o
                    vuelva a escanear el código QR. Si el código es correcto, acérquese a las
                    oficinas del SEDAG antes de dar por válido el documento.
                </p>
            </div>

        </HojaOficial>
    );
}
