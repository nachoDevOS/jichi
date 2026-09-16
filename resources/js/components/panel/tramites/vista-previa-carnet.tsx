import { UserRound } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { SituacionBeneficiario } from '@/types/beneficiarios';

/**
 * ============================================================================
 *  VISTA PREVIA DEL CARNET
 * ============================================================================
 *
 * Reproduce, a escala, la credencial plastificada que se entrega en ventanilla:
 * el mismo verde con el sello de agua, el mismo encabezado, el mismo título en
 * rojo perfilado de dorado, la foto a la izquierda con la cédula debajo y los
 * seis renglones sobre sus tiras claras.
 *
 * ----------------------------------------------------------------------------
 *  ESTE COMPONENTE Y EL PDF SON EL MISMO DISEÑO, ESCRITO DOS VECES
 * ----------------------------------------------------------------------------
 *
 * Lo que sale impreso lo dibuja
 * `resources/views/documentos/carnet-pescador.blade.php`.
 *
 * **SI SE TOCA ESTE, SE TOCA AQUEL.** Si los dos se separan, esta vista previa
 * pasa a ser una promesa que el PDF no cumple, y el operador se entera con el
 * pescador ya en la ventanilla — que es justo el problema que este recuadro
 * viene a evitar. Ver docs/modulos/CARNETS.md §1.
 *
 * Una cosa que el PDF hace DISTINTO a propósito: **un valor largo se achica en
 * vez de cortarse**. Acá el `truncate` está bien —es una maqueta y el dato
 * completo está en la ficha—, pero en el plástico un nombre recortado pierde los
 * apellidos, que es lo que identifica a la persona en un control.
 *
 * FORMATO CR80 (85,6 × 54 mm): el tamaño de una tarjeta bancaria. Se respeta con
 * `aspectRatio`, así lo que se ve en pantalla tiene exactamente la proporción de
 * lo que sale impreso —una foto que en la maqueta entra justa no se va a
 * deformar en la impresora—.
 *
 * ----------------------------------------------------------------------------
 *  EL RUBRO Y EL CUPO AHORA SÍ VAN IMPRESOS
 * ----------------------------------------------------------------------------
 *
 * Este comentario decía lo contrario hasta el cambio de modelo, y el motivo
 * viejo era bueno: el carnet era UNO por persona y gestión, con los rubros
 * colgados aparte, así que una adición de octubre dejaba vieja la lista impresa
 * y la tarjeta pasaba a decir MENOS de lo que la persona podía hacer. Con el
 * cupo pasaba algo parecido: era un tope POR ACTIVIDAD, y con dos rubros había
 * dos cupos y un solo renglón donde ponerlos.
 *
 * Hoy el carnet es de UN rubro, y ese rubro es parte de la llave que lo
 * identifica: no cambia nunca. No queda nada que pueda dejar vieja la
 * impresión, y el cupo es uno solo.
 *
 * Y es más que una posibilidad, es necesario: dos carnets de la misma persona
 * en la misma gestión son dos plásticos con el mismo nombre, la misma foto y el
 * mismo domicilio. Sin el rubro impreso nada los distingue a simple vista.
 *
 * LO QUE SIGUE SIN IR es la FECHA DE VENCIMIENTO: todos los carnets de una
 * gestión vencen el mismo día, así que el año ya lo dice; y si la pregunta es
 * si HOY vale, la fecha impresa nunca fue la respuesta, porque un carnet puede
 * estar anulado o suspendido con su fecha intacta. Eso se consulta en el panel.
 *
 * EL TÍTULO LLEVA EL RUBRO ADENTRO: «CÉDULA DE PESCADOR», como el plástico de
 * papel. Decía «CÉDULA» a secas mientras el carnet era uno para todas las
 * actividades de una persona —nombrar una habría dicho algo que el documento no
 * era—; hoy es de un solo rubro que no cambia nunca, y el título es lo que se
 * lee de lejos, antes que cualquier renglón.
 *
 * ----------------------------------------------------------------------------
 *  LA TARJETA NO LLEVA QR, Y ESO TIENE UN COSTO
 * ----------------------------------------------------------------------------
 *
 * Lo tuvo y se sacó a pedido. La verificación pública sigue existiendo —la
 * pantalla, la firma de validación, todo— pero DESDE EL PLÁSTICO YA NO HAY FORMA
 * DE LLEGAR A ELLA: quien tenga el carnet en la mano no puede comprobar si es
 * real ni ver si sigue vigente. Eso ahora solo se consulta desde el panel.
 *
 * Es la misma limitación que tenía la credencial de papel, que era su problema
 * central: quien la miraba tenía que creerle.
 *
 * Lo que sí se imprime es el NÚMERO DE REGISTRO —000013, el id del carnet—:
 * corto, dictable por teléfono y, sobre todo, inofensivo, porque no abre nada.
 * La FIRMA de validación no se imprime en ningún lado del plástico.
 *
 * ----------------------------------------------------------------------------
 *  PARA QUÉ SIRVE MIENTRAS SE CARGA EL TRÁMITE
 * ----------------------------------------------------------------------------
 *
 * El operador está cargando papeles y depósitos, y lo que va a recibir el
 * pescador es esta tarjeta. Verla armarse responde de un vistazo que la ficha NO
 * tiene fotografía cargada —y el carnet sale con el hueco—, que es lo que de
 * otro modo se descubre recién al imprimir.
 *
 * ----------------------------------------------------------------------------
 *  LOS COLORES VAN ESCRITOS FIJOS, NO CON TOKENS DEL TEMA
 * ----------------------------------------------------------------------------
 *
 * El resto del sistema cambia con el modo claro y oscuro del dispositivo. Esto
 * no: no es una pantalla, es la representación de una tarjeta IMPRESA. El verde
 * institucional tiene que verse igual siempre, porque el operador la compara
 * contra la credencial que tiene en la mano.
 */
export function VistaPreviaCarnet({
    situacion,
    beneficiario,
    asociacion,
    rubro,
    capacidadKg,
    requiereCapacidad = false,
}: {
    situacion: SituacionBeneficiario;
    beneficiario: {
        nombreCompleto: string;
        documento_identidad: string;
        foto_url: string | null;
        ciudad: string | null;
        provincia: string | null;
        direccion: string | null;
    };
    /**
     * La que se está escribiendo en el formulario, para verla aparecer mientras
     * se teclea. Si viene vacía se cae a la que ya tiene el carnet emitido.
     */
    asociacion?: string;
    /**
     * EL RUBRO ELEGIDO EN EL PASO ANTERIOR, y con él se decide todo lo demás.
     *
     * Es el dato que convirtió a esta vista previa en algo distinto: con un
     * carnet por actividad, la tarjeta que se va a imprimir depende del rubro,
     * no solo de la persona. Sin rubro elegido todavía —paso 1 del formulario—
     * llega en null y el renglón muestra su molde.
     */
    rubro?: string | null;
    /** El cupo que se está tecleando, en kilos. Se formatea acá. */
    capacidadKg?: string;
    /**
     * Si la actividad elegida se autoriza por volumen.
     *
     * Cuando es `false` el renglón del rubro NO lleva el par del cupo y se queda
     * con la tira entera — igual que el PDF. Un renglón partido con la mitad
     * derecha vacía se lee como un dato que falta, cuando en realidad esa
     * actividad no tiene cupo que declarar.
     */
    requiereCapacidad?: boolean;
}) {
    /*
     * EL CARNET QUE SE ESTÁ POR TOCAR ES EL DE ESTE RUBRO, no «el de la
     * gestión»: la persona puede tener varios y solo uno corresponde.
     *
     * Si lo encuentra, el trámite es una ACTUALIZACIÓN y la tarjeta ya existe
     * —se muestra su número y sus datos—. Si no, es una emisión inicial y todo
     * va con su molde.
     */
    const carnet = situacion.carnets.find((c) => c.rubro === rubro) ?? null;

    /*
     * EL NÚMERO DE REGISTRO ES LO QUE SE IMPRIME.
     *
     * Lo asigna el servidor al emitir —es el id de la fila rellenado con ceros—,
     * así que hasta entonces se dibuja el molde atenuado con la forma exacta que
     * va a tener; un renglón vacío se leería como que el carnet va a salir sin
     * número.
     *
     * Si la persona YA tiene carnet DE ESTE RUBRO se muestra el suyo de verdad:
     * el trámite es una actualización y no va a emitir otro, así que el número
     * impreso no cambia.
     */
    const registro = carnet?.registro ?? '';
    const moldeRegistro = '000000';

    /*
     * Lo que se escribe AHORA gana sobre lo que tiene el carnet emitido.
     *
     * Así el operador ve la asociación aparecer en la tarjeta mientras la
     * teclea, que es el punto de tener una vista previa. Sobre un carnet que ya
     * existe, el campo arranca vacío y se ve la que quedó impresa.
     */
    const asociacionImpresa = asociacion?.trim() || carnet?.asociacion || '';
    const sinCargar = 'Sin cargar en la ficha';

    /*
     * EL CUPO SE ESCRIBE COMO VA IMPRESO: «600 KG».
     *
     * Lo que llega es lo tecleado en el formulario —texto suelto— y lo que el
     * carnet ya tiene viene del servidor ya formateado (Carnet::capacidadLegible).
     * Se unifican acá para que la maqueta muestre siempre la misma forma que el
     * PDF, que es la única razón de ser de esta vista previa.
     *
     * Los decimales en cero se recortan: la unidad trabaja en kilos enteros y
     * «600,00 KG» gasta cuatro caracteres de un renglón que ya viene justo.
     */
    const cupoTecleado = capacidadKg?.trim() ?? '';
    const cupoNumero = Number(cupoTecleado.replace(',', '.'));
    const cupoImpreso =
        cupoTecleado !== '' && Number.isFinite(cupoNumero) && cupoNumero > 0
            ? `${cupoNumero.toLocaleString('es-BO', { maximumFractionDigits: 2 })} KG`
            : (carnet?.capacidad ?? '');

    return (
        <div className="space-y-2">
            <div
                className="relative mx-auto w-full max-w-md overflow-hidden rounded-xl border border-black/20 shadow-md"
                style={{
                    aspectRatio: '85.6 / 54',
                    background: 'linear-gradient(180deg, #719327 0%, #5c8b18 55%, #518411 100%)',
                }}
            >
                {/* Marca de agua: el sello del SEDAG, como en la credencial real.
                    aria-hidden porque no aporta información. */}
                <img
                    src="/image/sedag.png"
                    alt=""
                    aria-hidden
                    className="pointer-events-none absolute top-1/2 left-1/2 w-[46%] -translate-x-1/2 -translate-y-1/2 opacity-30 select-none"
                />

                <div className="relative flex h-full flex-col p-[3.5%] text-[#14350f]">
                    <Encabezado />

                    <Titulo rubro={rubro} />

                    <div className="mt-[1%] flex min-h-0 flex-1 gap-[2.3%]">
                        <Retrato
                            documento={beneficiario.documento_identidad}
                            foto={beneficiario.foto_url}
                        />

                        <dl className="flex min-w-0 flex-1 flex-col justify-start gap-[2%]">
                            <Renglon etiqueta="Nombre" valor={beneficiario.nombreCompleto} molde="Sin nombre en la ficha" />

                            {/*
                                NI EL RUBRO NI EL CUPO TIENEN RENGLÓN ACÁ, y los
                                dos se fueron por el mismo motivo: en una CR80
                                entran seis, y gastarlos en datos que caben en
                                otro lado es lo más caro que puede hacerse.

                                El RUBRO lo dice el TÍTULO, arriba. El CUPO va en
                                la columna de la foto, debajo de la cédula, donde
                                había espacio muerto. Ver
                                CarnetImpresionController::cupo().
                            */}

                            <Renglon etiqueta="Asociación" valor={asociacionImpresa} molde="Cargue la asociación" />

                            {/*
                                El domicilio sale de la FICHA de la persona, no
                                del formulario: son datos del padrón. Si aparecen
                                vacíos hay que ir a editar al beneficiario, y por
                                eso el molde lo dice en vez de dejar el renglón en
                                blanco —que se leería como un error del sistema—.

                            */}
                            {/* Cada una en su renglón. Compartían uno mientras
                                el rubro ocupaba una tira; al pasar el rubro al
                                título se liberó el lugar. Y con eso «Provincia»
                                vuelve entera: se abreviaba porque el rótulo del
                                segundo par tiene una caja más chica. */}
                            <Renglon
                                etiqueta="Ciudad"
                                valor={beneficiario.ciudad ?? ''}
                                molde={sinCargar}
                            />
                            <Renglon
                                etiqueta="Provincia"
                                valor={beneficiario.provincia ?? ''}
                                molde={sinCargar}
                            />
                            <Renglon etiqueta="Dirección" valor={beneficiario.direccion ?? ''} molde={sinCargar} />
                            {/*
                                El registro y la gestión van en el MISMO
                                renglón. El número
                                se reinicia con cada gestión —es el id del
                                carnet, y los carnets son por año—, así que el
                                000002 de 2026 y el de 2027 son dos credenciales
                                distintas con el mismo número impreso: quien lee
                                el plástico necesita los dos datos a la vez.
                            */}
                            <Renglon
                                etiqueta="Registro"
                                valor={registro}
                                molde={moldeRegistro}
                                segundo={{ etiqueta: 'Gestión', valor: String(situacion.gestion) }}
                                // El cupo va como TERCER valor, sin rótulo:
                                // «800 KG» se lee solo, y en un renglón de tres
                                // no hay lugar para otra etiqueta. Solo si la
                                // actividad se autoriza por volumen. Ver
                                // CarnetImpresionController::renglonRegistro().
                                tercero={requiereCapacidad ? cupoImpreso || '— KG' : undefined}
                            />
                        </dl>
                    </div>
                </div>
            </div>

            <p className="text-center text-xs text-muted-foreground">
                Formato CR80 — 85,6 × 54 mm, el tamaño de una tarjeta bancaria.
            </p>

            {/* El aviso que más sirve, y el que solo se puede dar acá: sin foto
                el carnet sale con el hueco, y eso se descubre al imprimir —con
                el pescador ya en su casa—. */}
            {!beneficiario.foto_url && (
                <p className="text-center text-xs text-amber-700 dark:text-amber-400">
                    La ficha no tiene fotografía. El carnet se va a imprimir con el recuadro vacío.
                </p>
            )}
        </div>
    );
}

/**
 * EL ENCABEZADO, EN DOS PISOS.
 *
 * Arriba el lockup de la Gobernación —escudo y texto, como grupo centrado— y
 * DEBAJO el bloque de la Secretaría a todo el ancho, también centrado. No van
 * uno al lado del otro con un filete en el medio: así es la credencial que se
 * tomó de modelo.
 *
 * Y APILADOS ENTRAN MÁS GRANDES, que es la ventaja de fondo. Al costado, el
 * bloque del SEDAG tenía 112 pt y sus dos líneas largas estaban topadas contra
 * el ancho de su caja; a todo el ancho tiene 226 y vuelven a entrar de a UNA
 * línea, sin los cortes forzados que la versión al costado necesitaba.
 *
 * «Gobernación», «Beni» y «SEDAG - BENI» van los tres al mismo cuerpo, a pedido.
 * Las dos líneas de la Secretaría van más chicas justamente para que ese tercero
 * entre sin empujar el título: apilado, el encabezado paga en alto lo que gana
 * en ancho. Las medidas exactas están en `carnet-pescador.blade.php`.
 */
function Encabezado() {
    return (
        <div className="shrink-0">
            {/* Primer piso: escudo y texto se centran COMO GRUPO, no cada uno
                por su lado — el texto va pegado al escudo. */}
            <div className="flex items-center justify-center gap-[1.5%]">
                <img
                    src="/image/icon.png"
                    alt=""
                    aria-hidden
                    className="h-[2.4rem] w-auto shrink-0 object-contain"
                />

                {/* El filete entre el escudo y el texto, como en el lockup
                    oficial: un hilo vertical, no una caja. Blanco porque
                    acompaña al texto. En el PDF son 0,7 pt con 2,8 de calle a
                    cada lado, y esos 6,3 forman parte del ancho del grupo que se
                    centra — ver las cuentas en el Blade. */}
                <div className="h-[2.4rem] w-px shrink-0 bg-white" />

                {/* EL LOCKUP VA EN BLANCO PERFILADO, a pedido, y solo él: el
                    bloque del SEDAG sigue en verde oscuro. Mismo contorno que
                    los rótulos, y por el mismo motivo — sobre este verde un
                    texto claro sin borde se desdibuja donde pasa el sello. */}
                <div
                    className="shrink-0 leading-[1.15] font-bold text-white uppercase"
                    style={{ textShadow: PERFIL_ROTULO }}
                >
                    <p className="text-[0.62rem] tracking-wide">Gobernación</p>
                    <p className="text-[0.4rem]">Gobierno Autónomo</p>
                    <p className="text-[0.4rem]">Departamental del</p>
                    <p className="text-[0.62rem] tracking-wide">Beni</p>
                </div>
            </div>

            {/* Segundo piso: a todo el ancho de la tarjeta, SOBRE UNA BANDA
                BLANCA con la letra negra.

                Queda coherente con la regla que ordena el resto de la tarjeta
                —lo que es dato va negro sobre blanco, lo que es andamio va sobre
                el verde— y resuelve el contraste: era lo único que seguía en
                verde oscuro sobre un fondo que bajó dos escalones.

                ES UN CUADRO CON BORDE Y ESQUINAS REDONDEADAS, dentro del
                margen — no una faja que cruza la tarjeta—. El radio lo deja casi
                como una pastilla, que es la forma que tiene en la credencial de
                referencia. En el PDF son 9 pt de radio sobre una caja de 22.

                EL FONDO ES VERDE MUY CLARO Y SEMITRANSPARENTE, no blanco: el
                sello de agua se ve pasar por debajo y el cuadro pertenece al
                fondo en vez de recortarse contra él.

                En el PDF esto es el único `rgba()` de la tarjeta, contra lo que
                el proyecto tiene anotado de DomPDF — se comprobó midiendo el
                archivo que la versión que corre sí lo respeta. El 0,65 es lo más
                transparente que aguanta el texto negro encima: más abajo, el
                engranaje del sello se le mete por detrás a «SEDAG - BENI».

                Y VA CON SERIFAS, como la credencial de referencia — es lo único
                de la tarjeta que no usa la tipografía del sistema. En el PDF es
                DejaVu Serif, la única serif que DomPDF trae con los acentos
                completos.

                En el PDF el relleno de esta banda suma al alto del encabezado,
                que es la medida más ajustada de la tarjeta; acá el flex se
                acomoda solo. */}
            <div className="mt-[1%] rounded-full border border-[#3d6b17] bg-[#e8eed2]/65 py-[0.4%] text-center font-serif leading-[1.15] font-bold text-black uppercase">
                <p className="text-[0.5rem]">Secretaría Dptal. de Desarrollo Productivo,</p>
                <p className="text-[0.5rem]">Recursos Naturales y Medio Ambiente</p>
                <p className="text-[0.62rem] tracking-wide">SEDAG - BENI</p>
            </div>
        </div>
    );
}

/**
 * El título en rojo perfilado de dorado, como en el plástico.
 *
 * MÁS CHICO QUE EN LA CÉDULA DE PAPEL, a pedido y en dos pasos: ahí ocupaba casi
 * un tercio de la tarjeta y acá se comía el aire entre el encabezado y los datos.
 * **Acá se frena**: la palabra tiene que seguir siendo lo más grande de la
 * carilla, que es lo que hace la credencial reconocible de lejos.
 *
 * El corrimiento del contorno y el interletrado bajan en la misma proporción que
 * el cuerpo, o el título se deforma al achicarse.
 *
 * El contorno va con `text-shadow` en cuatro direcciones y no con
 * `-webkit-text-stroke`: el trazo centrado del stroke se come el interior de las
 * letras a este cuerpo. En el PDF el mismo efecto se consigue dibujando el texto
 * cinco veces, porque DomPDF no tiene ninguna de las dos propiedades.
 */
function Titulo({ rubro }: { rubro?: string | null }) {
    const texto = rubro?.trim() ? `Cédula de ${rubro.trim()}`.toUpperCase() : 'CÉDULA';

    /*
     * EL TÍTULO SE ACHICA CUANDO NO ENTRA, igual que en el PDF.
     *
     * Su largo lo decide el catálogo: «CÉDULA DE COMERCIALIZADOR» entra holgado,
     * pero un rubro de más de treinta caracteres se pasaría del ancho de la
     * tarjeta. El umbral es el mismo que usa
     * `CarnetImpresionController::CARACTERES_TITULO`, y los dos tienen que
     * moverse juntos o la maqueta deja de prometer lo que el PDF cumple.
     *
     * BAJAN LAS TRES MEDIDAS A LA VEZ —cuerpo, interletrado y contorno—. Un
     * borde de 0,45 px sobre una letra más chica pasa de ser un 6% a un 9% del
     * cuerpo y la engorda hasta cerrarle los huecos, que es justo lo que el
     * perfilado viene a evitar.
     */
    const largo = texto.length > 30;
    const borde = largo ? 0.36 : 0.45;

    return (
        <p
            className={cn(
                'mt-[1%] shrink-0 text-center leading-tight font-bold text-[#a01717]',
                largo ? 'text-[0.66rem] tracking-[0.04em]' : 'text-[0.82rem] tracking-[0.07em]',
            )}
            style={{
                textShadow: `${borde}px ${borde}px 0 #e8b21c, -${borde}px ${borde}px 0 #e8b21c, ${borde}px -${borde}px 0 #e8b21c, -${borde}px -${borde}px 0 #e8b21c`,
            }}
        >
            {texto}
        </p>
    );
}

/**
 * La cédula, la foto y el QR: la columna de la izquierda.
 *
 * El recuadro de la foto va SIEMPRE, con foto o sin ella. Es lo que hacía la
 * unidad con la cédula de papel cuando la persona traía la foto después: se
 * imprimía el marco vacío y se pegaba encima. OJO: la foto NO se elige en este
 * formulario —es un dato del padrón, se carga en la ficha del beneficiario—.
 *
 * NO HAY QR: se sacó a pedido. Ver la nota del encabezado sobre lo que eso
 * implica.
 */
function Retrato({ documento, foto }: { documento: string; foto: string | null }) {
    return (
        // mt-[7%] baja la columna respecto de los renglones de al lado, igual
        // que el `top: 76pt` del PDF: apoyada arriba, la izquierda terminaba
        // mucho antes que la derecha.
        <div className="mt-[7%] flex w-[21%] shrink-0 flex-col gap-[4%]">
            <div className="flex aspect-square items-center justify-center overflow-hidden border border-[#2f6b1f] bg-white">
                {foto ? (
                    <img src={foto} alt="" aria-hidden className="size-full object-cover" />
                ) : (
                    <UserRound className="size-1/2 text-black/25" />
                )}
            </div>

            {/* La cédula va DEBAJO de la foto, a pedido. En el plástico de papel
                iba arriba, sobre el borde.

                Y va EN SU PROPIA TIRA BLANCA, como los demás valores: es un dato
                de la persona, no un rótulo, y era el único que quedaba suelto
                sobre el verde. La tira toma el ancho de la columna de la foto,
                así que las dos cierran contra la misma vertical — en el PDF eso
                mismo se pide con un ancho fijo de 47,6 pt. */}
            <p className="truncate bg-white px-1 py-[1%] text-center text-[0.45rem] leading-tight text-black">
                C.I. {documento || '—'}
            </p>

            {/* EL CUPO, DEBAJO DE LA CÉDULA — misma tira, en negrita.

                Va acá y no como un renglón más porque en la columna de datos no
                entraba: con el cupo como renglón, la tarjeta de un pescador
                llegaba a SIETE y había que apretar el salto. Acá aprovecha el
                espacio que quedaba muerto bajo la cédula.

                Solo sale si la actividad se autoriza por volumen. Un recuadro
                vacío se leería como un dato que falta. */}
        </div>
    );
}

/**
 * EL PERFILADO — el borde oscuro que recorta un texto claro del fondo.
 *
 * HOY LO USA SOLO EL LOCKUP del encabezado. Los rótulos lo tuvieron y se les
 * sacó: agrandarlos resolvió el mismo problema sin ensuciar el blanco.
 *
 * El blanco solo no alcanza sobre este verde. Es un verde claro, y no es liso:
 * abajo corre el sello de agua del SEDAG, que le cambia el tono al rótulo según
 * por dónde pase. A este cuerpo, el blanco puro se desdibuja en los tramos
 * claros del sello.
 *
 * Acá va con `text-shadow` en cuatro direcciones. En el PDF el mismo efecto se
 * consigue dibujando el texto cinco veces, porque DomPDF no tiene `text-shadow`
 * ni `-webkit-text-stroke`: ver `documentos/partes/texto-perfilado.blade.php`.
 *
 * NO se usa `-webkit-text-stroke` tampoco acá: su trazo va CENTRADO sobre el
 * contorno de la letra, así que la mitad se come el relleno y a 0,42 rem los
 * huecos de la «O» y la «E» se cierran.
 *
 * ES UN HILO A PROPÓSITO, de 0,25 px. El contorno acá no es un efecto: es un
 * seguro contra el sello, y cada décima que se le agrega se la come al blanco. A
 * 0,4 px el oscuro le comía casi la mitad del ancho a cada lado del trazo y el
 * rótulo se leía GRIS — que es exactamente lo que el blanco venía a evitar—. Así
 * que el borde solo recorta; el que manda es el blanco.
 *
 * Por lo mismo no se copia el 0,5 del título: ese va sobre un texto casi tres
 * veces más grande, donde medio punto es una fracción chica del trazo.
 */
const PERFIL_ROTULO =
    '0.25px 0.25px 0 #14350f, -0.25px 0.25px 0 #14350f, 0.25px -0.25px 0 #14350f, -0.25px -0.25px 0 #14350f';

/**
 * Un renglón: el rótulo en su columna fija —así todos los valores arrancan
 * parejos— y el valor sobre su tira clara, como las líneas blancas del plástico
 * sobre las que se escribía a máquina.
 *
 * EL RÓTULO VA EN BLANCO, SIN CONTORNO Y MÁS GRUESO. El blanco es lo que separa
 * el andamio del dato: «NOMBRE» no es información, es el cartelito que dice qué
 * se está leyendo, y en verde oscuro pesaba lo mismo que el nombre de la persona.
 *
 * EL BLANCO ES PURO Y AUN ASÍ PUEDE VERSE GRIS. No es un problema de color
 * —medido sobre el PDF, el núcleo del glifo da 255— sino de GROSOR: a un cuerpo
 * chico el trazo es tan fino que el ojo lo promedia con el verde de atrás. La
 * única palanca real es el cuerpo, y por eso este renglón se fue agrandando.
 *
 * TUVO CONTORNO Y SE LE SACÓ. Se le había puesto uno oscuro porque al cuerpo
 * anterior el blanco se desdibujaba donde pasa el sello de agua. Lo que resolvió
 * el problema de verdad fue AGRANDARLO: la mancha blanca ya es lo bastante
 * gruesa como para imponerse sobre el sello sola, y sin el borde se lee
 * francamente blanca en vez de blanca-con-suciedad. Menos capas y mejor
 * resultado.
 *
 * Un renglón puede llevar un SEGUNDO PAR a la derecha. Hoy lo llevan tres:
 * Rubro + Cupo, Ciudad + Provincia y Registro + Gestión.
 *
 * Cuando lo lleva, las dos tiras se reparten el ancho por igual —`flex-1` sobre
 * ambas, el equivalente en pantalla del corte a la mitad que hace el Blade con
 * coordenadas fijas—. Alcanza para los cuatro valores que hoy comparten renglón:
 * Ciudad + Provincia y Registro + Gestión, todos cortos.
 *
 * Hubo un reparto DESPAREJO, para el renglón «Rubro + Cupo»: «Comercializador»
 * contra «600 KG» no es un corte a la mitad. Se fue con ese renglón, que se sacó
 * al pasar el rubro al título.
 */
function Renglon({
    etiqueta,
    valor,
    molde,
    segundo,
    tercero,
}: {
    etiqueta: string;
    valor: string;
    /** Qué dibujar mientras no hay valor, para mostrar la forma que tendrá. */
    molde: string;
    /** El par de la derecha, para los renglones compartidos. */
    segundo?: { etiqueta: string; valor: string };
    /**
     * Un TERCER valor, sin rótulo. Hoy solo el cupo: «800 KG» se lee solo y en
     * un renglón de tres no entra otra etiqueta.
     */
    tercero?: string;
}) {
    return (
        <div className="flex items-center gap-[2%]">
            <dt className="w-[25%] shrink-0 text-[0.56rem] font-bold text-white uppercase">{etiqueta}</dt>

            <Tira valor={valor} molde={molde} />

            {segundo && (
                <>
                    <dt className="shrink-0 pl-[3%] text-[0.56rem] font-bold text-white uppercase">
                        {segundo.etiqueta}
                    </dt>
                    <Tira valor={segundo.valor} molde="—" />
                </>
            )}

            {tercero !== undefined && <Tira valor={tercero} molde="— KG" />}
        </div>
    );
}

/**
 * La tira blanca sobre la que se escribe el valor.
 *
 * BLANCO PURO Y LETRA NEGRA FINA, como una cédula de identidad. Antes era crema
 * con la letra en verde oscuro negrita; sobre el verde oscuro del fondo, el
 * crema se leía como un papel viejo, y la negrita no hace falta cuando el dato
 * ya está sobre blanco.
 *
 * Lo que la ficha no tiene cargado se dibuja ATENUADO, no en blanco: una tira
 * vacía en una credencial se lee como un error del sistema, y el molde le dice
 * al operador qué es lo que falta. Ahora que el valor cargado también va en
 * regular, **lo único que los separa es el gris**.
 */
function Tira({ valor, molde }: { valor: string; molde: string }) {
    const vacio = valor === '';

    return (
        <dd
            className={cn(
                'min-w-0 flex-1 truncate bg-white px-1 py-[1%] text-[0.55rem] leading-tight',
                vacio ? 'text-black/35' : 'text-black',
            )}
        >
            {valor || molde}
        </dd>
    );
}
