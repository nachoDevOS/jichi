import { Head, router, useForm } from '@inertiajs/react';
import { Building2, Pencil, Plus, Search, X } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Campo } from '@/components/ui/campo';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { EstadoVacio } from '@/components/ui/estado-vacio';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { usePermisos } from '@/hooks/use-permisos';
import LayoutPanel from '@/layouts/layout-panel';
import type { OpcionEnum } from '@/types';
import type { AsociacionFila } from '@/types/catalogos';

/**
 * ============================================================================
 *  CATÁLOGO DE ASOCIACIONES — y el patrón de las tres pantallas de catálogo
 * ============================================================================
 *
 * ----------------------------------------------------------------------------
 *  LA TABLA OCUPA EL ANCHO ENTERO Y EL FORMULARIO SE ABRE ARRIBA
 * ----------------------------------------------------------------------------
 *
 * El formulario sigue en la MISMA pantalla que la lista —navegar a un alta y
 * volver hace perder de vista aquello contra lo que se compara mientras se
 * carga: «¿ya está ASOPESCA?», «¿este tramo se pisa con el anterior?»— pero va
 * ARRIBA y no en una columna al costado.
 *
 * Dos motivos. Uno: la tabla necesita el ancho, igual que la del padrón — con
 * un tercio de la pantalla robado, las columnas se aprietan y la de «en uso» se
 * corta. Dos: a lo ancho los campos entran en UNA fila en vez de apilarse en
 * una columna angosta, así que el formulario ocupa menos alto del que ocupaba
 * al costado.
 *
 * Es lo contrario de Beneficiarios, que sí tiene pantallas aparte: ahí el
 * formulario tiene veinte campos y una foto, y no entra en una fila.
 *
 * ----------------------------------------------------------------------------
 *  UN SOLO ESTADO DECIDE TODO: `editando`
 * ----------------------------------------------------------------------------
 *
 *   null      -> el formulario está cerrado
 *   'nueva'   -> alta
 *   <fila>    -> edición de esa fila
 *
 * Con tres booleanos sueltos —`abierto`, `esAlta`, `filaActual`— se pueden
 * combinar en estados imposibles: abierto sin fila y sin ser alta. Un único
 * valor no lo permite.
 *
 * ----------------------------------------------------------------------------
 *  NO HAY BOTÓN DE BORRAR, Y ES LA REGLA
 * ----------------------------------------------------------------------------
 *
 * Los carnets y las guías emitidas apuntan acá. Una asociación que se deja de
 * usar se pone INACTIVA: desaparece de los desplegables de alta y los
 * documentos históricos la siguen mostrando.
 *
 * La columna «En uso» está para que eso se entienda solo: con 12 carnets
 * colgando, que no haya papelera deja de parecer un descuido.
 */
export default function CatalogoAsociaciones({
    asociaciones,
    filtros,
    estados,
}: {
    asociaciones: AsociacionFila[];
    filtros: { buscar: string | null };
    estados: OpcionEnum[];
}) {
    const { puede } = usePermisos();
    const [buscar, setBuscar] = useState(filtros.buscar ?? '');
    const [editando, setEditando] = useState<AsociacionFila | 'nueva' | null>(null);

    function filtrar(e: FormEvent) {
        e.preventDefault();
        router.get(route('asociaciones.index'), { buscar }, {
            preserveState: true,
            preserveScroll: true,
            // replace evita llenar el historial con una entrada por búsqueda:
            // «atrás» debe volver a la pantalla anterior, no a la búsqueda
            // anterior.
            replace: true,
        });
    }

    return (
        <LayoutPanel
            titulo="Asociaciones"
            descripcion="Los gremios que certifican al beneficiario. Se imprimen en el carnet y en las guías."
            acciones={
                puede('catalogos.gestionar') && (
                    <Button onClick={() => setEditando('nueva')}>
                        <Plus className="size-4" />
                        Nueva asociación
                    </Button>
                )
            }
        >
            <Head title="Asociaciones" />

            <div className="space-y-6">
                {/* --------------------------------------------------- Formulario */}
                {editando !== null && puede('catalogos.gestionar') && (
                    <FormularioAsociacionCard
                        // La `key` fuerza a React a rehacer el componente al
                        // cambiar de fila. Sin ella reutilizaría el mismo, y el
                        // useForm de adentro conservaría los valores de la fila
                        // anterior: se abriría «editar ASOPESCA» con los datos
                        // de APREMA cargados.
                        key={editando === 'nueva' ? 'nueva' : editando.id}
                        asociacion={editando === 'nueva' ? null : editando}
                        estados={estados}
                        onCerrar={() => setEditando(null)}
                    />
                )}

                {/* ------------------------------------------------------ Tabla */}
                {/*
                    `min-w-0` NO ES DECORACIÓN. Un elemento de grilla arranca con
                    `min-width: auto` —«no te encojas por debajo de tu
                    contenido»— así que la tarjeta se estiraría al ancho de la
                    tabla y el que terminaría con barra de desplazamiento sería
                    el DOCUMENTO ENTERO. En el celular eso corre de costado el
                    menú y el encabezado para leer una columna.
                */}
                <Card className="min-w-0">
                    <CardHeader className="gap-3">
                        <CardTitle>Registradas</CardTitle>

                        {/* El buscador no crece con la tarjeta: una caja de texto de 1400 px
                            no se lee mejor, solo se ve rara. */}
                        <form onSubmit={filtrar} className="flex gap-2 sm:max-w-md">
                            <Input
                                value={buscar}
                                onChange={(e) => setBuscar(e.target.value)}
                                placeholder="Buscar por nombre o sigla…"
                            />
                            <Button type="submit" variant="outline">
                                <Search className="size-4" />
                            </Button>
                        </form>
                    </CardHeader>

                    <CardContent className="p-0">
                        {asociaciones.length === 0 ? (
                            <EstadoVacio
                                icono={Building2}
                                titulo="Sin asociaciones"
                                descripcion={
                                    filtros.buscar
                                        ? 'Ninguna coincide con la búsqueda.'
                                        : 'Cargue los gremios de la resolución: sin al menos uno no se puede emitir un carnet.'
                                }
                            />
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="border-y border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                                        <tr>
                                            <th className="px-5 py-2.5 font-medium">Asociación</th>
                                            <th className="px-5 py-2.5 font-medium">Estado</th>
                                            <th className="px-5 py-2.5 text-right font-medium">En uso</th>
                                            <th className="px-5 py-2.5" />
                                        </tr>
                                    </thead>

                                    <tbody className="divide-y divide-border">
                                        {asociaciones.map((a) => (
                                            <tr key={a.id} className="hover:bg-secondary/50">
                                                <td className="px-5 py-2.5">
                                                    <p className="font-medium">{a.nombre}</p>
                                                    {a.sigla && (
                                                        <p className="font-mono text-xs text-muted-foreground">
                                                            {a.sigla}
                                                        </p>
                                                    )}
                                                </td>

                                                <td className="px-5 py-2.5">
                                                    <Badge color={a.estado_color}>{a.estado_etiqueta}</Badge>
                                                </td>

                                                {/*
                                                    Los dos conteos juntos: es lo que
                                                    explica por qué no hay papelera.
                                                */}
                                                <td className="px-5 py-2.5 text-right tabular-nums text-muted-foreground">
                                                    {a.carnets_count + a.guias_count === 0 ? (
                                                        <span className="opacity-60">—</span>
                                                    ) : (
                                                        <>
                                                            {a.carnets_count} carnet(s)
                                                            <br />
                                                            <span className="text-xs">
                                                                {a.guias_count} guía(s)
                                                            </span>
                                                        </>
                                                    )}
                                                </td>

                                                <td className="px-5 py-2.5 text-right">
                                                    {puede('catalogos.gestionar') && (
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => setEditando(a)}
                                                        >
                                                            <Pencil className="size-4" />
                                                        </Button>
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>

            </div>
        </LayoutPanel>
    );
}

/**
 * El formulario de alta y edición.
 *
 * Es el MISMO para los dos casos, igual que el Request del servidor comparte
 * las reglas: escrito dos veces, alcanza con tocar uno para que crear y editar
 * acepten cosas distintas.
 */
function FormularioAsociacionCard({
    asociacion,
    estados,
    onCerrar,
}: {
    asociacion: AsociacionFila | null;
    estados: OpcionEnum[];
    onCerrar: () => void;
}) {
    const esAlta = asociacion === null;

    const form = useForm({
        nombre: asociacion?.nombre ?? '',
        sigla: asociacion?.sigla ?? '',
        estado: asociacion?.estado ?? 'activo',
    });

    function enviar(e: FormEvent) {
        e.preventDefault();

        const opciones = {
            preserveScroll: true,
            // Al guardar bien, el panel se cierra solo: dejarlo abierto con los
            // datos ya guardados invita a apretar otra vez y crear un duplicado.
            onSuccess: () => onCerrar(),
        };

        if (esAlta) {
            form.post(route('asociaciones.store'), opciones);
        } else {
            form.put(route('asociaciones.update', asociacion.id), opciones);
        }
    }

    return (
        <Card>
            <CardHeader className="flex-row items-center justify-between gap-2 space-y-0">
                <CardTitle>{esAlta ? 'Nueva asociación' : 'Editar asociación'}</CardTitle>

                <Button variant="ghost" size="sm" onClick={onCerrar} aria-label="Cerrar">
                    <X className="size-4" />
                </Button>
            </CardHeader>

            <CardContent>
                <form onSubmit={enviar} className="space-y-4">
                    {/* A lo ancho los tres campos entran en una fila. */}
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <Campo etiqueta="Nombre" htmlFor="nombre" error={form.errors.nombre} obligatorio>
                        <Input
                            id="nombre"
                            value={form.data.nombre}
                            onChange={(e) => form.setData('nombre', e.target.value)}
                            aria-invalid={Boolean(form.errors.nombre)}
                            placeholder="Asociación de Pescadores de Trinidad"
                        />
                    </Campo>

                    <Campo
                        etiqueta="Sigla"
                        htmlFor="sigla"
                        error={form.errors.sigla}
                        ayuda="Es lo que entra en el renglón angosto del carnet. El servidor la guarda en mayúsculas."
                    >
                        <Input
                            id="sigla"
                            value={form.data.sigla}
                            onChange={(e) => form.setData('sigla', e.target.value.toUpperCase())}
                            aria-invalid={Boolean(form.errors.sigla)}
                            placeholder="ASOPESTRI"
                            className="font-mono"
                        />
                    </Campo>

                    <Campo
                        etiqueta="Estado"
                        htmlFor="estado"
                        error={form.errors.estado}
                        ayuda="Una asociación inactiva no aparece al emitir, pero los documentos ya emitidos la siguen mostrando."
                        obligatorio
                    >
                        <Select
                            id="estado"
                            value={form.data.estado}
                            onChange={(e) => form.setData('estado', e.target.value as typeof form.data.estado)}
                            aria-invalid={Boolean(form.errors.estado)}
                        >
                            {estados.map((o) => (
                                <option key={o.value} value={o.value}>
                                    {o.label}
                                </option>
                            ))}
                        </Select>
                    </Campo>
                    </div>

                    <div className="flex gap-2 pt-1">
                        <Button type="submit" disabled={form.processing}>
                            {esAlta ? 'Registrar' : 'Guardar cambios'}
                        </Button>

                        <Button type="button" variant="outline" onClick={onCerrar}>
                            Cancelar
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}
