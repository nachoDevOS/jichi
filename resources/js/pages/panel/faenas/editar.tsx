import { Head, useForm } from '@inertiajs/react';
import { Info } from 'lucide-react';
import { type FormEvent } from 'react';
import { Retrato } from '@/components/comunes/retrato';
import { Button } from '@/components/ui/button';
import { Campo } from '@/components/ui/campo';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import LayoutPanel from '@/layouts/layout-panel';
import type { FaenaEnCorreccion } from '@/types/faenas';

/**
 *  Corregir un permiso de faena en borrador
 *
 * Es el mismo formulario que la emisión SIN el buscador: el titular y el
 * carnet llegan fijos. Cambiar de titular no es corregir una salida, es emitir
 * otra — ver EmitirFaenaService::editar().
 */
export default function EditarFaena({
    faena,
    vigencia,
}: {
    faena: FaenaEnCorreccion;
    /** «30 días desde la aprobación» o «hasta el 31 de diciembre»: lo configura la unidad. */
    vigencia: string;
}) {
    const form = useForm({
        embarcacion: faena.embarcacion ?? '',
        propietario: faena.propietario ?? '',
        comandante_barco: faena.comandante_barco ?? '',
        matricula_naval: faena.matricula_naval ?? '',
        nro_kardex: faena.nro_kardex ?? '',
        region_desde: faena.region_desde ?? '',
        region_hasta: faena.region_hasta ?? '',
    });

    // Todos los renglones son obligatorios: sin uno vacío no se guarda.
    const completo = Object.values(form.data).every((v) => String(v).trim() !== '');

    function enviar(e: FormEvent) {
        e.preventDefault();
        form.patch(route('faenas.update', faena.id));
    }

    return (
        <LayoutPanel
            titulo={`Corregir la faena N° ${faena.numero_legible}`}
            descripcion="Solo mientras está PENDIENTE de pago. El titular y el carnet no se cambian."
        >
            <Head title={`Corregir faena ${faena.numero_legible}`} />

            <form onSubmit={enviar} className="grid gap-6 lg:grid-cols-3">
                <Card className="lg:col-span-2">
                    <CardHeader>
                        <CardTitle>Datos de la salida</CardTitle>
                    </CardHeader>

                    <CardContent className="space-y-5">
                        {/* El titular, fijo y para mirar. Mismo bloque que el resto
                            del sistema: cara, nombre, cédula y carnet. */}
                        <div className="flex items-center gap-3 rounded-lg border border-border bg-secondary/40 p-3">
                            <Retrato url={faena.foto_url} nombre={faena.beneficiario ?? 'Sin nombre'} />

                            <div className="min-w-0">
                                <p className="font-medium">{faena.beneficiario ?? '—'}</p>
                                <p className="tabular-nums text-xs text-muted-foreground">{faena.documento ?? '—'}</p>
                                <p className="font-mono text-xs text-muted-foreground">
                                    Carnet N° {faena.carnet_registro ?? '—'}
                                </p>
                            </div>
                        </div>

                        {/* Los renglones del talonario: obligatorios, igual que en EmitirFaenaRequest. */}
                        <div className="space-y-4 border-t border-border pt-5">
                            <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                El Área de Fiscalización y Control de la Actividad Pesquera autoriza a
                            </p>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <Campo
                                    etiqueta="La embarcación"
                                    htmlFor="embarcacion"
                                    error={form.errors.embarcacion}
                                    obligatorio
                                >
                                    <Input
                                        id="embarcacion"
                                        value={form.data.embarcacion}
                                        onChange={(e) => form.setData('embarcacion', e.target.value)}
                                        aria-invalid={Boolean(form.errors.embarcacion)}
                                    />
                                </Campo>

                                <Campo
                                    etiqueta="De propiedad de"
                                    htmlFor="propietario"
                                    error={form.errors.propietario}
                                    obligatorio
                                    ayuda="Si es del propio pescador, va su nombre."
                                >
                                    <Input
                                        id="propietario"
                                        value={form.data.propietario}
                                        onChange={(e) => form.setData('propietario', e.target.value)}
                                        aria-invalid={Boolean(form.errors.propietario)}
                                    />
                                </Campo>

                                <Campo
                                    etiqueta="Comandante de barco"
                                    htmlFor="comandante_barco"
                                    error={form.errors.comandante_barco}
                                    obligatorio
                                >
                                    <Input
                                        id="comandante_barco"
                                        value={form.data.comandante_barco}
                                        onChange={(e) => form.setData('comandante_barco', e.target.value)}
                                        aria-invalid={Boolean(form.errors.comandante_barco)}
                                    />
                                </Campo>

                                <Campo
                                    etiqueta="Matrícula naval N°"
                                    htmlFor="matricula_naval"
                                    error={form.errors.matricula_naval}
                                    obligatorio
                                >
                                    <Input
                                        id="matricula_naval"
                                        value={form.data.matricula_naval}
                                        onChange={(e) => form.setData('matricula_naval', e.target.value)}
                                        aria-invalid={Boolean(form.errors.matricula_naval)}
                                        className="font-mono"
                                    />
                                </Campo>

                                <Campo
                                    etiqueta="N° Kardex"
                                    htmlFor="nro_kardex"
                                    error={form.errors.nro_kardex}
                                    obligatorio
                                >
                                    <Input
                                        id="nro_kardex"
                                        value={form.data.nro_kardex}
                                        onChange={(e) => form.setData('nro_kardex', e.target.value)}
                                        aria-invalid={Boolean(form.errors.nro_kardex)}
                                        className="font-mono"
                                    />
                                </Campo>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <Campo
                                    etiqueta="Pescar en la región desde"
                                    htmlFor="region_desde"
                                    error={form.errors.region_desde}
                                    obligatorio
                                >
                                    <Input
                                        id="region_desde"
                                        value={form.data.region_desde}
                                        onChange={(e) => form.setData('region_desde', e.target.value)}
                                        aria-invalid={Boolean(form.errors.region_desde)}
                                    />
                                </Campo>

                                <Campo
                                    etiqueta="Hasta"
                                    htmlFor="region_hasta"
                                    error={form.errors.region_hasta}
                                    obligatorio
                                >
                                    <Input
                                        id="region_hasta"
                                        value={form.data.region_hasta}
                                        onChange={(e) => form.setData('region_hasta', e.target.value)}
                                        aria-invalid={Boolean(form.errors.region_hasta)}
                                    />
                                </Campo>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* ------------------------------------------------ Consecuencias */}
                <Card className="h-fit">
                    <CardHeader>
                        <CardTitle>Lo que se va a guardar</CardTitle>
                    </CardHeader>

                    <CardContent className="space-y-4">
                        <div>
                            <p className="text-xs uppercase tracking-wide text-muted-foreground">Kilos del permiso</p>
                            <p className="text-3xl font-semibold tabular-nums">
                                {faena.kilos_extraidos}
                                <span className="ml-1 text-base font-normal text-muted-foreground">kg</span>
                            </p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Fijos: los puso la autorización al emitir. No se corrigen.
                            </p>
                        </div>

                        <p className="flex items-start gap-2 text-xs text-muted-foreground">
                            <Info className="mt-0.5 size-4 shrink-0" />
                            El número del talonario no cambia. La salida y el desembarque los fija la aprobación: sale
                            ese día y vale {vigencia}.
                        </p>

                        <Button type="submit" disabled={form.processing || !completo} className="w-full">
                            Guardar corrección
                        </Button>
                    </CardContent>
                </Card>
            </form>
        </LayoutPanel>
    );
}
