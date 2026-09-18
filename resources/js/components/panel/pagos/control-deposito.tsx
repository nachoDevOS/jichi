import { router, useForm } from '@inertiajs/react';
import { Check, ShieldAlert } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { ConfirmarAccion } from '@/components/ui/confirmar-accion';
import { ConfirmarConMotivo } from '@/components/ui/confirmar-con-motivo';
import { usePermisos } from '@/hooks/use-permisos';
import { fechaHora } from '@/lib/utils';
import type { ControlDePago } from '@/types/pagos';

/**
 * ============================================================================
 *  EL CONTROL DE UN DEPÓSITO
 * ============================================================================
 *
 * Alguien abre la boleta escaneada y la compara contra el extracto del banco.
 * Si cuadra la VALIDA; si no, la OBSERVA y escribe qué está mal.
 *
 * Son DOS componentes y no uno, porque se usan en lugares distintos: el estado
 * se muestra en las dos pantallas —libro de caja y ficha del trámite— y los
 * botones solo donde se trabaja.
 *
 * ----------------------------------------------------------------------------
 *  QUIÉN VE LOS BOTONES
 * ----------------------------------------------------------------------------
 *
 * Hacen falta DOS cosas, y las dos vienen del servidor:
 *
 *   el PERMISO `pagos.validar`  — quién puede controlar, en general;
 *   `puede_validarse`           — si puede controlar ESTE depósito, que es
 *                                 false cuando lo cargó uno mismo.
 *
 * Esconder un botón no es seguridad: quien lo aplica de verdad es el middleware
 * de la ruta y `ValidacionPagoService`. Esto es para que la pantalla no ofrezca
 * lo que va a rebotar.
 */
export function EstadoControl({ pago }: { pago: ControlDePago }) {
    return (
        <div className="space-y-1">
            <Badge color={pago.validacion_color}>{pago.validacion_etiqueta}</Badge>

            {/*
                Quién y cuándo, que es el punto de todo esto: una auditoría
                pregunta quién dio por bueno ese dinero y en qué momento.

                Se muestra también cuando está OBSERVADO: observar también es
                haber revisado, y sirve para saber hace cuánto está trabado.
            */}
            {pago.validado_por && (
                <p className="text-xs text-muted-foreground">
                    {pago.validado_por} · {fechaHora(pago.validado_at)}
                </p>
            )}

            {pago.motivo_observacion && (
                <p className="max-w-xs text-xs text-rose-700 dark:text-rose-400">
                    {pago.motivo_observacion}
                </p>
            )}
        </div>
    );
}

/**
 * Los dos botones.
 *
 * `compacto` los deja en tamaño chico y sin texto para la fila de una tabla;
 * sin él van con rótulo, que es lo que corresponde en la ficha, donde hay lugar
 * y la acción es la que el revisor vino a hacer.
 */
export function AccionesControl({
    pago,
    compacto = false,
}: {
    pago: ControlDePago & { id: number; nro_transaccion: string };
    compacto?: boolean;
}) {
    const { puede } = usePermisos();
    const [validando, setValidando] = useState(false);
    const [observando, setObservando] = useState(false);

    const form = useForm({ motivo: '' });

    if (!puede('pagos.validar')) {
        return null;
    }

    /*
     * CUANDO NO SE PUEDE, SE DICE.
     *
     * Son dos motivos distintos y el servidor los distingue:
     *
     *   NO ES EL MOMENTO   el control es parte de la revisión, así que un
     *                      expediente pendiente todavía no y uno resuelto ya no;
     *   NO ES LA PERSONA   quien cargó la boleta no puede darla por buena.
     *
     * En vez de esconder los botones y dejar al operador preguntándose qué pasó
     * —que es lo que más confunde cuando algo desaparece— se muestra el texto
     * que mandó el servidor. Ver `Pago::motivoSinControl()`.
     */
    if (pago.motivo_sin_control) {
        return <p className="text-xs text-muted-foreground">{pago.motivo_sin_control}</p>;
    }

    /*
     * VALIDAR PASÓ A PEDIR CONFIRMACIÓN, y no es una traba de más.
     *
     * Antes era un clic directo. Pero validar no es «guardar»: es DECLARAR que
     * se comparó la boleta contra el extracto del banco, y esa declaración
     * queda firmada con nombre y hora. Un solo clic la vuelve un trámite de
     * memoria —a la décima vez la mano va sola— que es justo lo contrario de un
     * control.
     *
     * La casilla dice qué se está afirmando. Es la frase que después respalda
     * la firma.
     */
    function validar() {
        router.patch(
            route('pagos.validar', pago.id),
            {},
            { preserveScroll: true, onSuccess: () => setValidando(false) },
        );
    }

    function observar() {
        form.patch(route('pagos.observar', pago.id), {
            preserveScroll: true,
            onSuccess: () => {
                setObservando(false);
                form.reset();
            },
        });
    }

    return (
        <>
            <div className="flex items-center gap-1.5">
                {/*
                    VALIDAR SOLO SOBRE UN DEPÓSITO SIN CONTROLAR.

                    Hubo una versión donde el botón aparecía también sobre un
                    OBSERVADO, pensando en el revisor que ve la boleta corregida
                    y la da por buena sin pasos de más. Tenía sentido mientras no
                    existía la corrección: era la única salida que había.

                    Hoy es al revés. Validar un observado sin que nadie haya
                    tocado el dato es dar por bueno justo lo que se marcó como
                    malo, y el problema señalado se pierde sin que quede si se
                    arregló. El camino es:

                        OBSERVADO ──[corregir]──▶ SIN CONTROLAR ──[validar]──▶ VALIDADO

                    y al corregir el depósito vuelve solo a «sin controlar». Lo
                    mismo vale si la observación estaba equivocada: se abre
                    «Corregir» y se guarda sin cambiar nada.

                    `ValidacionPagoService::validar()` aplica la misma regla; esto
                    es para que la pantalla no ofrezca lo que va a rebotar.
                */}
                {/*
                    COMPACTO SIGNIFICA SIN TEXTO, NO SIN NOMBRE.

                    En la fila de una tabla el rótulo no entra, pero un icono
                    suelto no se puede identificar: ni un lector de pantalla ni
                    alguien que pasa el mouse sabrían cuál es cuál. Por eso van
                    `title` —el globito del navegador— y `aria-label` siempre, y
                    el texto visible solo cuando hay lugar.

                    El nombre incluye el NÚMERO DE TRANSACCIÓN: con quince filas en
                    pantalla, «Validar» a secas no dice cuál de todas.
                */}
                {pago.validacion === 'pendiente' && (
                    <Button
                        type="button"
                        variant="outline"
                        size={compacto ? 'sm' : 'default'}
                        onClick={() => setValidando(true)}
                        title={`Validar el depósito ${pago.nro_transaccion} — la boleta cuadra con el extracto`}
                        aria-label={`Validar el depósito ${pago.nro_transaccion}`}
                    >
                        <Check className="size-4" />
                        {compacto ? null : 'Validar'}
                    </Button>
                )}

                {/*
                    EN LUGAR DEL BOTÓN, LA SALIDA ESCRITA.

                    Sin esto el observado queda con un solo botón —«Observar»,
                    que ya está puesto— y el revisor no tiene cómo saber que lo
                    que falta es que ventanilla lo corrija. Es el mismo criterio
                    de `motivo_sin_control`: cuando algo no se puede, se dice, y
                    se dice qué SÍ se puede.

                    En modo compacto no va: en la fila de una tabla no hay lugar,
                    y ahí la columna «Control» ya muestra el estado «Observado»
                    con su motivo.
                */}
                {pago.validacion === 'observado' && !compacto && (
                    <p className="max-w-56 text-xs text-muted-foreground">
                        Corríjalo para volver a controlarlo.
                    </p>
                )}

                {pago.validacion !== 'observado' && (
                    <Button
                        type="button"
                        variant="ghost"
                        size={compacto ? 'sm' : 'default'}
                        onClick={() => setObservando(true)}
                        title={`Observar el depósito ${pago.nro_transaccion} — marcar que no cuadra, con el motivo`}
                        aria-label={`Observar el depósito ${pago.nro_transaccion}`}
                    >
                        <ShieldAlert className="size-4" />
                        {compacto ? null : 'Observar'}
                    </Button>
                )}
            </div>

            <ConfirmarAccion
                abierto={validando}
                titulo={`Validar el depósito ${pago.nro_transaccion}`}
                descripcion={
                    <>
                        Queda registrado con su nombre y la hora, y el trámite pasa a poder
                        aprobarse. Si después aparece un problema, el depósito se puede
                        observar.
                    </>
                }
                textoConfirmar="Validar depósito"
                confirmacion="Comparé la boleta con el extracto del banco y el número de transacción, el monto y la fecha coinciden."
                onConfirmar={validar}
                onCancelar={() => setValidando(false)}
            />

            <ConfirmarConMotivo
                abierto={observando}
                titulo={`Observar el depósito ${pago.nro_transaccion}`}
                // Sin `descripcion`: se sacó a pedido. Lo que decía —que el
                // trámite no se puede aprobar y que esto no borra nada— ya está
                // en el aviso de la ficha y en la casilla de abajo, y repetido
                // en la ventana solo empujaba el campo del motivo fuera de vista.
                etiquetaMotivo="Qué no cuadra"
                ayuda="Es lo único que le dice a ventanilla qué tiene que corregir."
                placeholder="Ej.: la boleta dice 120 Bs y la fila está cargada con 150."
                textoConfirmar="Observar depósito"
                confirmacion="Revisé la boleta y confirmo que el dato observado no coincide con lo cargado."
                valor={form.data.motivo}
                onCambiar={(v) => form.setData('motivo', v)}
                error={form.errors.motivo}
                procesando={form.processing}
                onConfirmar={observar}
                onCancelar={() => setObservando(false)}
            />
        </>
    );
}
