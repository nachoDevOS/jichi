import { usePage } from '@inertiajs/react';
import { Campo } from '@/components/ui/campo';
import { Select } from '@/components/ui/select';
import { bs } from '@/lib/utils';
import type { PageProps } from '@/types';
import type { ServicioSireb } from '@/types/catalogos';

/**
 * El select de tarifa de SIREB, agrupado por servicio. Elegir una tarifa
 * devuelve también su servicio: así no se pueden cruzar. Lo usan la escala y
 * los tipos de carnet.
 */
export function SelectorTarifaSireb({
    tarifa,
    tarifaGuardada,
    servicioGuardado,
    serviciosSireb,
    tarifasUsadas,
    compartible = false,
    error,
    onElegir,
}: {
    tarifa: string;
    /** La que tiene hoy el registro (null en un alta): no cuenta como ocupada. */
    tarifaGuardada: string | null;
    servicioGuardado: string | null;
    /** null = SIREB no respondió: el select queda deshabilitado. */
    serviciosSireb: ServicioSireb[] | null;
    /** Id de tarifa → qué registro la usa ya. */
    tarifasUsadas: Record<string, string>;
    /** true = varios registros pueden usar la misma tarifa: se avisa, no se bloquea. */
    compartible?: boolean;
    error?: string;
    onElegir: (tarifaId: string, servicioId: string) => void;
}) {
    const { institucion } = usePage<PageProps>().props;

    // La tarifa guardada ya no llega de SIREB (o SIREB no responde): se ofrece
    // igual, para que editar otro campo no la borre sin avisar.
    const guardadaAusente =
        tarifaGuardada !== null &&
        !(serviciosSireb ?? []).some((s) => s.tarifas.some((t) => t.id === tarifaGuardada));

    function elegir(tarifaId: string) {
        const servicio = serviciosSireb?.find((s) => s.tarifas.some((t) => t.id === tarifaId));

        onElegir(tarifaId, servicio?.id ?? servicioGuardado ?? '');
    }

    return (
        <Campo
            etiqueta="Tarifa SIREB"
            htmlFor="tarifa_sireb"
            error={error}
            ayuda={
                serviciosSireb === null
                    ? 'Recaudaciones (SIREB) no responde: no se puede elegir la tarifa ahora.'
                    : 'De ahí sale el precio, que se congela al cobrar.'
            }
            obligatorio
        >
            <Select
                id="tarifa_sireb"
                value={tarifa}
                onChange={(e) => elegir(e.target.value)}
                disabled={serviciosSireb === null}
                aria-invalid={Boolean(error)}
            >
                <option value="">Elija una tarifa…</option>

                {guardadaAusente && (
                    <option value={tarifaGuardada}>
                        Tarifa actual{serviciosSireb === null ? '' : ' (no está en SIREB)'}
                    </option>
                )}

                {(serviciosSireb ?? []).map((s) => (
                    <optgroup
                        key={s.id}
                        label={`${s.nombre}${s.codigo ? ` (${s.codigo})` : ''}${s.activo ? '' : ' — de baja'}`}
                    >
                        {s.tarifas.map((t) => {
                            const usadaPor = tarifasUsadas[t.id];
                            const ocupada = usadaPor !== undefined && t.id !== tarifaGuardada;

                            return (
                                <option key={t.id} value={t.id} disabled={!s.activo || (ocupada && !compartible)}>
                                    {t.etiqueta || 'Sin etiqueta'} — {bs(t.monto, institucion.moneda)}
                                    {ocupada ? ` (${compartible ? 'también' : 'ya la usa'}: ${usadaPor})` : ''}
                                </option>
                            );
                        })}
                    </optgroup>
                ))}
            </Select>
        </Campo>
    );
}
