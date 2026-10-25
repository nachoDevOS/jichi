import { useForm } from '@inertiajs/react';
import { Banknote, Info, LoaderCircle } from 'lucide-react';
import { type FormEvent } from 'react';
import { PasosNumerados, ResumenCobro, VentanaPago, type DetallePago } from '@/components/panel/pagos/ventana-pago';
import { Button } from '@/components/ui/button';
import { Campo } from '@/components/ui/campo';
import { Input } from '@/components/ui/input';
import { SelectorArchivo } from '@/components/ui/selector-archivo';
import { bs, cn } from '@/lib/utils';

/** Cargar en SIREB la boleta de un depósito ya hecho. Validarlo sigue siendo de Recaudaciones. */
export function ModalCargarPago({
    abierto,
    monto,
    codigoPago,
    pago,
    rutaCargar,
    onCerrar,
}: {
    abierto: boolean;
    monto: number;
    codigoPago: string | null;
    pago?: DetallePago;
    /** `route('carnets.cargar-pago', id)`. */
    rutaCargar: string;
    onCerrar: () => void;
}) {
    const carga = useForm<{ numero_transaccion: string; banco: string; comprobante: File | null }>({
        numero_transaccion: '',
        banco: '',
        comprobante: null,
    });

    function cerrar() {
        carga.reset();
        carga.clearErrors();
        onCerrar();
    }

    function cargar(e: FormEvent) {
        e.preventDefault();
        carga.post(rutaCargar, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: cerrar,
        });
    }

    return (
        <VentanaPago
            abierto={abierto}
            titulo="Cargar boleta del banco"
            subtitulo="El titular ya depositó: copie los datos del comprobante"
            icono={<Banknote className="size-5" />}
            tono="emerald"
            bloqueada={carga.processing}
            onCerrar={cerrar}
            pie={
                <>
                    <Button
                        key="cancelar-carga"
                        type="button"
                        variant="outline"
                        disabled={carga.processing}
                        onClick={cerrar}
                    >
                        Cancelar
                    </Button>
                    <Button key="enviar-carga" type="submit" form="form-cargar-pago" disabled={carga.processing}>
                        {carga.processing && <LoaderCircle className="size-4 animate-spin" />}
                        {carga.processing ? 'Cargando…' : 'Cargar boleta'}
                    </Button>
                </>
            }
        >
            <div className="grid gap-6 p-5 text-sm md:grid-cols-[1fr_18rem]">
                <form id="form-cargar-pago" onSubmit={cargar} className="space-y-4" noValidate>
                    <p className="flex items-start gap-2 rounded-lg bg-sky-50 p-3 text-sky-900 dark:bg-sky-500/10 dark:text-sky-100">
                        <Info className="mt-0.5 size-4 shrink-0" />
                        <span>
                            Revise que la boleta sea por <strong className="tabular-nums">{bs(monto)}</strong>
                            {codigoPago && (
                                <>
                                    {' '}
                                    y que lleve el código <strong className="font-mono">{codigoPago}</strong>
                                </>
                            )}
                            .
                        </span>
                    </p>

                    <Campo
                        etiqueta="N° de transacción"
                        htmlFor="numero_transaccion"
                        error={carga.errors.numero_transaccion}
                        ayuda="En el comprobante figura como «N° de operación», «Transacción» o «Referencia»."
                        obligatorio
                    >
                        <Input
                            id="numero_transaccion"
                            value={carga.data.numero_transaccion}
                            onChange={(e) => carga.setData('numero_transaccion', e.target.value)}
                            aria-invalid={Boolean(carga.errors.numero_transaccion)}
                            placeholder="Ej.: 884512"
                            inputMode="numeric"
                            maxLength={50}
                            autoFocus
                        />
                    </Campo>

                    <Campo etiqueta="Banco" htmlFor="banco" error={carga.errors.banco} obligatorio>
                        {/* Los más usados a un clic; los demás se escriben con sugerencias. */}
                        <div className="flex flex-wrap gap-1.5">
                            {BANCOS_FRECUENTES.map((b) => (
                                <button
                                    key={b}
                                    type="button"
                                    onClick={() => carga.setData('banco', b)}
                                    aria-pressed={carga.data.banco === b}
                                    className={cn(
                                        'rounded-full border px-2.5 py-1 text-xs transition-colors hover:bg-muted',
                                        carga.data.banco === b &&
                                            'border-emerald-500 bg-emerald-50 text-emerald-900 hover:bg-emerald-50 dark:bg-emerald-500/15 dark:text-emerald-100',
                                    )}
                                >
                                    {b}
                                </button>
                            ))}
                        </div>
                        <Input
                            id="banco"
                            list="bancos-bolivia"
                            value={carga.data.banco}
                            onChange={(e) => carga.setData('banco', e.target.value)}
                            aria-invalid={Boolean(carga.errors.banco)}
                            placeholder="O escriba otro banco"
                            maxLength={100}
                        />
                        <datalist id="bancos-bolivia">
                            {BANCOS.map((b) => (
                                <option key={b} value={b} />
                            ))}
                        </datalist>
                    </Campo>

                    <Campo etiqueta="Foto o PDF del comprobante (opcional, hasta 3 MB)" htmlFor="comprobante">
                        <SelectorArchivo
                            id="comprobante"
                            archivo={carga.data.comprobante}
                            onElegir={(archivo) => carga.setData('comprobante', archivo)}
                            error={carga.errors.comprobante}
                        />
                    </Campo>
                </form>

                <div className="space-y-4">
                    <ResumenCobro monto={monto} codigoPago={codigoPago} pago={pago} />
                    <div className="rounded-lg border p-4">
                        <p className="mb-3 font-medium">Qué pasa después</p>
                        <PasosNumerados
                            pasos={[
                                'La boleta queda cargada en Recaudaciones, por validar.',
                                'Recaudaciones la revisa contra el banco.',
                                'Al validarla, el trámite se aprueba solo y sale el recibo.',
                            ]}
                        />
                    </div>
                </div>
            </div>
        </VentanaPago>
    );
}

const BANCOS_FRECUENTES = [
    'Banco Unión',
    'Banco Nacional de Bolivia (BNB)',
    'Banco Mercantil Santa Cruz',
    'Banco Ganadero',
];

/** Sugerencias para el campo Banco: se puede escribir otro. */
const BANCOS = [
    'Banco Unión',
    'Banco Nacional de Bolivia (BNB)',
    'Banco Mercantil Santa Cruz',
    'Banco de Crédito de Bolivia (BCP)',
    'Banco Bisa',
    'Banco Ganadero',
    'Banco Económico',
    'Banco Sol',
    'Banco FIE',
    'Banco Fortaleza',
    'Banco Prodem',
    'Banco PYME de la Comunidad',
];
