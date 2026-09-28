import { Plus, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import type { CatalogosGuia, FilaDetalle, ProductoGuia } from '@/types/guias';

/** Una fila nueva del cuadro, con su `key` estable. */
export function filaVacia(): FilaDetalle {
    return {
        // Sin `key` estable, quitar la del medio remonta las de abajo y React
        // les mezcla los valores. Misma razón que en la tarjeta de pagos.
        key: `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
        producto_id: '',
        condicion: '',
        cantidad_kg: '',
    };
}

/**
 *  EL CUADRO D — una fila por especie.
 *
 * El talonario trae cinco renglones y acá no hay tope. El producto se elige del
 * catálogo y su precio por kilo NO se escribe: lo copia el servidor al guardar.
 * El importe sale de kilos × precio.
 */
export function TablaDetalle({
    filas,
    productos,
    condiciones,
    errores,
    onCambiar,
}: {
    filas: FilaDetalle[];
    productos: ProductoGuia[];
    condiciones: CatalogosGuia['condiciones'];
    errores: Partial<Record<string, string>>;
    onCambiar: (filas: FilaDetalle[]) => void;
}) {
    const editar = (i: number, campo: keyof FilaDetalle, valor: string) =>
        onCambiar(filas.map((f, j) => (j === i ? { ...f, [campo]: valor } : f)));

    // Los grupos del papel en su orden: «Fresco o Refrigerado», «Congelado» y las sueltas.
    const agrupadas = condiciones.reduce<{ grupo: string; opciones: typeof condiciones }[]>((bloques, c) => {
        const ultimo = bloques[bloques.length - 1];

        if (c.grupo !== '' && ultimo?.grupo === c.grupo) {
            ultimo.opciones.push(c);
        } else {
            bloques.push({ grupo: c.grupo, opciones: [c] });
        }

        return bloques;
    }, []);
    const grupoDe = (valor: string) => condiciones.find((c) => c.value === valor)?.grupo ?? '';

    const precioDe = (f: FilaDetalle) => productos.find((p) => String(p.id) === f.producto_id)?.precio_kg ?? 0;

    const totalKg = filas.reduce((suma, f) => suma + Number(f.cantidad_kg || 0), 0);
    const totalBs = filas.reduce((suma, f) => suma + Number(f.cantidad_kg || 0) * precioDe(f), 0);

    return (
        <div className="space-y-3">
            {/* `min-w-0` en el contenedor: sin él la tabla estira la tarjeta a su
                ancho natural y el que termina con barra de desplazamiento es el
                documento entero. Ver CLAUDE.md. */}
            <div className="min-w-0 overflow-x-auto">
                <table className="w-full min-w-[640px] text-sm">
                    <thead>
                        <tr className="border-b border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                            <th className="py-2 pr-2 font-medium">Producto</th>
                            <th className="py-2 pr-2 font-medium">Condición</th>
                            <th className="py-2 pr-2 text-right font-medium">Kg</th>
                            <th className="py-2 pr-2 text-right font-medium">Precio Bs/kg</th>
                            <th className="py-2 pr-2 text-right font-medium">Importe</th>
                            <th className="w-10 py-2" />
                        </tr>
                    </thead>

                    <tbody>
                        {filas.map((fila, i) => (
                            <tr key={fila.key} className="border-b border-border/60 align-top last:border-0">
                                <td className="py-2 pr-2">
                                    <Select
                                        value={fila.producto_id}
                                        onChange={(e) => editar(i, 'producto_id', e.target.value)}
                                        aria-invalid={Boolean(errores[`detalles.${i}.producto_id`])}
                                        aria-label={`Producto del renglón ${i + 1}`}
                                    >
                                        <option value="">— elegir —</option>
                                        {productos.map((p) => (
                                            <option key={p.id} value={p.id}>
                                                {p.nombre}
                                                {!p.estado && ' (fuera de uso)'}
                                            </option>
                                        ))}
                                    </Select>
                                </td>

                                <td className="py-2 pr-2">
                                    <Select
                                        value={fila.condicion}
                                        onChange={(e) => editar(i, 'condicion', e.target.value)}
                                        aria-invalid={Boolean(errores[`detalles.${i}.condicion`])}
                                        aria-label={`Condición del renglón ${i + 1}`}
                                    >
                                        <option value="">— elegir —</option>
                                        {agrupadas.map((bloque) =>
                                            bloque.grupo === '' ? (
                                                bloque.opciones.map((c) => (
                                                    <option key={c.value} value={c.value}>
                                                        {c.label}
                                                    </option>
                                                ))
                                            ) : (
                                                <optgroup key={bloque.grupo} label={bloque.grupo}>
                                                    {bloque.opciones.map((c) => (
                                                        <option key={c.value} value={c.value}>
                                                            {c.corta}
                                                        </option>
                                                    ))}
                                                </optgroup>
                                            ),
                                        )}
                                    </Select>
                                    {/* Cerrado, el select muestra solo «Entero»: el grupo va debajo. */}
                                    {grupoDe(fila.condicion) && (
                                        <p className="mt-1 text-xs text-muted-foreground">{grupoDe(fila.condicion)}</p>
                                    )}
                                </td>

                                <td className="py-2 pr-2">
                                    <Input
                                        type="number"
                                        step="0.01"
                                        min={0}
                                        value={fila.cantidad_kg}
                                        onChange={(e) => editar(i, 'cantidad_kg', e.target.value)}
                                        aria-invalid={Boolean(errores[`detalles.${i}.cantidad_kg`])}
                                        className="text-right tabular-nums"
                                        aria-label={`Kilos del renglón ${i + 1}`}
                                    />
                                </td>

                                {/* Del catálogo, solo lectura: el servidor lo copia al guardar. */}
                                <td className="py-2 pr-2 pt-4 text-right tabular-nums text-muted-foreground">
                                    {fila.producto_id ? precioDe(fila).toFixed(2) : '—'}
                                </td>

                                <td className="py-2 pr-2 pt-4 text-right tabular-nums text-muted-foreground">
                                    {(Number(fila.cantidad_kg || 0) * precioDe(fila)).toFixed(2)}
                                </td>

                                <td className="py-2 text-right">
                                    {/* La última fila no se puede quitar: una guía
                                        sin renglones no ampara nada, y el servidor
                                        la rechaza igual. */}
                                    <Button
                                        type="button"
                                        variant="eliminar"
                                        size="icon"
                                        disabled={filas.length <= 1}
                                        onClick={() => onCambiar(filas.filter((_, j) => j !== i))}
                                        aria-label={`Quitar el renglón ${i + 1}`}
                                    >
                                        <Trash2 className="size-4" />
                                    </Button>
                                </td>
                            </tr>
                        ))}
                    </tbody>

                    <tfoot>
                        <tr className="border-t border-border font-medium">
                            <td className="py-2 pr-2" colSpan={2}>
                                Totales
                            </td>
                            <td className="py-2 pr-2 text-right tabular-nums">{totalKg.toFixed(2)}</td>
                            <td />
                            <td className="py-2 pr-2 text-right tabular-nums">{totalBs.toFixed(2)}</td>
                            <td />
                        </tr>
                    </tfoot>
                </table>
            </div>

            {/* El error del arreglo entero: «cargue al menos una especie». */}
            {errores.detalles && (
                <p className="text-sm text-destructive" role="alert">
                    {errores.detalles}
                </p>
            )}

            <Button
                type="button"
                variant="ver"
                onClick={() => onCambiar([...filas, filaVacia()])}
            >
                <Plus className="size-4" />
                Agregar especie
            </Button>
        </div>
    );
}
