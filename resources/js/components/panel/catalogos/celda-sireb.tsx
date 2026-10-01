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
    estado,
}: {
    servicioId: string | null;
    tarifaId: string | null;
    servicio: string | null;
    etiqueta: string | null;
    /** Estado de la tarifa en SIREB; sin él no se muestra la etiqueta. */
    estado?: string | null;
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
            {estado && (
                <Badge color={estado === 'activo' ? 'emerald' : 'slate'} className="mt-1">
                    {estado === 'activo' ? 'Activa' : 'Inactiva'}
                </Badge>
            )}
        </div>
    );
}
