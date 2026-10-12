import { Head, useForm } from '@inertiajs/react';
import { Info, ShieldAlert, Ship } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { BuscadorBeneficiario } from '@/components/panel/comunes/buscador-beneficiario';
import { SelectorTarifa } from '@/components/panel/comunes/selector-tarifa';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Campo } from '@/components/ui/campo';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import LayoutPanel from '@/layouts/layout-panel';
import { fecha } from '@/lib/utils';
import type { TarifaSireb } from '@/types';
import type { BeneficiarioSugerido, CarnetVigenteSugerido } from '@/types/beneficiarios';

/**
 *  Emitir un permiso de faena — paso 4 del flujo
 */
export default function CrearFaena({
    beneficiario,
    carnetElegido,
    diasVigencia,
    tarifas,
    servicioConfigurado,
}: {
    beneficiario: (BeneficiarioSugerido & { carnets_vigentes: CarnetVigenteSugerido[] }) | null;
    /** `?carnet=`: llega desde la ficha del cupo con el carnet ya elegido. */
    carnetElegido: number | null;
    /** El plazo de la resolución. Llega del servidor para que no haya dos copias. */
    diasVigencia: number;
    /** Las tarifas del servicio de la faena en SIREB; null si no responde. */
    tarifas: TarifaSireb[] | null;
    /** false = falta elegir el servicio en Catálogos › Aranceles. */
    servicioConfigurado: boolean;
}) {
    const [persona, setPersona] = useState<BeneficiarioSugerido | null>(beneficiario);
    // Solo si está entre los vigentes y puede emitir: si no, se elige a mano.
    const inicial =
        beneficiario?.carnets_vigentes.find((c) => c.id === carnetElegido && c.puede_emitir_faenas) ?? null;
    const [carnet, setCarnet] = useState<CarnetVigenteSugerido | null>(inicial);

    const form = useForm({
        carnet_id: inicial?.id ?? (null as number | null),
        // Si el servicio tiene una sola tarifa que se cobra, va elegida.
        tarifa_sireb: tarifas?.filter((t) => t.activa).length === 1 ? tarifas.find((t) => t.activa)!.id : '',
        embarcacion: '',
        propietario: '',
        comandante_barco: '',
        matricula_naval: '',
        nro_kardex: '',
        region_desde: '',
        region_hasta: '',
    });

    function elegirPersona(elegida: BeneficiarioSugerido | null) {
        setPersona(elegida);
        setCarnet(null);
        form.setData('carnet_id', null);
        form.clearErrors('carnet_id');
    }

    function elegirCarnet(c: CarnetVigenteSugerido) {
        setCarnet(c);
        form.setData('carnet_id', c.id);
        form.clearErrors('carnet_id');
    }

    function enviar(e: FormEvent) {
        e.preventDefault();
        form.post(route('faenas.store'));
    }

    const tarifaElegida = tarifas?.find((t) => t.id === form.data.tarifa_sireb) ?? null;

    return (
        <LayoutPanel
            titulo="Registrar faena"
            descripcion={`Autoriza UNA salida de ${diasVigencia} días. Nace PENDIENTE: autoriza recién cuando se pague en Recaudaciones.`}
        >
            <Head title="Registrar faena" />

            <form onSubmit={enviar} className="grid gap-6 lg:grid-cols-3">
                <Card className="lg:col-span-2">
                    <CardHeader>
                        <CardTitle>Datos de la salida</CardTitle>
                    </CardHeader>

                    <CardContent className="space-y-5">
                        <Campo etiqueta="Pescador" obligatorio error={form.errors.carnet_id}>
                            <BuscadorBeneficiario
                                seleccionado={persona}
                                onSeleccionar={elegirPersona}
                                ayuda="Tiene que tener carnet de pescador vigente y su autorización aprobada."
                            />
                        </Campo>

                        {persona && (
                            <Campo etiqueta="Carnet" obligatorio>
                                <ListaDeCarnets
                                    carnets={persona.carnets_vigentes}
                                    elegido={carnet}
                                    onElegir={elegirCarnet}
                                />
                            </Campo>
                        )}

                        {carnet && (
                            <>
                                <SelectorTarifa
                                    tarifas={tarifas}
                                    servicioConfigurado={servicioConfigurado}
                                    valor={form.data.tarifa_sireb}
                                    onElegir={(t) => form.setData('tarifa_sireb', t)}
                                    etiqueta="Tarifa del permiso"
                                    error={form.errors.tarifa_sireb}
                                />

                                {/*
                                    LOS RENGLONES DEL TALONARIO. Ninguno es obligatorio: el
                                    papel se llena a mano y llega incompleto, y frenar por
                                    una matrícula que no trajeron deja al pescador sin
                                    permiso por un dato que nadie controla.
                                */}
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
                                                onChange={(e) =>
                                                    form.setData('comandante_barco', e.target.value)
                                                }
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
                                                onChange={(e) =>
                                                    form.setData('matricula_naval', e.target.value)
                                                }
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

                                    {/* Las fechas no se tipean: las fija la aprobación. */}
                                    <p className="flex items-start gap-2 rounded-md bg-muted px-3 py-2 text-sm text-muted-foreground">
                                        <Info className="mt-0.5 size-4 shrink-0" />
                                        La fecha de salida es el día en que se aprueba el permiso, y la de
                                        desembarque, {diasVigencia} días después. Las pone el sistema.
                                    </p>
                                </div>
                            </>
                        )}
                    </CardContent>
                </Card>

                {/* ------------------------------------------------ Consecuencias */}
                <Card className="h-fit">
                    <CardHeader>
                        <CardTitle>Lo que se va a registrar</CardTitle>
                    </CardHeader>

                    <CardContent className="space-y-4">
                        {carnet === null ? (
                            <p className="flex items-start gap-2 text-sm text-muted-foreground">
                                <Info className="mt-0.5 size-4 shrink-0" />
                                Elija a la persona y su carnet de pescador para ver con cuántos kilos
                                sale el permiso.
                            </p>
                        ) : (
                            <>
                                <div>
                                    <p className="text-xs uppercase tracking-wide text-muted-foreground">
                                        Kilos del permiso
                                    </p>
                                    {/* Fijos: los kilos disponibles de la autorización. La faena no descuenta. */}
                                    <p className="text-3xl font-semibold tabular-nums">
                                        {carnet.kilos_disponibles ?? '—'}
                                        <span className="ml-1 text-base font-normal text-muted-foreground">
                                            kg
                                        </span>
                                    </p>
                                </div>

                                <div>
                                    <p className="text-xs uppercase tracking-wide text-muted-foreground">
                                        Arancel
                                    </p>
                                    {/* De referencia: el que vale lo pide el servidor al guardar. */}
                                    <p className="text-xl font-semibold tabular-nums">
                                        {tarifaElegida ? tarifaElegida.monto.toFixed(2) : '—'}
                                        {tarifaElegida && (
                                            <span className="ml-1 text-sm font-normal text-muted-foreground">Bs</span>
                                        )}
                                    </p>
                                </div>

                                <p className="text-xs text-muted-foreground">
                                    Sale el día que se aprueba y vale {diasVigencia} días.
                                </p>
                            </>
                        )}

                        <Button
                            type="submit"
                            disabled={
                                form.processing ||
                                carnet === null ||
                                form.data.tarifa_sireb === ''
                            }
                            className="w-full"
                        >
                            Registrar faena
                        </Button>
                    </CardContent>
                </Card>
            </form>
        </LayoutPanel>
    );
}

/**
 * Los carnets vigentes de la persona, con el que no sirve deshabilitado.
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
                Esta persona no tiene ningún carnet vigente. Hay que emitirle uno antes: el carnet es
                la autorización anual, y la faena cuelga de él.
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
                            disabled={!c.puede_emitir_faenas}
                            onClick={() => onElegir(c)}
                            className={[
                                'flex w-full items-center gap-3 rounded-md border p-3 text-left transition-colors',
                                activo ? 'border-primary bg-secondary' : 'border-border',
                                c.puede_emitir_faenas
                                    ? 'hover:bg-secondary'
                                    : 'cursor-not-allowed opacity-50',
                            ].join(' ')}
                        >
                            <Ship className="size-4 shrink-0 text-muted-foreground" />

                            <span className="min-w-0 flex-1 space-y-0.5">
                                <span className="block text-sm font-medium">
                                    {c.registro ? `Carnet N° ${c.registro}` : (c.tipo ?? c.tipo_actor_etiqueta)}
                                    <span className="ml-2 font-mono text-xs font-normal text-muted-foreground">
                                        {c.codigo}
                                    </span>
                                </span>
                                <span className="block text-xs text-muted-foreground">
                                    {c.tipo ?? c.tipo_actor_etiqueta}
                                    {c.fecha_vencimiento && ` · vence ${fecha(c.fecha_vencimiento)}`}
                                </span>
                                {c.volumen_total_kg !== null && (
                                    <span className="block text-xs text-muted-foreground">
                                        Capacidad {c.capacidad ?? '—'} · otorgado{' '}
                                        <span className="tabular-nums">{c.volumen_total_kg} kg</span>
                                    </span>
                                )}
                            </span>

                            {c.puede_emitir_faenas ? (
                                <Badge color="emerald">{c.kilos_disponibles} kg por faena</Badge>
                            ) : (
                                <span className="text-xs text-muted-foreground">
                                    {c.tipo_actor === 'comercializador'
                                        ? 'no emite faenas'
                                        : 'sin autorización vigente'}
                                </span>
                            )}
                        </button>
                    </li>
                );
            })}
        </ul>
    );
}
