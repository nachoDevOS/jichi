import { Clock, IdCard, ShieldAlert, ShieldCheck, ShieldX } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { fecha } from '@/lib/utils';
import type { CredencialDelSolicitante, TramiteEnCurso } from '@/types/tramites';

/**
 * El estado de la Cédula de Pescador de un solicitante.
 *
 * ¿POR QUÉ TIENE CUATRO ESTADOS Y NO DOS?
 *
 * Porque cada uno pide una acción distinta en ventanilla:
 *
 *   sin cédula   → hay que emitirla desde cero
 *   en trámite   → no hay nada que hacer acá: falta que la aprueben. Volver a
 *                  cargarla sería duplicar el trámite
 *   vencida      → hay que renovarla para la gestión en curso
 *   vigente      → adelante
 *
 * Un cartel que dijera solo «no habilitado» obligaría al operador a ir a la
 * ficha del solicitante a averiguar cuál de las cuatro cosas es.
 *
 * `compacto` es la versión para las filas del buscador, donde solo entra una
 * etiqueta chica.
 */
export function EstadoCredencial({
    credencial,
    enTramite = null,
    compacto = false,
}: {
    credencial: CredencialDelSolicitante | null;
    /** Cédula pedida y todavía sin emitir. */
    enTramite?: TramiteEnCurso | null;
    compacto?: boolean;
}) {
    /*
     * El trámite en curso se mira ANTES que la ausencia de credencial: quien
     * ya la pidió no está en la misma situación que quien nunca la sacó,
     * aunque en los dos casos todavía no tenga documento.
     */
    if (credencial === null && enTramite !== null) {
        return compacto ? (
            <Badge color={enTramite.estado_color}>Cédula en trámite</Badge>
        ) : (
            <Linea
                icono={<Clock className="size-5 text-sky-600 dark:text-sky-400" />}
                titulo={`Cédula de Pescador en trámite — ${enTramite.estado_etiqueta}`}
                detalle={`Trámite N° ${enTramite.id}. Falta que se apruebe y se emita; recién ahí habilita a pedir faena o guía.`}
                tono="proceso"
            />
        );
    }

    if (credencial === null) {
        return compacto ? (
            <Badge color="slate">Sin cédula</Badge>
        ) : (
            <Linea
                icono={<ShieldX className="size-5 text-muted-foreground" />}
                titulo="Sin Cédula de Pescador"
                detalle="Nunca se le emitió. Para pedir faena o guía de transporte necesita sacarla primero."
                tono="neutro"
            />
        );
    }

    if (! credencial.vigente) {
        const vencio = credencial.fecha_vencimiento
            ? `Venció el ${fecha(credencial.fecha_vencimiento)}.`
            : 'No está vigente.';

        return compacto ? (
            <Badge color="amber">Cédula vencida</Badge>
        ) : (
            <Linea
                icono={<ShieldAlert className="size-5 text-amber-600 dark:text-amber-400" />}
                titulo="Cédula de Pescador vencida"
                detalle={`${vencio} Hay que renovarla para la gestión en curso.`}
                tono="alerta"
            />
        );
    }

    const hasta = credencial.fecha_vencimiento
        ? `Vigente hasta el ${fecha(credencial.fecha_vencimiento)}.`
        : 'Vigente.';

    return compacto ? (
        <Badge color="emerald">Cédula vigente</Badge>
    ) : (
        <Linea
            icono={<ShieldCheck className="size-5 text-emerald-600 dark:text-emerald-400" />}
            titulo="Cédula de Pescador vigente"
            detalle={`${hasta} Código de verificación ${credencial.codigo_verificacion}.`}
            tono="ok"
        />
    );
}

function Linea({
    icono,
    titulo,
    detalle,
    tono,
}: {
    icono: React.ReactNode;
    titulo: string;
    detalle: string;
    tono: 'ok' | 'alerta' | 'proceso' | 'neutro';
}) {
    /*
     * Las clases se escriben enteras y no armadas juntando textos.
     * Tailwind solo incluye en el CSS final las que puede leer literalmente en
     * el código: `bg-${color}-500/10` no existiría en la hoja de estilos.
     */
    const marco = {
        ok: 'border-emerald-500/40 bg-emerald-500/10',
        alerta: 'border-amber-500/40 bg-amber-500/10',
        proceso: 'border-sky-500/40 bg-sky-500/10',
        neutro: 'border-border bg-muted/40',
    }[tono];

    return (
        <div className={`flex items-start gap-3 rounded-lg border p-4 ${marco}`}>
            <span className="mt-0.5 shrink-0">{icono}</span>

            <div className="min-w-0 space-y-0.5 text-sm">
                <p className="flex items-center gap-1.5 font-semibold">
                    <IdCard className="size-4 shrink-0 opacity-70" />
                    {titulo}
                </p>
                <p className="text-muted-foreground">{detalle}</p>
            </div>
        </div>
    );
}
