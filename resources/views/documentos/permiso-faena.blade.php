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
        .sello {
            position: absolute;
            top: 400pt;
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

        .caja-bs {
            border: 0.9pt solid #444;
            border-radius: 9pt;
            height: 15pt;
            text-align: center;
            font-size: 10pt;
            font-weight: bold;
            color: #111;
            padding-top: 3pt;
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
        .firma { text-align: center; font-size: 8.5pt; padding-top: 118pt; }

        .nota { font-size: 7.5pt; line-height: 1.4; padding-top: 12pt; }
        .nota .titulo-nota { font-size: 8pt; }

        /* El pie de las tres copias, separado por una raya como en el papel. */
        .copias {
            border-top: 0.7pt solid #2e7d32;
            font-size: 7.5pt;
            margin-top: 10pt;
            padding-top: 5pt;
        }
    </style>
</head>

<body>

@if ($selloSedag)
    <div class="sello"><img src="{{ $selloSedag }}" alt=""></div>
@endif

<div class="hoja">

    {{-- Encabezado: escudo a la izquierda, entidad centrada, logo a la derecha.
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

            <td width="80" align="right" valign="top">
                @if ($logo)
                    <img src="{{ $logo }}" style="width: 56pt;" alt="">
                @endif
            </td>
        </tr>
    </table>

    <div class="marco">

        {{-- Título centrado con el N° del talonario a la derecha --}}
        <table width="100%" cellspacing="0" cellpadding="0">
            <tr>
                <td width="80"></td>
                <td class="titulo" align="center" valign="middle">PERMISO POR FAENA</td>
                <td width="110" align="right" valign="middle">
                    <div class="rotulo-numero">N<sup>o</sup> {{ $numero }}</div>
                </td>
            </tr>
        </table>

        {{-- «N° RECIBO [____]» a la izquierda y «Bs. ( 15,00 )» a la derecha --}}
        <table width="100%" cellspacing="0" cellpadding="0" style="padding-top: 6pt;">
            <tr>
                <td width="90"></td>

                <td valign="middle">
                    <table cellspacing="0" cellpadding="0">
                        <tr>
                            <td width="40" class="caja-recibo" valign="middle">N<sup>o</sup><br>RECIBO</td>
                            <td width="150" class="caja-recibo-valor" valign="middle">{{ $recibo }}</td>
                        </tr>
                    </table>
                </td>

                <td width="140" valign="middle">
                    <table cellspacing="0" cellpadding="0">
                        <tr>
                            <td width="22" valign="middle" style="font-size: 10pt; font-weight: bold;">Bs.</td>
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

        {{-- El hueco de la firma manuscrita: son los 74pt de padding de .firma,
             no una línea, porque el talonario tampoco la trae. --}}
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
