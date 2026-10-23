import { Check, ShieldAlert } from 'lucide-react';
import { BuscadorBeneficiario } from '@/components/panel/comunes/buscador-beneficiario';
import { BarraDisponible } from '@/components/panel/comunes/ficha';
import { Campo } from '@/components/ui/campo';
import { cn, fecha } from '@/lib/utils';
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
                    {pescador.nombreCompleto} no tiene una Autorización de Pesca aprobada y vigente: no se le puede
                    comprar pescado.
                </p>
            )}

            {autorizaciones.length > 0 && (
                <Campo etiqueta="Autorización de la que sale el pescado" obligatorio>
                    <div role="radiogroup" aria-label="Autorización" className="grid gap-2 sm:grid-cols-2">
                        {autorizaciones.map((a) => {
                            const activa = a.id === autorizacionId;

                            return (
                                <button
                                    key={a.id}
                                    type="button"
                                    role="radio"
                                    aria-checked={activa}
                                    onClick={() => onAutorizacion(a)}
                                    className={cn(
                                        'relative space-y-2 rounded-lg border px-3 py-2.5 text-left transition-all',
                                        activa
                                            ? 'border-primary bg-primary/5 ring-2 ring-primary/30'
                                            : 'border-border hover:border-primary/50 hover:shadow-md',
                                    )}
                                >
                                    {activa && (
                                        <Check className="absolute top-2 right-2 size-3.5 rounded-full bg-primary p-0.5 text-primary-foreground" />
                                    )}
                                    <span className="block pr-5 text-sm">
                                        <span className="font-semibold">Autorización N° {a.numero}</span>
                                        <span className="text-muted-foreground">
                                            {' '}
                                            · vence {fecha(a.fecha_vencimiento)}
                                        </span>
                                    </span>
                                    {/* Acá sí va el saldo: es lo que esta guía descuenta. */}
                                    <BarraDisponible
                                        disponible={a.kilos_disponibles}
                                        total={a.volumen_total_kg}
                                        aprobada
                                    />
                                </button>
                            );
                        })}
                    </div>
                </Campo>
            )}
        </div>
    );
}
