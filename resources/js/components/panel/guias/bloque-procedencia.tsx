import { ShieldAlert } from 'lucide-react';
import { BuscadorBeneficiario } from '@/components/panel/comunes/buscador-beneficiario';
import { Campo } from '@/components/ui/campo';
import { fecha } from '@/lib/utils';
import type { AutorizacionVendible, BeneficiarioSugerido } from '@/types/beneficiarios';

/**
 *  Procedencia del pescado — a qué pescador se le compró. Sus kilos se
 *  descuentan de la autorización elegida al registrar la guía
 */
export function BloqueProcedencia({
    pescador,
    autorizacionId,
    error,
    onPescador,
    onAutorizacion,
}: {
    pescador: BeneficiarioSugerido | null;
    autorizacionId: number | null;
    error?: string;
    onPescador: (p: BeneficiarioSugerido | null) => void;
    onAutorizacion: (a: AutorizacionVendible | null) => void;
}) {
    const autorizaciones = pescador?.autorizaciones_vendibles ?? [];
    // Buscada por código, pero esa autorización no está aprobada y en fecha.
    const buscadaNoVendible =
        pescador?.autorizacion_buscada_id != null &&
        !autorizaciones.some((a) => a.id === pescador.autorizacion_buscada_id);

    return (
        <div className="space-y-5">
            <Campo etiqueta="Pescador que vende" obligatorio error={error}>
                <BuscadorBeneficiario
                    seleccionado={pescador}
                    onSeleccionar={(p) => {
                        onPescador(p);
                        const vendibles = p?.autorizaciones_vendibles ?? [];
                        // Por código: esa y ninguna otra. Si no, la única vigente que suele tener.
                        const elegida =
                            p?.autorizacion_buscada_id != null
                                ? (vendibles.find((a) => a.id === p.autorizacion_buscada_id) ?? null)
                                : vendibles.length === 1
                                  ? vendibles[0]
                                  : null;
                        onAutorizacion(elegida);
                    }}
                    porCodigoDeAutorizacion
                    ayuda="Nombre, C.I. o código de su autorización (o escanee el QR). Tiene que estar aprobada y en fecha."
                />
            </Campo>

            {buscadaNoVendible && autorizaciones.length > 0 && (
                <p className="flex items-start gap-2 rounded-md border border-destructive/40 bg-destructive/5 p-3 text-sm text-destructive">
                    <ShieldAlert className="mt-0.5 size-4 shrink-0" />
                    La autorización de ese código no está aprobada y en fecha. Elija otra de la lista.
                </p>
            )}

            {pescador && autorizaciones.length === 0 && (
                <p className="flex items-start gap-2 rounded-md border border-destructive/40 bg-destructive/5 p-3 text-sm text-destructive">
                    <ShieldAlert className="mt-0.5 size-4 shrink-0" />
                    {pescador.nombreCompleto} no tiene una Autorización de Pesca aprobada y vigente: no se le
                    puede comprar pescado.
                </p>
            )}

            {autorizaciones.length > 0 && (
                <Campo etiqueta="Autorización de la que sale el pescado" obligatorio>
                    <ul className="space-y-2">
                        {autorizaciones.map((a) => {
                            const activa = a.id === autorizacionId;

                            return (
                                <li key={a.id}>
                                    <button
                                        type="button"
                                        onClick={() => onAutorizacion(a)}
                                        className={[
                                            'flex w-full items-center justify-between gap-3 rounded-md border p-3 text-left transition-colors hover:bg-secondary',
                                            activa ? 'border-primary bg-secondary' : 'border-border',
                                        ].join(' ')}
                                    >
                                        <span className="min-w-0">
                                            <span className="block text-sm font-medium">
                                                N° {a.numero} · {a.escala ?? `${a.volumen_total_kg} kg`}
                                            </span>
                                            <span className="block text-xs text-muted-foreground">
                                                vence {fecha(a.fecha_vencimiento)}
                                            </span>
                                        </span>
                                        <span className="shrink-0 text-right text-sm tabular-nums">
                                            <span className="font-semibold">{a.kilos_disponibles} kg</span>
                                            <span className="block text-xs text-muted-foreground">
                                                disponibles de {a.volumen_total_kg}
                                            </span>
                                        </span>
                                    </button>
                                </li>
                            );
                        })}
                    </ul>
                </Campo>
            )}
        </div>
    );
}
