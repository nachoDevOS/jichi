import { Head } from '@inertiajs/react';
import { Printer, ScanLine } from 'lucide-react';
import { BuscadorCodigo } from '@/components/publico/buscador-codigo';
import { FichaCarnet } from '@/components/publico/ficha-carnet';
import { HojaOficial } from '@/components/publico/hoja-oficial';
import { SplashVerificacion } from '@/components/publico/splash-verificacion';
import LayoutPublico from '@/layouts/layout-publico';
import { fechaHora } from '@/lib/utils';
import type { CarnetPublico, InstitucionPublica } from '@/types/publico';

/**
 * ============================================================================
 *  VERIFICACIÓN PÚBLICA DE CARNETS
 * ============================================================================
 *
 * La ÚNICA pantalla del sistema que se ve sin iniciar sesión, y la más
 * importante de todas: es la que sostiene el valor de cada carnet que emite la
 * Gobernación.
 *
 * Es la dirección codificada dentro del QR impreso. Un inspector escanea el QR
 * de un pescador con su teléfono, en el muelle, y cae acá.
 *
 * ----------------------------------------------------------------------------
 *  UN SOLO DATO: LA FIRMA
 * ----------------------------------------------------------------------------
 *
 * El carnet no tiene número: se identifica por su firma de validación, dieciséis
 * caracteres alfanuméricos generados al azar y únicos. Es lo único que hay que
 * saber para consultarlo, y lo único que hace falta para no poder consultarlo:
 * ~8 · 10^24 combinaciones, más el límite de intentos por minuto de la ruta.
 *
 * ----------------------------------------------------------------------------
 *  EL DISEÑO: UN ACTA, NO UNA PANTALLA
 * ----------------------------------------------------------------------------
 *
 * Todo lo que se muestra va sobre una hoja blanca con membrete y guarda. El
 * motivo está explicado largo en `hoja-oficial.tsx`, pero en corto: el ciudadano
 * tiene el papel en la mano y compara. Si la pantalla se parece a una
 * aplicación, no hay nada que comparar; si se parece a otro papel oficial, la
 * comparación la hace cualquiera sin que le expliquen.
 *
 * ----------------------------------------------------------------------------
 *  LOS CUATRO RESULTADOS POSIBLES
 * ----------------------------------------------------------------------------
 *
 *   VIGENTE   verde  — auténtico y habilita las actividades que lista
 *   VENCIDO   ámbar  — auténtico pero cerró la gestión: NO habilita
 *   ANULADO   rojo   — la institución lo dio de baja
 *   NO EXISTE gris   — ninguna credencial responde a esa firma
 *
 * «Vigente» lo decide `Carnet::estaVigente()` en PHP, que mira el estado Y la
 * fecha: el estado lo escribe un comando programado que corre una vez al día, y
 * entre corrida y corrida un carnet que venció ayer sigue diciendo «vigente» en
 * la columna. Acá eso sería habilitar a alguien con un documento caído.
 *
 * ----------------------------------------------------------------------------
 *  LO QUE ESTA PANTALLA NO MUESTRA
 * ----------------------------------------------------------------------------
 *
 * Cualquiera que levante un carnet del suelo puede abrirla, así que solo aparece
 * lo mínimo para constatar autenticidad: nunca el CI completo (llega enmascarado
 * desde PHP), ni la dirección, ni el teléfono del titular. Ver
 * `VerificacionController::datosPublicos()`.
 */
interface Props {
    /** La firma que venía en la URL, normalizada. Null si se entró sin nada. */
    firma: string | null;
    /** El carnet hallado, o null. */
    carnet: CarnetPublico | null;
    /**
     * Tres estados posibles, y hay que distinguirlos:
     *   null   -> todavía no se buscó nada (se entró a /verificar pelado)
     *   false  -> se buscó y no apareció: ninguna credencial tiene esa firma
     *   true   -> se encontró
     * Con solo `carnet` no se podría separar «aún no buscaste» de «buscaste y no
     * existe», que son mensajes muy distintos.
     */
    encontrado: boolean | null;
    institucion: InstitucionPublica;
}

export default function Verificar({ firma, carnet, encontrado, institucion }: Props) {
    // El splash solo tiene sentido cuando de verdad se verificó algo. Entrar a
    // /verificar sin código es buscar el formulario, no escanear un QR.
    const seVerifico = encontrado !== null;

    return (
        <LayoutPublico>
            <Head title="Verificación de carnets" />

            {seVerifico && <SplashVerificacion />}

            {/* Sin código todavía: se explica qué es esto y se ofrece el
                formulario para tipear código y firma a mano. */}
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
                            Escanee el código QR impreso en el carnet, o ingrese aquí la firma de
                            validación que figura debajo del QR.
                        </p>
                    </div>

                    <div className="mt-6">
                        <BuscadorCodigo firmaInicial={firma} />
                    </div>
                </HojaOficial>
            )}

            {/* Se buscó y no apareció. */}
            {encontrado === false && <NoEncontrado firma={firma} institucion={institucion} />}

            {/* Se encontró: el acta con el resultado. */}
            {carnet && <FichaCarnet carnet={carnet} institucion={institucion} />}

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
                            Verificar otro carnet
                        </p>
                        <BuscadorCodigo firmaInicial={null} />
                    </div>
                </div>
            )}
        </LayoutPublico>
    );
}

/**
 * Acta de carnet no hallado.
 *
 * Sale en la misma hoja que las demás, y no en una pantalla de error. Es
 * deliberado: que no figure un carnet es un RESULTADO de la consulta, tan válido
 * como los otros tres, y merece la misma constancia. Una pantalla de error haría
 * dudar de si el sistema falló o si el documento es falso.
 *
 * EL TEXTO NO ACUSA A NADIE, y eso importa más de lo que parece. Puede ser un
 * carnet falso, pero también una firma mal tipeada, un QR borroso o un 0 leído
 * como O. Acusar de falsificación a quien se equivocó en una letra sería un
 * problema real en una ventanilla pública. Se informa el hecho y se dice qué
 * hacer.
 */
function NoEncontrado({
    firma,
    institucion,
}: {
    firma: string | null;
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
                    Se deja constancia de que, consultado el registro electrónico de carnets emitidos
                    por esta institución, la firma de validación{' '}
                    <b className="font-mono text-[12px] font-bold tracking-wider break-all text-slate-900">
                        {firma}
                    </b>{' '}
                    NO corresponde a ningún carnet emitido.
                </p>
            </div>

            <div className="mt-6 border-l-4 border-slate-400/50 bg-slate-50 py-2.5 pr-3 pl-3.5">
                <p className="text-[12px] leading-snug text-slate-700">
                    <b className="block font-semibold">Antes de dar por falso el carnet</b>
                    Revise que la firma esté bien escrita —conviene confundir el 0 con la O y el 1
                    con la I— o vuelva a escanear el código QR. Si la firma es correcta, acérquese a
                    las oficinas del SEDAG antes de dar por válido el documento.
                </p>
            </div>
        </HojaOficial>
    );
}
