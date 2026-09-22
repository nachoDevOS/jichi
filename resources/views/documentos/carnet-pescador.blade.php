{{--
  CÉDULA DE PESCADOR — calco del carnet plastificado del SEDAG
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
         */
        .fondo { position: absolute; top: 0; left: 0; width: 243pt; height: 153pt; }

        .bloque { position: absolute; }

        /* ---------------------------------------------------------------
           EL ENCABEZADO, EN DOS PISOS
        --------------------------------------------------------------- */
        .escudo { position: absolute; top: 2pt; left: 77pt; height: 24pt; }

        /*
         * EL FILETE entre el escudo y el texto, como en el lockup oficial: un
         * hilo vertical, no una caja. Blanco porque acompaña al texto, que es
         * blanco; en verde oscuro sobre este fondo no se vería.
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
           --------------------------------------------------------------- */
        .titulo.largo span { font-size: 7.6pt; letter-spacing: 0.5pt; }
        .titulo.largo .e1 { top: 0.36pt; left: 0.36pt; }
        .titulo.largo .e2 { top: 0.36pt; left: -0.36pt; }
        .titulo.largo .e3 { top: -0.36pt; left: 0.36pt; }
        .titulo.largo .e4 { top: -0.36pt; left: -0.36pt; }

        /* --- La foto y la cédula ------------------------------------------ */

        /* ---------------------------------------------------------------
           LA CÉDULA, EN SU PROPIA TIRA BLANCA
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
         */
        /* ---------------------------------------------------------------
           LA FOTO ARRANCA MÁS ABAJO QUE LOS RENGLONES, a pedido.
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

        /*
         * EL CORRIMIENTO DEL LOCKUP VA CONTRA EL CUERPO DE CADA LÍNEA, no el
         * mismo para las tres. Los 0,2 de arriba son el ~3% de los 7 pt de
         * GOBERNACIÓN y BENI: medido, el contorno quedaba tan fino que el
         * lockup daba 3,7:1 contra el verde. Al 6% de siempre da 0,4 —y 0,27
         * en las dos líneas chicas, que van a 4,5 pt y con 0,4 se llenarían—.
         */
        .gobernacion .l1 .e1, .gobernacion .l3 .e1 { top: 0.4pt; left: 0.4pt; }
        .gobernacion .l1 .e2, .gobernacion .l3 .e2 { top: 0.4pt; left: -0.4pt; }
        .gobernacion .l1 .e3, .gobernacion .l3 .e3 { top: -0.4pt; left: 0.4pt; }
        .gobernacion .l1 .e4, .gobernacion .l3 .e4 { top: -0.4pt; left: -0.4pt; }

        .gobernacion .l2 .e1 { top: 0.27pt; left: 0.27pt; }
        .gobernacion .l2 .e2 { top: 0.27pt; left: -0.27pt; }
        .gobernacion .l2 .e3 { top: -0.27pt; left: 0.27pt; }
        .gobernacion .l2 .e4 { top: -0.27pt; left: -0.27pt; }

        /* ---------------------------------------------------------------
           LOS RÓTULOS: BLANCOS, GRUESOS Y PERFILADOS
        --------------------------------------------------------------- */
        /*
         * LOS RÓTULOS VUELVEN A LLEVAR CONTORNO. Lo tuvieron, se los sacó, y
         * medido en el PDF era un error: en blanco puro daban 3,3:1 contra los
         * tramos claros del sello —debajo del mínimo legible— y desaparecían
         * del todo impresos con poco tóner. El cuerpo se deja en 5,8: lo que
         * faltaba no era tamaño sino despegarlos del fondo.
         */
        .campo .rotulo {
            position: absolute;
            top: 2.4pt;
            left: 0;
            width: 44pt;
        }

        .campo .dospuntos {
            position: absolute;
            top: 2.4pt;
            left: 44pt;
        }

        /*
         * Corrido 0,35 sobre 5,8 pt — el ~6% de siempre. Las cinco copias van
         * sin ancho declarado: el rótulo va alineado a la izquierda, así que
         * `absolute` sin `width` se encoge al texto y cada copia cae encima de
         * la anterior, que es justo lo que el perfilado necesita.
         */
        .campo .rotulo span,
        .campo .dospuntos span {
            position: absolute;
            /* 5,8 y no 6,1: un punto menos que el valor, lo justo para que
               el rótulo no compita con el dato. Ver la nota de «PROV.»: a
               menor cuerpo, además, el rótulo largo tiene más margen. */
            font-size: 5.8pt;
            font-weight: bold;
        }

        .campo .rotulo .borde,
        .campo .dospuntos .borde { color: #14350f; }
        .campo .rotulo .e1, .campo .dospuntos .e1 { top: 0.35pt; left: 0.35pt; }
        .campo .rotulo .e2, .campo .dospuntos .e2 { top: 0.35pt; left: -0.35pt; }
        .campo .rotulo .e3, .campo .dospuntos .e3 { top: -0.35pt; left: 0.35pt; }
        .campo .rotulo .e4, .campo .dospuntos .e4 { top: -0.35pt; left: -0.35pt; }
        .campo .rotulo .cara,
        .campo .dospuntos .cara { top: 0; left: 0; color: #ffffff; }

        /*
         * EL CUERPO LO MANDA EL CONTROLADOR, renglón por renglón: un valor que
         * no entra se dibuja más chico y, si hace falta, en dos líneas. Cortarlo
         * perdería los apellidos, que es lo que identifica a la persona en un
         * control. Ver CarnetImpresionController::texto().
         */
        /*
         * LA TIRA VA BLANCA Y LA LETRA NEGRA FINA, como una cédula de identidad.
         */
        /*
         * EL RELLENO SUMA AL ANCHO, y acá estaba mal declarado: la tira tiene
         * que ir de 46 a 176 —los 130 que quedan de la columna— pero se le
         * escribían 130 de ancho MÁS 4 de relleno, así que la caja terminaba en
         * 180. Con el campo plantado en 60, eso son 240 de una carilla de 243:
         * la tira quedaba a 2,4 pt del borde mientras la foto respeta 8,5 del
         * otro lado, y la tarjeta salía descentrada.
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
        --------------------------------------------------------------- */
        .campo .valor.angosto { width: 42pt; }

        /* «GESTIÓN» a 5,2 pt mide 25,8: en los 26 de antes quedaba al límite, así
           que la caja se corrió a 94 y se abrió a 30. El valor del registro
           cierra en 92, así que hay lugar. */
        .campo .rotulo.segundo { left: 96pt; width: 32pt; }
        .campo .dospuntos.segundo { left: 128pt; }
        .campo .valor.segundo { left: 131.5pt; width: 41pt; }

        /* ===============================================================
           LA CARILLA DE ATRÁS — el reglamento y el recuadro de la firma
           =============================================================== */
        /*
         * Sale en una SEGUNDA PÁGINA del mismo PDF y no en una hoja aparte: el
         * plástico se imprime de los dos lados y las dos carillas tienen que
         * salir del mismo archivo, o ventanilla acaba imprimiendo dos veces.
         *
         * Acá es donde la nota de `.carilla` deja de ser teórica: este bloque
         * es `position: relative` y sus `absolute` se miden contra ÉL. Sin eso
         * se medirían contra la página y aterrizarían encima del anverso.
         */
        .dorso { page-break-before: always; }

        /*
         * EL VELO — lo que hace legible al texto blanco del dorso.
         *
         * El fondo del carnet no es liso: abajo corre el sello del SEDAG, y en
         * sus tramos claros el texto blanco se desdibujaba. Un verde oscuro
         * semitransparente encima empareja la carilla entera y recién ahí las
         * siete reglas se leen de corrido.
         *
         * Va con `rgba` y no con un PNG nuevo: la 3.1.6 de DomPDF lo respeta
         * —medido en el recibo—, y regenerar el fondo obligaría a rehacer la
         * mezcla del sello. El anverso NO lo lleva: ahí el texto va sobre tiras
         * blancas y el sello se tiene que ver pasar.
         */
        .dorso .velo {
            position: absolute;
            top: 0;
            left: 0;
            width: 243pt;
            height: 153pt;
            background-color: rgba(18, 48, 14, 0.40);
        }

        /* El lockup del dorso NO lleva el renglón «GOBERNACIÓN»: en el plástico
           de papel son tres líneas, no cuatro. Las coordenadas son las mismas
           del anverso para que los dos lados calcen al trasluz. */
        .dorso .escudo { top: 4pt; left: 78pt; height: 21pt; }
        .dorso .filete-lockup { top: 4pt; left: 100.5pt; height: 21pt; }
        .dorso .gobernacion { top: 5pt; left: 104pt; }

        /* ---------------------------------------------------------------
           EL TÍTULO DEL DORSO — serif, blanco, a todo el ancho
           --------------------------------------------------------------- */
        .titulo-dorso {
            top: 25.5pt;
            left: 0;
            width: 243pt;
            font-family: 'DejaVu Serif', serif;
            text-align: center;
            line-height: 1;
            color: #ffffff;
        }

        /*
          * EL ALTO VA DECLARADO, como en las reglas. Sin él, DomPDF arma cada
          * línea con las métricas de la DejaVu Serif y no con el `line-height`:
          * medido en el PDF, dos líneas de 7,6 pt ocupaban 12,6 pt cada una en
          * vez de los 8,7 que dice la hoja, y el título terminaba justo encima
          * de la regla 1. Con `height` fijo las dos cierran donde se les dice.
          *
          * 8,2 y no 7,6: contra un texto blanco que se lee gris la única
          * palanca es el CUERPO —el blanco ya es 255—, y al correr los márgenes
          * a 8,5 quedó lugar. A 8,2 el renglón más largo mide 210 pt de los 226
          * útiles.
          */
        .titulo-dorso div { position: relative; height: 10.5pt; }

        /*
         * PERFILADO, como el lockup del anverso: el contorno verde oscuro es lo
         * que despega la letra del sello de agua. Corrido 0,5 sobre 8,2 pt, que
         * es el ~6% de la regla. Ver `partes/texto-perfilado`.
         */
        .titulo-dorso span { position: absolute; width: 243pt; font-size: 8.2pt; font-weight: bold; }
        .titulo-dorso .borde { color: #14350f; }
        .titulo-dorso .e1 { top: 0.5pt; left: 0.5pt; }
        .titulo-dorso .e2 { top: 0.5pt; left: -0.5pt; }
        .titulo-dorso .e3 { top: -0.5pt; left: 0.5pt; }
        .titulo-dorso .e4 { top: -0.5pt; left: -0.5pt; }
        .titulo-dorso .cara { top: 0; left: 0; color: #ffffff; }

        /* ---------------------------------------------------------------
           LAS SIETE REGLAS
           --------------------------------------------------------------- */
        /*
         * MARGEN 8,5, el mismo del anverso. Estaba en 11 sin motivo, y esos
         * 2,5 pt de cada lado son justo lo que le faltaba al cuerpo de las
         * reglas para dejar de verse gris.
         */
        .reglas { top: 49.5pt; left: 8.5pt; width: 226pt; }

        /*
         * 5,55 pt, y lo manda la regla 6: son 68 caracteres, el renglón más
         * largo, y medido en el PDF ocupa 224 pt de los 226 útiles. Un cuarto
         * de punto más y se desborda — el `overflow: hidden` de la carilla se
         * la comería por el costado sin ningún aviso, así que al tocar este
         * número hay que volver a medir el PDF.
         *
         * El alto del renglón es 7,5 y no el del cuerpo: las siete reglas más
         * el título tienen que cerrar antes del recuadro de la firma, y son los
         * dos únicos bloques con los que se puede negociar.
         */
        .reglas div {
            position: relative;
            font-family: 'DejaVu Serif', serif;
            line-height: 1;
            height: 7.5pt;
        }

        /*
         * Corrido 0,33 sobre 5,55 pt — el ~6% de siempre. Medio punto acá NO
         * perfila: a este cuerpo engorda la letra hasta cerrarle los huecos y
         * la «o» se llena, que es lo contrario de lo que el contorno viene a
         * hacer. Al tocar el cuerpo hay que mover también este número.
         */
        .reglas span { position: absolute; width: 226pt; font-size: 5.55pt; font-weight: bold; }
        .reglas .borde { color: #14350f; }
        .reglas .e1 { top: 0.33pt; left: 0.33pt; }
        .reglas .e2 { top: 0.33pt; left: -0.33pt; }
        .reglas .e3 { top: -0.33pt; left: 0.33pt; }
        .reglas .e4 { top: -0.33pt; left: -0.33pt; }
        .reglas .cara { top: 0; left: 0; color: #ffffff; }

        /* ---------------------------------------------------------------
           EL RECUADRO DE LA FIRMA — se llena a mano, sobre el plástico
           --------------------------------------------------------------- */
        /*
         * Va SIEMPRE, con nombre cargado o sin él, y va vacío arriba a
         * propósito: esos 24 pt son el lugar donde el Gobernador firma y donde
         * cae el sello de agua. Cierra en 234,5 —el mismo margen derecho que
         * los renglones del anverso— y a 8 pt del borde de abajo.
         */
        .firma {
            top: 103pt;
            left: 120.5pt;
            width: 114pt;
            height: 42pt;
            background-color: #ffffff;
            border-radius: 3pt;
        }

        /*
         * El nombre va en la MITAD IZQUIERDA del recuadro, no centrado: la
         * derecha es donde se estampa el sello redondo, y centrado le quedaría
         * debajo.
         */
        .firma-datos {
            top: 128pt;
            left: 122pt;
            width: 62pt;
            text-align: center;
            line-height: 1.15;
            color: #000000;
        }

        .firma-datos .nombre { font-size: 4.6pt; font-weight: bold; }
        .firma-datos .cargo { font-size: 4pt; }

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
    --}}
    @foreach ($campos as $i => $campo)
        <div class="bloque campo @isset($campo['reparto']) {{ $campo['reparto'] }} @endisset"
             style="top: {{ 66 + $i * 14 }}pt;">
            <div class="rotulo">@include('documentos.partes.texto-perfilado', ['texto' => $campo['rotulo']])</div>
            <div class="dospuntos">@include('documentos.partes.texto-perfilado', ['texto' => ':'])</div>
            <div class="valor @if ($campo['valor'] === '') molde @endif @isset($campo['segundo']) angosto @endisset"
                 style="font-size: {{ $campo['cuerpo'] }}pt; height: {{ $campo['alto'] }}pt;">{{ $campo['valor'] !== '' ? $campo['valor'] : $campo['molde'] }}</div>

            {{-- Solo el último renglón lleva acompañantes: GESTIÓN siempre, y el
                 CUPO cuando la actividad se autoriza por volumen. Con cupo el
                 renglón usa el reparto `triple` —ver la hoja de estilos— porque
                 tres valores no entran en dos mitades. --}}
            @isset ($campo['segundo'])
                <div class="rotulo segundo">@include('documentos.partes.texto-perfilado', ['texto' => $campo['segundo']['rotulo']])</div>
                <div class="dospuntos segundo">@include('documentos.partes.texto-perfilado', ['texto' => ':'])</div>
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

{{--
    LA CARILLA DE ATRÁS. El reglamento sale del controlador y no escrito acá
    porque es texto del reglamento del SEDAG: si cambia, cambia en un solo
    lugar. Ver CarnetImpresionController::REGLAS_REVERSO.
--}}
<div class="carilla dorso">

    @if ($fondo !== '')
        <img class="fondo" src="{{ $fondo }}" alt="">
    @endif

    <div class="velo"></div>

    @if ($escudo !== '')
        <img class="escudo" src="{{ $escudo }}" alt="">
    @endif

    <div class="filete-lockup"></div>

    <div class="bloque gobernacion perfil">
        <div class="l2">@include('documentos.partes.texto-perfilado', ['texto' => 'GOBIERNO AUTÓNOMO'])</div>
        <div class="l2">@include('documentos.partes.texto-perfilado', ['texto' => 'DEPARTAMENTAL DEL'])</div>
        <div class="l3">@include('documentos.partes.texto-perfilado', ['texto' => 'BENI'])</div>
    </div>

    <div class="bloque titulo-dorso">
        @foreach ($reverso['titulo'] as $linea)
            <div>@include('documentos.partes.texto-perfilado', ['texto' => $linea])</div>
        @endforeach
    </div>

    <div class="bloque reglas">
        @foreach ($reverso['reglas'] as $i => $regla)
            <div>@include('documentos.partes.texto-perfilado', ['texto' => ($i + 1).'. '.$regla])</div>
        @endforeach
    </div>

    <div class="bloque firma"></div>

    {{-- El nombre puede venir vacío: mientras no se cargue el de la gestión en
         curso, el recuadro sale con el cargo y sin nombre. --}}
    <div class="bloque firma-datos">
        @if ($reverso['firmante']['nombre'] !== '')
            <div class="nombre">{{ $reverso['firmante']['nombre'] }}</div>
        @endif

        @if ($reverso['firmante']['cargo'] !== '')
            <div class="cargo">{{ $reverso['firmante']['cargo'] }}</div>
        @endif
    </div>

</div>

</body>
</html>
