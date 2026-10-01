import { Badge } from '@/components/ui/badge';

/**
 * La tarifa de SIREB de una fila de catálogo, por su nombre. Los ids quedan en
 * el `title`; se muestran solo si SIREB no responde o ya no tiene la tarifa.
 */
export function CeldaSireb({
    servicioId,
    tarifaId,
    servicio,
    etiqueta,
}: {
    servicioId: string | null;
    tarifaId: string | null;
    servicio: string | null;
    etiqueta: string | null;
}) {
    if (!tarifaId) {
        return <Badge color="amber">Sin tarifa</Badge>;
    }

    if (servicio === null) {
        return (
            <>
                <p className="font-mono text-xs">
                    <span className="text-muted-foreground">Tarifa </span>
                    {tarifaId}
                </p>
                <p className="font-mono text-xs text-muted-foreground">Servicio {servicioId}</p>
            </>
        );
    }

    return (
        <div title={`Tarifa ${tarifaId}\nServicio ${servicioId}`}>
            <p>{servicio}</p>
            <p className="text-xs text-muted-foreground">{etiqueta}</p>
        </div>
    );
}
