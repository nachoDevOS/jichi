import { router } from '@inertiajs/react';
import { Search, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import type { FiltrosTramites as Filtros, OpcionEstado } from '@/types/tramites';

/**
 * Barra de búsqueda del listado de trámites.
 *
 * CÓMO FUNCIONA UNA BÚSQUEDA EN INERTIA
 *
 * No se filtra en el navegador. Se le vuelve a pedir la página a Laravel con
 * el término puesto en la URL:
 *
 *     /panel/tramites?buscar=perez
 *
 * El controlador lo lee, arma la consulta SQL y devuelve la página ya filtrada
 * y paginada. Filtrar en el servidor es lo correcto acá: la tabla `tramites`
 * crece todos los días y no se pueden mandar todas las filas al navegador para
 * recortarlas ahí.
 *
 * Es el mismo componente que el de solicitantes, con otra ruta y otro texto de
 * ayuda. Están separados a propósito: el día que el listado de trámites sume un
 * filtro por estado o por fecha —que lo va a sumar—, ese filtro no tiene nada
 * que hacer en la pantalla del padrón.
 */
export function FiltrosTramites({
    filtros,
    opcionesPorPagina,
    opcionesEstado,
}: {
    /** Lo que el controlador devolvió: sirve para dejar los campos como estaban. */
    filtros: Filtros;
    /**
     * Cuántas filas se puede elegir mostrar. La lista la manda el controlador,
     * que es el único que la valida: escribirla también acá sería tener la
     * misma regla en dos lados, y tarde o temprano una se queda vieja.
     */
    opcionesPorPagina: number[];
    /**
     * Los estados del circuito, tal como los define App\Enums\EstadoTramite.
     * Por el mismo motivo que arriba: agregar un estado tiene que alcanzar con
     * tocar el enum.
     */
    opcionesEstado: OpcionEstado[];
}) {
    /*
     * El texto se guarda en el componente para que se vea al instante mientras
     * se escribe. Si dependiera solo de la respuesta del servidor, las letras
     * aparecerían con retraso.
     */
    const [buscar, setBuscar] = useState(filtros.buscar ?? '');

    /*
     * useRef guarda un valor que sobrevive entre pintados pero que, al cambiar,
     * NO provoca un repintado. Acá se usa como bandera para saber si es la
     * primera vez que se ejecuta el efecto.
     */
    const primerRender = useRef(true);

    /*
     * "DEBOUNCE": esperar a que el operador deje de escribir.
     *
     * Sin esto, escribir "perez" dispararía cinco consultas a la base de datos,
     * una por letra. Con esto se dispara una sola, 350 ms después de la última
     * tecla.
     *
     * Cómo lo logra: cada vez que `buscar` cambia, React ejecuta la limpieza
     * del efecto anterior (el return) y eso CANCELA el temporizador pendiente.
     * Solo sobrevive el último, el que ya no fue cancelado.
     */
    useEffect(() => {
        // En el primer render no hay que pedir nada: la página ya llegó
        // filtrada desde el servidor.
        if (primerRender.current) {
            primerRender.current = false;

            return;
        }

        const temporizador = setTimeout(
            () => aplicar({ termino: buscar, porPagina: filtros.por_pagina, estado: filtros.estado }),
            350,
        );

        return () => clearTimeout(temporizador);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [buscar]);

    /**
     * Pide la página de nuevo con lo que haya elegido el operador.
     *
     * Lo que está en su valor de siempre se SACA de la dirección en vez de
     * mandarse igual: así queda /panel/tramites y no
     * /panel/tramites?buscar=&por_pagina=15. El servidor, cuando no recibe
     * nada, usa exactamente esos mismos valores.
     *
     * Tampoco se manda la página: cualquier cambio vuelve a la primera. Si se
     * conservara, alguien parado en la página 4 buscaría un apellido con tres
     * resultados —o pasaría a 50 filas, donde todo entra en una sola— y vería
     * una tabla vacía.
     */
    function aplicar({
        termino,
        porPagina,
        estado,
    }: {
        termino: string;
        porPagina: number;
        estado: string | null;
    }) {
        const parametros: Record<string, string | number> = {};

        if (termino.trim() !== '') {
            parametros.buscar = termino.trim();
        }

        // Sin estado quiere decir «todos», y eso es lo que hace el servidor
        // cuando no recibe nada: no hace falta mandarlo.
        if (estado !== null && estado !== '') {
            parametros.estado = estado;
        }

        // La primera opción es la que el servidor usa por defecto.
        if (porPagina !== opcionesPorPagina[0]) {
            parametros.por_pagina = porPagina;
        }

        router.get(route('tramites.index'), parametros, {
            // preserveState: no reinicia lo escrito en el buscador.
            preserveState: true,
            // preserveScroll: la página no salta al principio.
            preserveScroll: true,
            // replace: no deja una entrada nueva en el historial por cada
            // letra, así el botón «atrás» vuelve al listado y no retrocede
            // búsqueda por búsqueda.
            replace: true,
        });
    }

    /**
     * Limpiar quita los dos filtros —lo escrito y el estado elegido— pero NO
     * las filas por página.
     *
     * Son cosas distintas: el filtro dice qué trámites se miran y el tamaño
     * dice cuántos entran en pantalla. A nadie le sirve que al soltar un
     * apellido la tabla vuelva sola de 50 filas a 15.
     */
    function limpiar() {
        setBuscar('');
        aplicar({ termino: '', porPagina: filtros.por_pagina, estado: null });
    }

    const hayFiltros = Boolean(filtros.buscar) || Boolean(filtros.estado);

    return (
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
            {/*
                El selector de filas por página, a la izquierda.

                No lleva espera de 350 ms como el buscador: acá no se escribe
                letra por letra, se elige una opción y esa es la decisión
                final. Consultar en el acto es lo correcto.
            */}
            <div className="flex shrink-0 items-center gap-2">
                <label
                    htmlFor="filas-por-pagina"
                    className="text-sm whitespace-nowrap text-muted-foreground"
                >
                    Mostrar
                </label>

                <Select
                    id="filas-por-pagina"
                    value={filtros.por_pagina}
                    onChange={(e) =>
                        aplicar({
                            termino: buscar,
                            porPagina: Number(e.target.value),
                            estado: filtros.estado,
                        })
                    }
                    className="w-24"
                >
                    {opcionesPorPagina.map((cantidad) => (
                        <option key={cantidad} value={cantidad}>
                            {cantidad}
                        </option>
                    ))}
                </Select>
            </div>

            {/*
                El filtro por estado, pegado a la izquierda del buscador.

                `ml-auto` está acá y no en el buscador: este es el primero del
                grupo de la derecha, así que es el que empuja a los tres
                —estado, búsqueda y el botón de limpiar— contra el borde.

                La opción vacía es «todos». Va con value="" y no con la palabra
                «todos» porque un estado vacío es exactamente lo que el
                servidor interpreta como sin filtrar; inventar un valor
                obligaría a traducirlo de un lado o del otro.
            */}
            <div className="flex shrink-0 items-center gap-2 sm:ml-auto">
                <label
                    htmlFor="estado-tramite"
                    className="text-sm whitespace-nowrap text-muted-foreground sm:sr-only"
                >
                    Estado
                </label>

                <Select
                    id="estado-tramite"
                    value={filtros.estado ?? ''}
                    onChange={(e) =>
                        aplicar({
                            termino: buscar,
                            porPagina: filtros.por_pagina,
                            estado: e.target.value || null,
                        })
                    }
                    className="w-40"
                >
                    <option value="">Todos</option>

                    {opcionesEstado.map((opcion) => (
                        <option key={opcion.value} value={opcion.value}>
                            {opcion.label}
                        </option>
                    ))}
                </Select>
            </div>

            {/*
                El buscador, ocupando un tercio de la barra —las 4 columnas de
                12 que se pidieron—. El margen que empuja el grupo a la derecha
                está en el selector de estado, que es el primero de los tres.

                En pantalla angosta el contenedor es `flex-col` y el ancho no
                aplica: el campo ocupa la línea entera, que es lo que
                corresponde en un teléfono.
            */}
            <div className="relative sm:w-1/3">
                <Search className="pointer-events-none absolute top-3 left-3 size-4 text-muted-foreground" />

                <Input
                    type="search"
                    value={buscar}
                    onChange={(e) => setBuscar(e.target.value)}
                    // El texto de ayuda se acortó al angostarse el campo: en un
                    // tercio del ancho, la lista completa de lo que se puede
                    // buscar se cortaba a la mitad y no se entendía nada.
                    placeholder="Buscar N°, solicitante, registro…"
                    className="pl-9"
                    aria-label="Buscar trámites"
                />
            </div>

            {/* Aparece cuando hay algo que soltar: texto buscado, estado
                elegido, o los dos. */}
            {hayFiltros && (
                <Button variant="ghost" onClick={limpiar} title="Quitar los filtros">
                    <X className="size-4" />
                    Limpiar
                </Button>
            )}
        </div>
    );
}
