import { Link, useForm } from '@inertiajs/react';
import { IdCard, ImageUp, LoaderCircle, Paperclip, Trash2, User } from 'lucide-react';
import { useEffect, useRef, useState, type FormEvent, type ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Campo } from '@/components/ui/campo';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { useArchivos } from '@/hooks/use-archivos';
import { cn } from '@/lib/utils';
import type { FormularioSolicitante, Opcion } from '@/types/solicitantes';

/**
 * ============================================================================
 *  FORMULARIO DE SOLICITANTE — se usa para crear Y para editar
 * ============================================================================
 *
 * Un solo componente para las dos pantallas. Si fueran dos archivos, cada
 * campo nuevo habría que agregarlo dos veces y tarde o temprano quedarían
 * distintos.
 *
 * ----------------------------------------------------------------------------
 *  useForm: la herramienta de Inertia para formularios
 * ----------------------------------------------------------------------------
 *
 *   const form = useForm({ ...valores iniciales... });
 *
 * Devuelve todo lo que hace falta:
 *
 *   form.data          los valores actuales de los campos
 *   form.setData(k,v)  cambiar un campo
 *   form.post(url)     enviarlo
 *   form.processing    true mientras viaja al servidor (para bloquear el botón)
 *   form.errors        los errores de validación que devolvió Laravel
 *
 * Lo importante: `form.errors` se llena SOLO. Cuando la validación de
 * GuardarSolicitanteRequest falla, Laravel redirige de vuelta con los errores
 * en la sesión, Inertia los recoge y los deja acá. No hay que escribir ni una
 * línea para conectarlos.
 *
 * ----------------------------------------------------------------------------
 *  POR QUÉ EL NOMBRE SON CINCO CAMPOS Y NO UNO
 * ----------------------------------------------------------------------------
 *
 * Porque así viene en la cédula y así lo piden los formularios en papel del
 * SEDAG. Un campo único obligaría a partirlo después con código, y ahí no hay
 * forma de acertar siempre: «Rosa Elena Antezana Áñez» pueden ser dos nombres
 * y dos apellidos, o un nombre y tres apellidos. Se pide separado porque
 * separado es como llega.
 *
 * ----------------------------------------------------------------------------
 *  CÓMO ESTÁ REPARTIDA LA PANTALLA
 * ----------------------------------------------------------------------------
 *
 * En pantalla ancha son dos columnas: los campos a la izquierda y, a la
 * derecha, una tarjeta fija con la foto y el nombre ya armado.
 *
 * La tarjeta no es decoración. Este formulario alimenta una credencial
 * plastificada, y las dos cosas que se imprimen —la cara y el nombre— son
 * justamente las que el operador no puede revisar mirando los campos sueltos:
 * el nombre está partido en cinco casillas y la foto es un archivo. Tenerlas
 * armadas al costado, sin scrollear, es lo que evita mandar a plastificar una
 * tarjeta mal escrita.
 *
 * En pantalla angosta la tarjeta pasa arriba y las columnas se apilan.
 */
export function FormularioSolicitante({
    modo,
    solicitanteId,
    valoresIniciales,
    fotoActual,
    expedidos,
    provincias,
}: {
    modo: 'crear' | 'editar';
    /** Solo en modo editar: a qué registro apuntar al guardar. */
    solicitanteId?: number;
    valoresIniciales: FormularioSolicitante;
    /** Foto ya guardada, en modo editar. */
    fotoActual?: string | null;
    /** Los nueve departamentos, para el lugar de expedición de la cédula. */
    expedidos: Opcion[];
    /** Las ocho provincias del Beni. */
    provincias: string[];
}) {
    const form = useForm<FormularioSolicitante>(valoresIniciales);
    const { data, setData, errors, processing } = form;

    const archivos = useArchivos();

    // El error del navegador es aparte del de Laravel: este aparece al elegir
    // la foto, el otro recién al enviar el formulario.
    const [errorFotoLocal, setErrorFotoLocal] = useState<string | null>(null);

    /**
     * Revisa la foto antes de guardarla en el formulario.
     *
     * La regla de verdad está en el servidor (`GuardarSolicitanteRequest`).
     * Esto es para no hacer subir 8 MB por la conexión de la Gobernación y
     * recién entonces avisar que no entraba. Una foto que no sirve no se
     * guarda: si se guardara, el formulario se enviaría con ella igual.
     */
    function elegirFoto(elegida: File | null) {
        if (elegida === null) {
            setErrorFotoLocal(null);
            setData('foto', null);
            setData('quitar_foto', false);

            return;
        }

        const problema = archivos.validar(elegida, { soloImagen: true });

        setErrorFotoLocal(problema);
        setData('foto', problema === null ? elegida : null);
        setData('quitar_foto', false);
    }

    function enviar(e: FormEvent) {
        // Sin esto el navegador recargaría la página entera al enviar,
        // que es el comportamiento por defecto de un <form> en HTML.
        e.preventDefault();

        if (modo === 'crear') {
            /*
             * forceFormData obliga a enviar el formulario como multipart, que
             * es el único formato que admite archivos. Se deja siempre activo
             * para que el envío sea idéntico haya foto o no.
             */
            form.post(route('solicitantes.store'), { forceFormData: true });

            return;
        }

        /*
         * PARA EDITAR HAY UN TRUCO NECESARIO.
         *
         * La ruta de actualizar es PUT, pero PHP no sabe leer archivos en una
         * petición PUT: solo los procesa en POST. La solución de siempre en
         * Laravel es mandar un POST con un campo extra `_method: 'put'`, y
         * Laravel lo trata como si fuera PUT.
         *
         * transform() agrega ese campo al vuelo, sin ensuciar form.data.
         */
        form.transform((datos) => ({ ...datos, _method: 'put' }));
        form.post(route('solicitantes.update', solicitanteId!), { forceFormData: true });
    }

    return (
        <form onSubmit={enviar} className="space-y-6" noValidate>
            <div className="grid gap-6 lg:grid-cols-3 lg:items-start">
                {/* =======================================================
                    COLUMNA IZQUIERDA — los campos

                    order-2 en móvil deja la tarjeta arriba; en pantalla
                    ancha vuelve a su lugar natural.
                ======================================================= */}
                <div className="order-2 space-y-6 lg:order-1 lg:col-span-2">
                    {/* ---------- IDENTIFICACIÓN ---------- */}
                    <Seccion
                        titulo="Identificación"
                        descripcion="Cédula de identidad con la que se identifica al pescador."
                        // La cédula es un número de siete dígitos y el
                        // complemento son dos caracteres: darles el mismo
                        // ancho hace pensar que se espera lo mismo en los dos.
                        columnas="sm:grid-cols-[2fr_1fr_1fr]"
                    >
                        <Campo
                            etiqueta="Cédula de Identidad"
                            htmlFor="ci_nit"
                            error={errors.ci_nit}
                            obligatorio
                        >
                            <Input
                                id="ci_nit"
                                value={data.ci_nit}
                                onChange={(e) => setData('ci_nit', e.target.value)}
                                inputMode="numeric"
                                autoComplete="off"
                                placeholder="7656924"
                                aria-invalid={Boolean(errors.ci_nit)}
                            />
                        </Campo>

                        <Campo
                            etiqueta="Complemento"
                            htmlFor="complemento"
                            error={errors.complemento}
                            ayuda="Solo si la cédula lo tiene."
                        >
                            <Input
                                id="complemento"
                                value={data.complemento}
                                onChange={(e) => setData('complemento', e.target.value)}
                                maxLength={5}
                                placeholder="1A"
                                aria-invalid={Boolean(errors.complemento)}
                            />
                        </Campo>

                        {/* Es una lista y no un campo libre porque son nueve
                            códigos fijos del SEGIP: escrito a mano aparecerían
                            «BE», «Beni» y «bn» para la misma cosa, y eso
                            después se imprime en la credencial. */}
                        <Campo
                            etiqueta="Expedido en"
                            htmlFor="expedido"
                            error={errors.expedido}
                            ayuda="Va al lado del número."
                        >
                            <Select
                                id="expedido"
                                value={data.expedido}
                                onChange={(e) => setData('expedido', e.target.value)}
                                aria-invalid={Boolean(errors.expedido)}
                            >
                                <option value="">Sin especificar</option>
                                {expedidos.map((d) => (
                                    <option key={d.value} value={d.value}>
                                        {d.label}
                                    </option>
                                ))}
                            </Select>
                        </Campo>
                    </Seccion>

                    {/* ---------- NOMBRE ---------- */}
                    <Seccion
                        titulo="Nombre"
                        descripcion="Tal como figura en la cédula, cada parte en su campo."
                    >
                        <Campo
                            etiqueta="Primer nombre"
                            htmlFor="primerNombre"
                            error={errors.primerNombre}
                            obligatorio
                        >
                            <Input
                                id="primerNombre"
                                value={data.primerNombre}
                                onChange={(e) => setData('primerNombre', e.target.value)}
                                placeholder="Rosa"
                                aria-invalid={Boolean(errors.primerNombre)}
                            />
                        </Campo>

                        <Campo
                            etiqueta="Segundo nombre"
                            htmlFor="segundoNombre"
                            error={errors.segundoNombre}
                            ayuda="Dejar vacío si no tiene."
                        >
                            <Input
                                id="segundoNombre"
                                value={data.segundoNombre}
                                onChange={(e) => setData('segundoNombre', e.target.value)}
                                placeholder="Elena"
                            />
                        </Campo>

                        {/* De los apellidos se exige al menos uno, no los dos:
                            hay pescadores con un solo apellido y pedir ambos
                            los dejaría fuera del sistema. La regla vive en el
                            Form Request; el asterisco se pone en el paterno,
                            que es donde Laravel devuelve el error. */}
                        <Campo
                            etiqueta="Apellido paterno"
                            htmlFor="apellidoPaterno"
                            error={errors.apellidoPaterno}
                            ayuda="Al menos uno de los dos apellidos."
                            obligatorio
                        >
                            <Input
                                id="apellidoPaterno"
                                value={data.apellidoPaterno}
                                onChange={(e) => setData('apellidoPaterno', e.target.value)}
                                placeholder="Justiniano"
                                aria-invalid={Boolean(errors.apellidoPaterno)}
                            />
                        </Campo>

                        <Campo
                            etiqueta="Apellido materno"
                            htmlFor="apellidoMaterno"
                            error={errors.apellidoMaterno}
                        >
                            <Input
                                id="apellidoMaterno"
                                value={data.apellidoMaterno}
                                onChange={(e) => setData('apellidoMaterno', e.target.value)}
                                placeholder="Roca"
                            />
                        </Campo>

                        <Campo
                            etiqueta="Apellido de casada"
                            htmlFor="apellidoCasada"
                            error={errors.apellidoCasada}
                            ayuda="Sin el «de»: el sistema lo agrega al imprimir."
                        >
                            <Input
                                id="apellidoCasada"
                                value={data.apellidoCasada}
                                onChange={(e) => setData('apellidoCasada', e.target.value)}
                                placeholder="Áñez"
                            />
                        </Campo>
                    </Seccion>

                    {/* ---------- DATOS PERSONALES ---------- */}
                    <Seccion titulo="Datos personales" columnas="sm:grid-cols-3">
                        <Campo
                            etiqueta="Fecha de nacimiento"
                            htmlFor="fechaNacimiento"
                            error={errors.fechaNacimiento}
                        >
                            <Input
                                id="fechaNacimiento"
                                type="date"
                                value={data.fechaNacimiento}
                                onChange={(e) => setData('fechaNacimiento', e.target.value)}
                                aria-invalid={Boolean(errors.fechaNacimiento)}
                            />
                        </Campo>

                        <Campo etiqueta="Género" htmlFor="genero" error={errors.genero}>
                            <Select
                                id="genero"
                                value={data.genero}
                                onChange={(e) => setData('genero', e.target.value)}
                            >
                                <option value="">Sin especificar</option>
                                <option value="masculino">Masculino</option>
                                <option value="femenino">Femenino</option>
                            </Select>
                        </Campo>

                        <Campo
                            etiqueta="Nacionalidad"
                            htmlFor="nacionalidad"
                            error={errors.nacionalidad}
                        >
                            <Input
                                id="nacionalidad"
                                value={data.nacionalidad}
                                onChange={(e) => setData('nacionalidad', e.target.value)}
                            />
                        </Campo>
                    </Seccion>

                    {/* ---------- DOMICILIO Y CONTACTO ---------- */}
                    <Seccion
                        titulo="Domicilio y contacto"
                        descripcion="Dónde vive y cómo avisarle de los vencimientos."
                    >
                        <Campo etiqueta="Teléfono" htmlFor="telefono" error={errors.telefono}>
                            <Input
                                id="telefono"
                                value={data.telefono}
                                onChange={(e) => setData('telefono', e.target.value)}
                                inputMode="tel"
                                placeholder="71234567"
                                aria-invalid={Boolean(errors.telefono)}
                            />
                        </Campo>

                        <Campo etiqueta="Correo electrónico" htmlFor="email" error={errors.email}>
                            <Input
                                id="email"
                                type="email"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                aria-invalid={Boolean(errors.email)}
                            />
                        </Campo>

                        <Campo etiqueta="Ciudad" htmlFor="ciudad" error={errors.ciudad}>
                            <Input
                                id="ciudad"
                                value={data.ciudad}
                                onChange={(e) => setData('ciudad', e.target.value)}
                                placeholder="Trinidad"
                            />
                        </Campo>

                        {/* Lista con opción libre: las ocho provincias son las
                            del Beni, pero un pescador puede vivir fuera del
                            departamento y no hay que dejarlo afuera. */}
                        <Campo etiqueta="Provincia" htmlFor="provincia" error={errors.provincia}>
                            <Input
                                id="provincia"
                                list="provincias-beni"
                                value={data.provincia}
                                onChange={(e) => setData('provincia', e.target.value)}
                                placeholder="Cercado"
                            />
                            <datalist id="provincias-beni">
                                {provincias.map((p) => (
                                    <option key={p} value={p} />
                                ))}
                            </datalist>
                        </Campo>

                        <Campo
                            etiqueta="Dirección"
                            htmlFor="direccion"
                            error={errors.direccion}
                            className="sm:col-span-2"
                        >
                            <Input
                                id="direccion"
                                value={data.direccion}
                                onChange={(e) => setData('direccion', e.target.value)}
                                placeholder="Puerto Almacén"
                            />
                        </Campo>
                    </Seccion>
                </div>

                {/* =======================================================
                    COLUMNA DERECHA — lo que se va a imprimir
                ======================================================= */}
                <aside className="order-1 lg:order-2 lg:sticky lg:top-6">
                    <TarjetaVistaPrevia
                        datos={data}
                        fotoActual={fotoActual ?? null}
                        errorFoto={errors.foto ?? errorFotoLocal ?? undefined}
                        onElegirFoto={elegirFoto}
                        onQuitarFoto={() => {
                            setErrorFotoLocal(null);
                            setData('foto', null);
                            setData('quitar_foto', true);
                        }}
                    />
                </aside>
            </div>

            {/* ---------------------------------------------------------------
                BOTONES
            --------------------------------------------------------------- */}
            <div className="flex items-center justify-end gap-3 border-t border-border pt-4">
                <Link href={route('solicitantes.index')}>
                    <Button type="button" variant="outline">
                        Cancelar
                    </Button>
                </Link>

                {/* `processing` bloquea el botón mientras la petición viaja:
                    sin eso, un doble clic crearía dos veces el mismo registro. */}
                <Button type="submit" disabled={processing}>
                    {processing && <LoaderCircle className="size-4 animate-spin" />}
                    {modo === 'crear' ? 'Registrar solicitante' : 'Guardar cambios'}
                </Button>
            </div>
        </form>
    );
}

/**
 * La tarjeta del costado: foto, nombre armado y cédula.
 *
 * Muestra las dos cosas que van impresas en la credencial y que el operador no
 * puede verificar mirando los campos sueltos, porque el nombre está partido en
 * cinco casillas y la foto es un archivo.
 */
function TarjetaVistaPrevia({
    datos,
    fotoActual,
    errorFoto,
    onElegirFoto,
    onQuitarFoto,
}: {
    datos: FormularioSolicitante;
    fotoActual: string | null;
    errorFoto?: string;
    onElegirFoto: (archivo: File | null) => void;
    onQuitarFoto: () => void;
}) {
    // Los formatos y el peso salen de config/jichi.php, igual que en el resto
    // del sistema: el texto de ayuda y la regla del servidor dicen el mismo
    // número porque leen el mismo valor.
    const archivos = useArchivos();

    const [vistaPrevia, setVistaPrevia] = useState<string | null>(null);
    const inputFoto = useRef<HTMLInputElement>(null);

    /*
     * Para mostrar un archivo que todavía no se subió, el navegador crea una
     * dirección temporal en memoria con URL.createObjectURL().
     *
     * Esa dirección hay que LIBERARLA cuando ya no se usa, o la imagen queda
     * ocupando memoria hasta que se cierre la pestaña. De eso se encarga el
     * `return` del efecto, que React ejecuta antes de volver a correrlo y al
     * desmontar el componente.
     */
    useEffect(() => {
        if (!datos.foto) {
            setVistaPrevia(null);

            return;
        }

        const url = URL.createObjectURL(datos.foto);
        setVistaPrevia(url);

        return () => URL.revokeObjectURL(url);
    }, [datos.foto]);

    const foto = vistaPrevia ?? (datos.quitar_foto ? null : fotoActual);
    const nombre = armarNombre(datos);
    const cedula = datos.ci_nit
        ? datos.ci_nit + (datos.complemento ? `-${datos.complemento}` : '')
        : null;

    return (
        <Card>
            <CardContent className="space-y-4 p-5">
                <p className="text-sm font-semibold">Vista previa</p>

                <div className="flex flex-col items-center gap-3 rounded-lg border border-dashed border-border bg-muted/30 p-5 text-center">
                    <div className="flex size-28 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-border bg-background">
                        {foto ? (
                            <img
                                src={foto}
                                alt="Fotografía del solicitante"
                                className="size-full object-cover"
                            />
                        ) : (
                            <User className="size-10 text-muted-foreground" />
                        )}
                    </div>

                    {/* min-h fija la altura para que la tarjeta no salte de
                        tamaño mientras se escribe el nombre. */}
                    <div className="min-h-[3.5rem] space-y-1">
                        <p
                            className={cn(
                                'text-base font-semibold leading-snug',
                                !nombre && 'text-muted-foreground',
                            )}
                        >
                            {nombre || 'Sin nombre todavía'}
                        </p>

                        <p className="flex items-center justify-center gap-1.5 font-mono text-xs text-muted-foreground">
                            <IdCard className="size-3.5 shrink-0" />
                            {cedula ?? 'Sin cédula'}
                        </p>
                    </div>
                </div>

                <Campo
                    etiqueta="Fotografía"
                    htmlFor="foto"
                    error={errorFoto}
                    ayuda={`Tipo carnet, fondo claro. JPG, PNG o WEBP, ${archivos.ayudaPeso}.`}
                >
                    {/*
                        El <input type="file"> nativo se esconde y se dispara
                        desde un botón propio. No es capricho: el control nativo
                        escribe al lado «Sin archivos seleccionados», un texto
                        que pone el navegador, que no se puede cambiar y que en
                        esta columna angosta se corta en «Sin ar…nados».

                        `sr-only` lo saca de la vista pero lo deja en el DOM y
                        accesible: los lectores de pantalla y el <label> del
                        Campo lo siguen encontrando por su id.
                    */}
                    <input
                        ref={inputFoto}
                        id="foto"
                        type="file"
                        accept={archivos.aceptaImagen}
                        className="sr-only"
                        // e.target.files es una lista aunque solo se acepte uno.
                        onChange={(e) => onElegirFoto(e.target.files?.[0] ?? null)}
                        aria-invalid={Boolean(errorFoto)}
                    />

                    <div className="flex flex-wrap items-center gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => inputFoto.current?.click()}
                        >
                            <ImageUp className="size-4" />
                            {foto ? 'Cambiar foto' : 'Elegir foto'}
                        </Button>

                        {foto && (
                            <Button type="button" variant="ghost" size="sm" onClick={onQuitarFoto}>
                                <Trash2 className="size-4" />
                                Quitar
                            </Button>
                        )}
                    </div>

                    {/* min-w-0 + truncate: el nombre de un escaneo puede ser
                        larguísimo y estiraría la tarjeta entera. */}
                    {datos.foto && (
                        <p className="flex min-w-0 items-center gap-1.5 text-xs text-muted-foreground">
                            <Paperclip className="size-3.5 shrink-0" />
                            <span className="truncate">{datos.foto.name}</span>
                        </p>
                    )}
                </Campo>

                {!foto && (
                    <p className="text-xs text-muted-foreground">
                        Sin foto no se puede emitir la cédula de pescador, pero el
                        solicitante igual se puede registrar y cargarla después.
                    </p>
                )}
            </CardContent>
        </Card>
    );
}

/**
 * Junta las cinco partes del nombre para mostrarlas.
 *
 * Repite la regla de Solicitante::nombreCompleto() en PHP. La duplicación es a
 * propósito y no se puede evitar: la del servidor es la que vale y la que se
 * guarda; esta solo existe para que el operador VEA el resultado antes de
 * enviar, sin ir y volver al servidor en cada tecla.
 */
function armarNombre(datos: FormularioSolicitante): string {
    const partes = [
        datos.primerNombre,
        datos.segundoNombre,
        datos.apellidoPaterno,
        datos.apellidoMaterno,
    ];

    if (datos.apellidoCasada.trim()) {
        partes.push(`de ${datos.apellidoCasada.trim()}`);
    }

    return partes
        .map((p) => p.trim())
        .filter(Boolean)
        .join(' ');
}

/**
 * Bloque de campos con título. Ordena el formulario en partes legibles en
 * lugar de dejar veinte campos seguidos.
 *
 * `columnas` permite cambiar el reparto de la grilla por sección: no todos los
 * campos merecen el mismo ancho —un complemento de dos caracteres al lado de
 * una cédula de siete dígitos se lee mal si ocupan lo mismo—.
 */
function Seccion({
    titulo,
    descripcion,
    columnas = 'sm:grid-cols-2',
    children,
}: {
    titulo: string;
    descripcion?: string;
    columnas?: string;
    children: ReactNode;
}) {
    return (
        <Card>
            <CardContent className="p-5">
                <div className="mb-4">
                    <h2 className="font-semibold">{titulo}</h2>
                    {descripcion && <p className="text-sm text-muted-foreground">{descripcion}</p>}
                </div>

                <div className={cn('grid gap-4', columnas)}>{children}</div>
            </CardContent>
        </Card>
    );
}
