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
import { bs } from '@/lib/utils';
import type { PageProps } from '@/types';
import type { TramoElegible } from '@/types/aprovechamientos';
import type { BeneficiarioSugerido } from '@/types/beneficiarios';

/**
 * Sugerencias del campo de embarcación, NO una lista cerrada.
 */
const TIPOS_DE_EMBARCACION = ['Canoa', 'Peque-peque', 'Bote', 'Chalana', 'Deslizador', 'Balsa'];

/**
 *  OTORGAR UNA BOLSA MADRE — paso 2 del flujo del pescador
 */
export default function CrearCupo({
    beneficiario,
    escala,
}: {
    /** Preseleccionado al llegar desde la ficha de una persona. */
    beneficiario: BeneficiarioSugerido | null;
    escala: TramoElegible[];
}) {
    const { institucion } = usePage<PageProps>().props;
    const [persona, setPersona] = useState<BeneficiarioSugerido | null>(beneficiario);

    const form = useForm({
        beneficiario_id: beneficiario?.id ?? null,
        categoria_aprov_id: '',
        tipo_embarcacion: '',
        // Lo normal es otorgar hoy. El servidor rechaza fechas futuras: el cupo
        // vence con la gestión, así que una del año que viene arrancaría vencida.
        fecha_solicitud: new Date().toISOString().slice(0, 10),
    });

    const tramo = escala.find((t) => String(t.id) === String(form.data.categoria_aprov_id)) ?? null;

    function elegirPersona(elegida: BeneficiarioSugerido | null) {
        setPersona(elegida);
        form.setData('beneficiario_id', elegida?.id ?? null);
        // Se limpia el error anterior: si venía de «ya tiene un cupo vigente»,
        // dejarlo colgado bajo otra persona diría una mentira.
        form.clearErrors('beneficiario_id');
    }

    function enviar(e: FormEvent) {
        e.preventDefault();
        form.post(route('aprovechamientos.store'));
    }

    // Sin escala cargada no hay nada que otorgar, y el formulario en blanco no
    // lo explicaría: mandaría al operador a buscar el error en otro lado.
    if (escala.length === 0) {
        return (
            <LayoutPanel titulo="Registrar aprovechamiento" descripcion="La bolsa madre del pescador.">
                <Head title="Registrar aprovechamiento" />

                <Card>
                    <EstadoVacio
                        icono={Waves}
                        titulo="La escala de aprovechamiento está vacía"
                        descripcion="No hay ningún tramo vigente, así que no se puede otorgar un cupo. Cárguela desde Catálogos › Escala."
                    />
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
                            etiqueta="Tramo de la escala"
                            htmlFor="categoria_aprov_id"
                            error={form.errors.categoria_aprov_id}
                            ayuda="Solo aparecen los tramos vigentes. De él salen los kilos y el monto."
                            obligatorio
                        >
                            <Select
                                id="categoria_aprov_id"
                                value={form.data.categoria_aprov_id}
                                onChange={(e) => form.setData('categoria_aprov_id', e.target.value)}
                                aria-invalid={Boolean(form.errors.categoria_aprov_id)}
                            >
                                <option value="">Elija un tramo…</option>
                                {escala.map((t) => (
                                    <option key={t.id} value={t.id}>
                                        {t.nro_escala} · {t.descripcion_kg} —{' '}
                                        {bs(t.valor_bs, institucion.moneda)}
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
                            ayuda="Como figura en el talonario. Va impreso en la autorización de pesca."
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

                        <Campo
                            etiqueta="Fecha de solicitud"
                            htmlFor="fecha_solicitud"
                            error={form.errors.fecha_solicitud}
                            ayuda="El día que la persona lo pidió. La fecha de otorgamiento la escribe el sistema al aprobarlo."
                            obligatorio
                            className="max-w-xs"
                        >
                            <Input
                                id="fecha_solicitud"
                                type="date"
                                value={form.data.fecha_solicitud}
                                onChange={(e) => form.setData('fecha_solicitud', e.target.value)}
                                aria-invalid={Boolean(form.errors.fecha_solicitud)}
                            />
                        </Campo>
                    </CardContent>
                </Card>

                {/* ------------------------------------------------ Consecuencias */}
                <Card className="h-fit">
                    <CardHeader>
                        <CardTitle>Lo que se va a registrar</CardTitle>
                    </CardHeader>

                    <CardContent className="space-y-4">
                        {tramo === null ? (
                            <p className="flex items-start gap-2 text-sm text-muted-foreground">
                                <Info className="mt-0.5 size-4 shrink-0" />
                                Elija un tramo para ver cuántos kilos se autorizan y cuánto se cobra.
                            </p>
                        ) : (
                            <>
                                <div>
                                    <p className="text-xs uppercase tracking-wide text-muted-foreground">
                                        Volumen autorizado
                                    </p>
                                    <p className="text-3xl font-semibold tabular-nums">
                                        {tramo.kilos_max}
                                        <span className="ml-1 text-base font-normal text-muted-foreground">
                                            kg
                                        </span>
                                    </p>
                                    {/*
                                        Se dice de dónde sale el número: es el TECHO
                                        del rango, no un promedio ni algo elegible.
                                        Sin esta línea, alguien va a preguntar por qué
                                        no puede escribir 350.
                                    */}
                                    <p className="text-xs text-muted-foreground">
                                        Es el techo del tramo ({tramo.kilos_min} – {tramo.kilos_max} kg).
                                    </p>
                                </div>

                                {/*
                                    EL RÉGIMEN SE VE ANTES DE OTORGAR: dice de dónde
                                    sale el valor —la progresión por kilos o una
                                    tasación fija por resolución—, que es lo que el
                                    pescador va a preguntar en el mostrador.
                                */}
                                <div>
                                    <p className="text-xs uppercase tracking-wide text-muted-foreground">
                                        Régimen
                                    </p>
                                    <p className="font-medium">{tramo.modalidad_etiqueta}</p>
                                    <p className="text-xs text-muted-foreground">
                                        {tramo.modalidad_descripcion}
                                    </p>
                                </div>

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
                                    <p className="text-2xl font-semibold tabular-nums">
                                        {bs(tramo.valor_bs, institucion.moneda)}
                                    </p>
                                    {/*
                                        Se dice a dónde lleva el botón. Decía que
                                        abría la CAJA, y hace rato que termina en
                                        la ficha: los depósitos se cargan ahí
                                        mismo, sin salir del expediente.
                                    */}
                                    <p className="text-xs text-muted-foreground">
                                        Al registrarlo queda PENDIENTE y se abre su ficha, donde se
                                        cargan los depósitos. Con el monto cubierto se envía a
                                        revisión, y recién con la firma autoriza a pescar.
                                    </p>
                                </div>
                            </>
                        )}

                        <Button
                            type="submit"
                            disabled={form.processing || persona === null || tramo === null}
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
