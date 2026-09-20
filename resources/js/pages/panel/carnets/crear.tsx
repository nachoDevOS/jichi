import { Head, useForm, usePage } from '@inertiajs/react';
import { Info, TriangleAlert } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { BuscadorBeneficiario } from '@/components/panel/comunes/buscador-beneficiario';
import { Button } from '@/components/ui/button';
import { Campo } from '@/components/ui/campo';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import LayoutPanel from '@/layouts/layout-panel';
import { bs } from '@/lib/utils';
import type { OpcionEnum, PageProps, TipoActor } from '@/types';
import type { BeneficiarioSugerido } from '@/types/beneficiarios';
import type { AsociacionElegible, CupoVigente, TipoElegible } from '@/types/carnets';

/**
 *  EMITIR UNA CREDENCIAL — paso 3 del flujo
 */
export default function CrearCarnet({
    beneficiario,
    cupoVigente,
    asociaciones,
    tipos,
    actores,
}: {
    beneficiario: BeneficiarioSugerido | null;
    cupoVigente: CupoVigente | null;
    asociaciones: AsociacionElegible[];
    tipos: TipoElegible[];
    actores: OpcionEnum[];
}) {
    const { institucion } = usePage<PageProps>().props;
    const [persona, setPersona] = useState<BeneficiarioSugerido | null>(beneficiario);

    const form = useForm({
        beneficiario_id: beneficiario?.id ?? null,
        asociacion_id: '',
        tipo_carnet_id: '',
        tipo_actor: '' as TipoActor | '',
        fecha_emision: new Date().toISOString().slice(0, 10),
    });

    const tipo = tipos.find((t) => String(t.id) === String(form.data.tipo_carnet_id)) ?? null;

    /*
     * El aviso solo aplica a la persona que llegó preseleccionada: el cupo se
     * consulta en el servidor al abrir la pantalla. Si el operador cambia de
     * persona con el buscador, no se sabe si la nueva tiene cupo, así que no se
     * afirma nada — y el servidor rechaza igual si hace falta.
     */
    const esPescador = form.data.tipo_actor === 'pescador';
    const faltaCupo = esPescador && beneficiario !== null && persona?.id === beneficiario.id && cupoVigente === null;

    function elegirPersona(elegida: BeneficiarioSugerido | null) {
        setPersona(elegida);
        form.setData('beneficiario_id', elegida?.id ?? null);
        // Se limpia el error anterior: si venía de «ya tiene carnet vigente»,
        // dejarlo colgado bajo otra persona diría una mentira.
        form.clearErrors('beneficiario_id');
    }

    function enviar(e: FormEvent) {
        e.preventDefault();
        form.post(route('carnets.store'));
    }

    return (
        <LayoutPanel
            titulo="Emitir carnet"
            descripcion="Paso 3 del flujo: la llave anual de la que cuelgan las faenas y las guías."
        >
            <Head title="Emitir carnet" />

            <form onSubmit={enviar} className="grid gap-6 lg:grid-cols-3">
                <Card className="lg:col-span-2">
                    <CardHeader>
                        <CardTitle>Datos de la credencial</CardTitle>
                    </CardHeader>

                    <CardContent className="space-y-5">
                        <Campo etiqueta="Titular" obligatorio error={form.errors.beneficiario_id}>
                            <BuscadorBeneficiario
                                seleccionado={persona}
                                onSeleccionar={elegirPersona}
                                ayuda="Tiene que estar en el padrón. Si es la primera vez que viene, regístrelo antes."
                            />
                        </Campo>

                        <Campo
                            etiqueta="Actividad que habilita"
                            htmlFor="tipo_actor"
                            error={form.errors.tipo_actor}
                            ayuda="Decide qué puede emitir el carnet: el pescador saca faenas, el comercializador saca guías. Solo el pescador lleva cupo."
                            obligatorio
                        >
                            <Select
                                id="tipo_actor"
                                value={form.data.tipo_actor}
                                onChange={(e) => form.setData('tipo_actor', e.target.value as TipoActor)}
                                aria-invalid={Boolean(form.errors.tipo_actor)}
                            >
                                <option value="">Elija la actividad…</option>
                                {actores.map((o) => (
                                    <option key={o.value} value={o.value}>
                                        {o.label}
                                    </option>
                                ))}
                            </Select>
                        </Campo>

                        {faltaCupo && (
                            <p className="flex items-start gap-2 rounded-md bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-500/10 dark:text-amber-200">
                                <TriangleAlert className="mt-0.5 size-4 shrink-0" />
                                <span>
                                    Esta persona <strong>no tiene un cupo de pesca vigente</strong>. El
                                    carnet de pescador imprime el volumen autorizado, así que hay que
                                    otorgarle el aprovechamiento antes. El servidor va a rechazar la
                                    emisión.
                                </span>
                            </p>
                        )}

                        <Campo
                            etiqueta="Tipo de carnet"
                            htmlFor="tipo_carnet_id"
                            error={form.errors.tipo_carnet_id}
                            ayuda="Es el nombre del documento y su arancel. No decide lo que el carnet habilita: eso lo dice la actividad."
                            obligatorio
                        >
                            <Select
                                id="tipo_carnet_id"
                                value={form.data.tipo_carnet_id}
                                onChange={(e) => form.setData('tipo_carnet_id', e.target.value)}
                                aria-invalid={Boolean(form.errors.tipo_carnet_id)}
                            >
                                <option value="">Elija un tipo…</option>
                                {tipos.map((t) => (
                                    <option key={t.id} value={t.id}>
                                        {t.nombre} — {bs(t.precio_bs, institucion.moneda)}
                                    </option>
                                ))}
                            </Select>
                        </Campo>

                        <Campo
                            etiqueta="Asociación"
                            htmlFor="asociacion_id"
                            error={form.errors.asociacion_id}
                            ayuda="La que certifica al titular. Se imprime en el plástico."
                            obligatorio
                        >
                            <Select
                                id="asociacion_id"
                                value={form.data.asociacion_id}
                                onChange={(e) => form.setData('asociacion_id', e.target.value)}
                                aria-invalid={Boolean(form.errors.asociacion_id)}
                            >
                                <option value="">Elija una asociación…</option>
                                {asociaciones.map((a) => (
                                    <option key={a.id} value={a.id}>
                                        {a.sigla ? `${a.sigla} — ${a.nombre}` : a.nombre}
                                    </option>
                                ))}
                            </Select>
                        </Campo>

                        <Campo
                            etiqueta="Fecha de emisión"
                            htmlFor="fecha_emision"
                            error={form.errors.fecha_emision}
                            ayuda="Puede ser pasada, para poner al día lo emitido en papel. Futura no: el carnet vence con la gestión."
                            obligatorio
                            className="max-w-xs"
                        >
                            <Input
                                id="fecha_emision"
                                type="date"
                                value={form.data.fecha_emision}
                                onChange={(e) => form.setData('fecha_emision', e.target.value)}
                                aria-invalid={Boolean(form.errors.fecha_emision)}
                            />
                        </Campo>
                    </CardContent>
                </Card>

                {/* ------------------------------------------------ Consecuencias */}
                <Card className="h-fit">
                    <CardHeader>
                        <CardTitle>Lo que se va a emitir</CardTitle>
                    </CardHeader>

                    <CardContent className="space-y-4">
                        {form.data.tipo_actor === '' ? (
                            <p className="flex items-start gap-2 text-sm text-muted-foreground">
                                <Info className="mt-0.5 size-4 shrink-0" />
                                Elija la actividad para ver qué va a habilitar la credencial.
                            </p>
                        ) : (
                            <div className="space-y-1 text-sm">
                                <p className="text-xs uppercase tracking-wide text-muted-foreground">
                                    Habilita
                                </p>
                                <p className="font-medium">
                                    {esPescador ? 'Emitir permisos de faena' : 'Emitir guías de movimiento'}
                                </p>

                                <p className="text-muted-foreground">
                                    {esPescador
                                        ? cupoVigente
                                            ? `Con el cupo vigente: ${cupoVigente.saldo_kg} de ${cupoVigente.volumen_total_kg} kg disponibles.`
                                            : 'Necesita una bolsa madre vigente, que es de donde salen los kilos de cada faena.'
                                        : 'No lleva cupo: la comercialización no se autoriza por volumen.'}
                                </p>
                            </div>
                        )}

                        {tipo && (
                            <div>
                                <p className="text-xs uppercase tracking-wide text-muted-foreground">
                                    A cobrar
                                </p>
                                <p className="text-2xl font-semibold tabular-nums">
                                    {bs(tipo.precio_bs, institucion.moneda)}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    Se puede pagar en cuotas. El carnet queda emitido igual, con saldo
                                    pendiente hasta cubrirlo.
                                </p>
                            </div>
                        )}

                        <Button
                            type="submit"
                            disabled={
                                form.processing ||
                                persona === null ||
                                form.data.tipo_actor === '' ||
                                form.data.tipo_carnet_id === '' ||
                                form.data.asociacion_id === ''
                            }
                            className="w-full"
                        >
                            Emitir carnet
                        </Button>
                    </CardContent>
                </Card>
            </form>
        </LayoutPanel>
    );
}
