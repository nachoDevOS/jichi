import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { colorSerie } from '@/lib/graficos';
import type { DocumentosPorTipo as FilaDocumento } from '@/types/dashboard';

/**
 * Cuántos documentos se emitieron este año, separados por categoría
 * (certificaciones, credenciales, permisos, licencias).
 *
 * Es una lista simple con un punto de color, no un gráfico de torta: con
 * cuatro categorías la lista se lee más rápido y muestra el número exacto,
 * que es lo que la unidad de recaudación necesita.
 */
export function DocumentosPorTipo({ datos }: { datos: FilaDocumento[] }) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Documentos emitidos este año</CardTitle>
            </CardHeader>

            <CardContent className="space-y-2">
                {datos.map((t, i) => (
                    <div key={t.tipo} className="flex items-center gap-3 text-sm">
                        {/*
                            El color va en `style` y no en una clase de Tailwind
                            porque se calcula en tiempo de ejecución. Tailwind
                            solo incluye en el CSS final las clases que puede
                            leer literalmente en el código: una clase armada con
                            una variable nunca llegaría al archivo compilado.
                        */}
                        <span
                            className="size-2.5 shrink-0 rounded-full"
                            style={{ background: colorSerie(i) }}
                        />

                        <span className="flex-1 truncate">{t.etiqueta}</span>

                        <span className="font-medium tabular-nums">{t.cantidad}</span>
                    </div>
                ))}

                {datos.length === 0 && (
                    <p className="py-4 text-center text-sm text-muted-foreground">
                        Sin documentos emitidos.
                    </p>
                )}
            </CardContent>
        </Card>
    );
}
