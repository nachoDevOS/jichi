import { useForm } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import { useEffect, type FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Campo } from '@/components/ui/campo';
import { Input } from '@/components/ui/input';
import { SelectorArchivo } from '@/components/ui/selector-archivo';
import { fechaInput } from '@/lib/utils';
import type { PagoDelCupo } from '@/types/aprovechamientos';

/**
 * Corregir un depósito: lo único que levanta una observación.
 */
export function DialogoCorregirPago({
    pago,
    onCerrar,
}: {
    /** El depósito a corregir, o null con la ventana cerrada. */
    pago: PagoDelCupo | null;
    onCerrar: () => void;
}) {
    const form = useForm({
        monto_parcial: '',
        nro_transaccion: '',
        fecha_deposito: '',
        comprobante: null as File | null,
    });

    /*
     * Los datos se cargan al abrir: `useForm` guarda su estado entre aperturas.
     * `form` NO va en las dependencias, o el efecto correría con cada tecla.
     */
    useEffect(() => {
        if (!pago) return;

        form.setData({
            monto_parcial: String(pago.monto_parcial),
            nro_transaccion: pago.nro_transaccion,
            // fechaInput() y no slice(0, 10): una fecha suelta interpretada como
            // instante UTC se muestra un día antes en UTC-4.
            fecha_deposito: fechaInput(pago.fecha_deposito),
            comprobante: null,
        });

        form.clearErrors();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [pago?.id]);

    /* Escape cierra, igual que en las otras ventanas del panel. */
    useEffect(() => {
        if (!pago) return;

        const alPresionar = (e: KeyboardEvent) => {
            if (e.key === 'Escape') onCerrar();
        };

        document.addEventListener('keydown', alPresionar);

        return () => document.removeEventListener('keydown', alPresionar);
    }, [pago, onCerrar]);

    if (!pago) return null;

    function enviar(e: FormEvent) {
        e.preventDefault();

        if (!pago) return;

        // `forceFormData` porque el archivo es opcional: sin él, Inertia
        // mandaría JSON y el mismo envío viajaría de dos formas distintas.
        form.post(route('pagos.corregir', pago.id), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: onCerrar,
        });
    }

    return (
        <div
            className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="titulo-corregir-pago"
        >
            <div className="w-full max-w-lg rounded-xl border border-border bg-card shadow-lg">
                <form onSubmit={enviar}>
                    <div className="flex items-start gap-4 p-6 pb-4">
                        <div className="flex size-10 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300">
                            <Pencil className="size-5" />
                        </div>

                        <div className="min-w-0 space-y-1">
                            <h2 id="titulo-corregir-pago" className="font-semibold">
                                Corregir el depósito
                            </h2>

                            <p className="text-sm text-muted-foreground">
                                Al guardar vuelve a quedar <strong>sin validar</strong>: el dato es
                                nuevo y hace falta que alguien lo valide de nuevo.
                            </p>
                        </div>
                    </div>

                    {/* Lo que se le objetó, a la vista: es el texto que dice
                        qué hay que cambiar. */}
                    {pago.observacion && (
                        <div className="mx-6 mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-200">
                            <p className="font-medium">Observación</p>
                            <p>{pago.observacion}</p>
                        </div>
                    )}

                    <div className="space-y-4 px-6 pb-2">
                        <Campo
                            etiqueta="Monto del depósito"
                            htmlFor="corregir-monto"
                            error={form.errors.monto_parcial}
                            obligatorio
                        >
                            <Input
                                id="corregir-monto"
                                type="number"
                                step="0.01"
                                min="0"
                                value={form.data.monto_parcial}
                                onChange={(e) => form.setData('monto_parcial', e.target.value)}
                            />
                        </Campo>

                        <Campo
                            etiqueta="Número de boleta"
                            htmlFor="corregir-boleta"
                            error={form.errors.nro_transaccion}
                            ayuda="No se puede repetir: una misma transacción no respalda dos pagos."
                            obligatorio
                        >
                            <Input
                                id="corregir-boleta"
                                value={form.data.nro_transaccion}
                                onChange={(e) => form.setData('nro_transaccion', e.target.value)}
                            />
                        </Campo>

                        <Campo
                            etiqueta="Fecha del depósito"
                            htmlFor="corregir-fecha"
                            error={form.errors.fecha_deposito}
                            ayuda="La que figura en la boleta, no la de hoy."
                            obligatorio
                        >
                            <Input
                                id="corregir-fecha"
                                type="date"
                                value={form.data.fecha_deposito}
                                onChange={(e) => form.setData('fecha_deposito', e.target.value)}
                            />
                        </Campo>

                        <Campo
                            etiqueta="Boleta escaneada"
                            htmlFor="corregir-comprobante"
                            error={form.errors.comprobante}
                            ayuda="Solo si hay que reemplazarla. Sin elegir ninguna, se conserva la que está cargada."
                        >
                            <SelectorArchivo
                                id="corregir-comprobante"
                                archivo={form.data.comprobante}
                                onElegir={(archivo) => form.setData('comprobante', archivo)}
                                error={form.errors.comprobante}
                            />
                        </Campo>
                    </div>

                    <div className="flex justify-end gap-2 p-6 pt-4">
                        <Button type="button" variant="outline" onClick={onCerrar}>
                            Cancelar
                        </Button>

                        <Button type="submit" disabled={form.processing}>
                            Guardar corrección
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    );
}
