import { router, useForm } from '@inertiajs/react';
import { Camera, IdCard, Phone, User, UserRound, X } from 'lucide-react';
import { useState, type FormEvent, type ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Campo } from '@/components/ui/campo';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useArchivos } from '@/hooks/use-archivos';
import { cn } from '@/lib/utils';
import { edadEnAnios, fecha } from '@/lib/utils';
import type { BeneficiarioFicha, FormularioBeneficiario } from '@/types/beneficiarios';

/**
 *  EL FORMULARIO DE BENEFICIARIO
 */
export function FormularioBeneficiarioComponente({
    beneficiario,
    expedidos,
    provincias,
}: {
    /** Null en el alta; la ficha cargada en la edición. */
    beneficiario?: (Partial<BeneficiarioFicha> & { id: number }) | null;
    expedidos: { value: string; label: string }[];
    provincias: string[];
}) {
    const editando = Boolean(beneficiario?.id);
    const { aceptaImagen, ayudaPeso, validar } = useArchivos();

    /*
     * useForm de Inertia guarda los valores, los errores que devuelve Laravel y
     * si se está enviando. No hace falta un useState por campo ni escribir
     * fetch(): `post()` manda un POST normal con el token CSRF.
     */
    const form = useForm<FormularioBeneficiario>({
        ci: beneficiario?.ci ?? '',
        complemento: beneficiario?.complemento ?? '',
        expedido: beneficiario?.expedido ?? '',
        primerNombre: beneficiario?.primerNombre ?? '',
        segundoNombre: beneficiario?.segundoNombre ?? '',
        apellidoPaterno: beneficiario?.apellidoPaterno ?? '',
        apellidoMaterno: beneficiario?.apellidoMaterno ?? '',
        apellidoCasado: beneficiario?.apellidoCasado ?? '',
        fechaNacimiento: beneficiario?.fechaNacimiento ?? '',
        genero: beneficiario?.genero ?? '',
        nacionalidad: beneficiario?.nacionalidad ?? 'Boliviana',
        direccion: beneficiario?.direccion ?? '',
        ciudad: beneficiario?.ciudad ?? '',
        provincia: beneficiario?.provincia ?? '',
        telefono: beneficiario?.telefono ?? '',
        email: beneficiario?.email ?? '',
        foto: null,
        quitar_foto: false,
        // Los formularios HTML solo saben GET y POST. Para que Laravel lo trate
        // como PUT hay que mandar este campo oculto: es lo que permite subir
        // archivos en una edición, cosa que router.put() no puede hacer.
        ...(editando ? { _method: 'put' as const } : {}),
    });

    const [vistaPrevia, setVistaPrevia] = useState<string | null>(beneficiario?.foto_url ?? null);
    const [errorFoto, setErrorFoto] = useState<string | null>(null);

    function elegirFoto(archivo: File | null) {
        setErrorFoto(null);

        if (!archivo) {
            return;
        }

        // Se comprueba en el navegador para no hacerle esperar al operador la
        // subida entera de un archivo que el servidor va a rechazar igual. Las
        // reglas de verdad están en GuardarBeneficiarioRequest y, como última
        // defensa, en StorageController::verificarPeso().
        const error = validar(archivo, { soloImagen: true });

        if (error) {
            setErrorFoto(error);

            return;
        }

        form.setData((datos) => ({ ...datos, foto: archivo, quitar_foto: false }));
        setVistaPrevia(URL.createObjectURL(archivo));
    }

    function quitarFoto() {
        form.setData((datos) => ({ ...datos, foto: null, quitar_foto: true }));
        setVistaPrevia(null);
    }

    function enviar(e: FormEvent) {
        e.preventDefault();

        // POST en los dos casos: el _method de arriba es lo que convierte este
        // POST en un PUT del lado de Laravel.
        form.post(
            editando
                ? route('beneficiarios.update', beneficiario!.id)
                : route('beneficiarios.store'),
            { forceFormData: true },
        );
    }

    return (
        <form onSubmit={enviar}>
            <div className="grid gap-6 lg:grid-cols-3">
                {/* ============================================ Los campos */}
                <Card className="h-fit lg:col-span-2">
                    <Seccion
                        numero={1}
                        icono={IdCard}
                        titulo="Documento de identidad"
                        descripcion="Tal como figura en la cédula."
                    >
                        <div className="grid gap-4 sm:grid-cols-4">
                            <Campo
                                etiqueta="Cédula de identidad"
                                htmlFor="ci"
                                obligatorio
                                error={form.errors.ci}
                                className="sm:col-span-2"
                            >
                                <Input
                                    id="ci"
                                    inputMode="numeric"
                                    autoFocus={!editando}
                                    value={form.data.ci}
                                    onChange={(e) => form.setData('ci', e.target.value)}
                                    aria-invalid={Boolean(form.errors.ci)}
                                />
                            </Campo>

                            <Campo
                                etiqueta="Complemento"
                                htmlFor="complemento"
                                error={form.errors.complemento}
                                ayuda="Solo si la cédula lo tiene"
                            >
                                <Input
                                    id="complemento"
                                    maxLength={5}
                                    value={form.data.complemento}
                                    // Se pasa a mayúscula mientras se escribe: así se
                                    // guarda, y así el operador ve desde el principio
                                    // lo que va a quedar.
                                    onChange={(e) =>
                                        form.setData('complemento', e.target.value.toUpperCase())
                                    }
                                />
                            </Campo>

                            <Campo etiqueta="Expedido en" htmlFor="expedido" error={form.errors.expedido}>
                                <Select
                                    id="expedido"
                                    value={form.data.expedido}
                                    onChange={(e) => form.setData('expedido', e.target.value)}
                                >
                                    <option value="">—</option>
                                    {expedidos.map((opcion) => (
                                        <option key={opcion.value} value={opcion.value}>
                                            {opcion.label}
                                        </option>
                                    ))}
                                </Select>
                            </Campo>
                        </div>
                    </Seccion>

                    <Seccion
                        numero={2}
                        icono={UserRound}
                        titulo="Nombre"
                        // Se explica acá arriba y no campo por campo: es la razón
                        // de que sean cinco cajas y no una, y conviene que se lea
                        // antes de empezar a escribir.
                        descripcion="Va partido en cinco campos porque así viene en la cédula. El sistema lo arma al imprimir."
                    >
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Campo
                                etiqueta="Primer nombre"
                                htmlFor="primerNombre"
                                obligatorio
                                error={form.errors.primerNombre}
                            >
                                <Input
                                    id="primerNombre"
                                    value={form.data.primerNombre}
                                    onChange={(e) => form.setData('primerNombre', e.target.value)}
                                    aria-invalid={Boolean(form.errors.primerNombre)}
                                />
                            </Campo>

                            <Campo
                                etiqueta="Segundo nombre"
                                htmlFor="segundoNombre"
                                error={form.errors.segundoNombre}
                                ayuda="Déjelo vacío si no tiene"
                            >
                                <Input
                                    id="segundoNombre"
                                    value={form.data.segundoNombre}
                                    onChange={(e) => form.setData('segundoNombre', e.target.value)}
                                />
                            </Campo>

                            <Campo
                                etiqueta="Apellido paterno"
                                htmlFor="apellidoPaterno"
                                obligatorio
                                error={form.errors.apellidoPaterno}
                            >
                                <Input
                                    id="apellidoPaterno"
                                    value={form.data.apellidoPaterno}
                                    onChange={(e) => form.setData('apellidoPaterno', e.target.value)}
                                    aria-invalid={Boolean(form.errors.apellidoPaterno)}
                                />
                            </Campo>

                            <Campo
                                etiqueta="Apellido materno"
                                htmlFor="apellidoMaterno"
                                error={form.errors.apellidoMaterno}
                            >
                                <Input
                                    id="apellidoMaterno"
                                    value={form.data.apellidoMaterno}
                                    onChange={(e) => form.setData('apellidoMaterno', e.target.value)}
                                />
                            </Campo>

                            <Campo
                                etiqueta="Apellido de casada"
                                htmlFor="apellidoCasado"
                                error={form.errors.apellidoCasado}
                                // El «de» NO se escribe: lo agrega el sistema al
                                // armar el nombre. Guardado con el «de» adentro,
                                // buscar «Justiniano» no encontraría a la persona.
                                ayuda="Escríbalo sin el «de». El sistema lo agrega solo."
                                className="sm:col-span-2"
                            >
                                <Input
                                    id="apellidoCasado"
                                    value={form.data.apellidoCasado}
                                    onChange={(e) => form.setData('apellidoCasado', e.target.value)}
                                />
                            </Campo>
                        </div>
                    </Seccion>

                    <Seccion numero={3} icono={User} titulo="Datos personales">
                        <div className="grid gap-4 sm:grid-cols-3">
                            <Campo
                                etiqueta="Fecha de nacimiento"
                                htmlFor="fechaNacimiento"
                                obligatorio
                                error={form.errors.fechaNacimiento}
                            >
                                <Input
                                    id="fechaNacimiento"
                                    type="date"
                                    // El navegador no deja elegir una fecha futura,
                                    // que es el error de tipeo más común —escribir
                                    // el año en curso en vez del de nacimiento—.
                                    // El servidor lo valida igual con `before:today`.
                                    max={new Date().toISOString().slice(0, 10)}
                                    value={form.data.fechaNacimiento}
                                    onChange={(e) => form.setData('fechaNacimiento', e.target.value)}
                                    aria-invalid={Boolean(form.errors.fechaNacimiento)}
                                />
                            </Campo>

                            <Campo etiqueta="Género" htmlFor="genero" error={form.errors.genero}>
                                <Select
                                    id="genero"
                                    value={form.data.genero}
                                    onChange={(e) => form.setData('genero', e.target.value)}
                                >
                                    <option value="">—</option>
                                    <option value="masculino">Masculino</option>
                                    <option value="femenino">Femenino</option>
                                </Select>
                            </Campo>

                            <Campo
                                etiqueta="Nacionalidad"
                                htmlFor="nacionalidad"
                                error={form.errors.nacionalidad}
                            >
                                <Input
                                    id="nacionalidad"
                                    value={form.data.nacionalidad}
                                    onChange={(e) => form.setData('nacionalidad', e.target.value)}
                                />
                            </Campo>
                        </div>
                    </Seccion>

                    <Seccion
                        numero={4}
                        icono={Phone}
                        titulo="Contacto"
                        descripcion="Todo opcional, pero el teléfono sirve para avisar cuando el carnet está listo."
                    >
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Campo etiqueta="Teléfono" htmlFor="telefono" error={form.errors.telefono}>
                                <Input
                                    id="telefono"
                                    inputMode="tel"
                                    value={form.data.telefono}
                                    onChange={(e) => form.setData('telefono', e.target.value)}
                                />
                            </Campo>

                            <Campo etiqueta="Correo electrónico" htmlFor="email" error={form.errors.email}>
                                <Input
                                    id="email"
                                    type="email"
                                    value={form.data.email}
                                    onChange={(e) => form.setData('email', e.target.value)}
                                />
                            </Campo>

                            <Campo etiqueta="Ciudad" htmlFor="ciudad" error={form.errors.ciudad}>
                                <Input
                                    id="ciudad"
                                    value={form.data.ciudad}
                                    onChange={(e) => form.setData('ciudad', e.target.value)}
                                />
                            </Campo>

                            <Campo
                                etiqueta="Provincia"
                                htmlFor="provincia"
                                error={form.errors.provincia}
                                // Lista abierta a propósito: el beneficiario puede
                                // vivir fuera del Beni y las ocho provincias son
                                // solo las de acá. Por eso es un datalist —sugiere
                                // pero no obliga— y no un <select>.
                                ayuda="Puede escribir otra si vive fuera del Beni"
                            >
                                <Input
                                    id="provincia"
                                    list="provincias-beni"
                                    value={form.data.provincia}
                                    onChange={(e) => form.setData('provincia', e.target.value)}
                                />
                            </Campo>

                            <datalist id="provincias-beni">
                                {provincias.map((p) => (
                                    <option key={p} value={p} />
                                ))}
                            </datalist>

                            <Campo
                                etiqueta="Dirección"
                                htmlFor="direccion"
                                error={form.errors.direccion}
                                className="sm:col-span-2"
                            >
                                <Textarea
                                    id="direccion"
                                    rows={2}
                                    value={form.data.direccion}
                                    onChange={(e) => form.setData('direccion', e.target.value)}
                                />
                            </Campo>
                        </div>
                    </Seccion>
                </Card>

                {/* ========================================== La vista previa */}
                <div className="lg:col-span-1">
                    {/*
                        sticky + top-20: la tarjeta acompaña el scroll y queda
                        debajo de la barra superior del panel. Así el operador ve
                        cómo va quedando la ficha mientras carga los campos de
                        abajo, que es justamente cuando ya no la tendría a la
                        vista.
                    */}
                    <Card className="lg:sticky lg:top-20">
                        <CardContent className="space-y-5 pt-5">
                            <Retrato
                                url={vistaPrevia}
                                aceptaImagen={aceptaImagen}
                                ayudaPeso={ayudaPeso}
                                error={errorFoto ?? form.errors.foto}
                                onElegir={elegirFoto}
                                onQuitar={quitarFoto}
                            />

                            <div className="space-y-3 border-t border-border pt-4 text-sm">
                                <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                    Así va a quedar
                                </p>

                                <Compuesto
                                    etiqueta="Nombre completo"
                                    valor={componerNombre(form.data)}
                                    vacio="Cargue el nombre y los apellidos"
                                />

                                <Compuesto
                                    etiqueta="Documento"
                                    valor={componerDocumento(form.data)}
                                    vacio="Cargue la cédula"
                                    mono
                                />

                                <Compuesto
                                    etiqueta="Edad"
                                    valor={
                                        form.data.fechaNacimiento
                                            ? `${edadEnAnios(form.data.fechaNacimiento)} años · ${fecha(form.data.fechaNacimiento)}`
                                            : ''
                                    }
                                    vacio="Cargue la fecha de nacimiento"
                                />
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>

            {/*
                BARRA DE ACCIONES FIJA AL PIE.
            */}
            <div className="sticky bottom-0 -mx-4 mt-6 border-t border-border bg-background/95 px-4 py-3 backdrop-blur sm:-mx-6 sm:px-6">
                <div className="flex flex-wrap items-center justify-end gap-2">
                    <p className="mr-auto text-xs text-muted-foreground">
                        Los campos marcados con
                        <span className="mx-0.5 text-destructive">*</span>
                        son obligatorios.
                    </p>

                    <Button
                        type="button"
                        variant="ghost"
                        disabled={form.processing}
                        onClick={() =>
                            router.visit(
                                editando
                                    ? route('beneficiarios.show', beneficiario!.id)
                                    : route('beneficiarios.index'),
                            )
                        }
                    >
                        Cancelar
                    </Button>

                    <Button type="submit" disabled={form.processing}>
                        {form.processing
                            ? 'Guardando…'
                            : editando
                              ? 'Guardar cambios'
                              : 'Registrar beneficiario'}
                    </Button>
                </div>
            </div>
        </form>
    );
}

/**
 * Un bloque del formulario: número, icono, título y los campos debajo.
 */
function Seccion({
    numero,
    icono: Icono,
    titulo,
    descripcion,
    children,
}: {
    numero: number;
    icono: typeof User;
    titulo: string;
    descripcion?: string;
    children: ReactNode;
}) {
    return (
        <section className={cn('p-5', numero > 1 && 'border-t border-border')}>
            <div className="mb-4 flex items-start gap-3">
                <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                    <Icono className="size-4" />
                </span>

                <div className="min-w-0">
                    <h2 className="font-semibold">
                        <span className="mr-1.5 text-muted-foreground">{numero}.</span>
                        {titulo}
                    </h2>
                    {descripcion && (
                        <p className="mt-0.5 text-sm text-muted-foreground">{descripcion}</p>
                    )}
                </div>
            </div>

            {children}
        </section>
    );
}

/**
 * La fotografía, recortada en círculo como sale en el carnet.
 */
function Retrato({
    url,
    aceptaImagen,
    ayudaPeso,
    error,
    onElegir,
    onQuitar,
}: {
    url: string | null;
    aceptaImagen: string;
    ayudaPeso: string;
    error?: string;
    onElegir: (archivo: File | null) => void;
    onQuitar: () => void;
}) {
    return (
        <div className="space-y-3 text-center">
            <div className="mx-auto flex size-32 items-center justify-center overflow-hidden rounded-full border-2 border-dashed border-border bg-muted">
                {url ? (
                    <img src={url} alt="Fotografía del beneficiario" className="size-full object-cover" />
                ) : (
                    <Camera className="size-8 text-muted-foreground" aria-hidden />
                )}
            </div>

            {/*
                El <input type="file"> nativo se esconde y se dispara desde el
                <label>. Es el único control que no se puede estilar de verdad:
                cada navegador dibuja su propio botón, con su propio texto y en su
                propio idioma. Con el label queda un botón igual al resto del
                sistema y en español siempre.
            */}
            <label
                htmlFor="foto"
                className="inline-flex cursor-pointer items-center gap-2 rounded-md border border-border bg-card px-3 py-2 text-sm font-medium transition-colors hover:bg-secondary"
            >
                <Camera className="size-4" />
                {url ? 'Cambiar fotografía' : 'Subir fotografía'}
            </label>

            <input
                id="foto"
                type="file"
                accept={aceptaImagen}
                className="sr-only"
                onChange={(e) => onElegir(e.target.files?.[0] ?? null)}
            />

            {url && (
                <Button type="button" variant="ghost" size="sm" onClick={onQuitar}>
                    <X className="size-4" />
                    Quitar
                </Button>
            )}

            {error ? (
                <p className="text-sm text-destructive" role="alert">
                    {error}
                </p>
            ) : (
                <p className="text-xs text-muted-foreground">
                    Se imprime en el carnet. JPG, PNG o WEBP, {ayudaPeso}.
                </p>
            )}
        </div>
    );
}

/** Un renglón de la vista previa: lo compuesto, o el aviso de qué falta. */
function Compuesto({
    etiqueta,
    valor,
    vacio,
    mono = false,
}: {
    etiqueta: string;
    valor: string;
    vacio: string;
    mono?: boolean;
}) {
    return (
        <div>
            <p className="text-xs text-muted-foreground">{etiqueta}</p>

            {valor ? (
                <p className={mono ? 'font-mono text-sm font-medium' : 'font-medium'}>{valor}</p>
            ) : (
                // El hueco dice QUÉ FALTA en vez de quedar en blanco: un espacio
                // vacío parece un error del sistema, y esto es una instrucción.
                <p className="text-sm italic text-muted-foreground/70">{vacio}</p>
            )}
        </div>
    );
}

/* ==========================================================================
   LAS TRES COMPOSICIONES DE LA VISTA PREVIA
   ========================================================================== */

function componerNombre(datos: FormularioBeneficiario): string {
    const partes = [
        datos.primerNombre,
        datos.segundoNombre,
        datos.apellidoPaterno,
        datos.apellidoMaterno,
    ];

    // El «de» se agrega acá, igual que en el modelo: se guarda sin él para que
    // buscar «Justiniano» encuentre a quien figura como «de Justiniano».
    if (datos.apellidoCasado.trim()) {
        partes.push(`de ${datos.apellidoCasado.trim()}`);
    }

    return partes
        .map((parte) => parte.trim())
        .filter(Boolean)
        .join(' ');
}

function componerDocumento(datos: FormularioBeneficiario): string {
    if (!datos.ci.trim()) {
        return '';
    }

    const cedula = datos.complemento.trim()
        ? `${datos.ci.trim()}-${datos.complemento.trim()}`
        : datos.ci.trim();

    return [cedula, datos.expedido].filter(Boolean).join(' ');
}

/*
 * La edad NO se calcula acá: vive en `lib/utils.ts`, junto a `fecha()`.
 */
