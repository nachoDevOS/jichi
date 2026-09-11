import { useForm } from '@inertiajs/react';
import { LoaderCircle, Save } from 'lucide-react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Campo } from '@/components/ui/campo';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import type {
    FormularioPermisoFaena,
    SolicitanteDelTramite,
    TipoTramiteOpcion,
} from '@/types/tramites';

/**
 * ============================================================================
 *  FORMULARIO — PERMISO POR FAENA
 * ============================================================================
 *
 * Cada campo de acá es un renglón del talonario del SEDAG - BENI. Se respetó
 * el orden del papel a propósito: el operador carga mirando el formulario que
 * tiene en la mano, y si el orden no coincide se equivoca de renglón.
 *
 * En el modelo definitivo, todo esto NO son columnas de `tramites`: van en
 * `datos_adicionales`, la columna jsonb que la migración dejó preparada para
 * los campos propios de cada tipo de trámite.
 */
export function FormularioPermisoFaena({
    tipo,
    solicitante,
    onCambio,
}: {
    tipo: TipoTramiteOpcion;
    solicitante: SolicitanteDelTramite;
    /** Avisa al padre cada vez que cambia algo, para la vista previa en vivo. */
    onCambio: (datos: FormularioPermisoFaena) => void;
}) {
    const form = useForm<FormularioPermisoFaena>({
        tipo: tipo.codigo,
        solicitante: solicitante.id,
        nro_recibo: '',
        embarcacion: '',
        propietario: '',
        comandante: '',
        matricula_naval: '',
        kardex: '',
        region_desde: '',
        region_hasta: '',
        fecha_salida: '',
        fecha_desembarque: '',
        cantidad_kg: '',
        observaciones: '',
    });

    const { data, setData, errors, processing } = form;

    /*
     * Un único ayudante para todos los campos: actualiza el formulario y avisa
     * al padre en el mismo paso. Sin esto habría que repetir las dos llamadas
     * en cada onChange, y basta olvidarse una para que la vista previa deje de
     * seguir a ese campo.
     */
    function cambiar<C extends keyof FormularioPermisoFaena>(
        campo: C,
        valor: FormularioPermisoFaena[C],
    ) {
        // Se arma el objeto completo una sola vez y se usa para las dos cosas.
        // Pasarle a setData el formulario entero, en vez de (clave, valor),
        // evita además el problema de tipos de la firma genérica de Inertia.
        const siguiente = { ...data, [campo]: valor };

        setData(siguiente);
        onCambio(siguiente);
    }

    function enviar(e: FormEvent) {
        e.preventDefault();

        // El controlador no guarda nada: responde con un aviso. Ver
        // TramiteController::store().
        form.post(route('tramites.store'));
    }

    return (
        <form onSubmit={enviar} className="space-y-4">
            <Card>
                <CardContent className="space-y-4 pt-6">
                    <p className="text-sm font-semibold">Datos de la faena</p>

                    <Campo etiqueta="N° de recibo" htmlFor="nro_recibo" error={errors.nro_recibo}>
                        <Input
                            id="nro_recibo"
                            value={data.nro_recibo}
                            onChange={(e) => cambiar('nro_recibo', e.target.value)}
                            placeholder="El del comprobante de caja"
                        />
                    </Campo>

                    <Campo
                        etiqueta="La embarcación"
                        htmlFor="embarcacion"
                        error={errors.embarcacion}
                        obligatorio
                    >
                        <Input
                            id="embarcacion"
                            value={data.embarcacion}
                            onChange={(e) => cambiar('embarcacion', e.target.value)}
                            placeholder="Nombre de la embarcación"
                        />
                    </Campo>

                    <Campo
                        etiqueta="De propiedad de"
                        htmlFor="propietario"
                        error={errors.propietario}
                        ayuda="Cuando el módulo esté conectado, este campo va a buscar en Solicitantes en vez de escribirse a mano."
                        obligatorio
                    >
                        <Input
                            id="propietario"
                            value={data.propietario}
                            onChange={(e) => cambiar('propietario', e.target.value)}
                            placeholder="Nombre del propietario"
                        />
                    </Campo>

                    <Campo
                        etiqueta="Comandante de barco"
                        htmlFor="comandante"
                        error={errors.comandante}
                    >
                        <Input
                            id="comandante"
                            value={data.comandante}
                            onChange={(e) => cambiar('comandante', e.target.value)}
                        />
                    </Campo>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <Campo
                            etiqueta="Matrícula Naval N°"
                            htmlFor="matricula_naval"
                            error={errors.matricula_naval}
                        >
                            <Input
                                id="matricula_naval"
                                value={data.matricula_naval}
                                onChange={(e) => cambiar('matricula_naval', e.target.value)}
                            />
                        </Campo>

                        <Campo etiqueta="N° Kardex" htmlFor="kardex" error={errors.kardex}>
                            <Input
                                id="kardex"
                                value={data.kardex}
                                onChange={(e) => cambiar('kardex', e.target.value)}
                            />
                        </Campo>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardContent className="space-y-4 pt-6">
                    <p className="text-sm font-semibold">Región y fechas</p>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <Campo
                            etiqueta="Pescar en la región desde"
                            htmlFor="region_desde"
                            error={errors.region_desde}
                        >
                            <Input
                                id="region_desde"
                                value={data.region_desde}
                                onChange={(e) => cambiar('region_desde', e.target.value)}
                                placeholder="Ej.: Río Mamoré"
                            />
                        </Campo>

                        <Campo etiqueta="Hasta" htmlFor="region_hasta" error={errors.region_hasta}>
                            <Input
                                id="region_hasta"
                                value={data.region_hasta}
                                onChange={(e) => cambiar('region_hasta', e.target.value)}
                                placeholder="Ej.: Puerto Varador"
                            />
                        </Campo>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <Campo
                            etiqueta="Fecha de salida"
                            htmlFor="fecha_salida"
                            error={errors.fecha_salida}
                        >
                            <Input
                                id="fecha_salida"
                                type="date"
                                value={data.fecha_salida}
                                onChange={(e) => cambiar('fecha_salida', e.target.value)}
                            />
                        </Campo>

                        <Campo
                            etiqueta="Fecha de desembarque"
                            htmlFor="fecha_desembarque"
                            error={errors.fecha_desembarque}
                        >
                            <Input
                                id="fecha_desembarque"
                                type="date"
                                value={data.fecha_desembarque}
                                onChange={(e) => cambiar('fecha_desembarque', e.target.value)}
                            />
                        </Campo>
                    </div>

                    <Campo
                        etiqueta="Cantidad autorizada de pescado extraído (Kg)"
                        htmlFor="cantidad_kg"
                        error={errors.cantidad_kg}
                    >
                        <Input
                            id="cantidad_kg"
                            type="number"
                            min="0"
                            step="0.01"
                            value={data.cantidad_kg}
                            onChange={(e) => cambiar('cantidad_kg', e.target.value)}
                        />
                    </Campo>

                    <Campo
                        etiqueta="Observaciones"
                        htmlFor="observaciones"
                        error={errors.observaciones}
                        ayuda="Uso interno: no se imprime en el permiso."
                    >
                        <Textarea
                            id="observaciones"
                            rows={3}
                            value={data.observaciones}
                            onChange={(e) => cambiar('observaciones', e.target.value)}
                        />
                    </Campo>
                </CardContent>
            </Card>

            <div className="flex justify-end">
                <Button type="submit" disabled={processing}>
                    {processing ? (
                        <LoaderCircle className="size-4 animate-spin" />
                    ) : (
                        <Save className="size-4" />
                    )}
                    Registrar permiso
                </Button>
            </div>
        </form>
    );
}
