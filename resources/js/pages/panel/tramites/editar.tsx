import { Head, useForm, usePage } from '@inertiajs/react';
import { FileText } from 'lucide-react';
import type { FormEvent } from 'react';
import {
    FormularioDeposito,
    ListaDepositos,
    ResumenDepositos,
} from '@/components/panel/tramites/depositos';
import { Button } from '@/components/ui/button';
import { Campo } from '@/components/ui/campo';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { SelectorArchivo } from '@/components/ui/selector-archivo';
import { Textarea } from '@/components/ui/textarea';
import { useArchivos } from '@/hooks/use-archivos';
import { usePermisos } from '@/hooks/use-permisos';
import LayoutPanel from '@/layouts/layout-panel';
import type { PageProps } from '@/types';
import type { FormularioEdicion, PagoDelTramite } from '@/types/tramites';

/**
 * ============================================================================
 *  EDITAR EL BORRADOR DEL EXPEDIENTE
 * ============================================================================
 *
 * Esta pantalla solo se abre en PENDIENTE. Una vez enviado a revisión el
 * expediente queda como se presentó: el recibo oficial ya salió con ese monto y
 * quien aprueba firma sobre estos papeles. Ver App\Enums\EstadoTramite.
 *
 * Se puede cambiar todo lo que es DATO del expediente: los dos papeles, la
 * asociación, el cupo en kilos y las observaciones. Y acá se cargan los
 * depósitos.
 *
 * ----------------------------------------------------------------------------
 *  EL RUBRO Y EL BENEFICIARIO NO SE TOCAN
 * ----------------------------------------------------------------------------
 *
 * Cambiarlos no sería corregir este expediente sino convertirlo en otro:
 *
 *   - el rubro define el COSTO, que ya se copió a `monto_requerido` y
 *     posiblemente ya se cobró;
 *   - el beneficiario define el CARNET del que cuelga el trámite, y con él la
 *     gestión.
 *
 * Por eso van arriba como datos de solo lectura, con el motivo escrito: que se
 * vean pero no se editen es más claro que esconderlos y que el operador los
 * busque. Si se pidió el rubro equivocado, lo que corresponde es eliminar el
 * expediente y presentar uno nuevo.
 *
 * Los dos adjuntos son OPCIONALES acá, a diferencia del alta: se reemplaza el
 * que salió ilegible y el otro se deja como está.
 */
export default function EditarTramite({
    tramite,
    pagos,
}: {
    tramite: {
        id: number;
        rubro: string;
        carnet_gestion: number;
        beneficiario: string;
        observaciones: string | null;
        asociacion: string | null;
        capacidad_kg: number | null;
        /** Si la actividad de este expediente se autoriza por volumen. */
        requiere_capacidad: boolean;
        ci_file_url: string | null;
        cert_asociacion_file_url: string | null;
        monto_requerido: number;
        monto_pagado: number;
        saldo_pendiente: number;
        admite_pagos: boolean;
    };
    pagos: PagoDelTramite[];
}) {
    const { ayudaPeso } = useArchivos();
    const { institucion } = usePage<PageProps>().props;
    const { puede } = usePermisos();

    const form = useForm<FormularioEdicion>({
        ciFile: null,
        certAsociacionFile: null,
        asociacion: tramite.asociacion ?? '',
        // A texto: el input lo devuelve así, y '' distingue «vacío» de cero.
        capacidad_kg: tramite.capacidad_kg?.toString() ?? '',
        observaciones: tramite.observaciones ?? '',
        // Campo oculto que convierte el POST en un PUT del lado de Laravel. Hace
        // falta porque el formulario lleva archivos, y router.put() no los manda.
        _method: 'put',
    });

    function enviar(e: FormEvent) {
        e.preventDefault();
        form.post(route('tramites.update', tramite.id), { forceFormData: true });
    }

    return (
        <LayoutPanel
            titulo={`Editar trámite #${tramite.id}`}
            descripcion={`${tramite.beneficiario} · ${tramite.rubro} · carnet ${tramite.carnet_gestion}`}
        >
            <Head title={`Editar trámite #${tramite.id}`} />

            {/*
                DOS FORMULARIOS HERMANOS, NO UNO ADENTRO DEL OTRO.

                Los papeles se guardan con un PUT al trámite; cada depósito se
                guarda con un POST propio a `pagos.store`. Anidar formularios es
                HTML inválido —el navegador cierra el de afuera al abrir el de
                adentro— y ninguno de los dos enviaría bien.

                Por eso el <div> envuelve y cada <form> es independiente: se puede
                cargar un depósito sin guardar los papeles, y al revés.
            */}
            {/* Mismo ancho que el formulario de alta y que los de beneficiario:
                max-w-7xl. Ver el comentario de pages/panel/tramites/crear.tsx
                sobre por qué hay un tope y no ancho libre. */}
            <div className="mx-auto max-w-7xl space-y-6">
                {/*
                    EL FORM LLEVA `id` PORQUE SU BOTÓN VIVE AFUERA.

                    «Guardar cambios» está al final de la página, debajo de la
                    tarjeta de depósitos, y esa tarjeta es OTRO formulario. Un
                    <button type="submit"> suelto no pertenece a ningún form; el
                    atributo `form` lo ata a este por id, que es la forma que el
                    estándar HTML da para exactamente esto.
                */}
                <form id="form-tramite" onSubmit={enviar} className="space-y-6">
                    {/*
                        LO QUE NO SE PUEDE CAMBIAR, PERO SÍ SE VE.

                        Mostrarlo en gris y explicar por qué es más claro que
                        esconderlo: el operador confirma que está editando el
                        expediente correcto y entiende de una por qué no hay
                        dónde tocar el rubro.
                    */}
                    <Card>
                        <CardContent className="grid gap-3 pt-5 sm:grid-cols-3">
                            <Fijo etiqueta="Beneficiario" valor={tramite.beneficiario} />
                            <Fijo etiqueta="Rubro solicitado" valor={tramite.rubro} />
                            <Fijo etiqueta="Gestión" valor={String(tramite.carnet_gestion)} />

                            <p className="text-xs text-muted-foreground sm:col-span-3">
                                El rubro y el beneficiario no se editan: cambiarlos sería
                                otro trámite y no una corrección de este —el rubro define
                                el costo ya cobrado, y el beneficiario, el carnet del que
                                cuelga—. Si se pidió el rubro equivocado, elimine el
                                expediente y presente uno nuevo.
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Datos del expediente</CardTitle>
                        </CardHeader>

                        <CardContent className="grid gap-4 sm:grid-cols-2">
                            <Campo
                                etiqueta="Asociación a la que pertenece"
                                htmlFor="asociacion"
                                error={form.errors.asociacion}
                                ayuda="Tal como figura en el certificado. Se imprime en el carnet."
                            >
                                <Input
                                    id="asociacion"
                                    value={form.data.asociacion}
                                    onChange={(e) => form.setData('asociacion', e.target.value)}
                                />
                            </Campo>

                            {/*
                                Solo si la actividad se autoriza por volumen. El
                                rubro NO se puede cambiar al editar, así que acá
                                alcanza con mirar el del expediente. Ver
                                CarnetImpresionController::renglonRubro().
                            */}
                            {tramite.requiere_capacidad && (
                                <Campo
                                    etiqueta="Capacidad autorizada (Kg)"
                                    htmlFor="capacidad_kg"
                                    error={form.errors.capacidad_kg}
                                    ayuda="Se imprime en el carnet, junto al rubro."
                                >
                                    <Input
                                        id="capacidad_kg"
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        value={form.data.capacidad_kg}
                                        onChange={(e) => form.setData('capacidad_kg', e.target.value)}
                                    />
                                </Campo>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Respaldos</CardTitle>
                        </CardHeader>

                        <CardContent className="grid gap-4 sm:grid-cols-2">
                            <div className="space-y-2">
                                <Campo
                                    etiqueta="Fotocopia de carnet de identidad"
                                    htmlFor="ciFile"
                                    ayuda={`Déjelo vacío para conservar el actual. PDF o imagen, ${ayudaPeso}.`}
                                >
                                    <SelectorArchivo
                                        id="ciFile"
                                        archivo={form.data.ciFile}
                                        onElegir={(a) => form.setData('ciFile', a)}
                                        error={form.errors.ciFile}
                                    />
                                </Campo>

                                <EnlaceActual url={tramite.ci_file_url} />
                            </div>

                            <div className="space-y-2">
                                <Campo
                                    etiqueta="Certificado de la asociación"
                                    htmlFor="certAsociacionFile"
                                    ayuda={`Déjelo vacío para conservar el actual. PDF o imagen, ${ayudaPeso}.`}
                                >
                                    <SelectorArchivo
                                        id="certAsociacionFile"
                                        archivo={form.data.certAsociacionFile}
                                        onElegir={(a) => form.setData('certAsociacionFile', a)}
                                        error={form.errors.certAsociacionFile}
                                    />
                                </Campo>

                                <EnlaceActual url={tramite.cert_asociacion_file_url} />
                            </div>

                            <Campo
                                etiqueta="Observaciones"
                                htmlFor="observaciones"
                                error={form.errors.observaciones}
                                className="sm:col-span-2"
                            >
                                <Textarea
                                    id="observaciones"
                                    rows={3}
                                    value={form.data.observaciones}
                                    onChange={(e) => form.setData('observaciones', e.target.value)}
                                />
                            </Campo>
                        </CardContent>
                    </Card>

                </form>

                {/* ==================================================== Depósitos
                    ACÁ es donde se cargan. La ficha del trámite los muestra pero
                    no deja cargarlos: tenerlo en las dos pantallas hacía que no
                    quedara claro cuál era el lugar. */}
                <Card>
                    <CardHeader>
                        <CardTitle>Depósitos</CardTitle>
                    </CardHeader>

                    <CardContent className="space-y-4">
                        <ResumenDepositos
                            montoRequerido={tramite.monto_requerido}
                            montoPagado={tramite.monto_pagado}
                            saldoPendiente={tramite.saldo_pendiente}
                            moneda={institucion.moneda}
                        />

                        <ListaDepositos
                            pagos={pagos}
                            moneda={institucion.moneda}
                            vacio="Cargue las boletas acá abajo."
                        />

                        {tramite.admite_pagos && puede('pagos.registrar') && (
                            <FormularioDeposito
                                tramiteId={tramite.id}
                                moneda={institucion.moneda}
                            />
                        )}
                    </CardContent>
                </Card>

                {/*
                    GUARDAR, AL FINAL DE TODO.

                    Va acá y no entre las tarjetas porque el operador baja
                    leyendo: primero los datos que no se tocan, después los que
                    sí, los papeles, y por último los depósitos. El botón cierra
                    ese recorrido.

                    `form="form-tramite"` lo ata al formulario de arriba aunque
                    esté fuera de él. Sin ese atributo no enviaría nada: entre el
                    botón y su form está la tarjeta de depósitos, que es otro
                    formulario, y anidarlos sería HTML inválido.

                    Los depósitos tienen su propio botón adentro de su tarjeta:
                    se registran de a uno y no dependen de este.
                */}
                <div className="flex justify-end border-t border-border pt-4">
                    <Button type="submit" form="form-tramite" disabled={form.processing}>
                        {form.processing ? 'Guardando…' : 'Guardar cambios'}
                    </Button>
                </div>
            </div>
        </LayoutPanel>
    );
}

/** Un dato del expediente que se muestra pero no se edita. */
function Fijo({ etiqueta, valor }: { etiqueta: string; valor: string }) {
    return (
        <div>
            <p className="text-xs uppercase tracking-wide text-muted-foreground">{etiqueta}</p>
            <p className="font-medium">{valor}</p>
        </div>
    );
}

function EnlaceActual({ url }: { url: string | null }) {
    if (!url) {
        return <p className="text-xs text-muted-foreground">No hay archivo cargado.</p>;
    }

    return (
        <a
            href={url}
            target="_blank"
            rel="noreferrer"
            className="inline-flex items-center gap-1.5 text-xs text-primary hover:underline"
        >
            <FileText className="size-3.5" />
            Ver el archivo actual
        </a>
    );
}
