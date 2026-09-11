import { UserRound } from 'lucide-react';
import { CodigoQr } from '@/components/comunes/codigo-qr';
import { CODIGO_MUESTRA } from '@/components/panel/tramites/hoja-documento';
import { MOLDE_REGISTRO } from '@/types/tramites';
import type { FormularioCedulaPescador, TipoTramiteOpcion } from '@/types/tramites';

/**
 * ============================================================================
 *  VISTA PREVIA — CÉDULA DE PESCADOR
 * ============================================================================
 *
 * Reproduce la credencial plastificada que emite el SEDAG, a escala.
 *
 * FORMATO CR80 (85,6 × 54 mm). Es el tamaño de una tarjeta bancaria, y es el
 * que ya devuelve `CategoriaDocumento::formatoPagina()` para la categoría
 * `credencial`. Acá se respeta con `aspect-[85.6/54]`: así lo que se ve en
 * pantalla tiene exactamente la proporción de lo que sale impreso, y una foto
 * que en la maqueta entra justa no se va a deformar en la impresora.
 *
 * LOS COLORES ESTÁN ESCRITOS FIJOS, igual que en HojaDocumento y por el mismo
 * motivo: esto no es una pantalla del sistema, es la representación de una
 * tarjeta impresa. El verde institucional tiene que verse igual en modo claro
 * y en modo oscuro, porque el operador la compara contra la credencial que
 * tiene en la mano.
 */
export function VistaPreviaCedula({
    datos,
    tipo,
    foto,
}: {
    datos: FormularioCedulaPescador;
    tipo: TipoTramiteOpcion;
    /**
     * La foto de la ficha del solicitante. No se elige en el formulario: es un
     * dato personal del padrón. Puede venir en NULL cuando este componente se
     * usa fuera del alta, y entonces se dibuja la silueta.
     */
    foto: string | null;
}) {
    return (
        <div className="space-y-2">
            <div
                className="relative mx-auto w-full max-w-md overflow-hidden rounded-xl border border-black/20 shadow-md"
                style={{
                    aspectRatio: '85.6 / 54',
                    background: 'linear-gradient(160deg, #b5d94a 0%, #7fbe3b 45%, #4f9e2c 100%)',
                }}
            >
                {/* Marca de agua: el sello del SEDAG, tenue, como en la
                    credencial real. aria-hidden porque no aporta información.
                    Va más bajo de opacidad que en las hojas de papel porque
                    acá el fondo ya es verde y el sello también. */}
                <img
                    src="/image/sedag.png"
                    alt=""
                    aria-hidden
                    className="pointer-events-none absolute top-1/2 left-1/2 w-[52%] -translate-x-1/2 -translate-y-1/2 opacity-20 select-none"
                />

                <div className="relative flex h-full flex-col p-[3.5%] text-[#14300f]">
                    <Encabezado tipo={tipo} />

                    <p
                        className="my-[1.5%] text-center text-lg leading-none font-black tracking-tight text-[#8a1b12] uppercase sm:text-xl"
                        style={{
                            // Contorno blanco: en la credencial real el título
                            // está impreso con borde para despegarlo del verde.
                            textShadow:
                                '0 1px 0 #fff, 1px 0 0 #fff, -1px 0 0 #fff, 0 -1px 0 #fff',
                        }}
                    >
                        Cédula de Pescador
                    </p>

                    <div className="flex min-h-0 flex-1 gap-[3%]">
                        <Retrato ci={datos.ci} expedido={datos.expedido} foto={foto} />

                        <dl className="flex min-w-0 flex-1 flex-col justify-center gap-[1.5%]">
                            <Renglon etiqueta="Nombre" valor={datos.nombre} />
                            <Renglon etiqueta="Asociación" valor={datos.asociacion} />
                            <Renglon etiqueta="Ciudad" valor={datos.ciudad} />
                            <Renglon etiqueta="Provincia" valor={datos.provincia} />
                            <Renglon etiqueta="Dirección" valor={datos.direccion} />

                            <div className="flex gap-[3%]">
                                {/* El código lo asigna el servidor al
                                    guardar. Hasta entonces se dibuja el molde,
                                    atenuado: el renglón vacío se lee como que
                                    la credencial va a salir sin número. */}
                                <Renglon
                                    etiqueta="Registro"
                                    valor={datos.registro}
                                    molde={MOLDE_REGISTRO}
                                    className="flex-1"
                                />
                                <Renglon
                                    etiqueta="Cupo"
                                    valor={datos.capacidad_kg ? `${datos.capacidad_kg} KG` : ''}
                                    className="flex-1"
                                />
                            </div>
                        </dl>

                        {/* El QR va al margen derecho: en una CR80 es el único
                            lugar donde entra sin comerse ningún renglón. */}
                        <div className="flex w-[19%] shrink-0 flex-col items-center justify-center gap-0.5">
                            <CodigoQr codigo={CODIGO_MUESTRA} tamano={58} className="p-0.5" />
                            <p className="text-center text-[0.4rem] leading-tight font-bold">
                                Verifique aquí
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <p className="text-center text-xs text-muted-foreground">
                Formato CR80 — 85,6 × 54 mm, el tamaño de una tarjeta bancaria.
            </p>
        </div>
    );
}

function Encabezado({ tipo }: { tipo: TipoTramiteOpcion }) {
    return (
        <div className="flex items-center gap-[2%]">
            <img
                src="/image/icon.png"
                alt=""
                aria-hidden
                className="h-[85%] max-h-[3.2rem] w-auto shrink-0 object-contain"
            />

            <div className="min-w-0 flex-1 text-center leading-[1.15] font-semibold tracking-tight uppercase">
                <p className="text-[0.52rem]">{tipo.membrete?.secretaria}</p>
                <p className="text-[0.55rem] font-bold">{tipo.membrete?.unidad}</p>
            </div>
        </div>
    );
}

/**
 * La foto del titular con su cédula de identidad debajo.
 *
 * Si todavía no se eligió foto se muestra el marco vacío con una silueta: así
 * se ve el espacio que va a ocupar y se entiende que falta cargarla.
 */
function Retrato({
    ci,
    expedido,
    foto,
}: {
    ci: string;
    expedido: string;
    foto: string | null;
}) {
    return (
        <div className="flex w-[26%] shrink-0 flex-col gap-[4%]">
            <div className="flex flex-1 items-center justify-center overflow-hidden rounded-sm border border-black/25 bg-white">
                {foto ? (
                    <img src={foto} alt="" aria-hidden className="size-full object-cover" />
                ) : (
                    <UserRound className="size-1/2 text-black/25" />
                )}
            </div>

            <p className="rounded-sm bg-white/85 px-1 py-0.5 text-center text-[0.55rem] leading-none font-bold">
                C.I. {ci || '—'} {expedido}
            </p>
        </div>
    );
}

function Renglon({
    etiqueta,
    valor,
    molde,
    className = '',
}: {
    etiqueta: string;
    valor: string;
    /** Qué dibujar mientras no hay valor, para mostrar la forma que tendrá. */
    molde?: string;
    className?: string;
}) {
    // El espacio duro mantiene la altura del renglón cuando no hay nada que
    // escribir: sin él la línea se achica y la tarjeta se descuadra.
    const vacio = valor === '';

    return (
        <div className={`flex items-baseline gap-1 ${className}`}>
            <dt className="shrink-0 text-[0.5rem] font-bold tracking-tight uppercase">
                {etiqueta}:
            </dt>
            <dd
                className={
                    vacio && molde
                        ? 'min-w-0 flex-1 truncate rounded-[2px] bg-white/85 px-1 text-[0.58rem] leading-[1.5] font-semibold text-black/35'
                        : 'min-w-0 flex-1 truncate rounded-[2px] bg-white/85 px-1 text-[0.58rem] leading-[1.5] font-semibold'
                }
            >
                {valor || molde || ' '}
            </dd>
        </div>
    );
}
