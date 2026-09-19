import { Head, useForm, usePage } from '@inertiajs/react';
import { CircleCheckBig, Info } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { BuscadorBeneficiario } from '@/components/panel/comunes/buscador-beneficiario';
import { Button } from '@/components/ui/button';
import { Campo } from '@/components/ui/campo';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import LayoutPanel from '@/layouts/layout-panel';
import { bs } from '@/lib/utils';
import type { MetodoPago, OpcionEnum, PageProps } from '@/types';
import type { BeneficiarioSugerido } from '@/types/beneficiarios';
import type { DeudaCobrable, LineaCobro } from '@/types/caja';

/**
 * ============================================================================
 *  COBRAR — el formulario de caja
 * ============================================================================
 *
 * ----------------------------------------------------------------------------
 *  UN RECIBO PUEDE CUBRIR VARIOS TRÁMITES, Y POR ESO SON CASILLAS
 * ----------------------------------------------------------------------------
 *
 * La persona llega con lo que debe —el carnet, el cupo, una guía— y entrega la
 * plata UNA vez. Un formulario que cobrara de a un trámite obligaría a emitir
 * tres papeles por una sola entrega, y a la persona a guardar tres.
 *
 * Por eso las deudas vienen juntas en una lista con casillas: se tilda lo que
 * se cobra y sale un solo comprobante numerado.
 *
 * ----------------------------------------------------------------------------
 *  CADA LÍNEA ARRANCA CON EL SALDO COMPLETO, PERO ES EDITABLE
 * ----------------------------------------------------------------------------
 *
 * Lo normal es pagar todo, así que ese es el valor por defecto. Bajarlo es lo
 * que hace un pago en cuotas: 40 hoy y 40 la semana que viene, cada uno con su
 * recibo.
 *
 * Lo que NO se puede es subirlo por encima del saldo. El servidor lo rechaza, y
 * la pantalla lo avisa antes: pagar de más no genera saldo a favor —el saldo se
 * corta en cero— así que el excedente se perdería.
 */
export default function Cobrar({
    beneficiario,
    deudas,
    metodos,
}: {
    beneficiario: (BeneficiarioSugerido & { ci: string }) | null;
    deudas: DeudaCobrable[];
    metodos: OpcionEnum[];
}) {
    const { institucion } = usePage<PageProps>().props;
    const [persona, setPersona] = useState<BeneficiarioSugerido | null>(beneficiario);

    const form = useForm({
        lineas: [] as LineaCobro[],
        metodo_pago: '' as MetodoPago | '',
        nit_ci_factura: beneficiario?.ci ?? '',
        nombre_factura: beneficiario?.nombreCompleto ?? '',
        concepto: '',
    });

    /** ¿Está tildada esta deuda? */
    const tildada = (d: DeudaCobrable) =>
        form.data.lineas.some((l) => l.tipo === d.tipo && l.id === d.id);

    const montoDe = (d: DeudaCobrable) =>
        form.data.lineas.find((l) => l.tipo === d.tipo && l.id === d.id)?.monto ?? '';

    function alternar(d: DeudaCobrable) {
        form.setData(
            'lineas',
            tildada(d)
                ? form.data.lineas.filter((l) => !(l.tipo === d.tipo && l.id === d.id))
                // Arranca con el saldo completo: es lo que se cobra casi siempre.
                : [...form.data.lineas, { tipo: d.tipo, id: d.id, monto: d.saldo }],
        );
    }

    function cambiarMonto(d: DeudaCobrable, monto: string) {
        form.setData(
            'lineas',
            form.data.lineas.map((l) =>
                l.tipo === d.tipo && l.id === d.id ? { ...l, monto } : l,
            ),
        );
    }

    const total = form.data.lineas.reduce((s, l) => s + Number(l.monto || 0), 0);

    // Se avisa antes de enviar, aunque el servidor lo rechaza igual: la
    // comprobación de verdad corre con la fila bloqueada, porque otra ventanilla
    // puede cobrar el mismo trámite en el mismo segundo.
    const excede = form.data.lineas.some((l) => {
        const d = deudas.find((x) => x.tipo === l.tipo && x.id === l.id);

        return d !== undefined && Number(l.monto || 0) > d.saldo;
    });

    function elegirPersona(elegida: BeneficiarioSugerido | null) {
        setPersona(elegida);

        // Al cambiar de persona se navega de nuevo: las deudas las arma el
        // servidor, y quedarse con las de la anterior sería cobrarle a quien no
        // es. La URL lleva el id para que el formulario vuelva a abrir resuelto.
        if (elegida) {
            window.location.href = route('caja.create', { beneficiario: elegida.id });
        }
    }

    function enviar(e: FormEvent) {
        e.preventDefault();
        form.post(route('caja.store'));
    }

    return (
        <LayoutPanel
            titulo="Cobrar"
            descripcion="Un recibo numerado puede cubrir varios trámites de la misma persona."
        >
            <Head title="Cobrar" />

            <form onSubmit={enviar} className="grid gap-6 lg:grid-cols-3">
                <div className="space-y-6 lg:col-span-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>¿A quién se le cobra?</CardTitle>
                        </CardHeader>

                        <CardContent>
                            <BuscadorBeneficiario
                                seleccionado={persona}
                                onSeleccionar={elegirPersona}
                                ayuda="Se listan sus trámites con saldo pendiente."
                            />
                        </CardContent>
                    </Card>

                    {persona && (
                        <Card>
                            <CardHeader>
                                <CardTitle>Qué se cobra</CardTitle>
                            </CardHeader>

                            <CardContent>
                                {form.errors.lineas && (
                                    <p className="mb-3 rounded-md bg-destructive/10 p-3 text-sm text-destructive">
                                        {form.errors.lineas}
                                    </p>
                                )}

                                {deudas.length === 0 ? (
                                    <p className="flex items-start gap-2 rounded-md bg-emerald-50 p-4 text-sm text-emerald-900 dark:bg-emerald-500/10 dark:text-emerald-200">
                                        <CircleCheckBig className="mt-0.5 size-5 shrink-0" />
                                        <span>
                                            Esta persona no debe nada. Todos sus trámites están
                                            cubiertos.
                                        </span>
                                    </p>
                                ) : (
                                    <ul className="divide-y divide-border">
                                        {deudas.map((d) => (
                                            <li
                                                key={`${d.tipo}-${d.id}`}
                                                className="flex flex-wrap items-center gap-3 py-3"
                                            >
                                                <input
                                                    type="checkbox"
                                                    checked={tildada(d)}
                                                    onChange={() => alternar(d)}
                                                    className="size-4 shrink-0 rounded border-input"
                                                    aria-label={`Cobrar ${d.titulo}`}
                                                />

                                                <span className="min-w-0 flex-1">
                                                    <span className="block text-sm font-medium">
                                                        {d.titulo}
                                                    </span>
                                                    <span className="block text-xs text-muted-foreground">
                                                        {d.detalle}
                                                    </span>
                                                </span>

                                                <span className="text-right text-sm">
                                                    <span className="block tabular-nums text-muted-foreground">
                                                        debe {bs(d.saldo, institucion.moneda)}
                                                    </span>
                                                    {/*
                                                        Lo ya abonado se muestra cuando existe: sin
                                                        eso, un saldo de 40 sobre un carnet de 80 se
                                                        lee como un carnet más barato en vez de como
                                                        la segunda cuota.
                                                    */}
                                                    {d.pagado > 0 && (
                                                        <span className="block text-xs text-muted-foreground">
                                                            ya pagó {bs(d.pagado, institucion.moneda)} de{' '}
                                                            {bs(d.monto, institucion.moneda)}
                                                        </span>
                                                    )}
                                                </span>

                                                <Input
                                                    type="number"
                                                    step="0.01"
                                                    min={0}
                                                    max={d.saldo}
                                                    disabled={!tildada(d)}
                                                    value={montoDe(d)}
                                                    onChange={(e) => cambiarMonto(d, e.target.value)}
                                                    className="w-28"
                                                    aria-label={`Monto a cobrar de ${d.titulo}`}
                                                    aria-invalid={Number(montoDe(d) || 0) > d.saldo}
                                                />
                                            </li>
                                        ))}
                                    </ul>
                                )}

                                {excede && (
                                    <p className="mt-3 text-sm text-destructive">
                                        Hay un monto mayor de lo que se debe. Pagar de más no genera
                                        saldo a favor: el excedente se perdería.
                                    </p>
                                )}
                            </CardContent>
                        </Card>
                    )}

                    {form.data.lineas.length > 0 && (
                        <Card>
                            <CardHeader>
                                <CardTitle>Datos del comprobante</CardTitle>
                            </CardHeader>

                            <CardContent className="space-y-4">
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <Campo
                                        etiqueta="NIT o CI"
                                        htmlFor="nit_ci_factura"
                                        error={form.errors.nit_ci_factura}
                                        obligatorio
                                    >
                                        <Input
                                            id="nit_ci_factura"
                                            value={form.data.nit_ci_factura}
                                            onChange={(e) => form.setData('nit_ci_factura', e.target.value)}
                                            aria-invalid={Boolean(form.errors.nit_ci_factura)}
                                            className="font-mono"
                                        />
                                    </Campo>

                                    <Campo
                                        etiqueta="A nombre de"
                                        htmlFor="nombre_factura"
                                        error={form.errors.nombre_factura}
                                        ayuda="Puede ser un tercero: la empresa que paga por la persona."
                                        obligatorio
                                    >
                                        <Input
                                            id="nombre_factura"
                                            value={form.data.nombre_factura}
                                            onChange={(e) => form.setData('nombre_factura', e.target.value)}
                                            aria-invalid={Boolean(form.errors.nombre_factura)}
                                        />
                                    </Campo>
                                </div>

                                <Campo
                                    etiqueta="Concepto"
                                    htmlFor="concepto"
                                    error={form.errors.concepto}
                                    ayuda="Opcional. Si se deja vacío, el sistema lo arma con los trámites cobrados."
                                >
                                    <Textarea
                                        id="concepto"
                                        rows={2}
                                        value={form.data.concepto}
                                        onChange={(e) => form.setData('concepto', e.target.value)}
                                    />
                                </Campo>
                            </CardContent>
                        </Card>
                    )}
                </div>

                {/* ------------------------------------------------ El total */}
                <Card className="h-fit">
                    <CardHeader>
                        <CardTitle>A cobrar</CardTitle>
                    </CardHeader>

                    <CardContent className="space-y-4">
                        {form.data.lineas.length === 0 ? (
                            <p className="flex items-start gap-2 text-sm text-muted-foreground">
                                <Info className="mt-0.5 size-4 shrink-0" />
                                Tilde los trámites que se están pagando.
                            </p>
                        ) : (
                            <>
                                <div>
                                    <p className="text-xs uppercase tracking-wide text-muted-foreground">
                                        Total del recibo
                                    </p>
                                    <p className="text-3xl font-semibold tabular-nums">
                                        {bs(total, institucion.moneda)}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {form.data.lineas.length} trámite(s) en un solo comprobante
                                    </p>
                                </div>

                                <Campo
                                    etiqueta="Método de pago"
                                    htmlFor="metodo_pago"
                                    error={form.errors.metodo_pago}
                                    obligatorio
                                >
                                    <Select
                                        id="metodo_pago"
                                        value={form.data.metodo_pago}
                                        onChange={(e) =>
                                            form.setData('metodo_pago', e.target.value as MetodoPago)
                                        }
                                        aria-invalid={Boolean(form.errors.metodo_pago)}
                                    >
                                        <option value="">Elija…</option>
                                        {metodos.map((o) => (
                                            <option key={o.value} value={o.value}>
                                                {o.label}
                                            </option>
                                        ))}
                                    </Select>
                                </Campo>
                            </>
                        )}

                        <Button
                            type="submit"
                            disabled={
                                form.processing ||
                                form.data.lineas.length === 0 ||
                                form.data.metodo_pago === '' ||
                                total <= 0 ||
                                excede
                            }
                            className="w-full"
                        >
                            Emitir recibo
                        </Button>
                    </CardContent>
                </Card>
            </form>
        </LayoutPanel>
    );
}
