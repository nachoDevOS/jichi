import { Head, useForm, usePage } from '@inertiajs/react';
import { Info, ShieldAlert, Truck } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { BuscadorBeneficiario } from '@/components/panel/comunes/buscador-beneficiario';
import { Button } from '@/components/ui/button';
import { Campo } from '@/components/ui/campo';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import LayoutPanel from '@/layouts/layout-panel';
import { bs } from '@/lib/utils';
import type { PageProps } from '@/types';
import type { BeneficiarioSugerido, CarnetVigenteSugerido } from '@/types/beneficiarios';

/**
 *  EMITIR UNA GUÍA DE MOVIMIENTO — paso 4, rama comercializador
 */
export default function CrearGuia({
    beneficiario,
    diasVigencia,
    tarifaBase,
    descuentoPiscicultura,
}: {
    beneficiario: (BeneficiarioSugerido & { carnets_vigentes: CarnetVigenteSugerido[] }) | null;
    diasVigencia: number;
    tarifaBase: number;
    descuentoPiscicultura: number;
}) {
    const { institucion } = usePage<PageProps>().props;
    const [persona, setPersona] = useState<BeneficiarioSugerido | null>(beneficiario);
    const [carnet, setCarnet] = useState<CarnetVigenteSugerido | null>(null);

    const form = useForm({
        carnet_id: null as number | null,
        codigo_guia: '',
        origen: '',
        destino: '',
        peso_total_kg: '',
        es_piscicultura: false,
        // Se manda con HORA: los cinco días se cuentan desde el instante, no
        // desde la medianoche. `toISOString().slice(0,16)` da el formato que
        // espera un <input type="datetime-local">.
        fecha_emision: new Date(Date.now() - new Date().getTimezoneOffset() * 60000)
            .toISOString()
            .slice(0, 16),
    });

    function elegirPersona(elegida: BeneficiarioSugerido | null) {
        setPersona(elegida);
        setCarnet(null);
        form.setData('carnet_id', null);
        form.clearErrors('carnet_id');
    }

    function enviar(e: FormEvent) {
        e.preventDefault();
        form.post(route('guias.store'));
    }

    const monto = form.data.es_piscicultura ? tarifaBase * (1 - descuentoPiscicultura) : tarifaBase;

    return (
        <LayoutPanel
            titulo="Emitir guía"
            descripcion={`Ampara UN traslado. Vale ${diasVigencia} días desde la hora de emisión.`}
        >
            <Head title="Emitir guía" />

            <form onSubmit={enviar} className="grid gap-6 lg:grid-cols-3">
                <Card className="lg:col-span-2">
                    <CardHeader>
                        <CardTitle>Datos del traslado</CardTitle>
                    </CardHeader>

                    <CardContent className="space-y-5">
                        <Campo etiqueta="Comercializador" obligatorio error={form.errors.carnet_id}>
                            <BuscadorBeneficiario
                                seleccionado={persona}
                                onSeleccionar={elegirPersona}
                                ayuda="Tiene que tener carnet de comercializador vigente."
                            />
                        </Campo>

                        {persona && (
                            <Campo etiqueta="Carnet" obligatorio>
                                <ListaDeCarnets
                                    carnets={persona.carnets_vigentes}
                                    elegido={carnet}
                                    onElegir={(c) => {
                                        setCarnet(c);
                                        form.setData('carnet_id', c.id);
                                        form.clearErrors('carnet_id');
                                    }}
                                />
                            </Campo>
                        )}

                        {carnet && (
                            <>
                                <Campo
                                    etiqueta="Código de la hoja del talonario"
                                    htmlFor="codigo_guia"
                                    error={form.errors.codigo_guia}
                                    ayuda="Único en todo el sistema. El servidor lo guarda en mayúsculas."
                                    obligatorio
                                    className="max-w-sm"
                                >
                                    <Input
                                        id="codigo_guia"
                                        value={form.data.codigo_guia}
                                        onChange={(e) =>
                                            form.setData('codigo_guia', e.target.value.toUpperCase())
                                        }
                                        aria-invalid={Boolean(form.errors.codigo_guia)}
                                        placeholder="GUI-2026-0001"
                                        className="font-mono"
                                    />
                                </Campo>

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <Campo
                                        etiqueta="Origen"
                                        htmlFor="origen"
                                        error={form.errors.origen}
                                        obligatorio
                                    >
                                        <Input
                                            id="origen"
                                            value={form.data.origen}
                                            onChange={(e) => form.setData('origen', e.target.value)}
                                            aria-invalid={Boolean(form.errors.origen)}
                                            placeholder="Puerto Almacén, Trinidad"
                                        />
                                    </Campo>

                                    <Campo
                                        etiqueta="Destino"
                                        htmlFor="destino"
                                        error={form.errors.destino}
                                        obligatorio
                                    >
                                        <Input
                                            id="destino"
                                            value={form.data.destino}
                                            onChange={(e) => form.setData('destino', e.target.value)}
                                            aria-invalid={Boolean(form.errors.destino)}
                                            placeholder="Santa Cruz de la Sierra"
                                        />
                                    </Campo>

                                    <Campo
                                        etiqueta="Peso total (kg)"
                                        htmlFor="peso_total_kg"
                                        error={form.errors.peso_total_kg}
                                        ayuda="Lo que dijo la balanza del origen. Al cerrar se puede corregir."
                                        obligatorio
                                    >
                                        <Input
                                            id="peso_total_kg"
                                            type="number"
                                            step="0.01"
                                            min={0}
                                            value={form.data.peso_total_kg}
                                            onChange={(e) => form.setData('peso_total_kg', e.target.value)}
                                            aria-invalid={Boolean(form.errors.peso_total_kg)}
                                        />
                                    </Campo>

                                    <Campo
                                        etiqueta="Emisión"
                                        htmlFor="fecha_emision"
                                        error={form.errors.fecha_emision}
                                        ayuda="Con hora: los 5 días se cuentan desde este instante."
                                        obligatorio
                                    >
                                        <Input
                                            id="fecha_emision"
                                            type="datetime-local"
                                            value={form.data.fecha_emision}
                                            onChange={(e) => form.setData('fecha_emision', e.target.value)}
                                            aria-invalid={Boolean(form.errors.fecha_emision)}
                                        />
                                    </Campo>
                                </div>

                                <Campo
                                    etiqueta="Origen del producto"
                                    error={form.errors.es_piscicultura}
                                    ayuda="El pescado de criadero no sale del río, así que no consume el recurso que la tasa protege."
                                >
                                    <label className="flex items-center gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            checked={form.data.es_piscicultura}
                                            onChange={(e) =>
                                                form.setData('es_piscicultura', e.target.checked)
                                            }
                                            className="size-4 rounded border-input"
                                        />
                                        Es producto de <strong>piscicultura</strong> — paga el{' '}
                                        {Math.round((1 - descuentoPiscicultura) * 100)}% del arancel
                                    </label>
                                </Campo>
                            </>
                        )}
                    </CardContent>
                </Card>

                {/* ------------------------------------------------ Consecuencias */}
                <Card className="h-fit">
                    <CardHeader>
                        <CardTitle>Lo que se va a emitir</CardTitle>
                    </CardHeader>

                    <CardContent className="space-y-4">
                        {carnet === null ? (
                            <p className="flex items-start gap-2 text-sm text-muted-foreground">
                                <Info className="mt-0.5 size-4 shrink-0" />
                                Elija a la persona y su carnet de comercializador.
                            </p>
                        ) : (
                            <>
                                <div>
                                    <p className="text-xs uppercase tracking-wide text-muted-foreground">
                                        A cobrar
                                    </p>
                                    <p className="text-3xl font-semibold tabular-nums">
                                        {bs(monto, institucion.moneda)}
                                    </p>

                                    {form.data.es_piscicultura && (
                                        <p className="text-xs text-muted-foreground">
                                            Con el descuento de piscicultura. Sin él serían{' '}
                                            {bs(tarifaBase, institucion.moneda)}.
                                        </p>
                                    )}
                                </div>

                                <p className="text-xs text-muted-foreground">
                                    La guía vale {diasVigencia} días desde la hora de emisión. Pasado
                                    ese plazo el traslado deja de estar amparado, aunque la carga
                                    siga en camino.
                                </p>
                            </>
                        )}

                        <Button
                            type="submit"
                            disabled={
                                form.processing ||
                                carnet === null ||
                                form.data.codigo_guia.trim() === '' ||
                                form.data.origen.trim() === '' ||
                                form.data.destino.trim() === '' ||
                                Number(form.data.peso_total_kg || 0) <= 0
                            }
                            className="w-full"
                        >
                            Emitir guía
                        </Button>
                    </CardContent>
                </Card>
            </form>
        </LayoutPanel>
    );
}

/**
 * Los carnets vigentes, con el que no sirve deshabilitado.
 */
function ListaDeCarnets({
    carnets,
    elegido,
    onElegir,
}: {
    carnets: CarnetVigenteSugerido[];
    elegido: CarnetVigenteSugerido | null;
    onElegir: (c: CarnetVigenteSugerido) => void;
}) {
    if (carnets.length === 0) {
        return (
            <p className="flex items-start gap-2 rounded-md border border-dashed border-border p-3 text-sm text-muted-foreground">
                <ShieldAlert className="mt-0.5 size-4 shrink-0" />
                Esta persona no tiene ningún carnet vigente. Hay que emitirle uno de comercializador
                antes: la guía cuelga de él.
            </p>
        );
    }

    return (
        <ul className="space-y-2">
            {carnets.map((c) => {
                const activo = elegido?.id === c.id;

                return (
                    <li key={c.id}>
                        <button
                            type="button"
                            disabled={!c.puede_emitir_guias}
                            onClick={() => onElegir(c)}
                            className={[
                                'flex w-full items-center gap-3 rounded-md border p-3 text-left transition-colors',
                                activo ? 'border-primary bg-secondary' : 'border-border',
                                c.puede_emitir_guias ? 'hover:bg-secondary' : 'cursor-not-allowed opacity-50',
                            ].join(' ')}
                        >
                            <Truck className="size-4 shrink-0 text-muted-foreground" />

                            <span className="min-w-0 flex-1">
                                <span className="block font-mono text-sm font-medium">{c.codigo}</span>
                                <span className="block text-xs text-muted-foreground">
                                    {c.tipo ?? c.tipo_actor_etiqueta}
                                </span>
                            </span>

                            {!c.puede_emitir_guias && (
                                <span className="text-xs text-muted-foreground">no emite guías</span>
                            )}
                        </button>
                    </li>
                );
            })}
        </ul>
    );
}
