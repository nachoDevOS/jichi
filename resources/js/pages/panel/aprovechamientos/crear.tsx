import { Head, useForm, usePage } from '@inertiajs/react';
import { CalendarClock, Check, Fish, Info, Scale, Ship, Wallet, Waves } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { BandaFormulario, kg, SeccionFormulario } from '@/components/panel/comunes/ficha';
import { BuscadorBeneficiario } from '@/components/panel/comunes/buscador-beneficiario';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { EstadoVacio } from '@/components/ui/estado-vacio';
import { Input } from '@/components/ui/input';
import LayoutPanel from '@/layouts/layout-panel';
import { bs, cn, fecha } from '@/lib/utils';
import type { PageProps, TarifaSireb } from '@/types';
import type { BeneficiarioSugerido } from '@/types/beneficiarios';

/** Sugerencias del campo de embarcación, NO una lista cerrada. */
const TIPOS_DE_EMBARCACION = ['Canoa', 'Peque-peque', 'Bote', 'Chalana', 'Deslizador', 'Balsa'];

/**
 *  Registrar una Autorización de Pesca para Aprovechamiento Pesquero (paso 2 del pescador)
 */
export default function CrearCupo({
    beneficiario,
    escala,
    servicioConfigurado,
}: {
    /** Preseleccionado al llegar desde la ficha de una persona. */
    beneficiario: BeneficiarioSugerido | null;
    /** Las tarifas del servicio de la escala en SIREB; null si SIREB no responde. */
    escala: TarifaSireb[] | null;
    /** false = falta elegir el servicio en Catálogos › Aranceles. */
    servicioConfigurado: boolean;
}) {
    const { institucion } = usePage<PageProps>().props;
    const [persona, setPersona] = useState<BeneficiarioSugerido | null>(beneficiario);

    const form = useForm({
        beneficiario_id: beneficiario?.id ?? null,
        tarifa_sireb: '',
        tipo_embarcacion: '',
    });

    const tramo = escala?.find((t) => t.id === form.data.tarifa_sireb) ?? null;
    // Los kilos son el `rango_hasta` de la escala: ventanilla no los escribe.
    const kilos = Number(tramo?.rango_hasta ?? 0);
    const embarcacion = form.data.tipo_embarcacion.trim();
    const listo = persona !== null && tramo !== null && kilos > 0 && embarcacion !== '';

    function elegirPersona(elegida: BeneficiarioSugerido | null) {
        setPersona(elegida);
        form.setData('beneficiario_id', elegida?.id ?? null);
        // Un error de la persona anterior («ya tiene una vigente») mentiría bajo otra.
        form.clearErrors('beneficiario_id');
    }

    function enviar(e: FormEvent) {
        e.preventDefault();
        // preserveState: si el servidor devuelve a esta misma pantalla, no se pierde lo cargado.
        form.post(route('aprovechamientos.store'), { preserveState: true });
    }

    // Sin escala no hay nada que otorgar: se dice por qué en vez de un formulario en blanco.
    const sinEscala = !servicioConfigurado
        ? {
              titulo: 'Falta el servicio de la escala',
              descripcion: 'Elija en Catálogos › Aranceles el servicio de SIREB que cobra la escala.',
          }
        : escala === null
          ? {
                titulo: 'Recaudaciones (SIREB) no responde',
                descripcion: 'La escala sale de SIREB. Espere unos minutos y vuelva a abrir el formulario.',
            }
          : escala.length === 0
            ? {
                  titulo: 'La escala de aprovechamiento está vacía',
                  descripcion: 'El servicio elegido en Catálogos › Aranceles no tiene tarifas en SIREB.',
              }
            : null;

    if (sinEscala !== null || escala === null) {
        return (
            <LayoutPanel titulo="Registrar autorización">
                <Head title="Registrar autorización" />
                <Card>
                    <EstadoVacio
                        icono={Waves}
                        titulo={sinEscala?.titulo ?? ''}
                        descripcion={sinEscala?.descripcion ?? ''}
                    />
                </Card>
            </LayoutPanel>
        );
    }

    return (
        <LayoutPanel titulo="Registrar autorización">
            <Head title="Registrar autorización" />

            <BandaFormulario
                icono={Waves}
                titulo="Autorización de Pesca para Aprovechamiento Pesquero"
                bajada="La bolsa de kilos del pescador para la gestión. Queda pendiente hasta que se pague."
                pasos={[
                    { texto: 'Pescador', hecho: persona !== null },
                    { texto: 'Escala', hecho: tramo !== null },
                    { texto: 'Embarcación', hecho: embarcacion !== '' },
                ]}
            />

            <form onSubmit={enviar} className="mt-6 grid gap-6 lg:grid-cols-3">
                <div className="min-w-0 space-y-6 lg:col-span-2">
                    <SeccionFormulario
                        obligatorio
                        numero={1}
                        titulo="¿Quién la pide?"
                        ayuda="Tiene que estar en el padrón. Si es la primera vez que viene, regístrelo antes."
                        hecho={persona !== null}
                    >
                        <BuscadorBeneficiario seleccionado={persona} onSeleccionar={elegirPersona} />
                        {form.errors.beneficiario_id && (
                            <p className="text-sm text-destructive">{form.errors.beneficiario_id}</p>
                        )}
                    </SeccionFormulario>

                    <SeccionFormulario
                        obligatorio
                        numero={2}
                        titulo="Escala"
                        ayuda="Las tarifas de Recaudaciones (SIREB): de cada una salen los kilos y el monto."
                        hecho={tramo !== null}
                    >
                        <div
                            role="radiogroup"
                            aria-label="Escala"
                            className="grid grid-cols-2 gap-2 sm:grid-cols-3 xl:grid-cols-4"
                        >
                            {escala.map((t) => {
                                const kilosTramo = Number(t.rango_hasta);
                                const elegible = t.activa && kilosTramo > 0;
                                const elegida = form.data.tarifa_sireb === t.id;

                                return (
                                    <button
                                        key={t.id}
                                        type="button"
                                        role="radio"
                                        aria-checked={elegida}
                                        disabled={!elegible}
                                        onClick={() => form.setData('tarifa_sireb', t.id)}
                                        className={cn(
                                            'relative rounded-lg border px-3 py-2 text-left transition-all',
                                            elegida
                                                ? 'border-primary bg-primary/5 ring-2 ring-primary/30'
                                                : 'border-border hover:-translate-y-0.5 hover:border-primary/50 hover:shadow-md',
                                            !elegible &&
                                                'cursor-not-allowed opacity-50 hover:translate-y-0 hover:shadow-none',
                                        )}
                                    >
                                        {elegida && (
                                            <Check className="absolute top-2 right-2 size-3.5 rounded-full bg-primary p-0.5 text-primary-foreground" />
                                        )}
                                        <p className="truncate pr-4 text-[11px] text-muted-foreground">{t.etiqueta}</p>
                                        <p className="flex flex-wrap items-baseline justify-between gap-x-2">
                                            <span className="text-lg font-semibold whitespace-nowrap tabular-nums">
                                                {kilosTramo > 0 ? kg(kilosTramo) : '—'}
                                            </span>
                                            <span className="text-xs font-medium whitespace-nowrap text-primary tabular-nums">
                                                {bs(t.monto, institucion.moneda)}
                                            </span>
                                        </p>
                                        {!elegible && (
                                            <p className="text-[11px] text-muted-foreground">
                                                {!t.activa ? 'Tarifa no disponible' : 'Sin rango en SIREB'}
                                            </p>
                                        )}
                                    </button>
                                );
                            })}
                        </div>
                        {form.errors.tarifa_sireb && (
                            <p className="text-sm text-destructive">{form.errors.tarifa_sireb}</p>
                        )}
                    </SeccionFormulario>

                    <SeccionFormulario
                        obligatorio
                        numero={3}
                        titulo="Tipo de embarcación"
                        ayuda="Como figura en el talonario: va impreso en la autorización."
                        hecho={embarcacion !== ''}
                    >
                        <div className="flex flex-wrap gap-2">
                            {TIPOS_DE_EMBARCACION.map((t) => (
                                <button
                                    key={t}
                                    type="button"
                                    onClick={() => form.setData('tipo_embarcacion', t)}
                                    className={cn(
                                        'flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-sm transition-colors',
                                        embarcacion === t
                                            ? 'border-primary bg-primary text-primary-foreground'
                                            : 'border-border hover:border-primary/50 hover:bg-secondary',
                                    )}
                                >
                                    <Ship className="size-3.5" />
                                    {t}
                                </button>
                            ))}
                        </div>
                        <Input
                            id="tipo_embarcacion"
                            value={form.data.tipo_embarcacion}
                            onChange={(e) => form.setData('tipo_embarcacion', e.target.value)}
                            aria-invalid={Boolean(form.errors.tipo_embarcacion)}
                            aria-label="Tipo de embarcación"
                            placeholder="U otra: escríbala acá"
                            maxLength={120}
                            className="max-w-sm"
                        />
                        {form.errors.tipo_embarcacion && (
                            <p className="text-sm text-destructive">{form.errors.tipo_embarcacion}</p>
                        )}
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
                                Volumen autorizado
                            </p>
                            <p
                                className={cn(
                                    'text-2xl font-semibold tabular-nums',
                                    tramo ? 'text-primary' : 'text-muted-foreground',
                                )}
                            >
                                {kilos > 0 ? kg(kilos) : '— kg'}
                            </p>
                            <p className="text-xs text-muted-foreground">{tramo?.etiqueta ?? 'Elija la escala'}</p>
                        </div>

                        <Renglon icono={Fish} etiqueta="Pescador" valor={persona?.nombreCompleto ?? null} />
                        <Renglon icono={Ship} etiqueta="Embarcación" valor={embarcacion || null} />
                        <Renglon icono={CalendarClock} etiqueta="Solicitud" valor={fecha(new Date())} />
                        <Renglon icono={Scale} etiqueta="Escala" valor={tramo?.etiqueta ?? null} />

                        <div className="flex items-baseline justify-between border-t border-border pt-4">
                            <span className="flex items-center gap-2 text-muted-foreground">
                                <Wallet className="size-4" />A cobrar
                            </span>
                            <span className="text-xl font-semibold tabular-nums">
                                {tramo ? bs(tramo.monto, institucion.moneda) : '—'}
                            </span>
                        </div>

                        <p className="flex items-start gap-2 rounded-lg bg-sky-50 p-3 text-xs text-sky-900 dark:bg-sky-500/10 dark:text-sky-200">
                            <Info className="mt-0.5 size-4 shrink-0" />
                            Queda pendiente: se paga en Recaudaciones y, cuando lo validan, queda aprobada y sale el
                            recibo.
                        </p>

                        <Button type="submit" disabled={form.processing || !listo} className="w-full">
                            {/* «Registrar» y no «otorgar»: lo que se crea queda pendiente de pago. */}
                            Registrar autorización
                        </Button>
                    </CardContent>
                </Card>
            </form>
        </LayoutPanel>
    );
}

function Renglon({ icono: Icono, etiqueta, valor }: { icono: typeof Fish; etiqueta: string; valor: string | null }) {
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
