import { Head, useForm, usePage } from '@inertiajs/react';
import { Info, Waves } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { BuscadorBeneficiario } from '@/components/panel/comunes/buscador-beneficiario';
import { Button } from '@/components/ui/button';
import { Campo } from '@/components/ui/campo';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { EstadoVacio } from '@/components/ui/estado-vacio';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import LayoutPanel from '@/layouts/layout-panel';
import { bs, fecha } from '@/lib/utils';
import type { PageProps, TarifaSireb } from '@/types';
import type { BeneficiarioSugerido } from '@/types/beneficiarios';

/**
 * Sugerencias del campo de embarcación, NO una lista cerrada.
 */
const TIPOS_DE_EMBARCACION = ['Canoa', 'Peque-peque', 'Bote', 'Chalana', 'Deslizador', 'Balsa'];

/**
 *  Otorgar una bolsa madre — paso 2 del flujo del pescador
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

    function elegirPersona(elegida: BeneficiarioSugerido | null) {
        setPersona(elegida);
        form.setData('beneficiario_id', elegida?.id ?? null);
        // Se limpia el error anterior: si venía de «ya tiene un cupo vigente»,
        // dejarlo colgado bajo otra persona diría una mentira.
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
            <LayoutPanel titulo="Registrar aprovechamiento" descripcion="La bolsa madre del pescador.">
                <Head title="Registrar aprovechamiento" />

                <Card>
                    <EstadoVacio icono={Waves} titulo={sinEscala?.titulo ?? ''} descripcion={sinEscala?.descripcion ?? ''} />
                </Card>
            </LayoutPanel>
        );
    }

    return (
        <LayoutPanel
            titulo="Registrar aprovechamiento"
            // descripcion="Paso 2 del flujo: queda PENDIENTE de cobro. Va antes del carnet, porque el carnet imprime este volumen."
        >
            <Head title="Registrar aprovechamiento" />

            <form onSubmit={enviar} className="grid gap-6 lg:grid-cols-3">
                <Card className="lg:col-span-2">
                    <CardHeader>
                        <CardTitle>Datos del otorgamiento</CardTitle>
                    </CardHeader>

                    <CardContent className="space-y-5">
                        <Campo etiqueta="Pescador" obligatorio error={form.errors.beneficiario_id}>
                            <BuscadorBeneficiario
                                seleccionado={persona}
                                onSeleccionar={elegirPersona}
                                ayuda="Tiene que estar en el padrón. Si es la primera vez que viene, regístrelo antes."
                            />
                        </Campo>

                        <Campo
                            etiqueta="Escala"
                            htmlFor="tarifa_sireb"
                            error={form.errors.tarifa_sireb}
                            ayuda="Las tarifas de Recaudaciones (SIREB): de cada una salen el monto y los kilos."
                            obligatorio
                        >
                            <Select
                                id="tarifa_sireb"
                                value={form.data.tarifa_sireb}
                                onChange={(e) => form.setData('tarifa_sireb', e.target.value)}
                                aria-invalid={Boolean(form.errors.tarifa_sireb)}
                            >
                                <option value="">Elija la escala…</option>
                                {escala.map((t) => (
                                    <option key={t.id} value={t.id} disabled={!t.activa || !(Number(t.rango_hasta) > 0)}>
                                        {/* Solo la escala: el precio se ve en «Lo que se va a registrar». */}
                                        {t.etiqueta}
                                        {!t.activa
                                            ? ' (tarifa no disponible)'
                                            : !(Number(t.rango_hasta) > 0)
                                              ? ' (sin rango en SIREB)'
                                              : ''}
                                    </option>
                                ))}
                            </Select>
                        </Campo>

                        {/*
                            EL RENGLÓN QUE FALTABA DEL TALONARIO.
                            El papel dice «Documento de Identidad: ___ Tipo de
                            Embarcación: ___», y el sistema no lo guardaba: la
                            autorización impresa desde el panel decía MENOS que
                            la que se llena a mano.
                        */}
                        <Campo
                            etiqueta="Tipo de embarcación"
                            htmlFor="tipo_embarcacion"
                            error={form.errors.tipo_embarcacion}
                            ayuda="Como figura en el talonario. Va impreso en la Autorización de Pesca para Aprovechamiento Pesquero."
                            obligatorio
                            className="max-w-sm"
                        >
                            <Input
                                id="tipo_embarcacion"
                                list="tipos-de-embarcacion"
                                value={form.data.tipo_embarcacion}
                                onChange={(e) => form.setData('tipo_embarcacion', e.target.value)}
                                aria-invalid={Boolean(form.errors.tipo_embarcacion)}
                                placeholder="Canoa, peque-peque, bote…"
                                maxLength={120}
                            />

                            <datalist id="tipos-de-embarcacion">
                                {TIPOS_DE_EMBARCACION.map((t) => (
                                    <option key={t} value={t} />
                                ))}
                            </datalist>
                        </Campo>
                    </CardContent>
                </Card>

                {/* ------------------------------------------------ Consecuencias */}
                <Card className="h-fit">
                    <CardHeader>
                        <CardTitle>Lo que se va a registrar</CardTitle>
                    </CardHeader>

                    <CardContent className="space-y-4">
                        {/* Solo se muestra: la fecha la pone el servidor al registrar. */}
                        <div>
                            <p className="text-xs uppercase tracking-wide text-muted-foreground">
                                Fecha de solicitud
                            </p>
                            <p className="font-medium">{fecha(new Date())}</p>
                        </div>

                        {tramo === null ? (
                            <p className="flex items-start gap-2 text-sm text-muted-foreground">
                                <Info className="mt-0.5 size-4 shrink-0" />
                                Elija la escala para ver cuánto se cobra.
                            </p>
                        ) : (
                            <>
                                <div>
                                    <p className="text-xs uppercase tracking-wide text-muted-foreground">Escala</p>
                                    <p className="font-medium">{tramo.etiqueta}</p>
                                </div>

                                {kilos > 0 && (
                                    <div>
                                        <p className="text-xs uppercase tracking-wide text-muted-foreground">
                                            Volumen autorizado
                                        </p>
                                        <p className="text-3xl font-semibold tabular-nums">
                                            {kilos}
                                            <span className="ml-1 text-base font-normal text-muted-foreground">kg</span>
                                        </p>
                                    </div>
                                )}

                                {form.data.tipo_embarcacion.trim() !== '' && (
                                    <div>
                                        <p className="text-xs uppercase tracking-wide text-muted-foreground">
                                            Embarcación
                                        </p>
                                        <p className="font-medium">{form.data.tipo_embarcacion}</p>
                                    </div>
                                )}

                                <div>
                                    <p className="text-xs uppercase tracking-wide text-muted-foreground">
                                        A cobrar
                                    </p>
                                    <p className="text-3xl font-semibold tabular-nums">
                                        {bs(tramo.monto, institucion.moneda)}
                                    </p>
                                </div>
                            </>
                        )}

                        <Button
                            type="submit"
                            disabled={form.processing || persona === null || tramo === null || kilos <= 0}
                            className="w-full"
                        >
                            {/* REGISTRAR y no «otorgar»: lo que se crea es un
                                expediente PENDIENTE, y el cupo queda otorgado
                                recién con la firma. */}
                            Registrar
                        </Button>
                    </CardContent>
                </Card>
            </form>
        </LayoutPanel>
    );
}
