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
                    ACÁ YA NO SE PUEDE ADELANTAR EL TIPO DE TRÁMITE, y no es una
                    simplificación: es que la pregunta cambió de dueño.

                    Con un carnet por persona, saber si era emisión o adición
                    dependía solo de la persona, así que se podía decidir apenas
                    se la elegía. Hoy depende del RUBRO —alguien con carnet de
                    Pescador hace una emisión inicial si pide Comercializador y
                    una actualización si pide Pescador— y el rubro se elige
                    después, en el paso siguiente del formulario.

                    Así que acá se muestra cuántas actividades tiene cubiertas, y
                    el tipo lo resuelve el paso del rubro. El detalle completo lo
                    pinta <SituacionBeneficiarioCard> justo debajo.
                */}
                <Badge color={seleccionado.situacion.tiene_carnet ? 'violet' : 'sky'}>
                    {seleccionado.situacion.carnets.length === 0
                        ? 'Sin carnets este año'
                        : `${seleccionado.situacion.carnets.length} carnet(s) ${seleccionado.situacion.gestion}`}
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
                                        Las actividades que ya tiene, en la fila
                                        misma. Con dos personas del mismo apellido
                                        —que en el padrón son muchas— esto es lo que
                                        permite elegir a la correcta sin abrir cada
                                        ficha.

                                        Ya no se muestra el número de registro: con
                                        varios carnets por persona habría que elegir
                                        cuál, y el que identifica en ventanilla es el
                                        RUBRO, no el número.
                                    */}
                                    {rubrosVigentes(s) && (
                                        <p className="truncate text-xs text-muted-foreground">
                                            {rubrosVigentes(s)}
                                        </p>
                                    )}
                                </div>

                                <Badge color={s.situacion.tiene_carnet ? 'violet' : 'sky'}>
                                    {s.situacion.carnets.length === 0
                                        ? 'Sin carnet'
                                        : `${s.situacion.carnets.length} carnet(s)`}
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
 * Las actividades que de verdad habilitan hoy, en texto corto para la fila.
 *
 * Se dejan afuera los carnets SUSPENDIDOS, ANULADOS y VENCIDOS: en una línea de
 * treinta caracteres no entra el estado de cada uno, y listar una actividad
 * cortada como si estuviera vigente es peor que no listarla. El detalle con
 * estados va en la tarjeta de situación, justo debajo.
 *
 * Se filtra por `vigente` y no por `estado === 'vigente'` porque ese campo lo
 * calcula el servidor mirando TAMBIÉN la fecha: el estado `vencido` lo escribe
 * un comando que corre una vez al día y puede estar desfasado. Ver
 * Carnet::estaVigente().
 */
function rubrosVigentes(sugerido: BeneficiarioSugerido): string {
    return sugerido.situacion.carnets
        .filter((carnet) => carnet.vigente)
        .map((carnet) => carnet.rubro)
        .filter(Boolean)
        .join(', ');
}
