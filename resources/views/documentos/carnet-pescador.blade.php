{{--
================================================================================
  CÉDULA DE PESCADOR — calco del carnet plastificado del SEDAG
================================================================================

  Reproduce la credencial que la unidad venía mandando a imprimir: el mismo verde
  con el sello de agua, el mismo encabezado con el escudo y el bloque del SEDAG,
  el mismo título en rojo perfilado de dorado, la foto a la izquierda con la
  cédula debajo y los mismos seis renglones sobre sus tiras claras.

  Quien la recibe está acostumbrado a ese formato, y el inspector que la revisa
  en el río la reconoce de lejos por su forma. Una versión «mejorada» se lee
  como si fuera otro documento — se probó una, con el nombre grande y sin tiras,
  y se descartó por eso mismo.

  --------------------------------------------------------------------------
  LO QUE SE APARTA DEL PLÁSTICO DE PAPEL, Y POR QUÉ
  --------------------------------------------------------------------------

  NO LLEVA QR, y se sacó a pedido después de haberlo tenido. Conviene saber qué
  se perdió con eso: la verificación pública sigue existiendo —la pantalla, la
  firma de validación, todo— pero **desde el plástico ya no hay forma de
  llegar a ella**. Quien tenga el carnet en la mano no puede comprobar si es
  real, ni ver qué rubros habilita; eso ahora solo se consulta desde el panel.

  Es la misma limitación que tenía la credencial de papel, que era su problema
  central: quien la miraba tenía que creerle.

  AHORA SÍ VAN EL RUBRO Y EL CUPO, y este comentario decía lo contrario hasta
  el cambio de modelo. Conviene entender por qué, porque el motivo viejo era
  bueno y lo que cambió no fue la opinión sino el sistema.

  El carnet era UNO por persona y gestión, con los rubros colgados en una tabla
  aparte: quien era pescador en febrero podía sumar comercializador en octubre
  sin que el plástico cambiara. Impresa, la lista de rubros quedaba vieja ese
  mismo día, y el documento pasaba a decir MENOS de lo que la persona estaba
  autorizada a hacer — que es peor que no decir nada. Con el cupo pasaba algo
  parecido: era un tope POR ACTIVIDAD, así que con dos rubros había dos cupos y
  un solo renglón donde ponerlos.

  Hoy el carnet es de UN rubro y ese rubro es parte de la llave que lo
  identifica: no cambia nunca, y sumar una actividad emite otro carnet con su
  propio plástico. No queda nada que pueda dejar vieja la impresión, y el cupo
  es uno solo. Los dos comparten el segundo renglón.

  Y es más que una posibilidad: es necesario. Dos carnets de la misma persona en
  la misma gestión son dos plásticos con el mismo nombre, la misma foto y el
  mismo domicilio. Sin el rubro impreso, nada los distingue a simple vista.

  TAMPOCO VA LA FECHA DE VENCIMIENTO. Todos los carnets de una gestión vencen el
  mismo día, así que el año del registro ya lo dice; y si la pregunta es si HOY
  vale, la fecha impresa nunca fue la respuesta, porque un carnet puede estar
  anulado o suspendido con su fecha intacta. Como la tarjeta tampoco lleva QR,
  eso solo se consulta desde el panel.

  --------------------------------------------------------------------------
  ESPEJO DE LA VISTA PREVIA DEL PANEL
  --------------------------------------------------------------------------

  `resources/js/components/panel/tramites/vista-previa-carnet.tsx` dibuja este
  mismo molde en pantalla, mientras el operador carga el trámite. **Si se toca
  una, se toca la otra**, o la vista previa pasa a prometer una tarjeta que el
  PDF no entrega.

  --------------------------------------------------------------------------
  POR QUÉ TODO ESTÁ POSICIONADO EN ABSOLUTO
  --------------------------------------------------------------------------

  Porque esto no es una página web que se acomoda al ancho del que mira: es una
  tarjeta de medida fija que tiene que salir SIEMPRE igual. Con el flujo normal
  del documento, un nombre más largo que otro corre todo lo que viene abajo y
  dos carnets salen distintos — y el troquel de la impresora de credenciales no
  perdona medio milímetro.

  Además lo dibuja DomPDF, que no es un navegador: no entiende flexbox, ni grid,
  ni variables CSS, ni `linear-gradient`. Lo que sí entiende bien es
  `position: absolute`, las tablas y los bordes.

  --------------------------------------------------------------------------
  EL SISTEMA DE COORDENADAS
  --------------------------------------------------------------------------

  La tarjeta es de 243 x 153 puntos —CR80 apaisada, 85,6 x 54 mm— y todos los
  `top` y `left` son puntos DENTRO de esa caja. No hay margen de página: el
  verde llega hasta el borde, como en el plástico.

      margen               =   8,5
      columna izquierda    =    46   ->  cédula y foto
      calle                =   5,5
      columna de datos     =   176   ->  hasta el margen derecho
      rótulos              =    41   ->  los valores arrancan todos parejos

  El tamaño NO está acá sino en CarnetImpresionController: es una decisión de
  impresión, no de diseño. Si cambia, estas coordenadas hay que revisarlas.
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Carnet {{ $carnet->codigo_legible }}</title>

    <style>
        * { margin: 0; padding: 0; }

        @page { margin: 0; }

        /*
         * DejaVu Sans es la única tipografía que DomPDF trae con los acentos y
         * la «ñ» completos. Con Helvetica, «ASOCIACIÓN» sale partida.
         */
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 5pt;

            /*
             * Los colores van escritos fijos, igual que en la vista previa: esto
             * no es una pantalla que sigue el tema del sistema, es una tarjeta
             * impresa y tiene que verse siempre igual.
             */
            color: #14350f;
        }

        /*
         * Va `position: relative` para que los `absolute` de adentro se midan
         * contra ELLA y no contra la página. Hoy da lo mismo —hay una sola
         * página— pero el día que vuelva a haber una segunda, sin esto sus
         * bloques se dibujarían encima de esta.
         */
        .carilla {
            position: relative;
            width: 243pt;
            height: 153pt;
            overflow: hidden;
        }

        /*
         * EL FONDO es una imagen y no un `background` con degradado porque
         * DomPDF no entiende `linear-gradient`: dibujaría un rectángulo de color
         * liso. El archivo trae además el sello del SEDAG ya atenuado adentro —
         * `opacity` en DomPDF es de lo menos confiable que tiene, y cuando lo
         * ignora el sello sale a pleno color tapando los datos.
         *
         * Se declara PRIMERO: DomPDF respeta el apilado por orden de
         * declaración, así que lo que viene detrás queda arriba.
         */
        .fondo { position: absolute; top: 0; left: 0; width: 243pt; height: 153pt; }

        .bloque { position: absolute; }

        /* ---------------------------------------------------------------
           EL ENCABEZADO, EN DOS PISOS

           Arriba el lockup de la Gobernación —escudo y texto, como grupo
           centrado— y DEBAJO el bloque de la Secretaría a todo el ancho, también
           centrado. No van uno al lado del otro con un filete en el medio: así
           es la credencial que se tomó de modelo.

           Y APILADOS ENTRAN MUCHO MÁS GRANDES, que es la ventaja de fondo. Al
           costado, el bloque del SEDAG tenía 112 pt y sus dos líneas largas
           estaban topadas —«SECRETARÍA DPTAL. DE DESARROLLO PRODUCTIVO,» ocupaba
           102,2 de esos 112—. A todo el ancho tiene 226, su tope se va a 7,74 pt
           y vuelve a entrar de a UNA línea, sin los cortes forzados que la
           versión al costado necesitaba.

               al costado   tres renglones de 4,8 pt, partidos a la fuerza
               apilado      dos renglones de 6 pt, cortados donde corta la frase

           EL LOCKUP VA CENTRADO COMO GRUPO, no cada parte por su lado: el
           escudo mide 20,7 pt de ancho, el texto 62 —lo marca «GOBERNACIÓN», que
           es la línea más larga— y con el filete y sus dos calles el grupo suma
           89. En una carilla de 243 eso arranca en 77. Mover cualquiera de las
           tres partes obliga a rehacer esa cuenta.

           EL COSTO ES VERTICAL, y es lo que hay que vigilar acá: dos pisos
           ocupan lo que antes ocupaba uno al lado del otro. El encabezado cierra
           en 52 y empuja todo lo de abajo, así que estas coordenadas se mueven
           todas juntas:

               lockup       2  ->  28,5
               cuadro SEDAG 29 ->  52     (bordeado, dentro del margen)
               título      53  ->  64
               renglones   66  ->  149      (seis, cada 14)
               margen                        4  hasta los 153 de la carilla

           CADA RENGLÓN DEL ENCABEZADO LLEVA SU ALTO ESCRITO, y no se lo deja al
           `line-height`. La caja de línea que DomPDF le da a DejaVu Sans es
           bastante más alta que el cuerpo —del orden de 1,17 em—, así que con
           `line-height: 1.05` el bloque medía más de lo que la cuenta decía y
           «BENI» terminaba encima de la primera línea del SEDAG. Con el alto
           escrito, apilar dos bloques vuelve a ser sumar.

           Los altos son el cuerpo por 1,15: deja lugar a la tilde de «Ó» sin
           abrir el interlineado.
        --------------------------------------------------------------- */
        .escudo { position: absolute; top: 2pt; left: 77pt; height: 24pt; }

        /*
         * EL FILETE entre el escudo y el texto, como en el lockup oficial: un
         * hilo vertical, no una caja. Blanco porque acompaña al texto, que es
         * blanco; en verde oscuro sobre este fondo no se vería.
         *
         * OJO CON LAS CUENTAS AL TOCARLO. El grupo entero va centrado en la
         * carilla, así que el filete no se puede correr solo: sus 0,7 pt más las
         * dos calles de 2,8 forman parte del ancho del grupo, y moverlo obliga a
         * recalcular dónde arranca el escudo y dónde el texto.
         *
         *     escudo   77    -> 97,7    (24 pt de alto, 20,7 de ancho)
         *     filete   100,5 -> 101,2
         *     texto    104   -> 166
         *     grupo    89 pt de ancho, centrado en 243 -> arranca en 77
         */
        .filete-lockup {
            position: absolute;
            top: 2pt;
            left: 100.5pt;
            width: 0.7pt;
            height: 25pt;
            background-color: #ffffff;
        }

        /*
         * EL LOCKUP VA EN BLANCO PERFILADO, a pedido, y solo él: el bloque del
         * SEDAG sigue en verde oscuro.
         *
         * Usa el mismo mecanismo que los rótulos —cinco copias, ver
         * `partes/texto-perfilado`— porque el problema es el mismo: sobre este
         * verde, un texto claro sin borde se desdibuja donde pasa el sello.
         *
         * El contorno es el `.perfil` de 0,2 pt, que se calibró para los
         * rótulos de 4,6. Acá va sobre 7 y 4,5, así que rinde todavía más fino
         * en proporción — y eso es lo que se busca: que se lean BLANCAS, con el
         * borde apenas recortándolas.
         *
         * Cada línea necesita `position: relative` propio: las cinco copias son
         * `absolute` y sin contenedor posicionado se medirían contra la carilla.
         */
        .gobernacion { top: 2pt; left: 104pt; width: 62pt; line-height: 1; }
        .gobernacion div { position: relative; }
        .gobernacion span { width: 62pt; }
        .gobernacion .l1 { height: 8.05pt; }
        .gobernacion .l1 span { font-size: 7pt; letter-spacing: 0.3pt; }
        .gobernacion .l2 { height: 5.2pt; }
        .gobernacion .l2 span { font-size: 4.5pt; }
        .gobernacion .l3 { height: 8.05pt; }
        .gobernacion .l3 span { font-size: 7pt; letter-spacing: 0.3pt; }

        /*
         * EL BLOQUE DEL SEDAG VA SOBRE UNA BANDA BLANCA, con la letra negra, a
         * pedido y siguiendo la credencial de referencia.
         *
         * Queda además coherente con el resto de la tarjeta, que ya se había
         * ordenado con esa misma regla: lo que es DATO va negro sobre blanco, lo
         * que es andamio va sobre el verde. Y resuelve de paso el contraste —era
         * lo único que seguía en verde oscuro sobre un fondo que bajó dos
         * escalones.
         *
         * ES UN CUADRO CON BORDE Y ESQUINAS REDONDEADAS, dentro del margen —no
         * una faja que cruza de borde a borde—. El radio de 9 pt sobre una caja
         * de unos 22 lo deja casi como una pastilla, que es la forma que tiene
         * en la credencial de referencia.
         *
         * `border-radius` lo dibuja DomPDF desde la 2.x y acá corre la 3.1.6,
         * así que se puede usar. Es una de las poquísimas propiedades modernas
         * que entiende: sigue sin haber flex, ni grid, ni degradados.
         *
         * El ancho es 224,6 y no 226 porque el borde SUMA: 224,6 más las dos
         * líneas de 0,7 dan los 226 que van de margen a margen. Y el relleno
         * bajó a 0,5 por lo mismo, que el borde también suma al alto y el
         * encabezado no tenía de dónde sacarlo.
         *
         * EL TEXTO VA CON SERIFAS, también como la credencial de referencia, y
         * es lo único de la tarjeta que no usa la DejaVu Sans. DejaVu Serif es
         * la única serif que DomPDF trae con los acentos completos — con Times
         * el «Í» de «SECRETARÍA» es una apuesta según la codificación.
         *
         * Ojo: la serif es un 5% más ancha que la sans al mismo cuerpo. A 5,5 pt
         * la línea larga mide 169,5 de los 235 útiles, así que entra; el tope
         * real es 7,63.
         *
         * OJO: EL RELLENO SUMA AL ALTO DEL ENCABEZADO, que es la medida más
         * ajustada de la tarjeta. Los 2 pt de relleno se compensaron sacándole
         * la separación que tenía «SEDAG - BENI» y subiendo el lockup medio
         * punto; sin eso, la banda se comía el título.
         */
        .secretaria {
            top: 29pt;
            left: 8.5pt;
            width: 224.6pt;
            padding: 0.5pt 0;
            border: 0.7pt solid #3d6b17;
            border-radius: 9pt;
            font-family: 'DejaVu Serif', serif;
            text-align: center;
            line-height: 1;
            color: #000000;

            /*
             * VERDE MUY CLARO Y SEMITRANSPARENTE, no blanco: el sello de agua
             * del SEDAG se ve pasar por debajo, y el cuadro pertenece al fondo
             * en vez de recortarse contra él.
             *
             * ESTE ES EL ÚNICO `rgba()` DE LA TARJETA, y va contra lo que el
             * proyecto tiene anotado —«el soporte de rgba en DomPDF depende de
             * la versión»—. Se comprobó midiendo el PDF en vez de suponer: con
             * la 3.1.6 que corre acá el relleno sale en 173,194,129, que es la
             * mezcla real contra el verde; si saliera opaco daría 232,238,210.
             *
             * Por eso mismo queda ATADO A LA VERSIÓN. En un DomPDF viejo esto
             * se dibuja opaco —no revienta, simplemente pierde la
             * transparencia—, y el reemplazo es un sólido ya mezclado, como el
             * que usa el resto del carnet.
             *
             * El 0,65 no es al gusto: es lo más transparente que aguanta el
             * texto negro encima. A 0,55 el engranaje del sello se le mete por
             * detrás a «SEDAG - BENI» y compite con la lectura.
             */
            background-color: rgba(232, 238, 210, 0.65);
        }

        .secretaria div { font-weight: bold; }
        .secretaria .l1 { height: 6.3pt; font-size: 5.5pt; }

        /* «SEDAG - BENI» va al MISMO cuerpo que GOBERNACIÓN y BENI, a pedido:
           son los tres renglones grandes del encabezado y pesan igual. Las dos
           líneas de arriba van a 5,5 y no a 6 justamente para que este entre a 7
           sin empujar el título: apilado, el encabezado paga en alto lo que gana
           en ancho. */
        .secretaria .l2 { height: 8.05pt; font-size: 7pt; letter-spacing: 0.3pt; }

        /* ---------------------------------------------------------------
           EL TÍTULO, ROJO PERFILADO DE DORADO

           En el plástico las letras van rojas con un contorno dorado. DomPDF no
           tiene `-webkit-text-stroke` ni `text-shadow`, así que el contorno se
           hace a mano: el mismo texto se dibuja CINCO veces —cuatro en dorado,
           corridas medio punto hacia cada esquina, y la quinta en rojo encima—.

           Parece un truco y lo es, pero es el único que sale igual en todas las
           versiones de DomPDF. Con `opacity` o filtros la tarjeta salía distinta
           según el servidor.

           AHORA DICE «CÉDULA DE PESCADOR», con el rubro adentro.

           Decía «CÉDULA» a secas, y el motivo se dio vuelta con el cambio de
           modelo: el carnet era UNO para todas las actividades de una persona,
           así que nombrar una en el título habría dicho algo que el documento
           no era. Hoy el carnet es de UN rubro que no cambia nunca, y el título
           es lo que se lee de lejos —antes que cualquier renglón—.

           El texto y su clase de tamaño los arma
           CarnetImpresionController::titulo(); sin rubro cargado vuelve a
           «CÉDULA» a secas.
        --------------------------------------------------------------- */
        .titulo { top: 53pt; left: 0; width: 243pt; height: 11pt; }

        .titulo span {
            position: absolute;
            width: 243pt;
            text-align: center;

            /*
             * MÁS CHICO QUE EL TÍTULO DEL PLÁSTICO DE PAPEL, a pedido, y en
             * dos pasos: 15 pt -> 11 -> 9,5. Ahí ocupaba casi un tercio de la
             * tarjeta, y en esta versión los seis renglones empiezan más arriba,
             * así que se comía el aire que separa el encabezado de los datos.
             *
             * ACÁ SE FRENA. La palabra tiene que seguir siendo lo más grande de
             * la carilla: es lo que hace que la credencial se reconozca de lejos
             * —el inspector la identifica por su forma antes de leer nada—, y por
             * debajo de este cuerpo deja de pesar más que el encabezado.
             *
             * El corrimiento del contorno y el interletrado bajaron en la misma
             * proporción: el contorno es un ~4,5% del cuerpo y el título entero
             * tiene que escalar junto o se deforma.
             */
            font-size: 9.5pt;
            font-weight: bold;
            letter-spacing: 0.8pt;
        }

        /* El perfilado: dorado, corrido medio punto. Ver `partes/texto-perfilado`. */
        .titulo .borde { color: #e8b21c; }
        .titulo .e1 { top: 0.45pt; left: 0.45pt; }
        .titulo .e2 { top: 0.45pt; left: -0.45pt; }
        .titulo .e3 { top: -0.45pt; left: 0.45pt; }
        .titulo .e4 { top: -0.45pt; left: -0.45pt; }
        .titulo .cara { top: 0; left: 0; color: #a01717; }

        /* ---------------------------------------------------------------
           EL TÍTULO LARGO — para un rubro que no entra a cuerpo pleno

           Desde que el título lleva el rubro adentro, su largo lo decide el
           catálogo. «CÉDULA DE COMERCIALIZADOR» entra holgado; un rubro de
           más de treinta caracteres se pasaría de los 243 pt de la tarjeta.

           BAJAN LAS TRES MEDIDAS JUNTAS, y eso es lo importante: el cuerpo,
           el interletrado y el corrimiento del contorno. Achicar solo la
           letra dejaría un borde de 0,45 pt sobre un cuerpo de 7,6 — un 6%
           pasa a ser un 9% y el contorno engorda la letra hasta cerrarle los
           huecos, que es justo lo que el perfilado viene a evitar.

           Quién la pone: CarnetImpresionController::titulo().
           --------------------------------------------------------------- */
        .titulo.largo span { font-size: 7.6pt; letter-spacing: 0.5pt; }
        .titulo.largo .e1 { top: 0.36pt; left: 0.36pt; }
        .titulo.largo .e2 { top: 0.36pt; left: -0.36pt; }
        .titulo.largo .e3 { top: -0.36pt; left: 0.36pt; }
        .titulo.largo .e4 { top: -0.36pt; left: -0.36pt; }

        /* --- La foto y la cédula ------------------------------------------ */

        /* ---------------------------------------------------------------
           LA CÉDULA, EN SU PROPIA TIRA BLANCA

           Va DEBAJO de la foto, a pedido. En el plástico de papel iba arriba,
           sobre el borde.

           Y va sobre tira blanca con letra negra, como los demás valores: es un
           DATO de la persona, no un rótulo, y era el único que quedaba suelto
           sobre el verde. Suelto obligaba además a perfilarlo para que se leyera
           sobre el sello; dentro de su caja, el problema no existe.

           EL ANCHO ESTÁ ATADO AL DE LA FOTO, no elegido: 43,6 de caja más 4 de
           relleno son los 47,6 pt que mide el recuadro de la foto con su borde,
           así que los dos cierran contra la misma vertical. Y TIENE que estar
           atado: la columna de datos arranca en 60 pt, y la caja anterior —80 pt
           de ancho desde el margen— llegaba hasta 88,5. Mientras el bloque era
           texto suelto sobre verde eso no se notaba porque la cédula es corta;
           con fondo blanco, la caja se habría metido por debajo del renglón de
           PROVINCIA.
        --------------------------------------------------------------- */
        .documento {
            top: 125pt;
            left: 8.5pt;
            width: 43.6pt;
            padding: 1pt 2pt 0 2pt;
            height: 7.5pt;
            text-align: center;
            line-height: 1.15;
            color: #000000;
            background-color: #ffffff;
            overflow: hidden;
        }

        /* Sin cédula cargada, atenuada — igual que los renglones. */
        .documento.molde { color: #8a8f85; }

        /* ---------------------------------------------------------------
           EL RENGLÓN PARTIDO EN TRES — REGISTRO + GESTIÓN + CUPO

           Solo lo usa el último renglón, y solo cuando la actividad lleva
           cupo. Sin cupo el renglón vuelve al reparto de dos de arriba.

           El reparto, medido sobre los 176 pt de la tira de datos:

               rótulo REGISTRO   0 -> 44      valor  47,5 -> 75,5
               rótulo GESTIÓN   78 -> 108     valor 111,5 -> 134,5
               valor  CUPO                          137   -> 176,5

           LA CAJA DEL RÓTULO «GESTIÓN» ES DE 30 pt Y NO DE 27, y costó una
           vuelta: con 27 la palabra se montaba sobre la tira del año y en el
           PDF salía «GESTIÓN2026» pegado. El cálculo de encogido de `texto()`
           protege a los VALORES; los rótulos son constantes y nadie los mide,
           así que uno largo se desborda en silencio. Es la misma trampa que ya
           había aparecido con «PROVINCIA».

           EL CUPO NO LLEVA RÓTULO —«800 KG» se lee solo— y por eso se queda
           con la tira más ancha de las tres: es el único que puede crecer, con
           un cupo de cinco dígitos y separador de miles.

           OJO CON EL RELLENO: `.campo .valor` lleva `padding: 1pt 2pt`, y en
           CSS eso SUMA al ancho declarado. Los anchos ÚTILES que salen de acá
           —24-4, 20-4 y 37,5-4— son los que el controlador tiene escritos en
           ANCHO_TRIPLE_*. Si se toca una medida hay que tocar la otra, o el
           cálculo de encogido mide contra una caja que no existe.
           --------------------------------------------------------------- */
        .campo.triple .valor.angosto { width: 24pt; }
        .campo.triple .rotulo.segundo { left: 78pt; width: 30pt; }
        .campo.triple .dospuntos.segundo { left: 108pt; }
        .campo.triple .valor.segundo { left: 111.5pt; width: 19pt; }
        .campo.triple .valor.tercero { left: 137pt; width: 35.5pt; }

        /*
         * El recuadro va SIEMPRE, con foto o sin ella. Es lo que hacía la unidad
         * con la cédula de papel cuando la persona traía la foto después: se
         * imprimía el marco vacío y se pegaba encima.
         *
         * `overflow: hidden` porque DomPDF NO TIENE `object-fit`: un retrato
         * metido a la fuerza en el recuadro saldría aplastado, así que la imagen
         * se dibuja a su proporción real desbordando, y acá se la recorta. El
         * estilo lo calcula CarnetImpresionController::fotoEmbebida().
         */
        /* ---------------------------------------------------------------
           LA FOTO ARRANCA MÁS ABAJO QUE LOS RENGLONES, a pedido.

           Estaba en 66, la misma altura que NOMBRE, y la columna izquierda
           terminaba 23 pt antes que la derecha: la foto y la cédula cerraban en
           122,5 y el último renglón en 145,5. Bajándola 10 pt las dos columnas
           quedan parejas a la vista.

           EL RECUADRO ES CUADRADO Y TIENE QUE SEGUIR SIÉNDOLO: 46 × 46, más
           0,8 de borde por lado. En DomPDF el borde SUMA al ancho declarado
           —como el padding— así que ocupa 47,6 × 47,6: sigue cuadrado porque
           los dos lados crecen igual. Tocar uno solo lo deforma.

           La foto de adentro NO se estira para llenarlo: se dibuja a su
           proporción real desbordando el recuadro y este la recorta con
           `overflow: hidden`. Ver fotoEmbebida(), que calcula el corrimiento.
           --------------------------------------------------------------- */
        .foto {
            top: 76pt;
            left: 8.5pt;
            width: 46pt;
            height: 46pt;
            border: 0.8pt solid #2f6b1f;
            background-color: #ffffff;
            overflow: hidden;
        }

        /* ---------------------------------------------------------------
           LOS SEIS RENGLONES

           Cada uno es un bloque plantado en su coordenada, y no filas de una
           tabla, por lo mismo que todo lo demás: una dirección de dos líneas no
           puede empujar al renglón de abajo.

           El valor va sobre su TIRA CLARA, como en el plástico —ahí eran las
           líneas blancas sobre las que se escribía a máquina—. El tono es un
           color sólido y no `rgba(255,255,255,.85)`: el soporte de rgba en
           DomPDF depende de la versión.
        --------------------------------------------------------------- */
        /*
         * LA COLUMNA ARRANCA EN 58 Y NO EN 60 para darle lugar al rótulo. La
         * foto y la tira de la cédula cierran en 56,1, así que 58 es lo más a la
         * izquierda que se puede plantar sin pisarlas, y esos 2 pt fueron
         * enteros al rótulo. Cierra en 234,5, que es el margen derecho.
         */
        .campo { left: 58pt; width: 176.5pt; height: 13pt; }

        /* El perfilado de cinco copias. Hoy lo usa SOLO el lockup del
           encabezado —los rótulos lo tuvieron y se los sacó—, pero la regla vive
           acá porque es genérica: la aplica cualquier bloque con clase
           `perfil`. Ver `partes/texto-perfilado`. */
        .perfil span { position: absolute; font-weight: bold; }

        .perfil .borde { color: #14350f; }
        .perfil .e1 { top: 0.2pt; left: 0.2pt; }
        .perfil .e2 { top: 0.2pt; left: -0.2pt; }
        .perfil .e3 { top: -0.2pt; left: 0.2pt; }
        .perfil .e4 { top: -0.2pt; left: -0.2pt; }
        .perfil .cara { top: 0; left: 0; color: #ffffff; }

        /* ---------------------------------------------------------------
           LOS RÓTULOS: BLANCOS, SIN CONTORNO Y MÁS GRUESOS

           El blanco es lo que separa el andamio del dato. «NOMBRE» no es
           información: es el cartelito que dice qué se está leyendo. En el verde
           oscuro del valor pesaba lo mismo que el nombre de la persona y el ojo
           tenía que descartarlo en cada renglón.

           TUVIERON CONTORNO Y SE LES SACÓ. Se les había puesto uno oscuro de
           0,2 pt porque a 4,6 el blanco se desdibujaba donde pasa el sello de
           agua. Lo que resolvió el problema de verdad fue AGRANDARLOS: sin el
           borde se leen francamente blancos en vez de blancos-con-suciedad.
           Menos capas y mejor resultado. El `:` va igual: también es andamio.

           EL BLANCO ES `#ffffff` PURO, Y AUN ASÍ PUEDE VERSE GRIS. No es un
           problema de color —medido sobre el PDF, el núcleo del glifo da 255—
           sino de GROSOR: a un cuerpo chico el trazo es tan fino que el ojo lo
           promedia con el verde de atrás. La única palanca real es el cuerpo, y
           por eso este renglón se fue agrandando: 4,6 -> 5,2 -> 6,1.

           EL LÍMITE LO MARCA «ASOCIACIÓN», que es el rótulo más largo: a 6,1 pt
           mide 42,8 de los 44 de su caja, y su tope es 6,27. Para pasar de ahí
           habría que sacarle ancho a la tira del valor, que es lo que no
           conviene. Medido con la DejaVu Sans Bold que embebe DomPDF.
        --------------------------------------------------------------- */
        .campo .rotulo {
            position: absolute;
            top: 2.4pt;
            left: 0;
            width: 44pt;
            font-size: 6.1pt;
            font-weight: bold;
            color: #ffffff;
        }

        .campo .dospuntos {
            position: absolute;
            top: 2.4pt;
            left: 44pt;
            font-size: 6.1pt;
            font-weight: bold;
            color: #ffffff;
        }

        /*
         * EL CUERPO LO MANDA EL CONTROLADOR, renglón por renglón: un valor que
         * no entra se dibuja más chico y, si hace falta, en dos líneas. Cortarlo
         * perdería los apellidos, que es lo que identifica a la persona en un
         * control. Ver CarnetImpresionController::texto().
         *
         * `overflow: hidden` queda como último tope para lo que ni apretado
         * entre: DomPDF no tiene `text-overflow`.
         */
        /*
         * LA TIRA VA BLANCA Y LA LETRA NEGRA FINA, como una cédula de identidad.
         *
         * Blanco puro y no el crema `#f6faee` de antes: sobre el verde oscuro,
         * el crema se leía como un papel viejo, y lo que la tarjeta imita es un
         * documento de identidad, donde el dato va sobre blanco.
         *
         * Y la letra en NEGRO REGULAR, no en verde oscuro negrita. La negrita
         * tenía sentido cuando la tira era clara sobre un verde claro y todo
         * competía; sobre blanco puro no hace falta gritar, y a 4,6 pt la
         * negrita empasta las letras entre sí. El negro fino es el de cualquier
         * cédula, y es el que aguanta mejor la impresora de credenciales.
         */
        /*
         * EL RELLENO SUMA AL ANCHO, y acá estaba mal declarado: la tira tiene
         * que ir de 46 a 176 —los 130 que quedan de la columna— pero se le
         * escribían 130 de ancho MÁS 4 de relleno, así que la caja terminaba en
         * 180. Con el campo plantado en 60, eso son 240 de una carilla de 243:
         * la tira quedaba a 2,4 pt del borde mientras la foto respeta 8,5 del
         * otro lado, y la tarjeta salía descentrada.
         *
         * Se veía poco porque las seis tiras desbordaban lo mismo. Saltó al
         * agrandar los rótulos: «GESTIÓN» se subía encima de la tira del
         * registro, que terminaba cuatro puntos más allá de donde la cuenta
         * decía.
         */
        .campo .valor {
            position: absolute;
            top: 1.5pt;
            left: 47.5pt;
            width: 125pt;
            padding: 1pt 2pt 0 2pt;
            line-height: 1.15;
            color: #000000;
            background-color: #ffffff;
            overflow: hidden;
        }

        /* Lo que la ficha no tiene cargado se dibuja atenuado, no en blanco: un
           renglón vacío se leería como un error del sistema. Ahora que el valor
           cargado también va en regular, lo único que los separa es el gris. */
        .campo .valor.molde { color: #8a8f85; }

        /* ---------------------------------------------------------------
           EL SEGUNDO PAR DEL RENGLÓN — hoy solo GESTIÓN, junto al REGISTRO

           Van juntos porque se leen juntos: el registro identifica el carnet y
           la gestión dice de qué año es, y un mismo número se repite cada año
           —el registro arranca de nuevo con cada gestión—. Separados, el número
           solo no alcanza para saber de qué carnet se está hablando.

           Comparten renglón y no ocupan uno propio porque en una CR80 no entran
           siete líneas sueltas sin apretar todo lo demás. El reparto: el
           registro son seis dígitos fijos y no necesita la tira entera, así que
           le cede la mitad derecha al año.

           Las coordenadas cierran contra los 176 pt de la columna, contando el
           relleno de cada tira:
               valor registro  47,5 -> 93,5   rótulo 96 -> 128   valor 131,5 -> 176,5
        --------------------------------------------------------------- */
        .campo .valor.angosto { width: 42pt; }

        /* «GESTIÓN» a 5,2 pt mide 25,8: en los 26 de antes quedaba al límite, así
           que la caja se corrió a 94 y se abrió a 30. El valor del registro
           cierra en 92, así que hay lugar. */
        .campo .rotulo.segundo { left: 96pt; width: 32pt; }
        .campo .dospuntos.segundo { left: 128pt; }
        .campo .valor.segundo { left: 131.5pt; width: 41pt; }

    </style>
</head>
<body>

<div class="carilla">

    @if ($fondo !== '')
        <img class="fondo" src="{{ $fondo }}" alt="">
    @endif

    @if ($escudo !== '')
        <img class="escudo" src="{{ $escudo }}" alt="">
    @endif

    <div class="filete-lockup"></div>

    <div class="bloque gobernacion perfil">
        <div class="l1">@include('documentos.partes.texto-perfilado', ['texto' => 'GOBERNACIÓN'])</div>
        <div class="l2">@include('documentos.partes.texto-perfilado', ['texto' => 'GOBIERNO AUTÓNOMO'])</div>
        <div class="l2">@include('documentos.partes.texto-perfilado', ['texto' => 'DEPARTAMENTAL DEL'])</div>
        <div class="l3">@include('documentos.partes.texto-perfilado', ['texto' => 'BENI'])</div>
    </div>

    {{-- A todo el ancho vuelven a entrar de a una línea. Ver el encabezado. --}}

    <div class="bloque secretaria">
        <div class="l1">SECRETARÍA DPTAL. DE DESARROLLO PRODUCTIVO,</div>
        <div class="l1">RECURSOS NATURALES Y MEDIO AMBIENTE</div>
        <div class="l2">SEDAG - BENI</div>
    </div>

    {{-- El titulo lleva el rubro adentro: «CÉDULA DE PESCADOR». La clase de
         tamaño la elige CarnetImpresionController::titulo() según el largo. --}}
    <div class="bloque titulo {{ $titulo['clase'] }}">
        @include('documentos.partes.texto-perfilado', ['texto' => $titulo['texto']])
    </div>

    <div class="bloque foto">
        @if ($foto !== null)
            <img src="{{ $foto['datos'] }}" style="{{ $foto['estilo'] }}" alt="">
        @endif
    </div>

    {{-- El cuerpo lo manda el controlador, como en los renglones: una cédula
         larga se dibuja más chica antes que salir recortada. --}}
    <div class="bloque documento @if ($documento['valor'] === '') molde @endif"
         style="font-size: {{ $documento['cuerpo'] }}pt;">{{ $documento['valor'] !== '' ? $documento['valor'] : $documento['molde'] }}</div>

    {{--
        Los renglones. El `top` se calcula acá y no en la hoja de estilos porque
        son coordenadas que solo se diferencian en un salto: escritas a mano,
        agregar un renglón obligaba a recorrer todas las de abajo.

        SIEMPRE SON SEIS, para cualquier actividad: el rubro lo dice el título
        y el cupo va en la columna de la foto. Por eso el salto volvió a ser
        fijo — con siete renglones había que apretarlo a 12 pt.
    --}}
    @foreach ($campos as $i => $campo)
        <div class="bloque campo @isset($campo['reparto']) {{ $campo['reparto'] }} @endisset"
             style="top: {{ 66 + $i * 14 }}pt;">
            <span class="rotulo">{{ $campo['rotulo'] }}</span>
            <span class="dospuntos">:</span>
            <div class="valor @if ($campo['valor'] === '') molde @endif @isset($campo['segundo']) angosto @endisset"
                 style="font-size: {{ $campo['cuerpo'] }}pt; height: {{ $campo['alto'] }}pt;">{{ $campo['valor'] !== '' ? $campo['valor'] : $campo['molde'] }}</div>

            {{-- Solo el último renglón lleva acompañantes: GESTIÓN siempre, y el
                 CUPO cuando la actividad se autoriza por volumen. Con cupo el
                 renglón usa el reparto `triple` —ver la hoja de estilos— porque
                 tres valores no entran en dos mitades. --}}
            @isset ($campo['segundo'])
                <span class="rotulo segundo">{{ $campo['segundo']['rotulo'] }}</span>
                <span class="dospuntos segundo">:</span>
                <div class="valor segundo @if ($campo['segundo']['valor'] === '') molde @endif"
                     style="font-size: {{ $campo['segundo']['cuerpo'] }}pt; height: {{ $campo['segundo']['alto'] }}pt;">{{ $campo['segundo']['valor'] !== '' ? $campo['segundo']['valor'] : $campo['segundo']['molde'] }}</div>
            @endisset

            {{-- El TERCER valor va SIN rótulo: es el cupo, y «800 KG» se lee
                 solo. Ver CarnetImpresionController::renglonRegistro(). --}}
            @isset ($campo['tercero'])
                <div class="valor tercero @if ($campo['tercero']['valor'] === '') molde @endif"
                     style="font-size: {{ $campo['tercero']['cuerpo'] }}pt; height: {{ $campo['tercero']['alto'] ?? 9.5 }}pt;">{{ $campo['tercero']['valor'] !== '' ? $campo['tercero']['valor'] : $campo['tercero']['molde'] }}</div>
            @endisset
        </div>
    @endforeach

</div>

</body>
</html>
