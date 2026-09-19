/**
 * Cuánto queda del cupo, en números y en barra.
 *
 * ----------------------------------------------------------------------------
 *  POR QUÉ ES UN COMPONENTE Y NO CÓDIGO DE LA PANTALLA
 * ----------------------------------------------------------------------------
 *
 * Lo usan el listado y la ficha, y tiene que decir lo MISMO en los dos: el
 * umbral de «le queda poco» pintado de un color en una pantalla y de otro en la
 * siguiente es peor que no pintarlo, porque enseña a desconfiar del color.
 *
 * ----------------------------------------------------------------------------
 *  EL PORCENTAJE LLEGA CALCULADO DEL SERVIDOR
 * ----------------------------------------------------------------------------
 *
 * No se divide acá. Un cupo de 0 kg no debería existir pero puede —un dato mal
 * cargado— y esa división reventaría la fila entera. `porcentajeUsado()` del
 * modelo devuelve 100 en ese caso y sigue.
 *
 * La barra se dibuja con un div de ancho porcentual y no con una librería: para
 * un solo valor, traer recharts sería cargar 100 KB para pintar un rectángulo.
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
    };
}) {
    /*
     * Se avisa en ámbar por debajo del 20%.
     *
     * Es el umbral en que conviene que el pescador se entere ANTES de salir:
     * descubrir que no alcanza cuando vuelve con la bodega llena no sirve de
     * nada, porque el producto ya se extrajo.
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
