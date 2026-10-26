import { Head, useForm } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    ArrowRight,
    CalendarClock,
    Check,
    Fish,
    IdCard,
    Info,
    MapPin,
    Ship,
    ShieldAlert,
    TriangleAlert,
    Wallet,
} from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Aviso, BandaFormulario, DatosDelCarnet, kg, SeccionFormulario } from '@/components/panel/comunes/ficha';
import { BuscadorBeneficiario } from '@/components/panel/comunes/buscador-beneficiario';
import { Button } from '@/components/ui/button';
import { Campo } from '@/components/ui/campo';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import LayoutPanel from '@/layouts/layout-panel';
import { bs, cn, fecha } from '@/lib/utils';
import type { BeneficiarioSugerido, CarnetVigenteSugerido } from '@/types/beneficiarios';

/** Los renglones del talonario: obligatorios desde el 23/10/2026, igual que en EmitirFaenaRequest. */
const RENGLONES = [
    { clave: 'embarcacion', etiqueta: 'La embarcación' },
    { clave: 'propietario', etiqueta: 'De propiedad de', ayuda: 'Si es del propio pescador, va su nombre.' },
    { clave: 'comandante_barco', etiqueta: 'Comandante de barco' },
    { clave: 'matricula_naval', etiqueta: 'Matrícula naval N°', mono: true },
    { clave: 'nro_kardex', etiqueta: 'N° Kardex', mono: true },
] as const;

/**
 *  Registrar un permiso de faena (paso 4 del flujo): una salida, pendiente hasta que se pague
 */
export default function CrearFaena({
    beneficiario,
    carnetElegido,
    vigencia,
    arancel,
    sinArancel,
}: {
    beneficiario: (BeneficiarioSugerido & { carnets_vigentes: CarnetVigenteSugerido[] }) | null;
    /** `?carnet=`: llega desde la ficha de la autorización con el carnet ya elegido. */
    carnetElegido: number | null;
    /** «30 días desde la aprobación» o «hasta el 31 de diciembre»: lo configura la unidad. */
    vigencia: string;
    /** La tarifa definida del permiso, de referencia; null si no se puede cobrar. */
    arancel: { etiqueta: string; monto: number } | null;
    /** Por qué no hay arancel (sin definir, SIREB caído, tarifa de baja); null si lo hay. */
    sinArancel: string | null;
}) {
    const [persona, setPersona] = useState<BeneficiarioSugerido | null>(beneficiario);
    // Solo si está entre los vigentes y puede emitir: si no, se elige a mano.
    const inicial = beneficiario?.carnets_vigentes.find((c) => c.id === carnetElegido && c.puede_emitir_faenas) ?? null;
    const [carnet, setCarnet] = useState<CarnetVigenteSugerido | null>(inicial);

    const form = useForm({
        carnet_id: inicial?.id ?? (null as number | null),
        embarcacion: '',
        // Llegando desde la ficha el pescador ya viene elegido: se propone igual su nombre.
        propietario: beneficiario?.nombreCompleto ?? '',
        comandante_barco: '',
        matricula_naval: '',
        nro_kardex: '',
        region_desde: '',
        region_hasta: '',
    });

    const faltan = [...RENGLONES.map((r) => r.clave), 'region_desde', 'region_hasta'].filter(
        (clave) => String(form.data[clave as keyof typeof form.data] ?? '').trim() === '',
    ).length;
    const conTalonario = faltan === 0;
    const region =
        form.data.region_desde || form.data.region_hasta
            ? `${form.data.region_desde || '—'} → ${form.data.region_hasta || '—'}`
            : null;

    function elegirPersona(elegida: BeneficiarioSugerido | null) {
        setPersona(elegida);
        setCarnet(null);
        // Casi siempre la embarcación es del propio pescador: se propone su nombre, y se cambia si no.
        form.setData((datos) => ({
            ...datos,
            carnet_id: null,
            propietario: datos.propietario.trim() === '' ? (elegida?.nombreCompleto ?? '') : datos.propietario,
        }));
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

    return (
        <LayoutPanel titulo="Registrar faena">
            <Head title="Registrar faena" />

            <BandaFormulario
                icono={Ship}
                titulo="Permiso de faena"
                bajada={`Autoriza una salida de pesca y vale ${vigencia}. Queda pendiente hasta que se pague.`}
                pasos={[
                    { texto: 'Pescador', hecho: persona !== null },
                    { texto: 'Carnet', hecho: carnet !== null },
                    { texto: 'Talonario', hecho: conTalonario },
                ]}
            />

            <form onSubmit={enviar} className="mt-6 grid gap-6 lg:grid-cols-3">
                <div className="min-w-0 space-y-6 lg:col-span-2">
                    <SeccionFormulario
                        obligatorio
                        numero={1}
                        titulo="¿Quién sale a pescar?"
                        ayuda="Tiene que tener carnet de pescador vigente y su autorización aprobada."
                        hecho={persona !== null}
                    >
                        <BuscadorBeneficiario seleccionado={persona} onSeleccionar={elegirPersona} />
                    </SeccionFormulario>

                    <SeccionFormulario
                        obligatorio
                        numero={2}
                        titulo="Carnet"
                        ayuda="La faena cuelga del carnet y sale con los kilos disponibles de su autorización, sin descontarlos."
                        hecho={carnet !== null}
                    >
                        {persona === null ? (
                            <p className="text-sm text-muted-foreground">Elija primero al pescador.</p>
                        ) : (
                            <ListaDeCarnets
                                carnets={persona.carnets_vigentes}
                                elegido={carnet}
                                onElegir={elegirCarnet}
                            />
                        )}
                        {form.errors.carnet_id && <p className="text-sm text-destructive">{form.errors.carnet_id}</p>}
                    </SeccionFormulario>

                    <SeccionFormulario
                        numero={3}
                        titulo="Datos del talonario"
                        ayuda="El Área de Fiscalización y Control de la Actividad Pesquera autoriza a… Todos son obligatorios."
                        hecho={conTalonario}
                    >
                        <div className="grid gap-x-4 gap-y-3 sm:grid-cols-2 xl:grid-cols-3">
                            {RENGLONES.map((r) => (
                                <Campo
                                    key={r.clave}
                                    etiqueta={r.etiqueta}
                                    htmlFor={r.clave}
                                    error={form.errors[r.clave]}
                                    ayuda={'ayuda' in r ? r.ayuda : undefined}
                                    obligatorio
                                >
                                    <Input
                                        id={r.clave}
                                        value={form.data[r.clave]}
                                        onChange={(e) => form.setData(r.clave, e.target.value)}
                                        aria-invalid={Boolean(form.errors[r.clave])}
                                        className={'mono' in r ? 'font-mono' : undefined}
                                    />
                                </Campo>
                            ))}
                        </div>

                        <div className="grid items-end gap-3 sm:grid-cols-[1fr_auto_1fr]">
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
                            <ArrowRight className="mx-auto mb-2.5 hidden size-4 text-primary sm:block" />
                            <Campo etiqueta="Hasta" htmlFor="region_hasta" error={form.errors.region_hasta} obligatorio>
                                <Input
                                    id="region_hasta"
                                    value={form.data.region_hasta}
                                    onChange={(e) => form.setData('region_hasta', e.target.value)}
                                    aria-invalid={Boolean(form.errors.region_hasta)}
                                />
                            </Campo>
                        </div>

                        {/* Las fechas no se tipean: las fija la aprobación. */}
                        <Aviso tono="info" icono={CalendarClock}>
                            La salida es el día en que se aprueba el permiso y el desembarque, al cumplirse la vigencia
                            ({vigencia}). Las pone el sistema.
                        </Aviso>
                    </SeccionFormulario>
                </div>

                {/* El resumen acompaña al bajar: es lo que se confirma antes de registrar. */}
                <Card className="h-fit overflow-hidden lg:sticky lg:top-40">
                    <CardHeader className="border-b border-border">
                        <CardTitle>Lo que se va a registrar</CardTitle>
                    </CardHeader>

                    <CardContent className="space-y-4 pt-5 text-sm">
                        <div className="rounded-lg bg-secondary/60 px-4 py-3">
                            <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                Kilos del permiso
                            </p>
                            {/* Fijos: los disponibles de la autorización ese día. La faena no descuenta. */}
                            <p
                                className={cn(
                                    'text-2xl font-semibold tabular-nums',
                                    carnet ? 'text-primary' : 'text-muted-foreground',
                                )}
                            >
                                {carnet?.kilos_disponibles != null ? kg(carnet.kilos_disponibles) : '— kg'}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                {carnet ? 'Lo disponible de su autorización hoy' : 'Elija el carnet'}
                            </p>
                        </div>

                        <Renglon icono={Fish} etiqueta="Pescador" valor={persona?.nombreCompleto ?? null} />
                        <Renglon
                            icono={IdCard}
                            etiqueta="Carnet"
                            valor={carnet?.registro ? `N° ${carnet.registro}` : null}
                        />
                        <Renglon icono={Ship} etiqueta="Embarcación" valor={form.data.embarcacion.trim() || null} />
                        <Renglon icono={MapPin} etiqueta="Región" valor={region} />
                        <Renglon icono={CalendarClock} etiqueta="Vale" valor={vigencia} />

                        <div className="flex items-baseline justify-between border-t border-border pt-4">
                            <span className="flex items-center gap-2 text-muted-foreground">
                                <Wallet className="size-4" />
                                Arancel
                            </span>
                            {/* De referencia: el que vale lo pide el servidor a SIREB al guardar. */}
                            <span className="text-xl font-semibold tabular-nums">
                                {arancel ? bs(arancel.monto) : '—'}
                            </span>
                        </div>

                        {sinArancel && (
                            <Aviso tono="peligro" icono={TriangleAlert}>
                                {sinArancel}
                            </Aviso>
                        )}

                        <p className="flex items-start gap-2 rounded-lg bg-sky-50 p-3 text-xs text-sky-900 dark:bg-sky-500/10 dark:text-sky-200">
                            <Info className="mt-0.5 size-4 shrink-0" />
                            Queda pendiente: se paga en Recaudaciones y, cuando lo validan, autoriza la salida desde ese
                            día.
                        </p>

                        {carnet !== null && !conTalonario && (
                            <p className="text-center text-xs font-medium text-amber-700 dark:text-amber-400">
                                Faltan {faltan} {faltan === 1 ? 'dato' : 'datos'} del talonario.
                            </p>
                        )}

                        <Button
                            type="submit"
                            disabled={form.processing || carnet === null || arancel === null || !conTalonario}
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

/** Los carnets vigentes de la persona, con el que no sirve deshabilitado y su porqué. */
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
            <Aviso tono="aviso" icono={ShieldAlert} titulo="Sin carnet vigente">
                Hay que emitirle uno antes: el carnet es la llave anual y la faena cuelga de él.
            </Aviso>
        );
    }

    return (
        <div role="radiogroup" aria-label="Carnet" className="grid gap-2 sm:grid-cols-2">
            {carnets.map((c) => {
                const activo = elegido?.id === c.id;

                return (
                    <button
                        key={c.id}
                        type="button"
                        role="radio"
                        aria-checked={activo}
                        disabled={!c.puede_emitir_faenas}
                        onClick={() => onElegir(c)}
                        className={cn(
                            'relative rounded-lg border px-3 py-2 text-left transition-all',
                            activo
                                ? 'border-primary bg-primary/5 ring-2 ring-primary/30'
                                : 'border-border hover:-translate-y-0.5 hover:border-primary/50 hover:shadow-md',
                            !c.puede_emitir_faenas &&
                                'cursor-not-allowed opacity-50 hover:translate-y-0 hover:shadow-none',
                        )}
                    >
                        {activo && (
                            <Check className="absolute top-2 right-2 size-3.5 rounded-full bg-primary p-0.5 text-primary-foreground" />
                        )}
                        {/* Lo que el carnet lleva impreso: el volumen autorizado, no el saldo. */}
                        <DatosDelCarnet carnet={c}>
                            {c.puede_emitir_faenas ? (
                                <span className="font-medium text-primary">
                                    {c.volumen_total_kg !== null ? `${kg(c.volumen_total_kg)} autorizados` : ''}
                                </span>
                            ) : (
                                <span className="text-muted-foreground">
                                    {c.tipo_actor === 'comercializador'
                                        ? 'No emite faenas'
                                        : 'Sin autorización vigente'}
                                </span>
                            )}
                        </DatosDelCarnet>
                    </button>
                );
            })}
        </div>
    );
}

function Renglon({ icono: Icono, etiqueta, valor }: { icono: LucideIcon; etiqueta: string; valor: string | null }) {
    return (
        <div className="flex items-center justify-between gap-3">
            <span className="flex items-center gap-2 text-muted-foreground">
                <Icono className="size-4" />
                {etiqueta}
            </span>
            <span className={cn('truncate text-right', valor ? 'font-medium' : 'text-muted-foreground')}>
                {valor ?? '—'}
            </span>
        </div>
    );
}
