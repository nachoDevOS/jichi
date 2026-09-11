import { useForm } from '@inertiajs/react';
import { LoaderCircle, Save } from 'lucide-react';
import type { FormEvent } from 'react';
import { filaVacia, TablaProductosGuia } from '@/components/panel/tramites/tabla-productos-guia';
import { Button } from '@/components/ui/button';
import { Campo } from '@/components/ui/campo';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type {
    CatalogosGuiaTransporte,
    FilaProductoGuia,
    FormularioGuiaTransporte,
    TipoTramiteOpcion,
} from '@/types/tramites';
import type { SolicitanteDelTramite } from '@/types/tramites';

/**
 * ============================================================================
 *  FORMULARIO — GUÍA ÚNICA DE TRANSPORTE DE PRODUCTOS ICTÍCOLAS
 * ============================================================================
 *
 * Sigue las cuatro secciones del talonario, en el mismo orden y con los mismos
 * nombres: A interesado, B ubicación, C transporte, D productos. El operador
 * carga mirando el papel, así que el orden no es un detalle estético.
 *
 * Los números que aparecen en el formulario impreso (1 a 14) se conservan en
 * las etiquetas de las secciones para poder cruzar pantalla y papel de un
 * vistazo cuando algo no coincide.
 */
export function FormularioGuiaTransporte({
    tipo,
    solicitante,
    catalogos,
    onCambio,
}: {
    tipo: TipoTramiteOpcion;
    solicitante: SolicitanteDelTramite;
    catalogos: CatalogosGuiaTransporte;
    onCambio: (datos: FormularioGuiaTransporte) => void;
}) {
    const form = useForm<FormularioGuiaTransporte>({
        tipo: tipo.codigo,
        solicitante: solicitante.id,
        fecha: new Date().toISOString().slice(0, 10),
        nro_recibo: '',
        comerciante: '',
        documento_identidad: '',
        origen_lugar: '',
        origen_departamento: 'Beni',
        origen_provincia: '',
        origen_distrito: '',
        destino_lugar: '',
        destino_departamento: '',
        destino_provincia: '',
        destino_distrito: '',
        via: '',
        medio: '',
        transporte_nombre: '',
        transporte_placa: '',
        transporte_capacidad: '',
        productos: [filaVacia()],
        observaciones: '',
    });

    const { data, setData, errors, processing } = form;

    function cambiar<C extends keyof FormularioGuiaTransporte>(
        campo: C,
        valor: FormularioGuiaTransporte[C],
    ) {
        // Se arma el objeto completo una sola vez y se usa para las dos cosas.
        // Pasarle a setData el formulario entero, en vez de (clave, valor),
        // evita además el problema de tipos de la firma genérica de Inertia.
        const siguiente = { ...data, [campo]: valor };

        setData(siguiente);
        onCambio(siguiente);
    }

    function cambiarProductos(productos: FilaProductoGuia[]) {
        cambiar('productos', productos);
    }

    function enviar(e: FormEvent) {
        e.preventDefault();

        // No guarda nada. Ver TramiteController::store().
        form.post(route('tramites.store'));
    }

    return (
        <form onSubmit={enviar} className="space-y-4">
            {/* --- Encabezado: fecha (1) y recibo (2) --------------------- */}
            <Card>
                <CardContent className="grid gap-4 pt-6 sm:grid-cols-2">
                    <Campo etiqueta="Fecha" htmlFor="fecha" error={errors.fecha} obligatorio>
                        <Input
                            id="fecha"
                            type="date"
                            value={data.fecha}
                            onChange={(e) => cambiar('fecha', e.target.value)}
                        />
                    </Campo>

                    <Campo etiqueta="N° de recibo" htmlFor="nro_recibo" error={errors.nro_recibo}>
                        <Input
                            id="nro_recibo"
                            value={data.nro_recibo}
                            onChange={(e) => cambiar('nro_recibo', e.target.value)}
                        />
                    </Campo>
                </CardContent>
            </Card>

            {/* --- A.- INTERESADO ----------------------------------------- */}
            <Card>
                <CardContent className="space-y-4 pt-6">
                    <p className="text-sm font-semibold">A.— Interesado</p>

                    <Campo
                        etiqueta="Nombre y apellido o razón social"
                        htmlFor="comerciante"
                        error={errors.comerciante}
                        ayuda="Cuando el módulo esté conectado, va a buscar en Solicitantes."
                        obligatorio
                    >
                        <Input
                            id="comerciante"
                            value={data.comerciante}
                            onChange={(e) => cambiar('comerciante', e.target.value)}
                        />
                    </Campo>

                    <Campo
                        etiqueta="Documento de identidad"
                        htmlFor="documento_identidad"
                        error={errors.documento_identidad}
                    >
                        <Input
                            id="documento_identidad"
                            value={data.documento_identidad}
                            onChange={(e) => cambiar('documento_identidad', e.target.value)}
                            placeholder="CI o NIT"
                        />
                    </Campo>
                </CardContent>
            </Card>

            {/* --- B.- UBICACIÓN ------------------------------------------ */}
            <Card>
                <CardContent className="space-y-4 pt-6">
                    <p className="text-sm font-semibold">B.— Ubicación</p>

                    <BloqueUbicacion
                        titulo="Producción u origen"
                        prefijo="origen"
                        datos={data}
                        errores={errors}
                        cambiar={cambiar}
                    />

                    <BloqueUbicacion
                        titulo="Destino"
                        prefijo="destino"
                        datos={data}
                        errores={errors}
                        cambiar={cambiar}
                    />

                    <Campo etiqueta="Vía de transporte" htmlFor="via" error={errors.via}>
                        <Select
                            id="via"
                            value={data.via}
                            onChange={(e) => cambiar('via', e.target.value)}
                        >
                            <option value="">Elegir vía…</option>
                            {catalogos.vias.map((v) => (
                                <option key={v.value} value={v.value}>
                                    {v.label}
                                </option>
                            ))}
                        </Select>
                    </Campo>
                </CardContent>
            </Card>

            {/* --- C.- TRANSPORTE ----------------------------------------- */}
            <Card>
                <CardContent className="space-y-4 pt-6">
                    <p className="text-sm font-semibold">C.— Transporte</p>

                    <Campo etiqueta="Medio" htmlFor="medio" error={errors.medio}>
                        <Select
                            id="medio"
                            value={data.medio}
                            onChange={(e) => cambiar('medio', e.target.value)}
                        >
                            <option value="">Elegir medio…</option>
                            {catalogos.medios.map((m) => (
                                <option key={m.value} value={m.value}>
                                    {m.label}
                                </option>
                            ))}
                        </Select>
                    </Campo>

                    <div className="grid gap-4 sm:grid-cols-3">
                        <Campo
                            etiqueta="Nombre o tipo"
                            htmlFor="transporte_nombre"
                            error={errors.transporte_nombre}
                        >
                            <Input
                                id="transporte_nombre"
                                value={data.transporte_nombre}
                                onChange={(e) => cambiar('transporte_nombre', e.target.value)}
                            />
                        </Campo>

                        <Campo
                            etiqueta="Placa o matrícula"
                            htmlFor="transporte_placa"
                            error={errors.transporte_placa}
                        >
                            <Input
                                id="transporte_placa"
                                value={data.transporte_placa}
                                onChange={(e) => cambiar('transporte_placa', e.target.value)}
                            />
                        </Campo>

                        <Campo
                            etiqueta="Capacidad máxima"
                            htmlFor="transporte_capacidad"
                            error={errors.transporte_capacidad}
                        >
                            <Input
                                id="transporte_capacidad"
                                value={data.transporte_capacidad}
                                onChange={(e) => cambiar('transporte_capacidad', e.target.value)}
                                placeholder="Ej.: 2.000 Kg"
                            />
                        </Campo>
                    </div>
                </CardContent>
            </Card>

            {/* --- D.- PRODUCTOS HIDROBIOLÓGICOS -------------------------- */}
            <Card>
                <CardContent className="space-y-4 pt-6">
                    <p className="text-sm font-semibold">D.— Productos hidrobiológicos</p>

                    <TablaProductosGuia
                        productos={data.productos}
                        catalogos={catalogos}
                        onCambiar={cambiarProductos}
                    />

                    <Campo
                        etiqueta="Observaciones"
                        htmlFor="observaciones"
                        error={errors.observaciones}
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
                    Registrar guía
                </Button>
            </div>
        </form>
    );
}

/**
 * Los cuatro campos de división política que el papel repite para origen y
 * para destino: lugar, departamento, provincia y distrito o cuenca.
 *
 * Está aparte porque son idénticos en las dos filas; escribirlos dos veces
 * sería ocho campos copiados que después se desincronizan.
 */
function BloqueUbicacion({
    titulo,
    prefijo,
    datos,
    errores,
    cambiar,
}: {
    titulo: string;
    prefijo: 'origen' | 'destino';
    datos: FormularioGuiaTransporte;
    errores: Partial<Record<string, string>>;
    cambiar: <C extends keyof FormularioGuiaTransporte>(
        campo: C,
        valor: FormularioGuiaTransporte[C],
    ) => void;
}) {
    const campos = [
        { sufijo: 'lugar', etiqueta: 'Lugar' },
        { sufijo: 'departamento', etiqueta: 'Departamento' },
        { sufijo: 'provincia', etiqueta: 'Provincia' },
        { sufijo: 'distrito', etiqueta: 'Distrito o cuenca' },
    ] as const;

    return (
        <fieldset className="rounded-lg border border-border p-3">
            <legend className="px-1 text-xs font-semibold text-muted-foreground">{titulo}</legend>

            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                {campos.map(({ sufijo, etiqueta }) => {
                    const campo = `${prefijo}_${sufijo}` as keyof FormularioGuiaTransporte;

                    return (
                        <Campo
                            key={campo}
                            etiqueta={etiqueta}
                            htmlFor={campo}
                            error={errores[campo]}
                        >
                            <Input
                                id={campo}
                                value={datos[campo] as string}
                                onChange={(e) => cambiar(campo, e.target.value as never)}
                            />
                        </Campo>
                    );
                })}
            </div>
        </fieldset>
    );
}
