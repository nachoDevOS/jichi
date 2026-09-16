import { Plus, Trash2 } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Campo } from '@/components/ui/campo';
import { Input } from '@/components/ui/input';
import { SelectorArchivo } from '@/components/ui/selector-archivo';
import { useArchivos } from '@/hooks/use-archivos';
import { bs } from '@/lib/utils';
import type { PagoInicial } from '@/types/tramites';

/**
 * ============================================================================
 *  LOS DEPÓSITOS QUE EL PESCADOR TRAE EL MISMO DÍA — Regla C
 * ============================================================================
 *
 * Es una LISTA y no un solo juego de campos, y esa es toda la gracia: el
 * trámite se puede cubrir con una boleta o con varias. Un único campo de monto
 * obligaría al operador a sumar a mano antes de escribir, y se perdería el
 * respaldo de cada parte —cuando alguien reclame, no habría forma de mostrar qué
 * boleta corresponde a qué monto—.
 *
 * Todo esto es OPCIONAL. El pescador puede registrar el trámite hoy y venir a
 * pagar la semana que viene; en ese caso las boletas se cargan después, desde la
 * ficha del expediente.
 */
export function CampoPagos({
    pagos,
    onCambiar,
    costoRubro,
    moneda,
    errores,
}: {
    pagos: PagoInicial[];
    onCambiar: (pagos: PagoInicial[]) => void;
    /** Lo que cuesta el rubro elegido, para mostrar cuánto falta. */
    costoRubro: number;
    moneda: string;
    /** Los errores de Laravel, con claves tipo 'pagos.0.monto'. */
    errores: Record<string, string>;
}) {
    // `acepta` lo resuelve SelectorArchivo por su cuenta.
    const { ayudaPeso } = useArchivos();

    const total = pagos.reduce((suma, p) => suma + (Number.parseFloat(p.monto) || 0), 0);
    const falta = Math.max(0, costoRubro - total);

    function agregar() {
        onCambiar([
            ...pagos,
            { nro_transaccion: '', monto: '', comprobante: null, fecha_pago: '', observaciones: '' },
        ]);
    }

    function quitar(indice: number) {
        onCambiar(pagos.filter((_, i) => i !== indice));
    }

    function actualizar(indice: number, campo: keyof PagoInicial, valor: string | File | null) {
        onCambiar(pagos.map((p, i) => (i === indice ? { ...p, [campo]: valor } : p)));
    }

    /*
     * EL NÚMERO DE TRANSACCIÓN SE LIMPIA AL TECLEAR, no al guardar.
     *
     * Los bancos numeran los depósitos con enteros, así que una letra o un
     * espacio es un error de tipeo. Se descartan mientras escribe en vez de
     * rebotarle el formulario al final: el operador está copiando de una boleta
     * de papel y lo que necesita es que el campo no le acepte lo que no va.
     *
     * NO SE USA `type="number"`, y esa es la parte importante. Ese tipo acepta
     * `e`, `+`, `-` y coma decimal —son números válidos para el navegador— y
     * además le pone flechitas al campo, que en un dato que no es una cantidad
     * no tienen ningún sentido. `inputMode="numeric"` abre el teclado numérico
     * en el teléfono sin traer nada de eso.
     */
    function soloDigitos(valor: string): string {
        return valor.replace(/\D/g, '');
    }

    return (
        <div className="space-y-4">
            {pagos.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    Sin depósitos cargados. Se pueden registrar ahora o más adelante, desde la ficha del
                    trámite. El expediente no se aprueba hasta cubrir el costo.
                </p>
            ) : (
                pagos.map((pago, i) => (
                    <div key={i} className="space-y-3 rounded-md border border-border p-4">
                        <div className="flex items-center justify-between">
                            <span className="text-sm font-medium">Depósito {i + 1}</span>

                            <Button type="button" variant="ghost" size="sm" onClick={() => quitar(i)}>
                                <Trash2 className="size-4" />
                                Quitar
                            </Button>
                        </div>

                        <div className="grid gap-3 sm:grid-cols-2">
                            <Campo
                                etiqueta="Nº de transacción"
                                htmlFor={`pago-${i}-nro`}
                                obligatorio
                                error={errores[`pagos.${i}.nro_transaccion`]}
                                // Es único en TODO el sistema: el mismo depósito
                                // no puede pagar dos expedientes.
                                ayuda="El que figura en la boleta del banco. Solo números."
                            >
                                <Input
                                    id={`pago-${i}-nro`}
                                    inputMode="numeric"
                                    value={pago.nro_transaccion}
                                    onChange={(e) =>
                                        actualizar(i, 'nro_transaccion', soloDigitos(e.target.value))
                                    }
                                />
                            </Campo>

                            <Campo
                                etiqueta={`Monto (${moneda})`}
                                htmlFor={`pago-${i}-monto`}
                                obligatorio
                                error={errores[`pagos.${i}.monto`]}
                            >
                                <Input
                                    id={`pago-${i}-monto`}
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    value={pago.monto}
                                    onChange={(e) => actualizar(i, 'monto', e.target.value)}
                                />
                            </Campo>

                            {/* Mismo selector que los adjuntos del trámite: con
                                dos o tres boletas cargadas, el control del
                                navegador no deja ver cuál quedó sin subir. */}
                            <Campo
                                etiqueta="Boleta escaneada"
                                htmlFor={`pago-${i}-comprobante`}
                                obligatorio
                                ayuda={`PDF o imagen, ${ayudaPeso}`}
                            >
                                <SelectorArchivo
                                    id={`pago-${i}-comprobante`}
                                    archivo={pago.comprobante}
                                    onElegir={(a) => actualizar(i, 'comprobante', a)}
                                    error={errores[`pagos.${i}.comprobante`]}
                                />
                            </Campo>

                            <Campo
                                etiqueta="Fecha del depósito"
                                htmlFor={`pago-${i}-fecha`}
                                error={errores[`pagos.${i}.fecha_pago`]}
                                // No es la fecha de carga: una boleta del viernes
                                // se registra el lunes.
                                ayuda="La del banco, no la de hoy. Vacío = hoy."
                            >
                                <Input
                                    id={`pago-${i}-fecha`}
                                    type="date"
                                    value={pago.fecha_pago}
                                    onChange={(e) => actualizar(i, 'fecha_pago', e.target.value)}
                                />
                            </Campo>
                        </div>
                    </div>
                ))
            )}

            <div className="flex flex-wrap items-center gap-3">
                <Button type="button" variant="outline" size="sm" onClick={agregar}>
                    <Plus className="size-4" />
                    Agregar depósito
                </Button>

                {costoRubro > 0 && (
                    <span className="flex flex-wrap items-center gap-2 text-sm">
                        <span className="text-muted-foreground">
                            Costo {bs(costoRubro, moneda)} · cargado {bs(total, moneda)}
                        </span>

                        <Badge color={falta > 0 ? 'amber' : 'emerald'}>
                            {falta > 0 ? `Faltan ${bs(falta, moneda)}` : 'Cubierto'}
                        </Badge>
                    </span>
                )}
            </div>
        </div>
    );
}
