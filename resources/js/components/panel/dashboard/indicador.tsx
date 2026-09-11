import type { LucideIcon } from 'lucide-react';
import { Card, CardContent } from '@/components/ui/card';

/**
 * Tarjeta con un número grande: "Recaudado hoy", "Trámites recibidos"...
 *
 * Es el mismo componente repetido cuatro veces con datos distintos. Ese es el
 * sentido de un componente: se escribe el diseño UNA vez y se reutiliza
 * cambiándole las props.
 */
export function Indicador({
    icono: Icono,
    etiqueta,
    valor,
    pie,
    destacado = false,
}: {
    /*
     * El icono se recibe como componente, no como texto. Por eso se renombra
     * a `Icono` con mayúscula al desestructurarlo: React solo trata como
     * componente lo que empieza con mayúscula. En minúscula, <icono /> se
     * interpretaría como una etiqueta HTML llamada "icono" y no se vería nada.
     */
    icono: LucideIcon;
    etiqueta: string;
    /** Ya viene formateado como texto ("1.250,50 Bs"), no como número. */
    valor: string;
    /** Línea chica de contexto debajo del número. */
    pie?: string;
    /** Lo pinta en dorado institucional. Se usa para el dato más importante. */
    destacado?: boolean;
}) {
    return (
        <Card className={destacado ? 'border-accent/40 bg-accent/5' : undefined}>
            <CardContent className="flex items-start gap-4 p-5">
                <div
                    className={`flex size-10 shrink-0 items-center justify-center rounded-lg ${
                        destacado ? 'bg-accent/20 text-accent-foreground' : 'bg-primary/10 text-primary'
                    }`}
                >
                    <Icono className="size-5" />
                </div>

                <div className="min-w-0">
                    <p className="text-sm text-muted-foreground">{etiqueta}</p>

                    {/* tabular-nums hace que todos los dígitos ocupen lo mismo:
                        los montos quedan alineados y no bailan al actualizarse. */}
                    <p className="mt-0.5 truncate text-2xl font-semibold tabular-nums">{valor}</p>

                    {pie && <p className="mt-1 truncate text-xs text-muted-foreground">{pie}</p>}
                </div>
            </CardContent>
        </Card>
    );
}
