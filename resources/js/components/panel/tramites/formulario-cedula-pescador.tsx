import { Link, useForm } from '@inertiajs/react';
import {
    ImageUp,
    LoaderCircle,
    Lock,
    PencilLine,
    Save,
    Trash2,
    TriangleAlert,
} from 'lucide-react';
import { useEffect, useState, type FormEvent } from 'react';
import { CampoPagos } from '@/components/panel/tramites/campo-pagos';
import { CampoRequisito } from '@/components/panel/tramites/campo-requisito';
import { Button } from '@/components/ui/button';
import { Campo } from '@/components/ui/campo';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useArchivos } from '@/hooks/use-archivos';
import type {
    CatalogosCedulaPescador,
    FormularioCedulaPescador,
    TipoTramiteOpcion,
} from '@/types/tramites';
import type { SolicitanteDelTramite } from '@/types/tramites';

/**
 * ============================================================================
 *  CON QUÉ ARRANCA EL FORMULARIO
 * ============================================================================
 *
 * Está acá afuera, exportado, porque lo necesitan DOS lugares: este formulario
 * y la pantalla que lo contiene, que mantiene una copia de los mismos datos
 * para dibujar la vista previa de la credencial mientras se escribe.
 *
 * Escrito dos veces se desincronizó enseguida: la pantalla arrancaba con el
 * nombre y la cédula vacíos, así que la credencial de la derecha salía sin
 * titular hasta que el operador tocaba cualquier campo —y si no tocaba
 * ninguno, se mandaba a plastificar mirando una tarjeta en blanco—.
 *
 * ----------------------------------------------------------------------------
 *  POR QUÉ ARRANCA CON LO QUE YA ESTÁ EN LA FICHA
 * ----------------------------------------------------------------------------
 *
 * Cédula, nombre y domicilio salen del padrón. Hacer que ventanilla los vuelva
 * a tipear no solo pierde tiempo: al escribirlos de nuevo salen distintos —una
 * tilde, un apellido abreviado— y la credencial termina diciendo algo que no
 * coincide con la ficha del mismo pescador.
 */
export function valoresInicialesCedula(
    tipo: TipoTramiteOpcion,
    solicitante: SolicitanteDelTramite,
    catalogos: CatalogosCedulaPescador,
): FormularioCedulaPescador {
    return {
        tipo: tipo.codigo,
        solicitante: solicitante.id,

        // Ninguno de estos cuatro se edita: viajan para la vista previa, y al
        // guardar el servidor los vuelve a leer de la ficha.
        ci: solicitante.ci_nit,
        expedido: solicitante.expedido ?? 'BN',
        nombre: solicitante.nombreCompleto,
        ciudad: solicitante.ciudad ?? '',
        provincia: solicitante.provincia ?? '',
        direccion: solicitante.direccion ?? '',

        asociacion: '',
        registro: '',
        capacidad_kg: '',

        // Solo se llenan cuando la ficha viene sin ellos. Ver el formulario.
        ciudad_solicitante: '',
        provincia_solicitante: '',
        direccion_solicitante: '',
        foto_solicitante: null,

        certificacion_asociacion: null,
        copia_ci: null,

        /*
         * Arranca con UN pago en blanco y no con la lista vacía.
         *
         * Una lista vacía obligaría al operador a apretar «agregar pago» antes
         * de poder escribir nada, y el caso normal —un solo comprobante— es
         * justamente el que no debería costar un clic de más. Los que pagaron
         * en dos veces agregan la segunda fila; los demás no se enteran.
         */
        pagos: [
            {
                forma: catalogos.formas_pago[0]?.value ?? 'transferencia',
                nro_transaccion: '',
                banco: '',
                monto: '',
                comprobante: null,
            },
        ],

        observaciones: '',
    };
}

/**
 * ============================================================================
 *  FORMULARIO — CÉDULA DE PESCADOR
 * ============================================================================
 *
 * Los campos son los renglones impresos en la credencial: nombre, asociación,
 * ciudad, provincia, dirección, registro y cupo autorizado, más la cédula de
 * identidad y la fotografía.
 *
 * Es el único de los tres servicios que lleva FOTO, y la foto tiene un
 * tratamiento propio: sale de la FICHA del solicitante, no del trámite. Si la
 * ficha ya la tiene, acá no se muestra nada. Si no la tiene, aparece el campo
 * para sacársela en el momento —mandarlo a otra pantalla a mitad del trámite es
 * perder al pescador que está en la ventanilla—, y lo que se cargue se guarda
 * en su ficha, no en la carpeta del trámite.
 *
 * ----------------------------------------------------------------------------
 *  POR QUÉ LOS REQUISITOS VAN PRIMEROS Y BLOQUEAN EL BOTÓN
 * ----------------------------------------------------------------------------
 *
 * La credencial no se emite contra la palabra del solicitante: la asociación
 * tiene que certificar que el pescador está afiliado, y la fotocopia del
 * carnet es lo que respalda el nombre y la C.I. que se van a plastificar.
 * Una credencial mal emitida no se corrige editando un registro — hay que
 * imprimir otra tarjeta.
 *
 * Por eso los tres adjuntos son la PRIMERA tarjeta de la pantalla y el botón
 * de registrar está apagado hasta que estén los tres: el operador se
 * entera del requisito antes de tipear veinte campos, no después.
 *
 * El bloqueo del botón es comodidad, igual que esconder un botón por permiso.
 * La regla de verdad está en el servidor, en TramiteController::store().
 */
export function FormularioCedulaPescador({
    tipo,
    solicitante,
    catalogos,
    onCambio,
    onFotoNueva,
}: {
    tipo: TipoTramiteOpcion;
    solicitante: SolicitanteDelTramite;
    catalogos: CatalogosCedulaPescador;
    onCambio: (datos: FormularioCedulaPescador) => void;
    /**
     * Avisa al padre con qué foto dibujar la credencial: la URL temporal de la
     * que se acaba de elegir, o NULL para que use la de la ficha.
     */
    onFotoNueva: (url: string | null) => void;
}) {
    const form = useForm<FormularioCedulaPescador>(
        valoresInicialesCedula(tipo, solicitante, catalogos),
    );

    const { data, setData, errors, processing } = form;

    const archivos = useArchivos();

    const [vistaFoto, setVistaFoto] = useState<string | null>(null);

    // El error del navegador es aparte del de Laravel: este aparece al elegir
    // la foto, el otro recién al enviar el formulario.
    const [errorFotoLocal, setErrorFotoLocal] = useState<string | null>(null);

    /*
     * La ficha ya tiene foto → no se pide nada. Este es el caso normal: el
     * solicitante se registra en ventanilla y ahí mismo se le saca.
     */
    const faltaFoto = solicitante.foto_url === null;

    /*
     * El padrón viejo tiene fichas sin dirección: se cargaron antes de que la
     * credencial existiera y nadie las completó. Cuando falta alguno de los
     * tres, el campo aparece para llenarlo desde acá y el dato se guarda en la
     * FICHA, no en el trámite.
     */
    const faltaCiudad = !solicitante.ciudad;
    const faltaProvincia = !solicitante.provincia;
    const faltaDireccion = !solicitante.direccion;
    const fichaIncompleta = faltaCiudad || faltaProvincia || faltaDireccion;

    /*
     * createObjectURL reserva memoria del navegador que NO se libera sola. Sin
     * este cleanup, cada foto que el operador prueba queda retenida hasta que
     * se recargue la página. El return del useEffect corre al cambiar la foto y
     * al desmontar el componente.
     */
    useEffect(() => {
        if (!data.foto_solicitante) {
            setVistaFoto(null);
            onFotoNueva(null);

            return;
        }

        const url = URL.createObjectURL(data.foto_solicitante);

        setVistaFoto(url);
        onFotoNueva(url);

        return () => URL.revokeObjectURL(url);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [data.foto_solicitante]);

    /*
     * Un pago está completo cuando tiene su comprobante, su monto y —si la
     * forma lo pide— su número de transacción. Es la misma regla que valida el
     * servidor en TramiteController::validarAdjuntos(); acá se repite solo
     * para poder apagar el botón, igual que se esconde un botón por permiso.
     */
    const pagosCompletos =
        data.pagos.length > 0 &&
        data.pagos.every((pago) => {
            const forma = catalogos.formas_pago.find((f) => f.value === pago.forma);
            const pideReferencia = forma?.requiere_referencia ?? true;

            return (
                pago.comprobante !== null &&
                Number(pago.monto) > 0 &&
                (! pideReferencia || pago.nro_transaccion.trim() !== '')
            );
        });

    /** Los datos de la ficha que este trámite necesita y todavía no están. */
    const fichaCompletada =
        (!faltaFoto || data.foto_solicitante) &&
        (!faltaCiudad || data.ciudad_solicitante.trim() !== '') &&
        (!faltaProvincia || data.provincia_solicitante.trim() !== '') &&
        (!faltaDireccion || data.direccion_solicitante.trim() !== '');

    /** Sin respaldos, sin pago o con la ficha incompleta no se emite nada. */
    const requisitosCompletos = Boolean(
        data.certificacion_asociacion && data.copia_ci && pagosCompletos && fichaCompletada,
    );

    function cambiar<C extends keyof FormularioCedulaPescador>(
        campo: C,
        valor: FormularioCedulaPescador[C],
    ) {
        const siguiente = { ...data, [campo]: valor };

        setData(siguiente);
        onCambio(siguiente);
    }

    /**
     * Completa un dato del domicilio que la ficha no tenía.
     *
     * Escribe DOS campos con el mismo valor: el `_solicitante`, que es lo que
     * se manda para guardar en la ficha, y el renglón impreso, que es lo que la
     * credencial de la derecha dibuja. Sin lo segundo, el operador escribiría la
     * dirección y la vista previa seguiría mostrando el renglón vacío, que se
     * lee como que el sistema no tomó el dato.
     */
    /**
     * Revisa la foto antes de guardarla en el formulario.
     *
     * Una foto que no sirve NO se guarda: si se guardara, el botón de registrar
     * se encendería con un archivo que el servidor va a rechazar igual.
     */
    function elegirFoto(elegida: File | null) {
        if (elegida === null) {
            setErrorFotoLocal(null);
            cambiar('foto_solicitante', null);

            return;
        }

        const problema = archivos.validar(elegida, { soloImagen: true });

        setErrorFotoLocal(problema);
        cambiar('foto_solicitante', problema === null ? elegida : null);
    }

    function completarFicha(campo: 'ciudad' | 'provincia' | 'direccion', valor: string) {
        const siguiente = { ...data, [`${campo}_solicitante`]: valor, [campo]: valor };

        setData(siguiente);
        onCambio(siguiente);
    }

    function enviar(e: FormEvent) {
        e.preventDefault();

        // Segundo cerrojo además del `disabled` del botón: el usuario puede
        // enviar con Enter desde cualquier input sin tocar el botón nunca.
        if (!requisitosCompletos) {
            return;
        }

        // No guarda nada. Ver TramiteController::store().
        // forceFormData porque hay archivos: es el único formato que los admite.
        form.post(route('tramites.store'), { forceFormData: true });
    }

    return (
        <form onSubmit={enviar} className="space-y-4">
            <Card>
                <CardContent className="space-y-4 pt-6">
                    <div className="space-y-1">
                        <p className="text-sm font-semibold">Requisitos</p>
                        <p className="text-xs text-muted-foreground">
                            Los papeles son obligatorios. Mientras falte alguno, la
                            credencial no se puede registrar.
                        </p>
                    </div>

                    <CampoRequisito
                        id="certificacion_asociacion"
                        etiqueta="Certificación emitida por su asociación"
                        ayuda={`Documento firmado por la asociación que acredita al pescador como afiliado. PDF o foto, ${archivos.ayudaPeso}.`}
                        error={errors.certificacion_asociacion}
                        archivo={data.certificacion_asociacion}
                        onCambio={(archivo) => cambiar('certificacion_asociacion', archivo)}
                    />

                    <CampoRequisito
                        id="copia_ci"
                        etiqueta="Fotocopia de carnet simple"
                        ayuda={`Fotocopia de la cédula de identidad, sin legalizar. PDF o foto, ${archivos.ayudaPeso}.`}
                        error={errors.copia_ci}
                        archivo={data.copia_ci}
                        onCambio={(archivo) => cambiar('copia_ci', archivo)}
                    />

                </CardContent>
            </Card>

            {/*
                EL PAGO VA EN SU PROPIA TARJETA.

                Los otros dos requisitos son un papel y nada más; el pago puede
                ser varios, cada uno con monto y número de transacción. Metido
                entre los otros dos, el bloque crecía hacia abajo cada vez que
                se agregaba un comprobante y tapaba la certificación y el
                carnet, que son lo primero que hay que revisar.
            */}
            <Card>
                <CardContent className="space-y-4 pt-6">
                    <CampoPagos
                        pagos={data.pagos}
                        formas={catalogos.formas_pago}
                        montoTasa={tipo.monto}
                        /*
                         * Laravel devuelve los errores del arreglo con clave
                         * anidada («pagos.0.monto»), que no es una propiedad de
                         * FormularioCedulaPescador. El tipo de `errors` no
                         * contempla esas claves, de ahí el cast.
                         */
                        errores={errors as unknown as Record<string, string>}
                        onCambio={(pagos) => cambiar('pagos', pagos)}
                    />
                </CardContent>
            </Card>

            <Card>
                <CardContent className="space-y-4 pt-6">
                    <p className="text-sm font-semibold">Titular</p>

                    {/*
                        NADA DE ESTE BLOQUE SE EDITA ACÁ.

                        Nombre, cédula, lugar de expedición y domicilio son los
                        de la ficha del solicitante y se muestran para
                        confirmar, no para corregir. Si se pudieran cambiar en
                        este formulario, la credencial impresa podría terminar
                        diciendo algo distinto a la ficha del mismo pescador, y
                        después nadie sabría cuál es el bueno.

                        Están todos juntos y no repartidos en dos tarjetas
                        porque son UNA sola cosa —quién es esta persona—, y el
                        operador los revisa de un vistazo antes de mandar a
                        plastificar. Para corregirlos está el enlace a la ficha.

                        El «Expedido» no tiene campo propio: ya viaja dentro de
                        `documento_identidad`, que es como se imprime — «7656924
                        BN».
                    */}
                    <div className="space-y-3 rounded-lg border border-dashed border-border bg-muted/30 p-4">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div className="min-w-0 space-y-1">
                                <p className="text-xs text-muted-foreground">
                                    Datos tomados de la ficha del solicitante
                                </p>
                                <p className="font-semibold">{solicitante.nombreCompleto}</p>
                                <p className="font-mono text-xs text-muted-foreground">
                                    C.I. {solicitante.documento_identidad}
                                </p>
                            </div>

                            <Link href={route('solicitantes.edit', solicitante.id)}>
                                <Button type="button" variant="ghost" size="sm">
                                    <PencilLine className="size-4" />
                                    Corregir en la ficha
                                </Button>
                            </Link>
                        </div>

                        <dl className="grid gap-x-6 gap-y-2 border-t border-dashed border-border pt-3 sm:grid-cols-2">
                            <DatoDeLaFicha etiqueta="Ciudad" valor={solicitante.ciudad} />
                            <DatoDeLaFicha etiqueta="Provincia" valor={solicitante.provincia} />
                            <DatoDeLaFicha
                                etiqueta="Dirección"
                                valor={solicitante.direccion}
                                className="sm:col-span-2"
                            />
                        </dl>
                    </div>

                    {/*
                        Y si la ficha viene sin alguno de esos datos, se
                        completa desde acá: lo que se escriba se guarda en la
                        FICHA, no en el trámite. Mismo criterio que la
                        fotografía — mandar al operador a otra pantalla es
                        perder al pescador que está en la ventanilla.
                    */}
                    {fichaIncompleta && (
                        <div className="space-y-4 rounded-lg border border-dashed border-amber-500/50 bg-amber-500/5 p-4">
                            <p className="flex items-start gap-1.5 text-xs text-amber-700 dark:text-amber-400">
                                <TriangleAlert className="mt-0.5 size-3.5 shrink-0" />
                                La ficha de {solicitante.nombreCompleto} está incompleta. Lo
                                que se cargue acá queda guardado en su ficha.
                            </p>

                            <div className="grid gap-4 sm:grid-cols-2">
                                {faltaCiudad && (
                                    <Campo
                                        etiqueta="Ciudad"
                                        htmlFor="ciudad_solicitante"
                                        error={errors.ciudad_solicitante}
                                        obligatorio
                                    >
                                        <Input
                                            id="ciudad_solicitante"
                                            value={data.ciudad_solicitante}
                                            onChange={(e) =>
                                                completarFicha('ciudad', e.target.value)
                                            }
                                            placeholder="Trinidad"
                                        />
                                    </Campo>
                                )}

                                {faltaProvincia && (
                                    <Campo
                                        etiqueta="Provincia"
                                        htmlFor="provincia_solicitante"
                                        error={errors.provincia_solicitante}
                                        obligatorio
                                    >
                                        <Select
                                            id="provincia_solicitante"
                                            value={data.provincia_solicitante}
                                            onChange={(e) =>
                                                completarFicha('provincia', e.target.value)
                                            }
                                        >
                                            <option value="">Elegir provincia…</option>
                                            {catalogos.provincias.map((p) => (
                                                <option key={p} value={p}>
                                                    {p}
                                                </option>
                                            ))}
                                        </Select>
                                    </Campo>
                                )}
                            </div>

                            {faltaDireccion && (
                                <Campo
                                    etiqueta="Dirección"
                                    htmlFor="direccion_solicitante"
                                    error={errors.direccion_solicitante}
                                    obligatorio
                                >
                                    <Input
                                        id="direccion_solicitante"
                                        value={data.direccion_solicitante}
                                        onChange={(e) =>
                                            completarFicha('direccion', e.target.value)
                                        }
                                        placeholder="Puerto Almacén"
                                    />
                                </Campo>
                            )}
                        </div>
                    )}

                    <Campo etiqueta="Asociación" htmlFor="asociacion" error={errors.asociacion}>
                        {/* Lista con opción libre: el padrón de asociaciones no
                            está cerrado, y obligar a elegir dejaría afuera a
                            cualquier sindicato nuevo. */}
                        <Input
                            id="asociacion"
                            list="asociaciones-pesca"
                            value={data.asociacion}
                            onChange={(e) => cambiar('asociacion', e.target.value)}
                            placeholder="SOC. IBARE - MAMORÉ"
                        />
                        <datalist id="asociaciones-pesca">
                            {catalogos.asociaciones.map((a) => (
                                <option key={a} value={a} />
                            ))}
                        </datalist>
                    </Campo>
                </CardContent>
            </Card>

            <Card>
                <CardContent className="space-y-4 pt-6">
                    {/* El domicilio subió a la tarjeta del titular: es parte
                        de quién es la persona, no de este trámite. Acá quedan
                        los dos datos que sí decide ventanilla. */}
                    <p className="text-sm font-semibold">Registro en el padrón</p>

                    {/*
                        EL N° DE REGISTRO NO ESTÁ EN ESTE FORMULARIO.

                        Lo asigna el sistema al guardar, único en todo el padrón.
                        Escrito a mano se repetía —nadie tiene a la vista los que
                        ya se usaron— y se tipeaba mal, y una credencial mal
                        numerada no se corrige editando un registro: hay que
                        imprimir otra tarjeta.

                        Tampoco se avisa acá: la credencial de la derecha ya
                        muestra el renglón con su molde «xxxx-xxxx-xxxx», que
                        dice lo mismo en el lugar donde el operador lo va a ver
                        impreso. Repetirlo en el formulario era ocupar espacio
                        para contar dos veces la misma cosa.
                    */}
                    <div className="grid gap-4 sm:grid-cols-2">
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
                                onChange={(e) => cambiar('capacidad_kg', e.target.value)}
                                placeholder="600"
                            />
                        </Campo>
                    </div>
                </CardContent>
            </Card>

            {/*
                LA FOTOGRAFÍA SOLO APARECE SI LA FICHA NO LA TIENE.

                Es un dato personal del padrón: se guarda en la ficha del
                solicitante y de ahí sale para todos sus documentos. Si se
                pudiera reemplazar en cada trámite, la misma persona terminaría
                con una cara distinta en cada credencial y sin forma de saber
                cuál es la buena — para corregirla está la ficha.

                Pero si todavía no tiene ninguna, se le saca ACÁ: mandar al
                pescador a otra pantalla a mitad del trámite es perderlo.
            */}
            {faltaFoto && (
                <Card>
                    <CardContent className="space-y-4 pt-6">
                        <div className="space-y-1">
                            <p className="text-sm font-semibold">Fotografía del titular</p>
                            <p className="text-xs text-muted-foreground">
                                La ficha de {solicitante.nombreCompleto} todavía no tiene
                                fotografía. La que se cargue acá queda guardada en su ficha
                                y se usa en esta credencial y en las próximas.
                            </p>
                        </div>

                        <Campo
                            etiqueta="Foto del titular"
                            htmlFor="foto_solicitante"
                            error={errors.foto_solicitante ?? errorFotoLocal ?? undefined}
                            ayuda={`Tipo carnet, fondo claro. Se imprime en la credencial. JPG, PNG o WEBP, ${archivos.ayudaPeso}.`}
                            obligatorio
                        >
                            <div className="flex items-center gap-3">
                                <span className="flex size-20 shrink-0 items-center justify-center overflow-hidden rounded-md border border-border bg-muted">
                                    {vistaFoto ? (
                                        <img
                                            src={vistaFoto}
                                            alt="Vista previa de la foto"
                                            className="size-full object-cover"
                                        />
                                    ) : (
                                        <ImageUp className="size-6 text-muted-foreground" />
                                    )}
                                </span>

                                <div className="space-y-2">
                                    <Input
                                        id="foto_solicitante"
                                        type="file"
                                        accept={archivos.aceptaImagen}
                                        aria-invalid={Boolean(
                                            errors.foto_solicitante ?? errorFotoLocal,
                                        )}
                                        onChange={(e) =>
                                            elegirFoto(e.target.files?.[0] ?? null)
                                        }
                                        className="h-auto py-1.5"
                                    />

                                    {data.foto_solicitante && (
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            onClick={() => elegirFoto(null)}
                                        >
                                            <Trash2 className="size-4" />
                                            Quitar foto
                                        </Button>
                                    )}
                                </div>
                            </div>
                        </Campo>
                    </CardContent>
                </Card>
            )}

            <Card>
                <CardContent className="space-y-4 pt-6">
                    <p className="text-sm font-semibold">Observaciones</p>

                    <Campo
                        etiqueta="Observaciones"
                        htmlFor="observaciones"
                        error={errors.observaciones}
                        ayuda="Uso interno: no se imprime en la credencial."
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

            <div className="flex flex-wrap items-center justify-end gap-3">
                {/* El motivo del bloqueo se dice en texto, al lado del botón.
                    Un botón gris sin explicación se lee como "el sistema está
                    roto", y el operador termina llamando por teléfono. */}
                {!requisitosCompletos && (
                    <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
                        <Lock className="size-3.5 shrink-0" />
                        {fichaCompletada
                            ? 'Falta adjuntar los requisitos'
                            : 'Falta completar los datos del solicitante'}
                    </p>
                )}

                <Button type="submit" disabled={processing || !requisitosCompletos}>
                    {processing ? (
                        <LoaderCircle className="size-4 animate-spin" />
                    ) : (
                        <Save className="size-4" />
                    )}
                    Registrar cédula
                </Button>
            </div>
        </form>
    );
}

/**
 * Un renglón del domicilio, tal como figura en la ficha.
 *
 * Cuando el dato falta se dice «Sin cargar» en vez de dejar el hueco vacío: un
 * espacio en blanco se lee como un error de la pantalla, y el operador no sabe
 * si el sistema no lo trajo o si la persona no lo tiene registrado.
 */
function DatoDeLaFicha({
    etiqueta,
    valor,
    className,
}: {
    etiqueta: string;
    valor: string | null;
    className?: string;
}) {
    return (
        <div className={className}>
            <dt className="text-xs text-muted-foreground">{etiqueta}</dt>
            <dd className={valor ? 'font-medium' : 'text-sm text-muted-foreground italic'}>
                {valor || 'Sin cargar'}
            </dd>
        </div>
    );
}
