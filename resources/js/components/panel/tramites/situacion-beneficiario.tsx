import { Link } from '@inertiajs/react';
import { AlertTriangle, BadgeCheck, BadgePlus, ShieldOff } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { fecha } from '@/lib/utils';
import type { SituacionBeneficiario } from '@/types/beneficiarios';

/**
 * ============================================================================
 *  QUÉ TIENE ESTA PERSONA, ANTES DE CARGAR NADA
 * ============================================================================
 *
 * Se dibuja apenas el operador elige al beneficiario, y responde las tres cosas
 * que hasta ahora solo se sabían después de guardar:
 *
 *   1. si le corresponde EMISIÓN INICIAL o ADICIÓN DE RUBRO;
 *   2. qué carnet tiene y hasta cuándo vale;
 *   3. qué rubros ya tiene habilitados —y por lo tanto no se pueden volver
 *      a pedir—.
 *
 * ----------------------------------------------------------------------------
 *  POR QUÉ IMPORTA MOSTRARLO ACÁ Y NO DEJAR QUE FALLE AL GUARDAR
 * ----------------------------------------------------------------------------
 *
 * El servidor ya rechaza un rubro repetido: `SolicitudCarnetService` lo
 * comprueba y el índice único de `carnet_rubro` lo garantiza. Pero rechazarlo al
 * final significa que el operador ya escaneó dos papeles, los adjuntó y cargó
 * una boleta —y al volver con el error los archivos NO se recuperan, porque los
 * navegadores no permiten rellenar un campo de tipo file—.
 *
 * Mostrarlo antes no es una comodidad: es la diferencia entre un aviso y un
 * trabajo perdido.
 */
export function SituacionBeneficiarioCard({ situacion }: { situacion: SituacionBeneficiario }) {
    // --- Sin carnet: emisión inicial
    if (!situacion.tiene_carnet || situacion.carnet === null) {
        return (
            <Aviso
                tono="sky"
                icono={BadgePlus}
                titulo={`Sin carnet de la gestión ${situacion.gestion}`}
            >
                Le corresponde una <strong>emisión inicial</strong>: al registrar la solicitud se
                crea el carnet del año y queda a la espera de aprobación.
            </Aviso>
        );
    }

    const { carnet } = situacion;

    // --- Tiene carnet pero no admite adiciones: anulado o vencido
    if (!carnet.admite_adiciones) {
        return (
            <Aviso
                tono="rose"
                icono={AlertTriangle}
                titulo={`Carnet de la gestión ${carnet.gestion} no vigente`}
            >
                Está <strong>{carnet.estado_etiqueta.toLowerCase()}</strong>, así que no se le pueden
                agregar rubros.
                {/*
                    Este caso tiene una consecuencia que no se ve y conviene decir:
                    el carnet anulado SIGUE OCUPANDO la gestión, así que tampoco se
                    puede emitir otro este año. Sin el aviso, el operador prueba,
                    el servidor rechaza y nadie entiende por qué.
                */}
                {carnet.estado === 'anulado' && (
                    <>
                        {' '}
                        Un carnet anulado sigue ocupando la gestión {carnet.gestion}: tampoco se
                        puede emitir uno nuevo hasta el año siguiente.
                    </>
                )}
            </Aviso>
        );
    }

    // --- Carnet vigente: adición de rubro
    return (
        <div className="rounded-lg border border-violet-300 bg-violet-50 p-4 dark:border-violet-500/40 dark:bg-violet-500/10">
            <div className="flex items-start gap-3">
                <BadgeCheck className="mt-0.5 size-5 shrink-0 text-violet-700 dark:text-violet-300" />

                <div className="min-w-0 flex-1 space-y-3 text-sm">
                    <div>
                        <p className="font-medium text-violet-900 dark:text-violet-200">
                            Ya tiene{' '}
                            <Link
                                href={route('carnets.show', carnet.id)}
                                className="underline underline-offset-2"
                            >
                                carnet
                            </Link>{' '}
                            de la gestión {carnet.gestion}
                        </p>

                        <p className="text-violet-800/80 dark:text-violet-200/80">
                            Le corresponde una <strong>adición de rubro</strong> sobre ese mismo
                            carnet —no se emite uno nuevo— y vence el{' '}
                            {fecha(carnet.fecha_vencimiento)}.
                        </p>
                    </div>

                    <div>
                        <p className="mb-1.5 text-xs font-semibold uppercase tracking-wide text-violet-900/70 dark:text-violet-200/70">
                            Rubros que ya tiene
                        </p>

                        {carnet.rubros.length === 0 ? (
                            // Pasa con un carnet cuya emisión inicial fue rechazada:
                            // el documento existe pero no habilita nada todavía.
                            <p className="text-violet-800/80 italic dark:text-violet-200/80">
                                Ninguno todavía. El carnet está emitido pero no habilita ninguna
                                actividad.
                            </p>
                        ) : (
                            <ul className="flex flex-wrap gap-2">
                                {carnet.rubros.map((rubro) => (
                                    <li key={rubro.id}>
                                        <Badge color={rubro.estado_color}>
                                            {rubro.estado === 'suspendido' && (
                                                <ShieldOff className="mr-1 size-3" aria-hidden />
                                            )}
                                            {rubro.nombre}
                                            <span className="ml-1 opacity-70">
                                                · {rubro.estado_etiqueta.toLowerCase()}
                                            </span>
                                        </Badge>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
}

/**
 * Un aviso de una línea, con su color.
 *
 * Los colores van escritos COMPLETOS en un `match` y no armados juntando textos
 * (`bg-${tono}-50`): Tailwind solo incluye en el CSS final las clases que puede
 * leer literalmente en el código, y una clase compuesta nunca llega a la hoja de
 * estilos. El aviso saldría sin fondo y sin ningún error que lo explique.
 */
function Aviso({
    tono,
    icono: Icono,
    titulo,
    children,
}: {
    tono: 'sky' | 'rose';
    icono: typeof BadgeCheck;
    titulo: string;
    children: React.ReactNode;
}) {
    const estilos =
        tono === 'sky'
            ? {
                  caja: 'border-sky-300 bg-sky-50 dark:border-sky-500/40 dark:bg-sky-500/10',
                  icono: 'text-sky-700 dark:text-sky-300',
                  titulo: 'text-sky-900 dark:text-sky-200',
                  texto: 'text-sky-800/80 dark:text-sky-200/80',
              }
            : {
                  caja: 'border-rose-300 bg-rose-50 dark:border-rose-500/40 dark:bg-rose-500/10',
                  icono: 'text-rose-700 dark:text-rose-300',
                  titulo: 'text-rose-900 dark:text-rose-200',
                  texto: 'text-rose-800/80 dark:text-rose-200/80',
              };

    return (
        <div className={`rounded-lg border p-4 ${estilos.caja}`}>
            <div className="flex items-start gap-3">
                <Icono className={`mt-0.5 size-5 shrink-0 ${estilos.icono}`} />

                <div className="min-w-0 text-sm">
                    <p className={`font-medium ${estilos.titulo}`}>{titulo}</p>
                    <p className={estilos.texto}>{children}</p>
                </div>
            </div>
        </div>
    );
}
