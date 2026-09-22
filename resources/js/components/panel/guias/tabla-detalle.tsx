import { Plus, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import type { CatalogosGuia, FilaDetalle } from '@/types/guias';

/** Una fila nueva del cuadro, con su `key` estable. */
export function filaVacia(): FilaDetalle {
    return {
        // Sin `key` estable, quitar la del medio remonta las de abajo y React
        // les mezcla los valores. Misma razón que en la tarjeta de pagos.
        key: `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
        especie: '',
        condicion: '',
        cantidad_kg: '',
        precio_kg: '',
    };
}

/**
 *  EL CUADRO D — una fila por especie.
 *
 * El talonario trae cinco renglones y acá no hay tope: quien necesite seis
 * emite una sola guía en vez de dos. El importe NO se escribe: sale de kilos ×
 * precio, y tenerlo como campo aparte deja dos números que pueden discrepar en
 * el mismo papel.
 */
export function TablaDetalle({
    filas,
    condiciones,
    errores,
    onCambiar,
}: {
    filas: FilaDetalle[];
    condiciones: CatalogosGuia['condiciones'];
    errores: Partial<Record<string, string>>;
    onCambiar: (filas: FilaDetalle[]) => void;
}) {
    const editar = (i: number, campo: keyof FilaDetalle, valor: string) =>
        onCambiar(filas.map((f, j) => (j === i ? { ...f, [campo]: valor } : f)));

    const totalKg = filas.reduce((suma, f) => suma + Number(f.cantidad_kg || 0), 0);
    const totalBs = filas.reduce(
        (suma, f) => suma + Number(f.cantidad_kg || 0) * Number(f.precio_kg || 0),
        0,
    );

    return (
        <div className="space-y-3">
            {/* `min-w-0` en el contenedor: sin él la tabla estira la tarjeta a su
                ancho natural y el que termina con barra de desplazamiento es el
                documento entero. Ver CLAUDE.md. */}
            <div className="min-w-0 overflow-x-auto">
                <table className="w-full min-w-[640px] text-sm">
                    <thead>
                        <tr className="border-b border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                            <th className="py-2 pr-2 font-medium">Especie</th>
                            <th className="py-2 pr-2 font-medium">Condición</th>
                            <th className="py-2 pr-2 text-right font-medium">Kg</th>
                            <th className="py-2 pr-2 text-right font-medium">Precio Bs/kg</th>
                            <th className="py-2 pr-2 text-right font-medium">Importe</th>
                            <th className="w-10 py-2" />
                        </tr>
                    </thead>

                    <tbody>
                        {filas.map((fila, i) => (
                            <tr key={fila.key} className="border-b border-border/60 last:border-0">
                                <td className="py-2 pr-2">
                                    <Input
                                        value={fila.especie}
                                        onChange={(e) => editar(i, 'especie', e.target.value)}
                                        aria-invalid={Boolean(errores[`detalles.${i}.especie`])}
                                        placeholder="Surubí"
                                        aria-label={`Especie del renglón ${i + 1}`}
                                    />
                                </td>

                                <td className="py-2 pr-2">
                                    <Select
                                        value={fila.condicion}
                                        onChange={(e) => editar(i, 'condicion', e.target.value)}
                                        aria-invalid={Boolean(errores[`detalles.${i}.condicion`])}
                                        aria-label={`Condición del renglón ${i + 1}`}
                                    >
                                        <option value="">— elegir —</option>
                                        {condiciones.map((c) => (
                                            <option key={c.value} value={c.value}>
                                                {c.label}
                                            </option>
                                        ))}
                                    </Select>
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

                                <td className="py-2 pr-2">
                                    <Input
                                        type="number"
                                        step="0.01"
                                        min={0}
                                        value={fila.precio_kg}
                                        onChange={(e) => editar(i, 'precio_kg', e.target.value)}
                                        aria-invalid={Boolean(errores[`detalles.${i}.precio_kg`])}
                                        className="text-right tabular-nums"
                                        aria-label={`Precio del renglón ${i + 1}`}
                                    />
                                </td>

                                <td className="py-2 pr-2 text-right tabular-nums text-muted-foreground">
                                    {(
                                        Number(fila.cantidad_kg || 0) * Number(fila.precio_kg || 0)
                                    ).toFixed(2)}
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
