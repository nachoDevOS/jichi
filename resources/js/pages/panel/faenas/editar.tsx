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
 *  CORREGIR UN PERMISO DE FAENA EN BORRADOR
 *
 * Es el mismo formulario que la emisión SIN el buscador: el titular y el
 * carnet llegan fijos. Cambiar de titular no es corregir una salida, es emitir
 * otra — ver EmitirFaenaService::editar().
 */
export default function EditarFaena({
    faena,
    diasVigencia,
    modoEstricto,
}: {
    faena: FaenaEnCorreccion;
    /** El plazo de la resolución. Llega del servidor para que no haya dos copias. */
    diasVigencia: number;
    /** Lo que dice APROVECHAMIENTO_ESTRICTO: es lo que decide si el botón frena. */
    modoEstricto: boolean;
}) {
    const form = useForm({
        kilos_extraidos: String(faena.kilos_extraidos ?? ''),
        embarcacion: faena.embarcacion ?? '',
        propietario: faena.propietario ?? '',
        comandante_barco: faena.comandante_barco ?? '',
        matricula_naval: faena.matricula_naval ?? '',
        nro_kardex: faena.nro_kardex ?? '',
        region_desde: faena.region_desde ?? '',
        region_hasta: faena.region_hasta ?? '',
    });

    function enviar(e: FormEvent) {
        e.preventDefault();
        form.patch(route('faenas.update', faena.id));
    }

    const kilos = Number(form.data.kilos_extraidos || 0);

    /*
     * El saldo llega resuelto del servidor, con los kilos propios sumados de
     * vuelta SOLO si esta faena estaba descontando. Una pendiente no descuenta.
     */
    const saldo = faena.saldo_kg;

    /* El HECHO y la CONSECUENCIA, separados: ver faenas/crear.tsx. */
    const excede = saldo !== null && kilos > saldo;
    const bloquea = excede && modoEstricto;

    return (
        <LayoutPanel
            titulo={`Corregir la faena N° ${faena.numero_legible}`}
            descripcion="Solo mientras está PENDIENTE y sin ningún depósito cargado. El titular y el carnet no se cambian."
        >
            <Head title={`Corregir faena ${faena.numero_legible}`} />

            <form onSubmit={enviar} className="grid gap-6 lg:grid-cols-3">
                <Card className="lg:col-span-2">
                    <CardHeader>
                        <CardTitle>Datos de la salida</CardTitle>
                    </CardHeader>

                    <CardContent className="space-y-5">
                        {/* EL TITULAR, FIJO Y PARA MIRAR. Mismo bloque que el resto
                            del sistema: cara, nombre, cédula y carnet. */}
                        <div className="flex items-center gap-3 rounded-lg border border-border bg-secondary/40 p-3">
                            <Retrato url={faena.foto_url} nombre={faena.beneficiario ?? 'Sin nombre'} />

                            <div className="min-w-0">
                                <p className="font-medium">{faena.beneficiario ?? '—'}</p>
                                <p className="tabular-nums text-xs text-muted-foreground">
                                    {faena.documento ?? '—'}
                                </p>
                                <p className="font-mono text-xs text-muted-foreground">
                                    Carnet N° {faena.carnet_registro ?? '—'}
                                </p>
                            </div>
                        </div>

                        {/* LOS RENGLONES DEL TALONARIO. Ninguno obligatorio: el papel
                            llega incompleto, y corregir es justamente completarlo. */}
                        <div className="space-y-4 border-t border-border pt-5">
                            <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                El Área de Fiscalización y Control de la Actividad Pesquera autoriza a
                            </p>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <Campo
                                    etiqueta="La embarcación"
                                    htmlFor="embarcacion"
                                    error={form.errors.embarcacion}
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
                                    ayuda="Solo si la embarcación no es del propio pescador."
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
                                >
                                    <Input
                                        id="region_hasta"
                                        value={form.data.region_hasta}
                                        onChange={(e) => form.setData('region_hasta', e.target.value)}
                                        aria-invalid={Boolean(form.errors.region_hasta)}
                                    />
                                </Campo>
                            </div>

                            <Campo
                                etiqueta="Cantidad autorizada de pescado extraído (kg)"
                                htmlFor="kilos_extraidos"
                                error={form.errors.kilos_extraidos}
                                ayuda={saldo !== null ? `Quedan ${saldo} kg en la bolsa madre.` : undefined}
                                obligatorio
                            >
                                <Input
                                    id="kilos_extraidos"
                                    type="number"
                                    step="0.01"
                                    min={0}
                                    value={form.data.kilos_extraidos}
                                    onChange={(e) => form.setData('kilos_extraidos', e.target.value)}
                                    aria-invalid={Boolean(form.errors.kilos_extraidos) || bloquea}
                                    className="sm:max-w-xs"
                                />
                            </Campo>
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
                            <p className="text-xs uppercase tracking-wide text-muted-foreground">
                                Cupo disponible
                            </p>
                            <p className="text-3xl font-semibold tabular-nums">
                                {saldo ?? '—'}
                                <span className="ml-1 text-base font-normal text-muted-foreground">kg</span>
                            </p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Una faena pendiente todavía no descuenta: la bolsa recién se
                                mueve cuando se aprueba.
                            </p>
                        </div>

                        {kilos > 0 && (
                            <div>
                                <p className="text-xs uppercase tracking-wide text-muted-foreground">
                                    Quedaría
                                </p>
                                <p
                                    className={
                                        bloquea
                                            ? 'text-2xl font-semibold tabular-nums text-destructive'
                                            : excede
                                              ? 'text-2xl font-semibold tabular-nums text-amber-700 dark:text-amber-400'
                                              : 'text-2xl font-semibold tabular-nums'
                                    }
                                >
                                    {saldo !== null ? Math.round((saldo - kilos) * 100) / 100 : '—'} kg
                                </p>

                                {excede &&
                                    (modoEstricto ? (
                                        <p className="mt-1 text-sm text-destructive">
                                            No entra en el cupo. Baje los kilos o tramite otro
                                            aprovechamiento.
                                        </p>
                                    ) : (
                                        <p className="mt-1 text-sm text-amber-700 dark:text-amber-400">
                                            Va a quedar por encima del cupo otorgado. El control de
                                            saldo está desactivado, así que se guarda igual y el
                                            exceso queda registrado.
                                        </p>
                                    ))}
                            </div>
                        )}

                        <p className="flex items-start gap-2 text-xs text-muted-foreground">
                            <Info className="mt-0.5 size-4 shrink-0" />
                            El número del talonario no cambia. La salida y el desembarque los fija
                            la aprobación: sale ese día y vale {diasVigencia} días.
                        </p>

                        <Button
                            type="submit"
                            disabled={
                                form.processing ||
                                kilos <= 0 ||
                                bloquea
                            }
                            className="w-full"
                        >
                            Guardar corrección
                        </Button>
                    </CardContent>
                </Card>
            </form>
        </LayoutPanel>
    );
}
