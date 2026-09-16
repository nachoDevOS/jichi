import { useForm } from '@inertiajs/react';
import { Check, FileText, Plus, TriangleAlert } from 'lucide-react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Campo } from '@/components/ui/campo';
import { Input } from '@/components/ui/input';
import { SelectorArchivo } from '@/components/ui/selector-archivo';
import { bs, fecha } from '@/lib/utils';
import type { FormularioPago } from '@/types/pagos';
import type { PagoDelTramite } from '@/types/tramites';

/**
 * ============================================================================
 *  LOS DEPÓSITOS DE UN TRÁMITE — Regla C
 * ============================================================================
 *
 * Las tres piezas viven acá y no dentro de una pantalla porque las usan DOS:
 *
 *   - la FICHA del trámite muestra el resumen y la lista, de solo lectura;
 *   - CORREGIR PAPELES muestra lo mismo y además el formulario para cargar.
 *
 * Que el formulario esté en un solo lugar es deliberado: con el mismo
 * formulario en las dos pantallas, no quedaba claro cuál era «el lugar» donde
 * se cargan los depósitos. Corregir un expediente es una sola pantalla.
 */

/**
 * Cuánto se cobró y cuánto falta, con barra de avance.
 *
 * La barra no es adorno: tres números sueltos obligan a restar mentalmente para
 * saber si el expediente está cerca de poder aprobarse, y el operador hace esa
 * cuenta decenas de veces por jornada.
 *
 * El estado se dice ADEMÁS con palabras —«Cubierto» / «Falta X»—: el color solo
 * no alcanza, porque un daltónico ve las dos barras iguales.
 */
export function ResumenDepositos({
    montoRequerido,
    montoPagado,
    saldoPendiente,
    moneda,
}: {
    montoRequerido: number;
    montoPagado: number;
    saldoPendiente: number;
    moneda: string;
}) {
    const cubierto = saldoPendiente <= 0;

    /*
     * Se corta en 100 %: un depósito puede venir por unos bolivianos de más
     * —el sistema no lo rechaza, ver PagoTramiteService— y sin el tope la barra
     * se saldría del riel.
     */
    const avance =
        montoRequerido > 0 ? Math.min(100, (montoPagado / montoRequerido) * 100) : 100;

    return (
        <div className="space-y-3 rounded-md bg-secondary/50 p-4">
            <div className="grid gap-3 sm:grid-cols-3">
                <Cifra etiqueta="Costo" valor={bs(montoRequerido, moneda)} />
                <Cifra etiqueta="Cobrado" valor={bs(montoPagado, moneda)} />
                <Cifra
                    etiqueta="Saldo"
                    valor={bs(saldoPendiente, moneda)}
                    destacado={cubierto ? 'emerald' : 'amber'}
                />
            </div>

            <div>
                <div className="h-1.5 overflow-hidden rounded-full bg-border">
                    <div
                        className={
                            cubierto
                                ? 'h-full rounded-full bg-emerald-500 transition-all'
                                : 'h-full rounded-full bg-amber-500 transition-all'
                        }
                        style={{ width: `${avance}%` }}
                    />
                </div>

                <p className="mt-1.5 flex items-center gap-1.5 text-xs">
                    {cubierto ? (
                        <>
                            <Check className="size-3.5 shrink-0 text-emerald-600" />
                            <span className="font-medium text-emerald-700 dark:text-emerald-400">
                                Costo cubierto — el expediente se puede aprobar
                            </span>
                        </>
                    ) : (
                        <>
                            <TriangleAlert className="size-3.5 shrink-0 text-amber-600" />
                            <span className="text-muted-foreground">
                                Falta {bs(saldoPendiente, moneda)} para poder aprobar ·{' '}
                                {Math.round(avance)} % cobrado
                            </span>
                        </>
                    )}
                </p>
            </div>
        </div>
    );
}

function Cifra({
    etiqueta,
    valor,
    destacado,
}: {
    etiqueta: string;
    valor: string;
    destacado?: 'amber' | 'emerald';
}) {
    return (
        <div>
            <p className="text-xs uppercase tracking-wide text-muted-foreground">{etiqueta}</p>
            <p
                className={
                    destacado === 'amber'
                        ? 'text-lg font-semibold tabular-nums text-amber-700 dark:text-amber-400'
                        : destacado === 'emerald'
                          ? 'text-lg font-semibold tabular-nums text-emerald-700 dark:text-emerald-400'
                          : 'text-lg font-semibold tabular-nums'
                }
            >
                {valor}
            </p>
        </div>
    );
}

/**
 * La lista de depósitos ya cargados.
 *
 * El MONTO va primero y grande, porque es lo que se busca al cuadrar contra el
 * banco. El número de transacción y la fecha van abajo, en gris: son la
 * referencia, no el dato.
 */
export function ListaDepositos({
    pagos,
    moneda,
    vacio,
}: {
    pagos: PagoDelTramite[];
    moneda: string;
    /** Qué decir cuando no hay ninguno. Cambia según la pantalla. */
    vacio: string;
}) {
    if (pagos.length === 0) {
        return (
            <p className="rounded-md border border-dashed border-border p-4 text-center text-sm text-muted-foreground">
                Sin depósitos registrados todavía.
                <br />
                <span className="text-xs">{vacio}</span>
            </p>
        );
    }

    return (
        <ul className="space-y-2">
            {pagos.map((pago, i) => (
                <li
                    key={pago.id}
                    className="flex items-center gap-3 rounded-md border border-border p-3"
                >
                    {/* El número de orden ubica el depósito dentro del
                        expediente: con tres boletas, «el segundo» es como se lo
                        nombra en ventanilla. */}
                    <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-secondary text-xs font-semibold tabular-nums text-muted-foreground">
                        {i + 1}
                    </span>

                    <div className="min-w-0 flex-1">
                        <p className="text-[15px] font-semibold tabular-nums">
                            {bs(pago.monto, moneda)}
                        </p>
                        <p className="truncate text-xs text-muted-foreground">
                            Nº {pago.nro_transaccion} · {fecha(pago.fecha_pago)}
                        </p>
                    </div>

                    {pago.comprobante_url && (
                        <a
                            href={pago.comprobante_url}
                            target="_blank"
                            rel="noreferrer"
                            className="inline-flex shrink-0 items-center gap-1.5 rounded-md border border-border px-2.5 py-1.5 text-xs transition-colors hover:bg-secondary"
                        >
                            <FileText className="size-3.5" />
                            Ver boleta
                        </a>
                    )}
                </li>
            ))}
        </ul>
    );
}

/**
 * El formulario para cargar un depósito.
 *
 * VIVE SOLO EN «CORREGIR PAPELES». La ficha del trámite los muestra pero no deja
 * cargarlos.
 *
 * Va en DOS FILAS y no en una de cuatro columnas: apretado en un renglón, el
 * selector de boleta quedaba tan angosto que cortaba el nombre del archivo.
 */
export function FormularioDeposito({
    tramiteId,
    moneda,
}: {
    tramiteId: number;
    moneda: string;
}) {
    const form = useForm<FormularioPago>({
        nro_transaccion: '',
        monto: '',
        comprobante: null,
        fecha_pago: '',
        observaciones: '',
    });

    function enviar(e: FormEvent) {
        e.preventDefault();

        form.post(route('pagos.store', tramiteId), {
            forceFormData: true,
            // Al terminar se limpian los campos para poder cargar la siguiente
            // boleta sin recargar la pantalla.
            onSuccess: () => form.reset(),
        });
    }

    return (
        <form onSubmit={enviar} className="space-y-3 border-t border-border pt-4">
            <p className="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                <Plus className="size-3.5" />
                Registrar un depósito
            </p>

            <div className="grid gap-3 sm:grid-cols-3">
                <Campo
                    etiqueta="Nº transacción"
                    htmlFor="nro_transaccion"
                    obligatorio
                    error={form.errors.nro_transaccion}
                    ayuda="El del comprobante del banco. Solo números."
                >
                    {/*
                        Solo dígitos, y se limpian AL TECLEAR en vez de rebotar
                        el formulario: el operador está copiando de una boleta de
                        papel. No es `type="number"` a propósito — ese tipo acepta
                        `e`, `+`, `-` y coma decimal, y le pone flechitas a un
                        campo que no es una cantidad. Ver campo-pagos.tsx.
                    */}
                    <Input
                        id="nro_transaccion"
                        inputMode="numeric"
                        value={form.data.nro_transaccion}
                        onChange={(e) =>
                            form.setData('nro_transaccion', e.target.value.replace(/\D/g, ''))
                        }
                    />
                </Campo>

                <Campo
                    etiqueta={`Monto (${moneda})`}
                    htmlFor="monto"
                    obligatorio
                    error={form.errors.monto}
                    ayuda="Lo que dice la boleta"
                >
                    <Input
                        id="monto"
                        type="number"
                        step="0.01"
                        min="0.01"
                        value={form.data.monto}
                        onChange={(e) => form.setData('monto', e.target.value)}
                    />
                </Campo>

                <Campo etiqueta="Boleta escaneada" htmlFor="comprobante" obligatorio>
                    <SelectorArchivo
                        id="comprobante"
                        archivo={form.data.comprobante}
                        onElegir={(a) => form.setData('comprobante', a)}
                        error={form.errors.comprobante}
                    />
                </Campo>
            </div>

            <div className="flex justify-end">
                <Button type="submit" disabled={form.processing}>
                    {form.processing ? 'Guardando…' : 'Registrar depósito'}
                </Button>
            </div>
        </form>
    );
}
