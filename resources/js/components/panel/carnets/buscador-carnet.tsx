import { BadgeCheck, Search, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import type { CarnetElegible } from '@/types/faenas';

/**
 * ============================================================================
 *  ELEGIR EL CARNET AL QUE SE LE EMITE UNA FAENA O UNA GUÍA
 * ============================================================================
 *
 * Lo comparten los dos formularios. `permiso` dice cuál: el servidor devuelve
 * solo carnets de rubros que emiten ESE papel, y solo vigentes.
 *
 * ----------------------------------------------------------------------------
 *  EL FILTRO ESTÁ EN EL SERVIDOR, NO ACÁ
 * ----------------------------------------------------------------------------
 *
 * Y es lo que evita el error más probable del módulo. La misma persona tiene
 * normalmente los DOS carnets —Pescador y Comercializador—, así que si la lista
 * los trajera todos, el operador elegiría el que no era y recién se enteraría al
 * guardar. Con la lista ya filtrada, elegir mal no es posible.
 *
 * El servicio igual lo vuelve a comprobar: esto es comodidad, no seguridad.
 *
 * ----------------------------------------------------------------------------
 *  POR QUÉ ESPERA ANTES DE BUSCAR
 * ----------------------------------------------------------------------------
 *
 * Sin la espera, escribir «antezana» dispara ocho consultas —una por letra— y
 * cada una recorre la tabla, porque el nombre se compara concatenado y ningún
 * índice ayuda. Con 300 ms se dispara una sola vez, al dejar de escribir.
 *
 * Mismo patrón que `BuscadorBeneficiario`. Son dos componentes y no uno con un
 * modo, porque lo que muestran en la fila es distinto: allá interesa la persona
 * y sus carnets; acá, el carnet concreto y su cupo.
 */
export function BuscadorCarnet({
    permiso,
    seleccionado,
    onSeleccionar,
    error,
}: {
    /** Qué papel se va a emitir. Filtra qué carnets devuelve el servidor. */
    permiso: 'faenas' | 'guias';
    seleccionado: CarnetElegible | null;
    onSeleccionar: (carnet: CarnetElegible | null) => void;
    error?: string;
}) {
    const [termino, setTermino] = useState('');
    const [sugerencias, setSugerencias] = useState<CarnetElegible[]>([]);
    const [buscando, setBuscando] = useState(false);

    /*
     * Guarda la petición en curso para poder cancelarla. Sin esto, dos búsquedas
     * seguidas pueden volver desordenadas —la primera tarda más que la segunda—
     * y la lista mostraría el resultado de lo que el operador YA borró.
     */
    const peticion = useRef<AbortController | null>(null);

    useEffect(() => {
        // Dos caracteres y no tres: acá también se busca por el número de
        // registro, y «13» es una búsqueda legítima.
        if (seleccionado || termino.trim().length < 2) {
            setSugerencias([]);

            return;
        }

        const temporizador = setTimeout(async () => {
            peticion.current?.abort();
            peticion.current = new AbortController();

            setBuscando(true);

            try {
                const respuesta = await fetch(
                    route('carnets.buscar', { q: termino, permiso }),
                    { signal: peticion.current.signal, headers: { Accept: 'application/json' } },
                );

                setSugerencias(respuesta.ok ? await respuesta.json() : []);
            } catch {
                // AbortError incluido: si se canceló, no hay nada que mostrar.
                setSugerencias([]);
            } finally {
                setBuscando(false);
            }
        }, 300);

        return () => clearTimeout(temporizador);
    }, [termino, seleccionado, permiso]);

    if (seleccionado) {
        return (
            <div className="flex items-center gap-3 rounded-md border border-border bg-secondary/40 p-3">
                <div className="flex size-11 shrink-0 items-center justify-center rounded-full bg-muted">
                    <BadgeCheck className="size-5 text-muted-foreground" />
                </div>

                {/* min-w-0 para que `truncate` funcione: sin él, el hijo se
                    niega a encogerse por debajo de su contenido. */}
                <div className="min-w-0 flex-1">
                    <p className="truncate font-medium">{seleccionado.beneficiario}</p>
                    <p className="text-sm text-muted-foreground">
                        {seleccionado.documento_identidad} · Carnet {seleccionado.registro}
                    </p>
                </div>

                <div className="hidden text-right sm:block">
                    <Badge color="violet">
                        {seleccionado.rubro} {seleccionado.gestion}
                    </Badge>

                    {/* El cupo ANUAL del carnet. Se muestra acá porque es contra
                        lo que el operador compara al escribir los kilos de la
                        faena — son dos topes distintos y conviene tener el otro
                        a la vista. */}
                    {seleccionado.capacidad && (
                        <p className="mt-1 text-xs text-muted-foreground">
                            Cupo anual: {seleccionado.capacidad}
                        </p>
                    )}
                </div>

                <button
                    type="button"
                    onClick={() => {
                        onSeleccionar(null);
                        setTermino('');
                    }}
                    className="rounded-md p-1.5 text-muted-foreground hover:bg-secondary"
                    aria-label="Elegir otro carnet"
                >
                    <X className="size-4" />
                </button>
            </div>
        );
    }

    return (
        <div className="space-y-2">
            <div className="relative">
                <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    className="pl-9"
                    placeholder="Buscar por nombre, cédula o número de carnet…"
                    value={termino}
                    onChange={(e) => setTermino(e.target.value)}
                    aria-invalid={Boolean(error)}
                />
            </div>

            {buscando && <p className="text-xs text-muted-foreground">Buscando…</p>}

            {/*
                El mensaje de «sin resultados» NO dice solo que no hay: dice las
                dos razones posibles, porque desde acá no se pueden distinguir y
                las dos se resuelven en otra pantalla.
            */}
            {!buscando && termino.trim().length >= 2 && sugerencias.length === 0 && (
                <p className="text-xs text-muted-foreground">
                    Sin carnets vigentes que puedan emitir{' '}
                    {permiso === 'faenas' ? 'faenas' : 'guías'}. Puede que la persona no tenga el
                    carnet de esa actividad, o que lo tenga vencido o suspendido.
                </p>
            )}

            {sugerencias.length > 0 && (
                <ul className="divide-y divide-border overflow-hidden rounded-md border border-border">
                    {sugerencias.map((c) => (
                        <li key={c.id}>
                            <button
                                type="button"
                                onClick={() => onSeleccionar(c)}
                                className="flex w-full items-center gap-3 p-3 text-left hover:bg-secondary"
                            >
                                <div className="min-w-0 flex-1">
                                    <p className="truncate font-medium">{c.beneficiario}</p>
                                    <p className="text-sm text-muted-foreground">
                                        {c.documento_identidad} · Carnet {c.registro}
                                    </p>
                                </div>

                                <Badge color="violet">
                                    {c.rubro} {c.gestion}
                                </Badge>
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
