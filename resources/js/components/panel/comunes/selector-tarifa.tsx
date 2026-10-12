import { usePage } from '@inertiajs/react';
import { Campo } from '@/components/ui/campo';
import { Select } from '@/components/ui/select';
import { bs } from '@/lib/utils';
import type { PageProps, TarifaSireb } from '@/types';

/**
 * Elegir una tarifa del servicio de SIREB que Catálogos › Aranceles asigna al
 * trámite. El precio es de referencia: el que vale lo congela la emisión.
 */
export function SelectorTarifa({
    tarifas,
    servicioConfigurado,
    valor,
    onElegir,
    etiqueta = 'Tarifa',
    error,
}: {
    /** null = SIREB no respondió. */
    tarifas: TarifaSireb[] | null;
    servicioConfigurado: boolean;
    valor: string;
    onElegir: (tarifaId: string) => void;
    etiqueta?: string;
    error?: string;
}) {
    const { institucion } = usePage<PageProps>().props;

    const aviso = !servicioConfigurado
        ? 'Falta elegir el servicio de SIREB en Catálogos › Aranceles.'
        : tarifas === null
          ? 'Recaudaciones (SIREB) no responde: las tarifas no se pueden cargar ahora.'
          : tarifas.length === 0
            ? 'El servicio de SIREB no tiene tarifas.'
            : null;

    return (
        <Campo
            etiqueta={etiqueta}
            htmlFor="tarifa_sireb"
            error={error ?? aviso ?? undefined}
            ayuda="Las tarifas de Recaudaciones (SIREB). El precio se confirma al guardar."
            obligatorio
        >
            <Select
                id="tarifa_sireb"
                value={valor}
                onChange={(e) => onElegir(e.target.value)}
                disabled={!tarifas || tarifas.length === 0}
                aria-invalid={Boolean(error)}
            >
                <option value="">Elija una tarifa…</option>
                {(tarifas ?? []).map((t) => (
                    <option key={t.id} value={t.id} disabled={!t.activa}>
                        {t.etiqueta || 'Sin etiqueta'} — {bs(t.monto, institucion.moneda)}
                        {!t.activa ? ' (no disponible)' : ''}
                    </option>
                ))}
            </Select>
        </Campo>
    );
}
