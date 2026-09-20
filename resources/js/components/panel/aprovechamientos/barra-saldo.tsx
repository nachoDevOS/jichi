/**
 * Cuánto queda del cupo, en números y en barra.
 */
export function BarraSaldo({
    cupo,
}: {
    cupo: {
        saldo_kg: number;
        volumen_total_kg: number;
        porcentaje_usado: number;
        /*
         * OPCIONALES porque la ficha de la faena manda un cupo recortado, y
         * pedirlos siempre obligaría a arrastrarlos hasta ahí para no mostrar
         * nada: en modo estricto el exceso es cero por construcción.
         */
        kilos_excedidos?: number;
        excedido?: boolean;
        /*
         * OPCIONAL por lo mismo. En `false` no se dibuja saldo: antes de la
         * firma el cupo no puede consumirse, así que la barra salía llena y
         * «disponible 200 de 200» describía un movimiento imposible. Lo que hay
         * es un volumen PEDIDO. Sin el dato —la ficha de la faena— se dibuja.
         */
        ya_fue_aprobado?: boolean;
    };
}) {
    if (cupo.ya_fue_aprobado === false) {
        return (
            <p className="text-sm tabular-nums">
                <strong>{cupo.volumen_total_kg}</strong>
                <span className="text-muted-foreground"> kg solicitados</span>
            </p>
        );
    }

    /*
     * Se avisa en ámbar por debajo del 20%.
     */
    const escaso = cupo.volumen_total_kg > 0 && cupo.saldo_kg / cupo.volumen_total_kg < 0.2;

    return (
        <div className="space-y-1">
            <p className="text-sm tabular-nums">
                <strong className={escaso ? 'text-amber-700 dark:text-amber-400' : undefined}>
                    {cupo.saldo_kg}
                </strong>
                <span className="text-muted-foreground"> / {cupo.volumen_total_kg} kg</span>
            </p>

            <div className="h-1.5 overflow-hidden rounded-full bg-secondary">
                <div
                    className={
                        cupo.excedido
                            ? 'h-full rounded-full bg-destructive'
                            : escaso
                              ? 'h-full rounded-full bg-amber-500'
                              : 'h-full rounded-full bg-primary'
                    }
                    style={{ width: `${cupo.porcentaje_usado}%` }}
                />
            </div>

            {/*
                EL EXCESO SOLO EXISTE EN MODO FLEXIBLE.
                Con APROVECHAMIENTO_ESTRICTO=true la emisión frena antes, así
                que este renglón no aparece nunca. Con la validación apagada sí,
                y es el único lugar donde se ve cuánto se pescó de más: el saldo
                se corta en cero y no puede decirlo.
            */}
            {cupo.excedido && (
                <p className="text-xs font-medium tabular-nums text-destructive">
                    {cupo.kilos_excedidos} kg por encima del cupo
                </p>
            )}
        </div>
    );
}
