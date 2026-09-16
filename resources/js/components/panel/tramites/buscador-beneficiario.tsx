import { Search, User, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import type { BeneficiarioSugerido } from '@/types/beneficiarios';

/**
 * Autocompletado para elegir al beneficiario del trámite.
 *
 * ----------------------------------------------------------------------------
 *  POR QUÉ NO ES UN <select> CON TODO EL PADRÓN
 * ----------------------------------------------------------------------------
 *
 * Porque el padrón tiene miles de personas y crecerá. Un <select> con todas
 * obligaría a mandarlas enteras en cada carga de la pantalla, y el operador
 * tendría que buscar a alguien desplazando una lista de miles.
 *
 * ----------------------------------------------------------------------------
 *  POR QUÉ ESPERA ANTES DE BUSCAR («debounce»)
 * ----------------------------------------------------------------------------
 *
 * Sin la espera, escribir «antezana» dispara ocho consultas —una por letra— y
 * cada una recorre la tabla entera, porque el nombre se compara concatenado y
 * ningún índice puede ayudar (ver Beneficiario::scopeBuscar). Con 300 ms de
 * pausa, se dispara una sola vez, cuando el operador dejó de escribir.
 *
 * El mínimo de 3 caracteres es por lo mismo: «a» traería medio padrón.
 */
export function BuscadorBeneficiario({
    seleccionado,
    onSeleccionar,
    error,
}: {
    seleccionado: BeneficiarioSugerido | null;
    onSeleccionar: (beneficiario: BeneficiarioSugerido | null) => void;
    error?: string;
}) {
    const [termino, setTermino] = useState('');
    const [sugerencias, setSugerencias] = useState<BeneficiarioSugerido[]>([]);
    const [buscando, setBuscando] = useState(false);

    /*
     * Guarda la petición en curso para poder cancelarla. Sin esto, dos búsquedas
     * seguidas pueden volver desordenadas —la primera tarda más que la segunda—
     * y la lista terminaría mostrando el resultado de lo que el operador YA
     * borró de la caja.
     */
    const peticion = useRef<AbortController | null>(null);

    useEffect(() => {
        if (seleccionado || termino.trim().length < 3) {
            setSugerencias([]);

            return;
        }

        const temporizador = setTimeout(async () => {
            peticion.current?.abort();
            peticion.current = new AbortController();

            setBuscando(true);

            try {
                // Esta es la ÚNICA llamada con fetch() del sistema, y está
                // justificada: el resto de las pantallas navegan con Inertia,
                // pero acá no se quiere navegar a ningún lado, solo traer una
                // lista mientras el operador escribe.
                const respuesta = await fetch(
                    route('beneficiarios.buscar', { q: termino }),
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
    }, [termino, seleccionado]);

    if (seleccionado) {
        return (
            <div className="flex items-center gap-3 rounded-md border border-border bg-secondary/40 p-3">
                <div className="flex size-11 shrink-0 items-center justify-center overflow-hidden rounded-full bg-muted">
                    {seleccionado.foto_url ? (
                        <img src={seleccionado.foto_url} alt="" className="size-full object-cover" />
                    ) : (
                        <User className="size-5 text-muted-foreground" />
                    )}
                </div>

                <div className="min-w-0 flex-1">
                    <p className="truncate font-medium">{seleccionado.nombreCompleto}</p>
                    <p className="text-sm text-muted-foreground">{seleccionado.documento_identidad}</p>
                </div>

                {/*
                    Se le adelanta al operador qué tipo de trámite va a salir. La
                    decisión REAL la toma el servidor al registrar, con la fila
                    bloqueada; esto es solo para que no se sorprenda.

                    El detalle completo —qué carnet, qué rubros ya tiene— lo pinta
                    <SituacionBeneficiarioCard> justo debajo.
                */}
                <Badge color={seleccionado.situacion.tiene_carnet ? 'violet' : 'sky'}>
                    {seleccionado.situacion.tiene_carnet ? 'Adición de rubro' : 'Emisión inicial'}
                </Badge>

                <button
                    type="button"
                    onClick={() => {
                        onSeleccionar(null);
                        setTermino('');
                    }}
                    className="rounded-md p-1.5 text-muted-foreground hover:bg-secondary"
                    aria-label="Elegir otro beneficiario"
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
                    placeholder="Buscar por cédula o nombre… (mínimo 3 letras)"
                    value={termino}
                    onChange={(e) => setTermino(e.target.value)}
                    aria-invalid={Boolean(error)}
                />
            </div>

            {buscando && <p className="text-xs text-muted-foreground">Buscando…</p>}

            {!buscando && termino.trim().length >= 3 && sugerencias.length === 0 && (
                <p className="text-xs text-muted-foreground">
                    Sin coincidencias. Si la persona no está en el padrón, hay que registrarla primero.
                </p>
            )}

            {sugerencias.length > 0 && (
                <ul className="divide-y divide-border overflow-hidden rounded-md border border-border">
                    {sugerencias.map((s) => (
                        <li key={s.id}>
                            <button
                                type="button"
                                onClick={() => onSeleccionar(s)}
                                className="flex w-full items-center gap-3 p-3 text-left hover:bg-secondary"
                            >
                                <div className="min-w-0 flex-1">
                                    <p className="truncate font-medium">{s.nombreCompleto}</p>
                                    <p className="text-sm text-muted-foreground">
                                        {s.documento_identidad}
                                    </p>

                                    {/*
                                        El carnet y sus rubros, en la fila misma.
                                        Con dos personas del mismo apellido —que en
                                        el padrón son muchas— esto es lo que permite
                                        elegir a la correcta sin abrir cada ficha.
                                    */}
                                    {s.situacion.carnet && (
                                        <p className="truncate text-xs text-muted-foreground">
                                            Carnet Nº {s.situacion.carnet.registro}
                                            {rubrosHabilitados(s) && ` · ${rubrosHabilitados(s)}`}
                                        </p>
                                    )}
                                </div>

                                <Badge color={s.situacion.tiene_carnet ? 'violet' : 'sky'}>
                                    {s.situacion.tiene_carnet ? 'Tiene carnet' : 'Sin carnet'}
                                </Badge>
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

/**
 * Los rubros que de verdad habilitan, en texto corto para la fila.
 *
 * Se dejan afuera los SUSPENDIDOS: en una línea de treinta caracteres no entra
 * el estado de cada uno, y listar un rubro cortado como si estuviera vigente es
 * peor que no listarlo. El detalle con estados va en la tarjeta de situación.
 */
function rubrosHabilitados(sugerido: BeneficiarioSugerido): string {
    return (sugerido.situacion.carnet?.rubros ?? [])
        .filter((rubro) => rubro.estado === 'habilitado')
        .map((rubro) => rubro.nombre)
        .filter(Boolean)
        .join(', ');
}
