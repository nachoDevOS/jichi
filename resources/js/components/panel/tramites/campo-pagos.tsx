import { Banknote, ExternalLink, Plus, Trash2, TriangleAlert } from 'lucide-react';
import { CampoRequisito } from '@/components/panel/tramites/campo-requisito';
import { Button } from '@/components/ui/button';
import { Campo } from '@/components/ui/campo';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { useArchivos } from '@/hooks/use-archivos';
import type { FormaPagoOpcion, PagoDeclarado } from '@/types/tramites';

/**
 * ============================================================================
 *  PAGOS DE LA CÉDULA — uno o varios comprobantes
 * ============================================================================
 *
 * Antes acá había un solo campo de archivo: «Comprobante de pago de la cédula».
 * En ventanilla eso no alcanza. El pescador cancela la cédula en una o en
 * varias veces —dos transferencias de días distintos, o un depósito más el
 * saldo en efectivo—, y cada parte llega con SU papel y SU número de
 * transacción. Con un campo único, el operador tenía que elegir cuál de los
 * dos adjuntaba, y el otro pago no quedaba registrado en ninguna parte: al
 * cerrar caja contra el extracto del banco, ese dinero no aparecía.
 *
 * ----------------------------------------------------------------------------
 *  POR QUÉ EL NÚMERO DE TRANSACCIÓN Y EL MONTO VAN JUNTO AL ARCHIVO
 * ----------------------------------------------------------------------------
 *
 * El comprobante es una foto o un PDF: para saber cuánto pagó alguien hay que
 * abrirlo y leerlo. Anotando el monto y el número al lado, el trámite sabe solo
 * cuánto lleva cubierto —de ahí sale `monto_pagado`— y el pago se puede cruzar
 * con el extracto del banco sin abrir un solo archivo.
 *
 * ----------------------------------------------------------------------------
 *  POR AHORA SOLO TRANSFERENCIA
 * ----------------------------------------------------------------------------
 *
 * El efectivo y el QR todavía no están habilitados: falta definir cómo se rinde
 * la caja del día y quién concilia el QR. La lista de formas la manda el
 * servidor desde `FormaPago::disponibles()`, así que esta pantalla no decide
 * nada — cuando se habiliten, el selector aparece solo.
 *
 * Mientras quede una sola forma, el selector no se dibuja: un desplegable con
 * una única opción es una pregunta que ya tiene respuesta, y el operador pierde
 * tiempo abriéndolo para ver si había algo más.
 *
 * ----------------------------------------------------------------------------
 *  EL TOTAL SE MUESTRA PERO NO BLOQUEA
 * ----------------------------------------------------------------------------
 *
 * Si lo declarado no llega a la tasa, aparece un aviso, no un candado. El pago
 * parcial es una situación REAL —el pescador deja un adelanto y vuelve—, y un
 * sistema que se niega a registrarlo obliga a ventanilla a inventar un número
 * para poder seguir, que es peor que un saldo pendiente bien anotado.
 */
export function CampoPagos({
    pagos,
    formas,
    montoTasa,
    errores,
    onCambio,
}: {
    pagos: PagoDeclarado[];
    formas: FormaPagoOpcion[];
    /** Lo que cuesta la cédula, para comparar. NULL = sin monto fijo. */
    montoTasa: number | null;
    /**
     * Los errores de Laravel llegan con clave anidada —`pagos.0.monto`—, que no
     * es una propiedad del formulario. Por eso entra como diccionario suelto y
     * no con el tipo de `errors`.
     */
    errores: Record<string, string>;
    onCambio: (pagos: PagoDeclarado[]) => void;
}) {
    const archivos = useArchivos();

    const total = pagos.reduce((suma, pago) => suma + (Number(pago.monto) || 0), 0);

    // Solo se avisa si el trámite tiene precio fijo Y ya hay algo anotado: un
    // formulario recién abierto no tiene por qué mostrar una advertencia.
    const saldo = montoTasa === null ? 0 : montoTasa - total;
    const faltaPlata = montoTasa !== null && total > 0 && saldo > 0;
    const sobraPlata = montoTasa !== null && saldo < 0;

    function actualizar(indice: number, cambios: Partial<PagoDeclarado>) {
        onCambio(pagos.map((pago, i) => (i === indice ? { ...pago, ...cambios } : pago)));
    }

    function agregar() {
        onCambio([
            ...pagos,
            {
                // El pago nuevo hereda la forma del anterior: quien paga en dos
                // transferencias casi nunca cambia de método a mitad de camino.
                forma: pagos[pagos.length - 1]?.forma ?? formas[0]?.value ?? 'transferencia',
                nro_transaccion: '',
                banco: '',
                monto: '',
                comprobante: null,
            },
        ]);
    }

    function quitar(indice: number) {
        onCambio(pagos.filter((_, i) => i !== indice));
    }

    return (
        <div className="space-y-3">
            <div className="space-y-1">
                <p className="text-sm font-semibold">Pago de la cédula</p>
                <p className="text-xs text-muted-foreground">
                    Por ahora la cédula se paga solo por transferencia bancaria. Si se
                    pagó en varias veces, agregue un pago por cada comprobante: cada uno
                    lleva su número de transacción y su monto.
                </p>
            </div>

            {pagos.map((pago, indice) => {
                /*
                 * Qué campos pide cada forma lo decide el servidor, no esta
                 * pantalla: `requiere_referencia` sale de
                 * `FormaPago::requiereReferencia()`. Hoy siempre es verdadero
                 * —solo hay transferencia—, pero la condición queda escrita
                 * para el día que vuelva el efectivo, que se cuenta en
                 * ventanilla contra el recibo y no tiene número que anotar.
                 */
                const forma = formas.find((f) => f.value === pago.forma);
                const pideReferencia = forma?.requiere_referencia ?? true;

                return (
                    <div
                        key={indice}
                        className="space-y-4 rounded-lg border border-border bg-muted/20 p-4"
                    >
                        <div className="flex items-center justify-between gap-3">
                            <p className="flex items-center gap-1.5 text-xs font-semibold text-muted-foreground">
                                <Banknote className="size-3.5" />
                                Pago {indice + 1}
                            </p>

                            {/* El único pago no se puede quitar: sin ningún
                                comprobante el trámite no se registra igual, y un
                                botón que deja el formulario en un estado
                                inválido solo corre el error más adelante. */}
                            {pagos.length > 1 && (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    onClick={() => quitar(indice)}
                                    aria-label={`Quitar el pago ${indice + 1}`}
                                >
                                    <Trash2 className="size-4" />
                                    Quitar
                                </Button>
                            )}
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            {/* Con una sola forma habilitada el selector sobra:
                                se muestra cuál es y listo. Ver el encabezado. */}
                            {formas.length > 1 ? (
                                <Campo
                                    etiqueta="Forma de pago"
                                    htmlFor={`pago-${indice}-forma`}
                                    error={errores[`pagos.${indice}.forma`]}
                                    obligatorio
                                >
                                    <Select
                                        id={`pago-${indice}-forma`}
                                        value={pago.forma}
                                        onChange={(e) =>
                                            actualizar(indice, { forma: e.target.value })
                                        }
                                    >
                                        {formas.map((f) => (
                                            <option key={f.value} value={f.value}>
                                                {f.label}
                                            </option>
                                        ))}
                                    </Select>
                                </Campo>
                            ) : (
                                <Campo etiqueta="Forma de pago" error={errores[`pagos.${indice}.forma`]}>
                                    <p className="flex h-9 items-center text-sm text-muted-foreground">
                                        {forma?.label ?? 'Transferencia bancaria'}
                                    </p>
                                </Campo>
                            )}

                            <Campo
                                etiqueta="Monto (Bs)"
                                htmlFor={`pago-${indice}-monto`}
                                error={errores[`pagos.${indice}.monto`]}
                                ayuda="Lo que dice este comprobante, no el total de la cédula."
                                obligatorio
                            >
                                <Input
                                    id={`pago-${indice}-monto`}
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    inputMode="decimal"
                                    value={pago.monto}
                                    onChange={(e) => actualizar(indice, { monto: e.target.value })}
                                    placeholder="0.00"
                                />
                            </Campo>
                        </div>

                        {/* Banco y número solo aparecen cuando la forma de pago
                            los usa. Mostrarlos siempre y dejarlos vacíos hace
                            dudar al operador de si se olvidó de algo. */}
                        {pideReferencia && (
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Campo
                                    etiqueta="N° de transacción"
                                    htmlFor={`pago-${indice}-nro`}
                                    error={errores[`pagos.${indice}.nro_transaccion`]}
                                    ayuda="El código del comprobante del banco o del QR."
                                    obligatorio
                                >
                                    <Input
                                        id={`pago-${indice}-nro`}
                                        value={pago.nro_transaccion}
                                        onChange={(e) =>
                                            actualizar(indice, {
                                                nro_transaccion: e.target.value,
                                            })
                                        }
                                        placeholder="884512203"
                                    />
                                </Campo>

                                <Campo
                                    etiqueta="Banco"
                                    htmlFor={`pago-${indice}-banco`}
                                    error={errores[`pagos.${indice}.banco`]}
                                    ayuda="Opcional. Ayuda a encontrar el pago en el extracto."
                                >
                                    <Input
                                        id={`pago-${indice}-banco`}
                                        value={pago.banco}
                                        onChange={(e) => actualizar(indice, { banco: e.target.value })}
                                        placeholder="Banco Unión"
                                    />
                                </Campo>
                            </div>
                        )}

                        <CampoRequisito
                            id={`pago-${indice}-comprobante`}
                            etiqueta="Comprobante"
                            ayuda={
                                pago.url_actual
                                    ? `Solo si hay que reemplazarlo. PDF o foto, ${archivos.ayudaPeso}.`
                                    : `Recibo, depósito o captura de la transferencia. PDF o foto, ${archivos.ayudaPeso}.`
                            }
                            error={errores[`pagos.${indice}.comprobante`]}
                            archivo={pago.comprobante}
                            onCambio={(archivo) => actualizar(indice, { comprobante: archivo })}
                        />

                        {/* El comprobante que ya está cargado, con enlace para
                            abrirlo. Al corregir un monto hay que poder mirar el
                            papel sin salir de la pantalla, y sin este enlace la
                            única forma sería volver a la ficha. */}
                        {pago.url_actual && pago.comprobante === null && (
                            <a
                                href={pago.url_actual}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="flex items-center gap-1.5 text-xs text-muted-foreground hover:text-foreground"
                            >
                                <ExternalLink className="size-3.5 shrink-0" />
                                Ver el comprobante cargado
                            </a>
                        )}
                    </div>
                );
            })}

            <div className="flex flex-wrap items-center justify-between gap-3">
                {/* El tope de 5 es el mismo que valida el servidor. Ver
                    TramiteController::validarAdjuntos(). */}
                <Button
                    type="button"
                    variant="outline"
                    onClick={agregar}
                    disabled={pagos.length >= 5}
                >
                    <Plus className="size-4" />
                    Agregar otro pago
                </Button>

                <div className="text-right text-xs">
                    <p className="text-muted-foreground">
                        Total declarado:{' '}
                        <span className="font-mono font-semibold text-foreground">
                            Bs {total.toFixed(2)}
                        </span>
                        {montoTasa !== null && (
                            <>
                                {' · Tasa: '}
                                <span className="font-mono">Bs {montoTasa.toFixed(2)}</span>
                            </>
                        )}
                    </p>

                    {faltaPlata && (
                        <p className="mt-1 flex items-center justify-end gap-1.5 text-amber-600 dark:text-amber-400">
                            <TriangleAlert className="size-3.5 shrink-0" />
                            Queda un saldo de Bs {saldo.toFixed(2)}
                        </p>
                    )}

                    {sobraPlata && (
                        <p className="mt-1 flex items-center justify-end gap-1.5 text-amber-600 dark:text-amber-400">
                            <TriangleAlert className="size-3.5 shrink-0" />
                            Lo declarado supera la tasa en Bs {Math.abs(saldo).toFixed(2)}
                        </p>
                    )}
                </div>
            </div>

            {/* El error del arreglo entero —«registre al menos un pago»— no
                pertenece a ninguna fila, así que se muestra acá abajo. */}
            {errores.pagos && (
                <p className="text-sm text-destructive" role="alert">
                    {errores.pagos}
                </p>
            )}
        </div>
    );
}
