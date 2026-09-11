import { router } from '@inertiajs/react';
import { Search, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import type { FiltrosSolicitantes } from '@/types/solicitantes';

/**
 * Barra de búsqueda del listado de solicitantes.
 *
 * CÓMO FUNCIONA UNA BÚSQUEDA EN INERTIA
 *
 * No se filtra en el navegador. Se le vuelve a pedir la página a Laravel con
 * el término puesto en la URL:
 *
 *     /solicitantes?buscar=perez
 *
 * El controlador lo lee, arma la consulta SQL y devuelve la página ya
 * filtrada. Es la misma lógica de siempre en Laravel; lo único que cambia es
 * que Inertia reemplaza el contenido sin recargar el navegador.
 *
 * Filtrar en el servidor es lo correcto acá: la institución puede tener miles de
 * solicitantes y no se pueden mandar todos al navegador para filtrarlos ahí.
 */
export function FiltrosSolicitantes({
    filtros,
    opcionesPorPagina,
}: {
    /** Lo que el controlador devolvió: sirve para dejar los campos como estaban. */
    filtros: FiltrosSolicitantes;
    /**
     * Cuántas filas se puede elegir mostrar. La lista la manda el controlador,
     * que es el único que la valida: escribirla también acá sería tener la
     * misma regla en dos lados, y tarde o temprano una se queda vieja.
     */
    opcionesPorPagina: number[];
}) {
    /*
     * El texto del buscador se guarda en el componente para que se vea al
     * instante mientras se escribe. Si dependiera solo de la respuesta del
     * servidor, las letras aparecerían con retraso.
     */
    const [buscar, setBuscar] = useState(filtros.buscar ?? '');

    /*
     * useRef guarda un valor que sobrevive entre pintados pero que, al cambiar,
     * NO provoca un repintado. Acá se usa como bandera para saber si es la
     * primera vez que se ejecuta el efecto.
     */
    const primerRender = useRef(true);

    /*
     * "DEBOUNCE": esperar a que el usuario deje de escribir.
     *
     * Sin esto, escribir "perez" dispararía cinco consultas a la base de datos,
     * una por letra. Con esto se dispara una sola, 350 ms después de la última
     * tecla.
     *
     * Cómo lo logra: cada vez que `buscar` cambia, React ejecuta la limpieza
     * del efecto anterior (el return) y eso CANCELA el temporizador que estaba
     * pendiente. Solo sobrevive el último, el que ya no fue cancelado.
     */
    useEffect(() => {
        // En el primer render no hay que pedir nada: la página ya llegó filtrada.
        if (primerRender.current) {
            primerRender.current = false;
            return;
        }

        const temporizador = setTimeout(
            () => aplicar({ termino: buscar, porPagina: filtros.por_pagina }),
            350,
        );

        return () => clearTimeout(temporizador);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [buscar]);

    /**
     * Pide la página de nuevo con lo que haya elegido el operador.
     *
     * Lo que está en su valor de siempre se SACA de la dirección en vez de
     * mandarse igual: así queda /panel/solicitantes y no
     * /panel/solicitantes?buscar=&por_pagina=15. El servidor, cuando no recibe
     * nada, usa exactamente esos mismos valores.
     *
     * Tampoco se manda la página: cualquier cambio vuelve a la primera. Si se
     * conservara, alguien parado en la página 4 buscaría un apellido con tres
     * resultados —o pasaría a 50 filas, donde todo entra en una sola— y vería
     * una tabla vacía.
     */
    function aplicar({ termino, porPagina }: { termino: string; porPagina: number }) {
        const parametros: Record<string, string | number> = {};

        if (termino.trim() !== '') {
            parametros.buscar = termino.trim();
        }

        // La primera opción es la que el servidor usa por defecto.
        if (porPagina !== opcionesPorPagina[0]) {
            parametros.por_pagina = porPagina;
        }

        router.get(route('solicitantes.index'), parametros, {
            // preserveState: no reinicia lo escrito en el buscador.
            preserveState: true,
            // preserveScroll: la página no salta al principio.
            preserveScroll: true,
            // replace: no deja una entrada nueva en el historial por cada letra,
            // así el botón "atrás" del navegador vuelve al listado y no
            // retrocede búsqueda por búsqueda.
            replace: true,
        });
    }

    const hayBusqueda = Boolean(filtros.buscar);

    /**
     * Limpiar borra la búsqueda y nada más: las filas por página quedan como
     * estaban. Son dos cosas distintas —una es qué se busca y la otra cuánto
     * se muestra— y a nadie le sirve que al borrar un apellido la tabla vuelva
     * sola de 50 filas a 15.
     */
    function limpiar() {
        setBuscar('');
        aplicar({ termino: '', porPagina: filtros.por_pagina });
    }

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
                        aplicar({ termino: buscar, porPagina: Number(e.target.value) })
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
                El buscador a la derecha, ocupando un tercio de la barra —las
                4 columnas de 12—.

                `ml-auto` es lo que lo empuja contra el borde derecho: como ya
                no estira para llenar el espacio, sin eso quedaría pegado al
                selector con un hueco grande al costado.

                En pantalla angosta el contenedor es `flex-col` y las dos
                reglas no aplican: el campo ocupa el ancho entero, que es lo
                que corresponde en un teléfono.
            */}
            <div className="relative sm:ml-auto sm:w-1/3">
                <Search className="pointer-events-none absolute top-3 left-3 size-4 text-muted-foreground" />

                <Input
                    type="search"
                    value={buscar}
                    onChange={(e) => setBuscar(e.target.value)}
                    // Texto corto porque el campo es angosto: la lista completa
                    // de lo que se puede buscar se cortaría a la mitad.
                    placeholder="Buscar cédula, nombre…"
                    className="pl-9"
                    aria-label="Buscar solicitantes"
                />
            </div>

            {/* Pegado al buscador, que es lo que limpia. */}
            {hayBusqueda && (
                <Button variant="ghost" onClick={limpiar} title="Limpiar la búsqueda">
                    <X className="size-4" />
                    Limpiar
                </Button>
            )}
        </div>
    );
}
