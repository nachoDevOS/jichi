import { Plus, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { bs } from '@/lib/utils';
import type { CatalogosGuiaTransporte, FilaProductoGuia } from '@/types/tramites';

/**
 * ============================================================================
 *  SECCIÓN D — PRODUCTOS HIDROBIOLÓGICOS
 * ============================================================================
 *
 * En el talonario esta sección es una grilla de trece columnas: cinco
 * renglones de especies contra diez columnas de presentación (fresco entero,
 * fresco eviscerado, congelado entero, congelado eviscerado, fileteado, seco,
 * sal preso, vivos, a granel, otros), donde se marca una cruz debajo de la
 * que corresponda.
 *
 * ACÁ NO SE COPIA ESA GRILLA, Y ES A PROPÓSITO.
 *
 * Trece columnas no entran en una pantalla de ventanilla, y menos en un
 * celular. Lo que el papel captura de cada renglón es siempre lo mismo: qué
 * especie, en qué presentación, cuántos kilos y a qué precio. Eso es una lista
 * desplegable de diez opciones, no diez columnas. El dato guardado es idéntico
 * y la carga es mucho más rápida.
 *
 * Lo que sí se gana sobre el papel: los renglones se agregan y se quitan, y el
 * total se calcula solo. En el talonario son cinco fijos y la suma se hace a
 * mano.
 */
export function TablaProductosGuia({
    productos,
    catalogos,
    onCambiar,
}: {
    productos: FilaProductoGuia[];
    catalogos: CatalogosGuiaTransporte;
    onCambiar: (productos: FilaProductoGuia[]) => void;
}) {
    function actualizar(id: string, campo: keyof FilaProductoGuia, valor: string) {
        onCambiar(productos.map((p) => (p.id === id ? { ...p, [campo]: valor } : p)));
    }

    function agregar() {
        onCambiar([...productos, filaVacia()]);
    }

    function quitar(id: string) {
        onCambiar(productos.filter((p) => p.id !== id));
    }

    return (
        <div className="space-y-3">
            {productos.map((producto, i) => (
                <div key={producto.id} className="rounded-lg border border-border p-3">
                    <div className="mb-2 flex items-center justify-between">
                        <span className="text-xs font-semibold text-muted-foreground">
                            Renglón {i + 1}
                        </span>

                        {/* Con un solo renglón no se ofrece borrarlo: dejaría la
                            guía sin ningún producto, que no tiene sentido. */}
                        {productos.length > 1 && (
                            <button
                                type="button"
                                onClick={() => quitar(producto.id)}
                                className="text-muted-foreground transition-colors hover:text-destructive"
                                aria-label={`Quitar el renglón ${i + 1}`}
                            >
                                <Trash2 className="size-4" />
                            </button>
                        )}
                    </div>

                    <div className="grid gap-3 sm:grid-cols-2">
                        <label className="space-y-1.5">
                            <span className="text-xs text-muted-foreground">Especie</span>
                            <Select
                                value={producto.especie}
                                onChange={(e) => actualizar(producto.id, 'especie', e.target.value)}
                            >
                                <option value="">Elegir especie…</option>
                                {catalogos.especies.map((especie) => (
                                    <option key={especie} value={especie}>
                                        {especie}
                                    </option>
                                ))}
                            </Select>
                        </label>

                        <label className="space-y-1.5">
                            <span className="text-xs text-muted-foreground">Presentación</span>
                            <Select
                                value={producto.presentacion}
                                onChange={(e) =>
                                    actualizar(producto.id, 'presentacion', e.target.value)
                                }
                            >
                                <option value="">Elegir presentación…</option>
                                {/* optgroup reproduce los tres bloques que el
                                    papel dibuja como columnas agrupadas. */}
                                {['Fresco o refrigerado', 'Congelado', 'Otros'].map((grupo) => (
                                    <optgroup key={grupo} label={grupo}>
                                        {catalogos.presentaciones
                                            .filter((p) => p.grupo === grupo)
                                            .map((p) => (
                                                <option key={p.value} value={p.value}>
                                                    {p.label}
                                                </option>
                                            ))}
                                    </optgroup>
                                ))}
                            </Select>
                        </label>

                        <label className="space-y-1.5">
                            <span className="text-xs text-muted-foreground">
                                Cantidad en Kg (descarga o trasbordo)
                            </span>
                            <Input
                                type="number"
                                min="0"
                                step="0.01"
                                value={producto.cantidad_kg}
                                onChange={(e) =>
                                    actualizar(producto.id, 'cantidad_kg', e.target.value)
                                }
                            />
                        </label>

                        <label className="space-y-1.5">
                            <span className="text-xs text-muted-foreground">
                                Precio por Kg en lugar de origen
                            </span>
                            <Input
                                type="number"
                                min="0"
                                step="0.01"
                                value={producto.precio_kg}
                                onChange={(e) =>
                                    actualizar(producto.id, 'precio_kg', e.target.value)
                                }
                            />
                        </label>
                    </div>

                    <p className="mt-2 text-right text-xs text-muted-foreground">
                        Importe del renglón:{' '}
                        <span className="font-semibold text-foreground">
                            {bs(importeDeFila(producto))}
                        </span>
                    </p>
                </div>
            ))}

            <div className="flex flex-wrap items-center justify-between gap-3">
                <Button type="button" variant="secondary" onClick={agregar}>
                    <Plus className="size-4" />
                    Agregar renglón
                </Button>

                <div className="text-right text-sm">
                    <p className="text-muted-foreground">
                        Total Kg:{' '}
                        <span className="font-semibold text-foreground">
                            {totalKg(productos).toFixed(2)}
                        </span>
                    </p>
                    <p className="text-muted-foreground">
                        Importe total:{' '}
                        <span className="font-semibold text-foreground">
                            {bs(totalImporte(productos))}
                        </span>
                    </p>
                </div>
            </div>
        </div>
    );
}

/**
 * Un renglón vacío, listo para llenar.
 *
 * El id no viaja al servidor: React lo necesita como `key` para no confundir
 * los renglones cuando se borra uno del medio. Si se usara el índice del array
 * como key, borrar el renglón 2 haría que el 3 herede su estado.
 */
export function filaVacia(): FilaProductoGuia {
    return {
        id: `fila-${Math.random().toString(36).slice(2, 9)}`,
        especie: '',
        presentacion: '',
        cantidad_kg: '',
        precio_kg: '',
    };
}

export function importeDeFila(fila: FilaProductoGuia): number {
    const kg = Number.parseFloat(fila.cantidad_kg);
    const precio = Number.parseFloat(fila.precio_kg);

    // Un campo a medio escribir da NaN; se trata como cero para que el total
    // no muestre "NaN Bs" mientras el operador todavía está tipeando.
    if (!Number.isFinite(kg) || !Number.isFinite(precio)) {
        return 0;
    }

    return Math.round(kg * precio * 100) / 100;
}

export function totalKg(productos: FilaProductoGuia[]): number {
    return productos.reduce((suma, p) => {
        const kg = Number.parseFloat(p.cantidad_kg);

        return suma + (Number.isFinite(kg) ? kg : 0);
    }, 0);
}

export function totalImporte(productos: FilaProductoGuia[]): number {
    return Math.round(productos.reduce((suma, p) => suma + importeDeFila(p), 0) * 100) / 100;
}
