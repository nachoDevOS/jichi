import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, ExternalLink, LoaderCircle, Save } from 'lucide-react';
import { type FormEvent } from 'react';
import { CampoPagos } from '@/components/panel/tramites/campo-pagos';
import { CampoRequisito } from '@/components/panel/tramites/campo-requisito';
import { Button } from '@/components/ui/button';
import { Campo } from '@/components/ui/campo';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useArchivos } from '@/hooks/use-archivos';
import LayoutPanel from '@/layouts/layout-panel';
import type {
    CatalogosCedulaPescador,
    FormularioCorreccionTramite,
    TramiteDetalle,
} from '@/types/tramites';

/**
 * ============================================================================
 *  CORREGIR UN TRÁMITE EN CURSO
 * ============================================================================
 *
 * Lo que se equivoca de verdad en ventanilla: un monto mal anotado, un número
 * de transacción cambiado, un escaneo que salió ilegible.
 *
 * ----------------------------------------------------------------------------
 *  ESTA PANTALLA NO REPITE EL ALTA
 * ----------------------------------------------------------------------------
 *
 * No están el solicitante, ni el servicio, ni el nombre, ni el domicilio, ni el
 * N° de registro. No es que falten: es que corregir esas cosas acá sería
 * corregirlas en el lugar equivocado.
 *
 *   - cambiar el solicitante o el servicio no es corregir un trámite, es otro
 *     trámite
 *   - nombre, cédula y domicilio son de la PERSONA y viven en su ficha; si se
 *     pudieran cambiar por trámite, la misma persona terminaría con datos
 *     distintos en cada credencial
 *   - el registro lo asigna el sistema, único; dejarlo editable sería devolver
 *     el problema que ese código vino a resolver
 *
 * Es la misma regla que sostiene el alta, y acá se sostiene igual.
 *
 * ----------------------------------------------------------------------------
 *  LOS PAPELES SON OPCIONALES ACÁ
 * ----------------------------------------------------------------------------
 *
 * En el alta son obligatorios porque no existe nada. Acá, no adjuntar nada
 * significa «dejá el que ya está», que es el caso normal: se corrige un monto y
 * no se vuelven a escanear los papeles. Cada uno muestra un enlace para abrir
 * el que está cargado, porque para decidir si hay que reemplazarlo primero hay
 * que poder mirarlo.
 */
interface Props {
    tramite: TramiteDetalle;
    catalogos: CatalogosCedulaPescador;
}

export default function EditarTramite({ tramite, catalogos }: Props) {
    const archivos = useArchivos();

    const esCedula = tramite.tipo.codigo === 'CAP';

    const form = useForm<FormularioCorreccionTramite>({
        asociacion: String(tramite.datos_adicionales.asociacion ?? ''),
        capacidad_kg: String(tramite.datos_adicionales.capacidad_kg ?? ''),
        observaciones: tramite.observaciones ?? '',

        certificacion_asociacion: null,
        copia_ci: null,

        /*
         * Los pagos arrancan con los que ya están. `archivo_actual` lleva la
         * ruta del comprobante guardado: si el operador solo corrige un monto,
         * el pago conserva su papel sin tener que volver a adjuntarlo.
         */
        pagos: tramite.pagos.map((p) => ({
            forma: p.forma ?? catalogos.formas_pago[0]?.value ?? 'transferencia',
            nro_transaccion: p.nro_transaccion ?? '',
            banco: p.banco ?? '',
            monto: String(p.monto),
            comprobante: null,
            archivo_actual: p.archivo ?? '',
            url_actual: p.url,
        })),
    });

    const { data, setData, errors, processing } = form;

    /*
     * Un pago está completo si tiene monto, número —cuando la forma lo pide— y
     * un comprobante: el que ya estaba o uno nuevo. Es la misma regla que
     * comprueba el servidor; acá solo apaga el botón.
     */
    const pagosCompletos =
        data.pagos.length > 0 &&
        data.pagos.every((pago) => {
            const forma = catalogos.formas_pago.find((f) => f.value === pago.forma);
            const pideReferencia = forma?.requiere_referencia ?? true;

            return (
                (pago.comprobante !== null || Boolean(pago.archivo_actual)) &&
                Number(pago.monto) > 0 &&
                (!pideReferencia || pago.nro_transaccion.trim() !== '')
            );
        });

    function enviar(e: FormEvent) {
        e.preventDefault();

        if (!pagosCompletos) {
            return;
        }

        /*
         * PUT es el verbo para reemplazar el registro, pero PHP no sabe leer
         * archivos en una petición PUT: solo los procesa en POST. Se manda un
         * POST con `_method: 'put'` y Laravel lo trata como PUT. Es el mismo
         * truco que usa el formulario de solicitantes.
         */
        form.transform((datos) => ({ ...datos, _method: 'put' }));
        form.post(route('tramites.update', tramite.id), { forceFormData: true });
    }

    return (
        <LayoutPanel
            titulo={`Corregir el trámite N° ${tramite.id}`}
            descripcion={`${tramite.tipo.nombre} · ${tramite.solicitante.nombreCompleto}`}
            acciones={
                <Link href={route('tramites.show', tramite.id)}>
                    <Button variant="outline">
                        <ArrowLeft className="size-4" />
                        Volver a la ficha
                    </Button>
                </Link>
            }
        >
            <Head title={`Corregir el trámite N° ${tramite.id}`} />

            <form onSubmit={enviar} className="max-w-3xl space-y-4">
                {esCedula && (
                    <Card>
                        <CardContent className="space-y-4 pt-6">
                            <p className="text-sm font-semibold">Datos del servicio</p>

                            <Campo
                                etiqueta="Asociación"
                                htmlFor="asociacion"
                                error={errors.asociacion}
                            >
                                <Input
                                    id="asociacion"
                                    list="asociaciones-pesca"
                                    value={data.asociacion}
                                    onChange={(e) => setData('asociacion', e.target.value)}
                                    placeholder="SOC. IBARE - MAMORÉ"
                                />
                                <datalist id="asociaciones-pesca">
                                    {catalogos.asociaciones.map((a) => (
                                        <option key={a} value={a} />
                                    ))}
                                </datalist>
                            </Campo>

                            <Campo
                                etiqueta="Cupo autorizado (Kg)"
                                htmlFor="capacidad_kg"
                                error={errors.capacidad_kg}
                            >
                                <Input
                                    id="capacidad_kg"
                                    type="number"
                                    min="0"
                                    value={data.capacidad_kg}
                                    onChange={(e) => setData('capacidad_kg', e.target.value)}
                                    placeholder="600"
                                    className="sm:max-w-40"
                                />
                            </Campo>
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardContent className="space-y-4 pt-6">
                        <div className="space-y-1">
                            <p className="text-sm font-semibold">Requisitos</p>
                            <p className="text-xs text-muted-foreground">
                                Adjunte un papel solo si hay que reemplazarlo. Lo que no se
                                toque queda como está.
                            </p>
                        </div>

                        {tramite.requisitos.map((r) => (
                            <div key={r.campo} className="space-y-2">
                                <CampoRequisito
                                    id={r.campo}
                                    etiqueta={r.etiqueta}
                                    ayuda={`Solo si hay que reemplazarlo. PDF o foto, ${archivos.ayudaPeso}.`}
                                    error={errors[r.campo as keyof typeof errors]}
                                    archivo={
                                        data[
                                            r.campo as
                                                | 'certificacion_asociacion'
                                                | 'copia_ci'
                                        ]
                                    }
                                    onCambio={(archivo) =>
                                        setData(
                                            r.campo as
                                                | 'certificacion_asociacion'
                                                | 'copia_ci',
                                            archivo,
                                        )
                                    }
                                />

                                {/* Para decidir si hay que reemplazarlo, primero
                                    hay que poder mirarlo. */}
                                <a
                                    href={r.url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="flex items-center gap-1.5 text-xs text-muted-foreground hover:text-foreground"
                                >
                                    <ExternalLink className="size-3.5 shrink-0" />
                                    Ver el que está cargado
                                </a>
                            </div>
                        ))}
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="space-y-4 pt-6">
                        <CampoPagos
                            pagos={data.pagos}
                            formas={catalogos.formas_pago}
                            montoTasa={tramite.monto_total}
                            errores={errors as unknown as Record<string, string>}
                            onCambio={(pagos) => setData('pagos', pagos)}
                        />
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="space-y-4 pt-6">
                        <Campo
                            etiqueta="Observaciones"
                            htmlFor="observaciones"
                            error={errors.observaciones}
                            ayuda="Uso interno: no se imprime en el documento."
                        >
                            <Textarea
                                id="observaciones"
                                rows={3}
                                value={data.observaciones}
                                onChange={(e) => setData('observaciones', e.target.value)}
                            />
                        </Campo>
                    </CardContent>
                </Card>

                <div className="flex flex-wrap items-center justify-end gap-3">
                    <Link href={route('tramites.show', tramite.id)}>
                        <Button type="button" variant="ghost">
                            Cancelar
                        </Button>
                    </Link>

                    <Button type="submit" disabled={processing || !pagosCompletos}>
                        {processing ? (
                            <LoaderCircle className="size-4 animate-spin" />
                        ) : (
                            <Save className="size-4" />
                        )}
                        Guardar correcciones
                    </Button>
                </div>
            </form>
        </LayoutPanel>
    );
}
