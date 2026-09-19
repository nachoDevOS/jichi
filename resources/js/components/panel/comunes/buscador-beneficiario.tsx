import { Search, UserRoundX, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Retrato } from '@/components/comunes/retrato';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { BeneficiarioSugerido } from '@/types/beneficiarios';

/**
 * ============================================================================
 *  AUTOCOMPLETADO DE BENEFICIARIOS
 * ============================================================================
 *
 * Lo usan todos los formularios que arrancan eligiendo a una persona: otorgar
 * un cupo, emitir un carnet, emitir una faena o una guía.
 *
 * Vive en `comunes/` y no dentro de un módulo porque es exactamente el mismo
 * problema en los cuatro. Copiado en cada uno, alcanzaría con que alguien
 * arreglara el retardo en uno para que los otros tres siguieran castigando al
 * servidor.
 *
 * ----------------------------------------------------------------------------
 *  EL RETARDO NO ES UN DETALLE DE PULIDO
 * ----------------------------------------------------------------------------
 *
 * La búsqueda del servidor compara contra las cinco partes del nombre
 * CONCATENADAS, y eso no lo puede resolver ningún índice: recorre la tabla
 * entera. Sin retardo, escribir «antezana» son ocho recorridos completos del
 * padrón, siete de los cuales se descartan antes de dibujarse.
 *
 * 300 ms es el umbral en que una pausa al tipear deja de sentirse como lentitud
 * y empieza a leerse como «está buscando».
 *
 * ----------------------------------------------------------------------------
 *  LA PETICIÓN VIEJA SE CANCELA, Y SIN ESO LA LISTA MIENTE
 * ----------------------------------------------------------------------------
 *
 * Dos búsquedas en vuelo pueden volver en cualquier orden. Si la de «ant»
 * tarda más que la de «antezana», llega después y PISA los resultados buenos:
 * la pantalla termina mostrando coincidencias de un texto que ya no está en la
 * caja. `AbortController` corta la anterior en cada tecleo.
 */
export function BuscadorBeneficiario({
    seleccionado,
    onSeleccionar,
    error,
    /** Texto de ayuda bajo la caja, para explicar qué se ofrece en cada módulo. */
    ayuda,
}: {
    seleccionado: BeneficiarioSugerido | null;
    onSeleccionar: (beneficiario: BeneficiarioSugerido | null) => void;
    error?: string;
    ayuda?: string;
}) {
    const [termino, setTermino] = useState('');
    const [resultados, setResultados] = useState<BeneficiarioSugerido[]>([]);
    const [buscando, setBuscando] = useState(false);
    const [buscado, setBuscado] = useState(false);

    // Guarda la petición en vuelo para poder cortarla en el próximo tecleo.
    const peticion = useRef<AbortController | null>(null);

    useEffect(() => {
        // Con menos de tres caracteres el servidor devuelve vacío igual (ver
        // BeneficiarioController::buscar), así que ni siquiera se sale a pedir:
        // sería un viaje garantizado a la nada.
        if (termino.trim().length < 3) {
            setResultados([]);
            setBuscado(false);

            return;
        }

        const reloj = setTimeout(() => {
            peticion.current?.abort();
            peticion.current = new AbortController();
            setBuscando(true);

            fetch(route('beneficiarios.buscar', { q: termino }), {
                headers: { Accept: 'application/json' },
                signal: peticion.current.signal,
            })
                .then((r) => r.json())
                .then((datos: BeneficiarioSugerido[]) => {
                    setResultados(datos);
                    setBuscado(true);
                })
                // Una petición cortada a propósito no es un error que mostrar:
                // significa que el operador siguió escribiendo.
                .catch(() => undefined)
                .finally(() => setBuscando(false));
        }, 300);

        // Se limpia el reloj en cada tecleo: es lo que hace que solo la última
        // pausa dispare la búsqueda.
        return () => clearTimeout(reloj);
    }, [termino]);

    // --- Con la persona ya elegida, la caja se reemplaza por su ficha.
    if (seleccionado) {
        return (
            <div className="flex items-center gap-3 rounded-md border border-border bg-secondary/40 p-3">
                <Retrato url={seleccionado.foto_url} nombre={seleccionado.nombreCompleto} />

                <div className="min-w-0 flex-1">
                    <p className="truncate font-medium">{seleccionado.nombreCompleto}</p>
                    <p className="font-mono text-xs text-muted-foreground">
                        {seleccionado.documento_identidad}
                    </p>
                </div>

                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={() => {
                        onSeleccionar(null);
                        setTermino('');
                        setResultados([]);
                        setBuscado(false);
                    }}
                    aria-label="Elegir otra persona"
                >
                    <X className="size-4" />
                </Button>
            </div>
        );
    }

    return (
        <div className="space-y-2">
            <div className="relative">
                <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />

                <Input
                    value={termino}
                    onChange={(e) => setTermino(e.target.value)}
                    placeholder="Buscar por cédula o nombre…"
                    aria-invalid={Boolean(error)}
                    className="pl-9"
                    // No es `type="search"`: el aspa que agregan algunos
                    // navegadores borra el texto sin avisarle a React, y la lista
                    // se queda con los resultados del término anterior.
                    autoComplete="off"
                />
            </div>

            {ayuda && <p className="text-xs text-muted-foreground">{ayuda}</p>}
            {error && <p className="text-sm text-destructive">{error}</p>}

            {termino.trim().length > 0 && termino.trim().length < 3 && (
                <p className="text-xs text-muted-foreground">Escriba al menos tres caracteres.</p>
            )}

            {buscando && <p className="text-xs text-muted-foreground">Buscando…</p>}

            {/*
                «No encontré nada» solo después de haber buscado de verdad: sin
                `buscado`, el cartel aparecería mientras la primera petición
                todavía está en vuelo y diría que no existe alguien que sí está.
            */}
            {buscado && !buscando && resultados.length === 0 && (
                <div className="flex items-center gap-2 rounded-md border border-dashed border-border p-3 text-sm text-muted-foreground">
                    <UserRoundX className="size-4 shrink-0" />
                    Nadie coincide con «{termino}». Si es la primera vez que viene, hay que
                    registrarlo en el padrón antes.
                </div>
            )}

            {resultados.length > 0 && (
                <ul className="divide-y divide-border overflow-hidden rounded-md border border-border">
                    {resultados.map((b) => (
                        <li key={b.id}>
                            <button
                                type="button"
                                onClick={() => onSeleccionar(b)}
                                className="flex w-full items-center gap-3 p-2.5 text-left transition-colors hover:bg-secondary"
                            >
                                <Retrato url={b.foto_url} nombre={b.nombreCompleto} className="size-8" />

                                <span className="min-w-0 flex-1">
                                    <span className="block truncate text-sm font-medium">
                                        {b.nombreCompleto}
                                    </span>
                                    <span className="block font-mono text-xs text-muted-foreground">
                                        {b.documento_identidad}
                                    </span>
                                </span>

                                {/*
                                    Cuántas credenciales vigentes tiene. Es el dato
                                    que evita el viaje en falso: quien no tiene
                                    ninguna no puede sacar faenas ni guías, y se ve
                                    antes de elegirlo.
                                */}
                                {b.carnets_vigentes.length > 0 && (
                                    <span className="shrink-0 text-xs text-muted-foreground">
                                        {b.carnets_vigentes.length} carnet(s)
                                    </span>
                                )}
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
