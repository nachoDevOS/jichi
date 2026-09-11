import { CodigoQr } from '@/components/comunes/codigo-qr';
import { HojaOficial } from '@/components/publico/hoja-oficial';
import { cn, fecha, fechaHora } from '@/lib/utils';
import type { DocumentoPublico, InstitucionPublica } from '@/types/publico';

/**
 * ============================================================================
 *  EL ACTA DE VERIFICACIÓN
 * ============================================================================
 *
 * El resultado, escrito como lo escribiría una oficina: un párrafo que deja
 * constancia de lo consultado y el detalle del documento debajo.
 *
 * Está pensado para leerse en dos tiempos, que es como se lee de verdad:
 *
 *   1. DE UN GOLPE — la última frase del párrafo y el renglón «Estado», que
 *      dicen la palabra que decide todo: VIGENTE, VENCIDO, ANULADO. El
 *      inspector resuelve con eso, parado en el muelle y a pleno sol.
 *   2. CON CALMA — el resto de los datos, para cuando hay que discutir algo
 *      o anotar el código de verificación.
 *
 * ----------------------------------------------------------------------------
 *  LA DISTINCIÓN QUE MÁS IMPORTA
 * ----------------------------------------------------------------------------
 *
 * «Auténtico» y «vigente» NO son lo mismo, y confundirlos es el error que este
 * diseño trata de evitar. Un permiso vencido salió de este sistema —es real,
 * no es una falsificación— pero no habilita a pescar. Si la pantalla dijera
 * solo «válido» o «inválido», el inspector no sabría si tiene enfrente un
 * documento falso o uno legítimo que caducó, que son dos situaciones con
 * consecuencias muy distintas para el pescador.
 *
 * Por eso el acta lo dice dos veces y de dos maneras: en el párrafo de arriba,
 * redactado, y en la nota del final, en lenguaje llano.
 */

/**
 * Lo que cambia según el estado. Las clases van escritas COMPLETAS
 * ('text-amber-800' y no `text-${color}-800`) porque Tailwind solo incluye en
 * el CSS final las que puede leer literalmente en el código: una clase armada
 * juntando textos nunca llegaría al archivo compilado.
 */
const TEXTO_ESTADO = {
    vigente: {
        constancia: 'figura EMITIDO por esta institución y se encuentra VIGENTE a la fecha.',
        notaTitulo: 'Documento auténtico y vigente',
        nota: 'Fue emitido y validado electrónicamente por el Gobierno Autónomo Departamental del Beni. Habilita la actividad que ampara.',
        marco: 'border-emerald-700/40 bg-emerald-50/60 text-emerald-900',
    },
    vencido: {
        constancia:
            'figura EMITIDO por esta institución, pero su período de vigencia se encuentra VENCIDO.',
        notaTitulo: 'Auténtico, pero fuera de vigencia',
        nota: 'El documento salió de este sistema y no es una falsificación, pero su período de vigencia expiró. NO habilita la actividad que ampara.',
        marco: 'border-amber-700/40 bg-amber-50/70 text-amber-900',
    },
    anulado: {
        constancia: 'figura EMITIDO por esta institución y fue posteriormente ANULADO.',
        notaTitulo: 'Documento dado de baja',
        nota: 'La institución anuló este documento. No tiene validez alguna, aunque el papel esté en buen estado.',
        marco: 'border-rose-700/40 bg-rose-50/70 text-rose-900',
    },
} as const;

export function FichaDocumento({
    documento,
    institucion,
}: {
    documento: DocumentoPublico;
    institucion: InstitucionPublica;
}) {
    const texto = TEXTO_ESTADO[documento.estado];

    return (
        <HojaOficial
            institucion={institucion}
            pie={
                <p className="text-center font-serif text-[11px] text-slate-500 italic">
                    Consulta realizada el {fechaHora(new Date())}
                </p>
            }
        >
            <h1 className="mt-6 text-center font-serif text-[17px] leading-tight font-bold tracking-[0.08em] text-slate-800 uppercase">
                Constancia de autenticidad
            </h1>

            {/*
                El párrafo redactado es lo que convierte esto en un acta y no
                en una ficha de datos. Dice, en una sola frase, las tres cosas
                que alguien necesita para actuar: qué se consultó, qué se
                encontró y en qué estado está.

                Va justificado y con serifas porque así se ve un documento; el
                resto del sistema usa la tipografía sin serifas de siempre.
            */}
            <p className="mt-4 text-justify font-serif text-[13px] leading-relaxed text-slate-700">
                Se deja constancia de que, consultado el registro electrónico de documentos
                emitidos por esta institución, el documento identificado con el código de
                verificación{' '}
                <b className="font-mono text-[12px] font-bold tracking-wider break-all text-slate-900">
                    {documento.codigo_verificacion}
                </b>{' '}
                {texto.constancia}
            </p>

            <div className="mt-5">
                {/*
                    <dl> es la etiqueta de HTML para listas de "término y
                    definición". Es lo correcto para una ficha de datos: los
                    lectores de pantalla anuncian el par completo —etiqueta y
                    valor— en vez de leer palabras sueltas.
                */}
                <dl>
                    <Renglon etiqueta="Titular" valor={documento.titular} />
                    {/* Llega enmascarado desde PHP: acá nunca se ve el CI entero. */}
                    <Renglon etiqueta="Cédula de identidad" valor={documento.documento_titular} mono />
                    <Renglon
                        etiqueta="Documento"
                        valor={`${documento.icono_area ?? ''} ${documento.tipo_documento}`.trim()}
                    />
                    <Renglon etiqueta="Categoría" valor={documento.categoria} />
                    <Renglon etiqueta="Unidad emisora" valor={documento.area} />
                    <Renglon etiqueta="Fecha de emisión" valor={fecha(documento.fecha_emision)} />
                    <Renglon
                        etiqueta="Vence"
                        valor={
                            documento.fecha_vencimiento
                                ? fecha(documento.fecha_vencimiento)
                                : 'Sin vencimiento'
                        }
                        resaltar={documento.estado === 'vencido'}
                    />
                    <Renglon
                        etiqueta="Estado"
                        valor={documento.estado_etiqueta}
                        resaltar={documento.estado !== 'vigente'}
                    />
                </dl>

                {/* El mismo QR que trae impreso el papel. Sirve para comparar a
                    ojo que el documento físico no fue alterado, y para volver a
                    esta misma pantalla desde otro teléfono. */}
                <div className="mt-6 flex items-center gap-3">
                    <CodigoQr codigo={documento.codigo_verificacion} tamano={64} />

                    <div className="min-w-0">
                        <p className="text-[9px] font-semibold tracking-[0.14em] text-slate-400 uppercase">
                            Código de verificación
                        </p>
                        <p className="font-mono text-[12px] font-bold tracking-wider break-all text-slate-700">
                            {documento.codigo_verificacion}
                        </p>
                    </div>
                </div>

            </div>

            {/* La misma advertencia del párrafo, ahora en lenguaje de la calle.
                Se repite a propósito: es la línea que decide si el pescador
                puede trabajar hoy o no. */}
            <div className={cn('mt-7 border-l-4 py-2.5 pr-3 pl-3.5', texto.marco)}>
                <p className="text-[12px] leading-snug">
                    <b className="block font-semibold">{texto.notaTitulo}</b>
                    {texto.nota}
                </p>
            </div>
        </HojaOficial>
    );
}

/**
 * Un renglón del detalle, con puntos suspensivos entre la etiqueta y el valor.
 *
 * La línea de puntos no es adorno: es lo que impide que se lea mal un renglón
 * corto en una pantalla angosta. Sin ella, «Vence» y «30/09/2026» quedan tan
 * separados que el ojo los toma como dos cosas distintas.
 *
 * Se dibuja con un borde punteado de un elemento vacío que crece —flex-1— en
 * vez de repetir caracteres «.», porque así no la lee en voz alta un lector de
 * pantalla ni se copia al seleccionar el texto.
 */
function Renglon({
    etiqueta,
    valor,
    mono = false,
    resaltar = false,
}: {
    etiqueta: string;
    valor: string;
    mono?: boolean;
    resaltar?: boolean;
}) {
    return (
        <div className="flex items-baseline gap-2 py-[5px]">
            <dt className="shrink-0 text-[10px] font-semibold tracking-[0.1em] text-slate-400 uppercase">
                {etiqueta}
            </dt>

            <i aria-hidden className="min-w-4 flex-1 border-b border-dotted border-slate-300" />

            {/*
                cn() y no un texto armado a mano: 'text-slate-800' y
                'text-amber-800' son la misma propiedad, y con una cadena suelta
                gana la que Tailwind haya puesto última en el CSS, no la que
                está última acá. cn() resuelve el conflicto y deja la correcta.
            */}
            <dd
                className={cn(
                    'min-w-0 text-right text-[12.5px] font-bold text-slate-800 uppercase',
                    mono && 'font-mono tracking-wide',
                    resaltar && 'text-amber-800',
                )}
            >
                {valor}
            </dd>
        </div>
    );
}
