import { Link } from '@inertiajs/react';
import { ArrowRight, Hammer, Lock, ShieldAlert } from 'lucide-react';
import { bs, cn } from '@/lib/utils';
import type { SolicitanteDelTramite, TipoTramiteOpcion } from '@/types/tramites';

/**
 * PASO 2 DEL ALTA: elegir qué servicio se está tramitando.
 *
 * ¿POR QUÉ TARJETAS Y NO UNA LISTA DESPLEGABLE?
 *
 * Porque en ventanilla el operador necesita ver el precio y la vigencia ANTES
 * de elegir, no después. Un <select> obliga a abrirlo, leer nombres sueltos y
 * elegir a ciegas; recién al guardar aparece el monto. Con tarjetas, los tres
 * datos que importan —qué es, cuánto cuesta y cuánto dura— están a la vista al
 * mismo tiempo.
 *
 * ----------------------------------------------------------------------------
 *  UNA TARJETA SE PUEDE APAGAR POR DOS MOTIVOS DISTINTOS
 * ----------------------------------------------------------------------------
 *
 *   1. El formulario todavía no está construido  → «Próximamente»
 *   2. Al solicitante le falta la cédula vigente → dice qué le falta y cómo
 *      resolverlo
 *
 * Son cosas muy distintas y se muestran distinto a propósito. La primera es
 * una limitación del sistema y no hay nada que hacer; la segunda es un
 * problema del trámite que el operador PUEDE resolver ahí mismo, sacándole la
 * cédula. Mezclarlas en un mismo cartel gris haría que el operador llame por
 * teléfono en un caso que sabía resolver solo.
 */
export function SelectorTipoTramite({
    tipos,
    solicitante,
}: {
    tipos: TipoTramiteOpcion[];
    solicitante: SolicitanteDelTramite;
}) {
    return (
        <div className="grid gap-4 md:grid-cols-2">
            {tipos.map((tipo) => (
                <TarjetaTipo key={tipo.codigo} tipo={tipo} solicitante={solicitante} />
            ))}
        </div>
    );
}

function TarjetaTipo({
    tipo,
    solicitante,
}: {
    tipo: TipoTramiteOpcion;
    solicitante: SolicitanteDelTramite;
}) {
    const construido = tipo.habilitado && Boolean(tipo.ruta);
    const bloqueado = tipo.habilitacion ? ! tipo.habilitacion.habilitado : false;
    const disponible = construido && ! bloqueado;

    const contenido = (
        <>
            <div className="flex items-start justify-between gap-3">
                <div className="flex items-center gap-2">
                    <span className="text-2xl" aria-hidden>
                        {tipo.icono}
                    </span>
                    <div className="leading-tight">
                        <p className="text-xs text-muted-foreground">{tipo.area}</p>
                        <p className="font-mono text-[11px] text-muted-foreground">{tipo.codigo}</p>
                    </div>
                </div>

                {disponible && <ArrowRight className="size-4 shrink-0 text-muted-foreground" />}
                {bloqueado && (
                    <ShieldAlert className="size-4 shrink-0 text-amber-600 dark:text-amber-400" />
                )}
                {! construido && <Hammer className="size-4 shrink-0 text-muted-foreground" />}
            </div>

            <p className="leading-snug font-semibold">{tipo.nombre}</p>

            {tipo.resumen && <p className="text-sm text-muted-foreground">{tipo.resumen}</p>}

            <div className="mt-auto flex items-baseline justify-between gap-2 border-t border-border pt-3">
                {/* Sin monto cargado se liquida por kilos: se dice
                    explícitamente para que nadie asuma que es gratis. */}
                <span className="text-sm font-semibold">
                    {tipo.monto === null ? 'Según liquidación' : bs(tipo.monto)}
                </span>
                <span className="text-right text-[11px] text-muted-foreground">
                    {tipo.vigencia}
                </span>
            </div>

            {/* El motivo del bloqueo, con lo que hay que hacer. Un cartel que
                solo dijera «no habilitado» obliga a adivinar. */}
            {bloqueado && tipo.habilitacion && (
                <div className="rounded-md border border-amber-500/40 bg-amber-500/10 p-3 text-xs">
                    <p className="font-semibold text-amber-900 dark:text-amber-200">
                        {tipo.habilitacion.motivo}
                    </p>
                    <p className="mt-0.5 text-amber-900/80 dark:text-amber-200/80">
                        {tipo.habilitacion.como_resolver}
                    </p>
                </div>
            )}

            {! construido && (
                <span className="text-[11px] font-medium text-muted-foreground">
                    Próximamente — el formulario todavía no está construido
                </span>
            )}
        </>
    );

    const estilo = cn(
        'flex h-full flex-col gap-2 rounded-lg border p-5 transition-colors',
        disponible && 'border-border hover:border-primary/60 hover:bg-accent/40',
        bloqueado && 'cursor-not-allowed border-amber-500/30 bg-amber-500/5',
        ! construido && 'cursor-not-allowed border-border bg-muted/40 opacity-60',
    );

    if (! disponible) {
        return <div className={estilo}>{contenido}</div>;
    }

    return (
        <Link
            href={route(tipo.ruta!, { solicitante: solicitante.id })}
            className={estilo}
        >
            {contenido}
        </Link>
    );
}
