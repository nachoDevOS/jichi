import { Link, router } from '@inertiajs/react';
import { ArrowRight, Search, UserPlus, Users } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { EstadoVacio } from '@/components/ui/estado-vacio';
import { Input } from '@/components/ui/input';
import { EstadoCredencial } from '@/components/panel/tramites/estado-credencial';
import type { SolicitanteDelTramite } from '@/types/tramites';

/**
 * ============================================================================
 *  PASO 0 DEL ALTA — ¿A QUIÉN SE ESTÁ ATENDIENDO?
 * ============================================================================
 *
 * El alta de un trámite empieza acá y no en el catálogo de servicios, y la
 * razón es la regla del negocio: hasta no saber de quién se trata, el sistema
 * no puede decir qué puede pedir. La faena y la guía de transporte exigen
 * Cédula de Pescador vigente; la cédula no.
 *
 * Elegir primero el servicio y descubrir después que la persona no está
 * habilitada sería hacerle cargar media pantalla para nada, con el pescador
 * esperando en la ventanilla.
 *
 * Cada resultado muestra el estado de su credencial. Eso es a propósito: el
 * operador ve, antes de hacer clic, si va a poder emitirle lo que vino a pedir.
 */
export function BuscadorSolicitante({
    resultados,
    busqueda,
}: {
    resultados: SolicitanteDelTramite[];
    /** Lo que ya se había buscado, para no perderlo al volver. */
    busqueda: string | null;
}) {
    const [termino, setTermino] = useState(busqueda ?? '');
    const primerRender = useRef(true);

    /*
     * Mismo "debounce" que el listado de solicitantes: se espera a que el
     * operador deje de escribir para consultar. Sin esto, «perez» dispararía
     * cinco consultas, una por letra.
     */
    useEffect(() => {
        if (primerRender.current) {
            primerRender.current = false;

            return;
        }

        const temporizador = setTimeout(() => {
            router.get(
                route('tramites.create'),
                termino.trim() === '' ? {} : { buscar: termino.trim() },
                { preserveState: true, preserveScroll: true, replace: true },
            );
        }, 350);

        return () => clearTimeout(temporizador);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [termino]);

    return (
        <Card>
            <CardContent className="space-y-4 p-5">
                <div>
                    <h2 className="font-semibold">¿Quién hace el trámite?</h2>
                    <p className="text-sm text-muted-foreground">
                        Buscá al pescador por cédula o por nombre. Si es la primera vez que
                        viene, registralo antes.
                    </p>
                </div>

                <div className="flex flex-col gap-3 sm:flex-row">
                    <div className="relative flex-1">
                        <Search className="pointer-events-none absolute left-3 top-3 size-4 text-muted-foreground" />
                        <Input
                            autoFocus
                            type="search"
                            value={termino}
                            onChange={(e) => setTermino(e.target.value)}
                            placeholder="Cédula, nombre o apellido…"
                            className="pl-9"
                            aria-label="Buscar solicitante"
                        />
                    </div>

                    <Link href={route('solicitantes.create')}>
                        <Button variant="outline" className="w-full sm:w-auto">
                            <UserPlus className="size-4" />
                            Nuevo solicitante
                        </Button>
                    </Link>
                </div>

                {/* Tres estados distintos, y cada uno dice algo distinto: todavía
                    no buscó, buscó y no hay, buscó y hay. Un único mensaje
                    genérico dejaría al operador sin saber si el sistema entendió. */}
                {termino.trim() === '' ? (
                    <EstadoVacio
                        icono={Search}
                        titulo="Escribí para buscar"
                        descripcion="El trámite se registra a nombre de un solicitante ya cargado en el padrón."
                    />
                ) : resultados.length === 0 ? (
                    <EstadoVacio
                        icono={Users}
                        titulo="Ningún solicitante coincide"
                        descripcion="Revisá la cédula, o registralo si es la primera vez que viene."
                        accion={
                            <Link href={route('solicitantes.create')}>
                                <Button>
                                    <UserPlus className="size-4" />
                                    Registrar solicitante
                                </Button>
                            </Link>
                        }
                    />
                ) : (
                    <ul className="divide-y divide-border rounded-lg border border-border">
                        {resultados.map((s) => (
                            <li key={s.id}>
                                <Link
                                    href={route('tramites.create', { solicitante: s.id })}
                                    className="flex items-center gap-3 p-3 transition-colors hover:bg-accent/40"
                                >
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate font-medium">{s.nombreCompleto}</p>
                                        <p className="font-mono text-xs text-muted-foreground">
                                            {s.documento_identidad}
                                        </p>
                                    </div>

                                    <EstadoCredencial
                                        credencial={s.credencial}
                                        enTramite={s.credencial_en_tramite}
                                        compacto
                                    />

                                    <ArrowRight className="size-4 shrink-0 text-muted-foreground" />
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}
