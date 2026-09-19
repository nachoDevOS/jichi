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
import type { BeneficiarioSugerido, CarnetVigenteSugerido } from '@/types/beneficiarios';

/**
 * ============================================================================
 *  EMITIR UN PERMISO DE FAENA — paso 4 del flujo
 * ============================================================================
 *
 * ----------------------------------------------------------------------------
 *  SE ELIGE UNA PERSONA Y DESPUÉS UN CARNET, NO AL REVÉS
 * ----------------------------------------------------------------------------
 *
 * En el mostrador la persona se identifica con su cédula, no con el código del
 * plástico: llega, lo deja sobre el escritorio y el operador tipea el nombre.
 * Por eso el formulario arranca con el buscador de beneficiarios y recién
 * después muestra qué credenciales tiene.
 *
 * Y las muestra TODAS las vigentes, no solo las que sirven. Un carnet de
 * comercializador aparece deshabilitado y con el motivo al lado, en vez de
 * desaparecer: si no está, el operador cree que la persona no tiene carnet y
 * va a emitirle otro.
 *
 * ----------------------------------------------------------------------------
 *  EL NÚMERO SE PROPONE, NO SE IMPONE
 * ----------------------------------------------------------------------------
 *
 * Sale de un talonario de PAPEL que el pescador se lleva. El sistema sugiere el
 * siguiente para no hacer contar hojas, pero el campo es editable: si la hoja
 * que el operador tiene en la mano dice otro número, hay algo que conviene
 * mirar antes de seguir, no autocorregir en silencio.
 */
export default function CrearFaena({
    beneficiario,
    diasVigencia,
    modoEstricto,
}: {
    beneficiario: (BeneficiarioSugerido & { carnets_vigentes: CarnetVigenteSugerido[] }) | null;
    /** El plazo de la resolución. Llega del servidor para que no haya dos copias. */
    diasVigencia: number;
    /**
     * Lo que dice APROVECHAMIENTO_ESTRICTO en el servidor, y ES LO QUE DECIDE
     * SI EL BOTÓN SE BLOQUEA.
     *
     * Con la validación apagada el servidor acepta la faena igual, así que
     * frenar acá sería la pantalla inventando una regla que el sistema no
     * tiene — y el operador se quedaría sin poder emitir algo perfectamente
     * válido, sin ningún mensaje que lo explique.
     */
    modoEstricto: boolean;
}) {
    const [persona, setPersona] = useState<BeneficiarioSugerido | null>(beneficiario);
    const [carnet, setCarnet] = useState<CarnetVigenteSugerido | null>(null);

    const form = useForm({
        carnet_id: null as number | null,
        numero_faena: '',
        kilos_extraidos: '',
        fecha_salida: new Date().toISOString().slice(0, 10),
    });

    function elegirPersona(elegida: BeneficiarioSugerido | null) {
        setPersona(elegida);
        setCarnet(null);
        form.setData('carnet_id', null);
        form.clearErrors('carnet_id');
    }

    function elegirCarnet(c: CarnetVigenteSugerido) {
        setCarnet(c);
        form.setData((datos) => ({
            ...datos,
            carnet_id: c.id,
            // El número propuesto se escribe al elegir: es el que el operador va
            // a confirmar contra la hoja, y tenerlo ya puesto le ahorra contar.
            numero_faena: c.siguiente_numero_faena !== null ? String(c.siguiente_numero_faena) : '',
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
            titulo="Emitir faena"
            descripcion={`Autoriza UNA salida. Vale ${diasVigencia} días y descuenta kilos del cupo.`}
        >
            <Head title="Emitir faena" />

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
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Campo
                                    etiqueta="N° de la hoja del talonario"
                                    htmlFor="numero_faena"
                                    error={form.errors.numero_faena}
                                    ayuda="El sistema propone el siguiente. Confírmelo contra la hoja que tiene en la mano."
                                    obligatorio
                                >
                                    <Input
                                        id="numero_faena"
                                        type="number"
                                        min={1}
                                        value={form.data.numero_faena}
                                        onChange={(e) => form.setData('numero_faena', e.target.value)}
                                        aria-invalid={Boolean(form.errors.numero_faena)}
                                        className="font-mono"
                                    />
                                </Campo>

                                <Campo
                                    etiqueta="Kilos autorizados"
                                    htmlFor="kilos_extraidos"
                                    error={form.errors.kilos_extraidos}
                                    ayuda={
                                        saldo !== null
                                            ? `Quedan ${saldo} kg en la bolsa madre.`
                                            : undefined
                                    }
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
                                    />
                                </Campo>

                                <Campo
                                    etiqueta="Fecha de salida"
                                    htmlFor="fecha_salida"
                                    error={form.errors.fecha_salida}
                                    ayuda="Puede ser pasada. Futura no: el permiso empezaría a valer antes de existir."
                                    obligatorio
                                >
                                    <Input
                                        id="fecha_salida"
                                        type="date"
                                        value={form.data.fecha_salida}
                                        onChange={(e) => form.setData('fecha_salida', e.target.value)}
                                        aria-invalid={Boolean(form.errors.fecha_salida)}
                                    />
                                </Campo>
                            </div>
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

                                <p className="text-xs text-muted-foreground">
                                    La faena vale {diasVigencia} días desde la salida. Si no se cierra
                                    antes, vence y su volumen vuelve al cupo.
                                </p>
                            </>
                        )}

                        <Button
                            type="submit"
                            disabled={
                                form.processing ||
                                carnet === null ||
                                form.data.numero_faena === '' ||
                                kilos <= 0 ||
                                bloquea
                            }
                            className="w-full"
                        >
                            Emitir faena
                        </Button>
                    </CardContent>
                </Card>
            </form>
        </LayoutPanel>
    );
}

/**
 * Los carnets vigentes de la persona, con el que no sirve deshabilitado.
 *
 * NO SE FILTRAN LOS QUE NO PUEDEN: aparecen en gris y con el motivo al lado. Un
 * carnet que desaparece de la lista le dice al operador «esta persona no tiene
 * carnet», que es falso y lo manda a emitir otro.
 *
 * `puede_emitir_faenas` llega RESUELTO del servidor: exige carnet vigente, de
 * pescador Y con cupo con saldo. Son tres condiciones que la pantalla no puede
 * juntar sola sin copiar la regla.
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

                            <span className="min-w-0 flex-1">
                                <span className="block font-mono text-sm font-medium">{c.codigo}</span>
                                <span className="block text-xs text-muted-foreground">
                                    {c.tipo ?? c.tipo_actor_etiqueta}
                                </span>
                            </span>

                            {c.puede_emitir_faenas ? (
                                <Badge color="emerald">{c.saldo_kg} kg</Badge>
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
