import { Head, useForm } from '@inertiajs/react';
import { Info, ShieldAlert, Ship } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { BuscadorBeneficiario } from '@/components/panel/comunes/buscador-beneficiario';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Campo } from '@/components/ui/campo';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import LayoutPanel from '@/layouts/layout-panel';
import { fecha } from '@/lib/utils';
import type { BeneficiarioSugerido, CarnetVigenteSugerido } from '@/types/beneficiarios';

/**
 *  EMITIR UN PERMISO DE FAENA — paso 4 del flujo
 */
export default function CrearFaena({
    beneficiario,
    carnetElegido,
    diasVigencia,
    tarifa,
    modoEstricto,
}: {
    beneficiario: (BeneficiarioSugerido & { carnets_vigentes: CarnetVigenteSugerido[] }) | null;
    /** `?carnet=`: llega desde la ficha del cupo con el carnet ya elegido. */
    carnetElegido: number | null;
    /** El plazo de la resolución. Llega del servidor para que no haya dos copias. */
    diasVigencia: number;
    /** El arancel de hoy. Se copia congelado en la fila al emitir. */
    tarifa: number;
    /**
     * Lo que dice APROVECHAMIENTO_ESTRICTO en el servidor, y ES LO QUE DECIDE
     * SI EL BOTÓN SE BLOQUEA.
     */
    modoEstricto: boolean;
}) {
    const [persona, setPersona] = useState<BeneficiarioSugerido | null>(beneficiario);
    // Solo si está entre los vigentes y puede emitir: si no, se elige a mano.
    const inicial =
        beneficiario?.carnets_vigentes.find((c) => c.id === carnetElegido && c.puede_emitir_faenas) ?? null;
    const [carnet, setCarnet] = useState<CarnetVigenteSugerido | null>(inicial);

    const form = useForm({
        carnet_id: inicial?.id ?? (null as number | null),
        kilos_extraidos: inicial?.saldo_kg ? String(inicial.saldo_kg) : '',
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
        form.setData((d) => ({ ...d, carnet_id: null, kilos_extraidos: '' }));
        form.clearErrors('carnet_id');
    }

    // Arranca con todo el saldo del cupo: es lo que casi siempre se pide, y se baja a mano.
    function elegirCarnet(c: CarnetVigenteSugerido) {
        setCarnet(c);
        form.setData((d) => ({
            ...d,
            carnet_id: c.id,
            kilos_extraidos: c.saldo_kg !== null && c.saldo_kg > 0 ? String(c.saldo_kg) : '',
        }));
        form.clearErrors('carnet_id');
    }

    function enviar(e: FormEvent) {
        e.preventDefault();
        form.post(route('faenas.store'));
    }

    const saldo = carnet?.saldo_kg ?? null;
    const kilos = Number(form.data.kilos_extraidos || 0);

    /*
     * `excede` es el HECHO —no entra en el saldo— y `bloquea` es la
     * CONSECUENCIA, que depende del modo. Separarlos es lo que deja avisar sin
     * frenar: en modo flexible el exceso se sigue mostrando, en ámbar, porque
     * es un dato que el operador tiene que ver aunque nadie lo detenga.
     */
    const excede = saldo !== null && kilos > saldo;
    const bloquea = excede && modoEstricto;

    return (
        <LayoutPanel
            titulo="Registrar faena"
            descripcion={`Autoriza UNA salida de ${diasVigencia} días. Nace PENDIENTE: autoriza recién cuando se cobre el arancel y la aprueben.`}
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
                                ayuda="Tiene que tener carnet de pescador vigente y cupo con saldo."
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
                                Elija a la persona y su carnet de pescador para ver cuánto le queda de
                                cupo.
                            </p>
                        ) : (
                            <>
                                <div>
                                    <p className="text-xs uppercase tracking-wide text-muted-foreground">
                                        Cupo disponible
                                    </p>
                                    <p className="text-3xl font-semibold tabular-nums">
                                        {saldo ?? '—'}
                                        <span className="ml-1 text-base font-normal text-muted-foreground">
                                            kg
                                        </span>
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

                                        {/*
                                            El aviso es local y adelanta lo que el servidor va a
                                            decir: la comprobación de verdad corre DENTRO de la
                                            transacción, con la fila del cupo bloqueada, porque otra
                                            ventanilla puede mover el saldo en el mismo segundo.
                                        */}
                                        {/*
                                            EL MISMO EXCESO SE DICE DE DOS MANERAS, y no es
                                            cosmética: en modo estricto el servidor RECHAZA la
                                            faena, y en modo flexible la acepta. Un aviso que
                                            dijera «no entra» donde sí entra enseñaría al operador
                                            a ignorarlo.
                                        */}
                                        {excede &&
                                            (modoEstricto ? (
                                                <p className="mt-1 text-sm text-destructive">
                                                    No entra en el cupo. Baje los kilos o tramite
                                                    otro aprovechamiento.
                                                </p>
                                            ) : (
                                                <p className="mt-1 text-sm text-amber-700 dark:text-amber-400">
                                                    Va a quedar por encima del cupo otorgado. El
                                                    control de saldo está desactivado, así que la
                                                    faena se emite igual y el exceso queda
                                                    registrado.
                                                </p>
                                            ))}
                                    </div>
                                )}

                                <div>
                                    <p className="text-xs uppercase tracking-wide text-muted-foreground">
                                        Arancel
                                    </p>
                                    <p className="text-xl font-semibold tabular-nums">
                                        {tarifa.toFixed(2)}
                                        <span className="ml-1 text-sm font-normal text-muted-foreground">
                                            Bs
                                        </span>
                                    </p>
                                </div>

                                <p className="text-xs text-muted-foreground">
                                    Sale el día que se aprueba y vale {diasVigencia} días. Si no se
                                    cierra antes, vence y su volumen vuelve al cupo.
                                </p>
                            </>
                        )}

                        <Button
                            type="submit"
                            disabled={
                                form.processing ||
                                carnet === null ||
                                kilos <= 0 ||
                                bloquea
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
                                        <span className="tabular-nums">{c.volumen_total_kg} kg</span> · disponible{' '}
                                        <span className="font-medium tabular-nums text-foreground">{c.saldo_kg} kg</span>
                                    </span>
                                )}
                            </span>

                            {c.puede_emitir_faenas ? (
                                <Badge color="emerald">{c.saldo_kg} kg disponibles</Badge>
                            ) : (
                                <span className="text-xs text-muted-foreground">
                                    {c.tipo_actor === 'comercializador'
                                        ? 'no emite faenas'
                                        : 'sin cupo con saldo'}
                                </span>
                            )}
                        </button>
                    </li>
                );
            })}
        </ul>
    );
}
