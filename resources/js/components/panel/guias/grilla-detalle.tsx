import { Plus, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import type { OpcionEnum } from '@/types';
import type { CondicionProducto, FilaDetalleFormulario } from '@/types/guias';

/** Una fila nueva, vacía. La condición arranca en la más común. */
export function filaVacia(): FilaDetalleFormulario {
    return {
        especie: '',
        condicion: 'fresco',
        cantidad_kg: '',
        precio_unitario: '',
        imponible: '',
    };
}

/**
 * ============================================================================
 *  LA CARGA DE LA GUÍA, ESPECIE POR ESPECIE
 * ============================================================================
 *
 * Es el corazón del formulario: una guía traslada varias especies a la vez, cada
 * una con su condición, sus kilos y su valor.
 *
 * ----------------------------------------------------------------------------
 *  LAS FILAS SE IDENTIFICAN POR POSICIÓN, NO POR UN id
 * ----------------------------------------------------------------------------
 *
 * Y eso obliga a tener cuidado con el `key` de React: usar el índice es
 * justamente lo que se desaconseja, porque al borrar la fila 2 las de abajo
 * cambian de índice y React reusa el <input> equivocado —el texto salta de
 * renglón—. Acá NO pasa porque cada fila se dibuja entera a partir de su valor y
 * no guarda estado propio; aun así, borrar y agregar siempre crean un array
 * nuevo en vez de mutar el existente.
 *
 * ----------------------------------------------------------------------------
 *  EL IMPORTE NO SE CALCULA SOLO
 * ----------------------------------------------------------------------------
 *
 * `imponible` es la base de cálculo que la unidad escribe en el papel, y cuando
 * aplica una rebaja o redondea, NO coincide con cantidad × precio. Si esta
 * grilla lo completara sola, el sistema terminaría contradiciendo una guía
 * firmada. Lo que sí hace es MOSTRAR la multiplicación como referencia, debajo
 * del campo, para que el operador note una diferencia grande.
 */
export function GrillaDetalle({
    filas,
    condiciones,
    onCambiar,
    errores,
    deshabilitado = false,
}: {
    filas: FilaDetalleFormulario[];
    condiciones: OpcionEnum[];
    onCambiar: (filas: FilaDetalleFormulario[]) => void;
    /** Los errores de Laravel, con claves tipo `detalles.0.especie`. */
    errores?: Record<string, string>;
    deshabilitado?: boolean;
}) {
    function cambiar(i: number, campo: keyof FilaDetalleFormulario, valor: string) {
        onCambiar(filas.map((f, j) => (i === j ? { ...f, [campo]: valor } : f)));
    }

    function agregar() {
        onCambiar([...filas, filaVacia()]);
    }

    function quitar(i: number) {
        // Nunca se queda sin filas: una guía sin carga no ampara nada, y una
        // grilla vacía sin caja donde escribir deja al operador trabado.
        const quedan = filas.filter((_, j) => j !== i);

        onCambiar(quedan.length > 0 ? quedan : [filaVacia()]);
    }

    const totalKg = filas.reduce((suma, f) => suma + (parseFloat(f.cantidad_kg) || 0), 0);
    const totalImporte = filas.reduce((suma, f) => suma + importeDe(f), 0);

    return (
        <div className="space-y-3">
            <div className="overflow-x-auto">
                <table className="w-full text-sm">
                    <thead className="text-left text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th className="pb-2 pr-2 font-medium">Especie</th>
                            <th className="pb-2 pr-2 font-medium">Condición</th>
                            <th className="pb-2 pr-2 font-medium">Kg</th>
                            <th className="pb-2 pr-2 font-medium">Precio unit.</th>
                            <th className="pb-2 pr-2 font-medium">Imponible</th>
                            <th className="pb-2" />
                        </tr>
                    </thead>

                    <tbody>
                        {filas.map((f, i) => (
                            <tr key={i} className="align-top">
                                <td className="py-1 pr-2">
                                    <Input
                                        value={f.especie}
                                        maxLength={100}
                                        placeholder="Surubí"
                                        disabled={deshabilitado}
                                        onChange={(e) => cambiar(i, 'especie', e.target.value)}
                                        aria-label={`Especie de la línea ${i + 1}`}
                                        aria-invalid={Boolean(errores?.[`detalles.${i}.especie`])}
                                    />
                                    {errores?.[`detalles.${i}.especie`] && (
                                        <p className="mt-1 text-xs text-destructive" role="alert">
                                            {errores[`detalles.${i}.especie`]}
                                        </p>
                                    )}
                                </td>

                                <td className="py-1 pr-2">
                                    <Select
                                        value={f.condicion}
                                        disabled={deshabilitado}
                                        onChange={(e) =>
                                            cambiar(i, 'condicion', e.target.value as CondicionProducto)
                                        }
                                        aria-label={`Condición de la línea ${i + 1}`}
                                    >
                                        {condiciones.map((c) => (
                                            <option key={c.value} value={c.value}>
                                                {c.label}
                                            </option>
                                        ))}
                                    </Select>
                                </td>

                                <td className="py-1 pr-2">
                                    <Input
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        className="w-24"
                                        value={f.cantidad_kg}
                                        disabled={deshabilitado}
                                        onChange={(e) => cambiar(i, 'cantidad_kg', e.target.value)}
                                        aria-label={`Kilos de la línea ${i + 1}`}
                                        aria-invalid={Boolean(errores?.[`detalles.${i}.cantidad_kg`])}
                                    />
                                    {errores?.[`detalles.${i}.cantidad_kg`] && (
                                        <p className="mt-1 text-xs text-destructive" role="alert">
                                            {errores[`detalles.${i}.cantidad_kg`]}
                                        </p>
                                    )}
                                </td>

                                <td className="py-1 pr-2">
                                    <Input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        className="w-24"
                                        value={f.precio_unitario}
                                        disabled={deshabilitado}
                                        onChange={(e) => cambiar(i, 'precio_unitario', e.target.value)}
                                        aria-label={`Precio por kilo de la línea ${i + 1}`}
                                    />
                                </td>

                                <td className="py-1 pr-2">
                                    <Input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        className="w-28"
                                        placeholder={sugerencia(f)}
                                        value={f.imponible}
                                        disabled={deshabilitado}
                                        onChange={(e) => cambiar(i, 'imponible', e.target.value)}
                                        aria-label={`Imponible de la línea ${i + 1}`}
                                    />
                                    {/*
                                        La multiplicación va como PLACEHOLDER y
                                        no como valor: si se escribiera sola,
                                        pisaría el número del papel cuando hubo
                                        una rebaja o un redondeo.
                                    */}
                                </td>

                                <td className="py-1">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        disabled={deshabilitado}
                                        onClick={() => quitar(i)}
                                        aria-label={`Quitar la línea ${i + 1}`}
                                    >
                                        <Trash2 className="size-4" />
                                    </Button>
                                </td>
                            </tr>
                        ))}
                    </tbody>

                    <tfoot className="border-t border-border">
                        <tr>
                            <td className="pt-2 text-xs uppercase tracking-wide text-muted-foreground">
                                Total
                            </td>
                            <td />
                            <td className="pt-2 font-semibold tabular-nums">
                                {totalKg.toFixed(2)}
                            </td>
                            <td />
                            <td className="pt-2 font-semibold tabular-nums">
                                {totalImporte.toFixed(2)}
                            </td>
                            <td />
                        </tr>
                    </tfoot>
                </table>
            </div>

            {/* El error de la lista entera —«cargue al menos una línea»— no
                pertenece a ninguna fila, así que va acá abajo. */}
            {errores?.detalles && (
                <p className="text-sm text-destructive" role="alert">
                    {errores.detalles}
                </p>
            )}

            {!deshabilitado && (
                <Button type="button" variant="outline" size="sm" onClick={agregar}>
                    <Plus className="size-4" />
                    Agregar especie
                </Button>
            )}
        </div>
    );
}

/**
 * Lo que vale una línea, con la misma regla que `GuiaDetalle::importe()` en PHP:
 * manda `imponible` y solo si no está se multiplica.
 *
 * Está escrita dos veces —acá y en el modelo— y no hay forma de evitarlo: el
 * total del pie se actualiza mientras el operador tipea, y eso solo puede pasar
 * en el navegador. Si una de las dos cambia, hay que tocar la otra.
 */
function importeDe(fila: FilaDetalleFormulario): number {
    const imponible = parseFloat(fila.imponible);

    if (!Number.isNaN(imponible)) {
        return imponible;
    }

    return (parseFloat(fila.cantidad_kg) || 0) * (parseFloat(fila.precio_unitario) || 0);
}

/** La multiplicación, como pista debajo del campo imponible. */
function sugerencia(fila: FilaDetalleFormulario): string {
    const calculado = (parseFloat(fila.cantidad_kg) || 0) * (parseFloat(fila.precio_unitario) || 0);

    return calculado > 0 ? calculado.toFixed(2) : '';
}
