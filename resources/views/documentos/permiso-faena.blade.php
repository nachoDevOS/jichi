{{--
    PERMISO POR FAENA — calco del talonario del SEDAG.

    Mismo criterio que la autorización de pesca: texto que FLUYE, no
    coordenadas fijas. Lo propio de este papel es el MARCO redondeado que
    encierra todo el cuerpo y el pie de tres copias.
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Permiso por faena {{ $numero }}</title>

    <style>
        * { margin: 0; padding: 0; }

        @page { margin: 0; }

        /* DejaVu Sans es la única que DomPDF trae con acentos y «ñ» completos. */
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 8pt;
            color: #1b5e20;
            width: 612pt;
            height: 792pt;
            position: relative;
        }

        /* El sello va declarado ANTES que el marco y con z-index negativo:
           DomPDF respeta el apilado solo si lo de atrás se declara primero.
           No lleva `opacity` —poco confiable en DomPDF—: la atenuación viene
           horneada en `faena-sello.png`, que es `sedag.png` mezclado contra
           blanco al 11% y guardado en paleta. Para aclararlo u oscurecerlo se
           REGENERA el PNG con otro factor, no con CSS. */
        /* CENTRADO SOBRE EL MARCO, no sobre la página: el marco arranca en
           109 pt y cierra en 714,5, así que su centro está en 412 y no en los
           396 de la hoja. Medido sobre el PDF renderizado. */
        .sello {
            position: absolute;
            top: 292pt;
            left: 186pt;
            width: 240pt;
            height: 240pt;
            z-index: -1;
        }
        .sello img { width: 240pt; height: 240pt; }

        .hoja { position: absolute; top: 30pt; left: 34pt; width: 544pt; }

        /* --- Encabezado --- */
        .entidad { text-align: center; line-height: 1.35; }
        .entidad .l1 { font-size: 8.5pt; font-weight: bold; }
        .entidad .l2,
        .entidad .l3 { font-size: 8pt; font-weight: bold; }
        .entidad .l4 { font-size: 8pt; font-weight: bold; padding-top: 1pt; }

        /* EL MARCO DEL TALONARIO. `border-radius` lo dibuja bien la 3.1.6 de
           DomPDF —medido—, así que no hace falta hornearlo en un PNG.
           El padding SUMA al ancho: 524 + 2×10 = 544, el ancho de la hoja. */
        .marco {
            border: 0.9pt solid #2e7d32;
            border-radius: 6pt;
            width: 524pt;
            padding: 10pt;
            margin-top: 8pt;
        }

        .titulo {
            font-size: 15pt;
            font-weight: bold;
            color: #2e7d32;
            white-space: nowrap;
        }

        /* El número va en rojo, como en el talonario: es lo único con otra
           tinta, para que salte a la vista al archivar. */
        .rotulo-numero { color: #c62828; font-size: 11pt; font-weight: bold; }

        /* La cajita «N° RECIBO» del papel: dos celdas con borde compartido. */
        .caja-recibo {
            border: 0.8pt solid #444;
            font-size: 7pt;
            font-weight: bold;
            color: #111;
            text-align: center;
            line-height: 1.1;
            padding: 2pt 4pt;
        }
        .caja-recibo-valor {
            border: 0.8pt solid #444;
            border-left: 0;
            height: 15pt;
            font-size: 9pt;
            color: #111;
            text-align: center;
            padding-top: 4pt;
        }

        /* SIN `padding-top`, y el alto entero en `height`: con relleno el
           número cae bajo el centro del recuadro y se le sale por abajo. El
           total no cambia —15 + 3 = 18—. Mismo arreglo que la autorización. */
        .caja-bs {
            border: 0.9pt solid #444;
            border-radius: 9pt;
            height: 18pt;
            text-align: center;
            font-size: 10pt;
            font-weight: bold;
            color: #111;
        }

        /* --- Renglones --- */
        .parrafo { font-size: 8pt; line-height: 1.5; text-align: justify; }
        .destacado { font-size: 8.5pt; font-weight: bold; text-align: center; padding-top: 6pt; }
        .rotulo { font-size: 8pt; white-space: nowrap; }

        /* El renglón punteado del formulario: es un borde inferior y no una
           fila de puntos escritos, así el dato se apoya ENCIMA de la línea. */
        .linea {
            border-bottom: 0.7pt dotted #2e7d32;
            padding: 0 4pt 1.5pt;
            color: #111;
            font-size: 8.5pt;
        }
        .fila { padding-top: 10pt; }

        .cierre { font-size: 8.5pt; padding-top: 16pt; }

        /* 20 y no 118: el QR se mudó a este hueco, así que el resto del espacio
           en blanco lo ocupa él. Los 118 originales se reparten entre el relleno
           de arriba (8), el margen del bloque (10), el bloque apilado (~80) y lo
           que queda acá para firmar a mano. Ver el cuerpo. */
        .firma { text-align: center; font-size: 8.5pt; padding-top: 20pt; }

        .nota { font-size: 7.5pt; line-height: 1.4; padding-top: 12pt; }
        .nota .titulo-nota { font-size: 8pt; }

        /* El pie de las tres copias, separado por una raya como en el papel. */
        .copias {
            border-top: 0.7pt solid #2e7d32;
            font-size: 7.5pt;
            margin-top: 10pt;
            padding-top: 5pt;
        }

        /* ---------------------------------------------------------------
           EL BLOQUE DE VERIFICACIÓN — QR + código escrito
           --------------------------------------------------------------- */
        /*
         * El andamio lo pone `partes/qr-verificacion`; acá van el color y el
         * cuerpo. El QR va SOBRE BLANCO: necesita su zona de silencio clara, y
         * sobre el sello de agua las cámaras dejan de engancharlo.
         */
        /* APILADO: el rótulo y el código van DEBAJO del QR, a pedido. Eso
           libera el ancho, así que lo que manda el ancho del bloque pasa a ser
           el código —103 pt a 8 pt monoespaciada— y no el QR. */
        .qr-bloque { margin-top: 10pt; text-align: center; }
        .qr-caja { background-color: #ffffff; }
        .qr-rotulo { font-size: 6pt; font-weight: bold; letter-spacing: 0.4pt; color: #1f3d13; }
        .qr-codigo {
            /* Monoespaciada: en el código se confunden el 0 con la O. */
            font-family: 'DejaVu Sans Mono', monospace;
            font-size: 8pt;
            font-weight: bold;
            letter-spacing: 0.6pt;
            padding-top: 1pt;
            color: #000000;
        }
        .qr-pie { font-size: 5.6pt; padding-top: 1pt; color: #55604d; }

    </style>
</head>

<body>

@if ($selloSedag)
    <div class="sello"><img src="{{ $selloSedag }}" alt=""></div>
@endif

<div class="hoja">

    {{-- Encabezado: escudo a la izquierda, entidad centrada, peces a la derecha.
         Va FUERA del marco, igual que en el talonario. --}}
    <table width="100%" cellspacing="0" cellpadding="0">
        <tr>
            <td width="80" valign="top">
                @if ($escudo)
                    <img src="{{ $escudo }}" style="width: 62pt;" alt="">
                @endif
            </td>

            <td class="entidad" valign="middle">
                <div class="l1">GOBIERNO AUTÓNOMO DEPARTAMENTAL DEL BENI</div>
                <div class="l2">SECRETARIA DE DESARROLLO PRODUCTIVO RECURSOS NATURALES Y MEDIO AMBIENTE</div>
                <div class="l3">PROGRAMA: FOMENTO A LA ACTIVIDAD PISCÍCOLA Y PESQUERA DPTO. DEL BENI</div>
                <div class="l4">SEDAG - BENI</div>
            </td>

            {{-- El emblema de peces del talonario —un surubí y un pacú—, no el
                 logo del SEDAG: ese ya está en el sello de agua del fondo. --}}
            <td width="80" align="right" valign="top">
                @if ($peces)
                    <img src="{{ $peces }}" style="width: 62pt;" alt="">
                @endif
            </td>
        </tr>
    </table>

    <div class="marco">

        {{--
            EL TÍTULO Y EL CUADRO DEL RECIBO VAN EN LA MISMA TABLA, en dos
            renglones: así el cuadro cae CENTRADO DEBAJO de «PERMISO POR FAENA»
            —como en el papel— sin depender de un margen calculado a mano, que
            deja de servir apenas cambia el cuerpo del título.
        --}}
        <table width="100%" cellspacing="0" cellpadding="0">
            <tr>
                {{-- LAS DOS COLUMNAS LATERALES MIDEN LO MISMO, y tienen que
                     medirlo: con 80 a la izquierda y 118 a la derecha el centro
                     de la columna del medio cae 19 pt corrido, y el título se
                     ve descentrado dentro del marco. --}}
                <td width="118"></td>
                <td class="titulo" align="center" valign="middle">PERMISO POR FAENA</td>
                {{-- CENTRADO sobre el grupo «Bs. + cuadro» de la fila de
                     abajo, no pegado a la derecha: con `align="right"` el
                     número cerraba contra el margen mientras el grupo arranca
                     antes, y se veía corrido. --}}
                <td width="118" valign="middle">
                    <div class="rotulo-numero" style="text-align: center;">N<sup>o</sup> {{ $numero }}</div>
                </td>
            </tr>

            <tr>
                <td></td>

                <td align="center" valign="middle" style="padding-top: 6pt;">
                    <table cellspacing="0" cellpadding="0" style="margin: 0 auto;">
                        <tr>
                            <td width="40" class="caja-recibo" valign="middle">N<sup>o</sup><br>RECIBO</td>
                            <td width="130" class="caja-recibo-valor" valign="middle">{{ $recibo }}</td>
                        </tr>
                    </table>
                </td>

                {{-- `width="100%"` y no `align="right"`: ajustada a su
                     contenido la caja no llega al margen y el N° de arriba sí,
                     y esa diferencia se ve. Al 100% de la columna las dos
                     mueren en el mismo borde. --}}
                <td valign="middle" style="padding-top: 6pt;">
                    <table width="100%" cellspacing="0" cellpadding="0">
                        <tr>
                            <td valign="middle" align="right"
                                style="font-size: 10pt; font-weight: bold; padding-right: 4pt;">Bs.</td>
                            <td width="86" valign="middle"><div class="caja-bs">{{ $monto }}</div></td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <div class="parrafo" style="padding-top: 10pt;">
            En cumplimiento a disposiciones contenidas en el Reglamento de Pesca y Comercialización de
            Especies Piscicolas en el Dpto. del Beni:
        </div>

        <div class="destacado">
            El Área de Fiscalización y Control de la Actividad Pesquera Autoriza a:
        </div>

        {{-- La embarcación --}}
        <table width="100%" cellspacing="0" cellpadding="0" class="fila">
            <tr>
                <td width="14"></td>
                <td class="rotulo" width="70" valign="bottom">La embarcación:</td>
                <td class="linea" valign="bottom">{{ $embarcacion }}</td>
            </tr>
        </table>

        {{-- De propiedad de --}}
        <table width="100%" cellspacing="0" cellpadding="0" class="fila">
            <tr>
                <td width="14"></td>
                <td class="rotulo" width="72" valign="bottom">De propiedad de:</td>
                <td class="linea" valign="bottom">{{ $propietario }}</td>
            </tr>
        </table>

        {{-- Comandante de barco --}}
        <table width="100%" cellspacing="0" cellpadding="0" class="fila">
            <tr>
                <td width="14"></td>
                <td class="rotulo" width="92" valign="bottom">Comandante de barco:</td>
                <td class="linea" valign="bottom">{{ $comandante }}</td>
            </tr>
        </table>

        {{-- Matrícula naval + Kardex: los dos en el mismo renglón, como el papel --}}
        <table width="100%" cellspacing="0" cellpadding="0" class="fila">
            <tr>
                <td width="14"></td>
                <td class="rotulo" width="100" valign="bottom">Bajo Matricula Naval No.:</td>
                <td class="linea" width="206" valign="bottom">{{ $matricula }}</td>
                <td class="rotulo" width="56" valign="bottom" style="padding-left: 8pt;">N<sup>o</sup> Kardex:</td>
                <td class="linea" valign="bottom">{{ $kardex }}</td>
            </tr>
        </table>

        {{-- Región: desde --}}
        <table width="100%" cellspacing="0" cellpadding="0" class="fila">
            <tr>
                <td width="14"></td>
                <td class="rotulo" width="104" valign="bottom">Pescar en la región desde:</td>
                <td class="linea" valign="bottom">{{ $regionDesde }}</td>
            </tr>
        </table>

        {{-- Región: hasta --}}
        <table width="100%" cellspacing="0" cellpadding="0" class="fila">
            <tr>
                <td width="14"></td>
                <td class="rotulo" width="26" valign="bottom">Hasta:</td>
                <td class="linea" valign="bottom">{{ $regionHasta }}</td>
            </tr>
        </table>

        {{-- Fecha de salida --}}
        <table width="100%" cellspacing="0" cellpadding="0" class="fila">
            <tr>
                <td width="14"></td>
                <td class="rotulo" width="66" valign="bottom">Fecha de Salida:</td>
                <td class="linea" valign="bottom">{{ $fechaSalida }}</td>
            </tr>
        </table>

        {{-- Fecha de desembarque --}}
        <table width="100%" cellspacing="0" cellpadding="0" class="fila">
            <tr>
                <td width="14"></td>
                <td class="rotulo" width="92" valign="bottom">Fecha de desembarque:</td>
                <td class="linea" valign="bottom">{{ $fechaDesembarque }}</td>
            </tr>
        </table>

        {{-- Cantidad autorizada: el «Kg.» va DESPUÉS de la línea, como el papel --}}
        <table width="100%" cellspacing="0" cellpadding="0" class="fila">
            <tr>
                <td width="14"></td>
                <td class="rotulo" width="164" valign="bottom">Cantidad autorizada de pescado extraído:</td>
                {{-- ANCHO FIJO: sin esto la línea se estira hasta el borde y
                     empuja el «Kg.» fuera del marco. --}}
                <td class="linea" width="180" valign="bottom" align="center">{{ $kilos }}</td>
                <td class="rotulo" width="24" valign="bottom" style="padding-left: 4pt;">Kg.</td>
                <td></td>
            </tr>
        </table>

        {{-- El pie del papel: «Trinidad, __ de ______ de 20__» --}}
        <table cellspacing="0" cellpadding="0" class="cierre" style="margin: 0 auto;">
            <tr>
                <td class="rotulo" valign="bottom">{{ $lugar }},</td>
                <td class="linea" width="40" valign="bottom" align="center">{{ $fecha['dia'] }}</td>
                <td class="rotulo" valign="bottom" style="padding: 0 4pt;">de</td>
                <td class="linea" width="104" valign="bottom" align="center">{{ $fecha['mes'] }}</td>
                <td class="rotulo" valign="bottom" style="padding: 0 4pt;">de 20</td>
                <td class="linea" width="36" valign="bottom" align="center">{{ $fecha['anio'] }}</td>
            </tr>
        </table>

        {{-- EL QR VA EN EL HUECO DE LA FIRMA, contra el margen derecho y
             ARRIBA del nombre del responsable, a pedido. Ocupa espacio que ya
             estaba en blanco, así que no empuja nada: lo que antes era todo
             `padding-top` de `.firma` ahora se reparte entre el bloque y lo que
             queda de hueco para la firma manuscrita. --}}
        <table width="100%" cellspacing="0" cellpadding="0" style="padding-top: 8pt;">
            {{-- La celda va con ANCHO FIJO, no con `align="right"`: sobre una
                 tabla anidada DomPDF ignora el align y el bloque se quedaba
                 pegado a la izquierda. Apilado, lo más ancho es el código —103
                 pt—, así que con 112 el bloque cierra contra el margen del
                 marco, en 569. --}}
            <tr>
                <td></td>
                <td width="112">@include('documentos.partes.qr-verificacion', ['lado' => 62, 'vertical' => true])</td>
            </tr>
        </table>

        {{-- El hueco de la firma manuscrita: es padding y no una línea, porque
             el talonario tampoco la trae. --}}
        <div class="firma">{{ $responsable }}</div>

        <div class="nota">
            <div class="titulo-nota">NOTA.-</div>
            a) La presente autorización tiene validez para una sola faena de pesca. Sin este documento
            no se otorgará el derecho de Zarpe por la capitania del Puerto.
        </div>

        <table width="100%" cellspacing="0" cellpadding="0" class="copias">
            <tr>
                <td align="left">Original: Cliente</td>
                <td align="center">Copia Amarilla: Contabilidad</td>
                <td align="right">Copia Verde: Archivo</td>
            </tr>
        </table>

    </div>

</div>

</body>
</html>
