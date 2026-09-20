{{--
    AUTORIZACIÓN DE PESCA — calco del talonario del SEDAG.

    Calca el formulario renglón por renglón: el mismo encabezado, los mismos
    campos de línea punteada, la tabla de tamaños mínimos y las reglas de redes.
    Quien lo recibe conoce ese formato.

    Es texto que FLUYE, al revés que el carnet y el recibo: el papel no tiene
    coordenadas fijas sino párrafos que se van apoyando uno debajo del otro.
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Autorización de pesca {{ $numero }}</title>

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
           No lleva `opacity` — el PNG ya viene atenuado. */
        .sello {
            position: absolute;
            top: 250pt;
            left: 156pt;
            width: 300pt;
            height: 300pt;
            z-index: -1;
        }
        .sello img { width: 300pt; height: 300pt; }

        .hoja { position: absolute; top: 28pt; left: 34pt; width: 544pt; }

        /* --- Encabezado --- */
        .entidad { text-align: center; line-height: 1.35; }
        .entidad .l1 { font-size: 8.5pt; font-weight: bold; }
        .entidad .l2,
        .entidad .l3 { font-size: 8pt; font-weight: bold; }
        .entidad .l4 { font-size: 8pt; font-weight: bold; padding-top: 1pt; }

        .raya { border-bottom: 1pt solid #2e7d32; height: 1pt; margin: 6pt 0 8pt; }

        .titulo {
            font-size: 14pt;
            font-weight: bold;
            color: #2e7d32;
            line-height: 1.2;
            white-space: nowrap;
        }

        /* El número va en rojo, como en el talonario: es lo único con otra
           tinta, para que salte a la vista al archivar. */
        .rotulo-numero { color: #c62828; font-size: 11pt; font-weight: bold; }
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
        .rotulo { font-size: 8pt; white-space: nowrap; }

        /* El renglón punteado del formulario: es un borde inferior y no una fila
           de puntos escritos, así el dato se apoya ENCIMA de la línea. */
        .linea {
            border-bottom: 0.7pt dotted #2e7d32;
            padding: 0 4pt 1.5pt;
            color: #111;
            font-size: 8.5pt;
        }
        .fila { padding-top: 7pt; }

        /* --- Tabla de tamaños --- */
        .especies { border-collapse: collapse; width: 420pt; margin: 6pt 0 6pt 56pt; }
        .especies th,
        .especies td {
            border: 0.7pt solid #2e7d32;
            font-size: 7.5pt;
            padding: 1.5pt 4pt;
        }
        .especies th { font-weight: bold; text-align: center; }
        .especies td.tam { color: #111; }

        /* --- Redes --- */
        .vinetas { font-size: 8pt; line-height: 1.55; }
        .vinetas td { padding-bottom: 1pt; }
        .vinetas .punto { width: 14pt; text-align: center; }

        .cierre { text-align: center; font-size: 8.5pt; padding-top: 14pt; }
    </style>
</head>

<body>

@if ($selloSedag)
    <div class="sello"><img src="{{ $selloSedag }}" alt=""></div>
@endif

<div class="hoja">

    {{-- Encabezado: escudo a la izquierda, entidad centrada, logo a la derecha --}}
    <table width="100%" cellspacing="0" cellpadding="0">
        <tr>
            <td width="80" valign="top">
                @if ($escudo)
                    <img src="{{ $escudo }}" style="width: 62pt;" alt="">
                @endif
            </td>

            <td class="entidad" valign="middle">
                <div class="l1">GOBIERNO AUTÓNOMO DEPARTAMENTAL DEL BENI</div>
                <div class="l2">SECRETARIA DE DESARROLLO PRODUCTIVO Y ECONOMIA PLURAL</div>
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

    <div class="raya"></div>

    {{-- Título a la izquierda en DOS renglones —nowrap, o a 14 pt se parte en
         cuatro— y a la derecha el N° arriba con la caja de Bs. abajo. --}}
    <table width="100%" cellspacing="0" cellpadding="0">
        <tr>
            <td class="titulo" width="290" valign="top">
                <div>AUTORIZACIÓN DE PESCA PARA</div>
                <div>APROVECHAMIENTO PESQUERO</div>
            </td>

            <td valign="top" style="padding-left: 12pt;">
                <div class="rotulo-numero">N<sup>o</sup> {{ $numero }}</div>

                <table cellspacing="0" cellpadding="0" style="padding-top: 6pt;">
                    <tr>
                        <td width="24" valign="middle" style="font-size: 11pt; font-weight: bold;">Bs.</td>
                        <td width="150" valign="middle">
                            <div class="caja-bs">{{ $monto }}</div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="parrafo" style="padding-top: 6pt;">
        El Servicio Departamental Autónomo Agropecuario &quot;SEDAG - BENI&quot;, otorga al:
    </div>

    {{-- Sr.(a) --}}
    <table width="100%" cellspacing="0" cellpadding="0" class="fila">
        <tr>
            <td class="rotulo" width="34" valign="bottom">Sr.(a):</td>
            <td class="linea" valign="bottom">{{ $beneficiario }}</td>
        </tr>
    </table>

    {{-- Domiciliado en --}}
    <table width="100%" cellspacing="0" cellpadding="0" class="fila">
        <tr>
            <td class="rotulo" width="70" valign="bottom">Domiciliado en:</td>
            <td class="linea" valign="bottom">{{ $domicilio }}</td>
        </tr>
    </table>

    {{-- Documento de Identidad + Tipo de Embarcación --}}
    <table width="100%" cellspacing="0" cellpadding="0" class="fila">
        <tr>
            <td class="rotulo" width="104" valign="bottom">Documento de Identidad:</td>
            <td class="linea" width="160" valign="bottom">{{ $documento }}</td>
            <td class="rotulo" width="100" valign="bottom" style="padding-left: 10pt;">Tipo de Embarcación</td>
            <td class="linea" valign="bottom">{{ $embarcacion }}</td>
        </tr>
    </table>

    {{-- Volumen establecido: el dato va EN MEDIO del párrafo, como en el papel --}}
    <table width="100%" cellspacing="0" cellpadding="0" class="fila">
        <tr>
            <td class="rotulo" width="92" valign="bottom">Volumen Establecido</td>
            <td class="linea" width="120" valign="bottom" align="center">{{ $volumen }}</td>
            <td class="rotulo" valign="bottom" style="padding-left: 4pt;">Kg. del Recurso Pesquero, Bajo las normas, derechos, obligaciones</td>
        </tr>
    </table>

    <div class="parrafo">
        y prohibiciiones establecidas en el desde el Art. 26 al 31 del Reglamento para Pesca y Comercialización
        de Especies Piscicolas en el Departamento del Beni y Todas las normas pertinentes para el normal
        desarrollo de la actividad pesquera.
    </div>

    {{-- Vigencia --}}
    <table width="100%" cellspacing="0" cellpadding="0" class="fila">
        <tr>
            <td class="rotulo" width="150" valign="bottom">Vigencia de la Temporada de Pesca:</td>
            <td class="linea" valign="bottom">{{ $vigencia }}</td>
        </tr>
    </table>

    {{-- Valor de la concesión + tiempo de cancelación --}}
    <table width="100%" cellspacing="0" cellpadding="0" class="fila">
        <tr>
            <td class="rotulo" width="140" valign="bottom">Valor de la Concesión Pesquera Bs.:</td>
            <td class="linea" width="120" valign="bottom">{{ $monto }}</td>
            <td class="rotulo" width="108" valign="bottom" style="padding-left: 10pt;">Tiempo de Cancelación</td>
            <td class="linea" valign="bottom">{{ $cancelacion }}</td>
        </tr>
    </table>

    <div class="parrafo" style="padding-top: 7pt;">
        Tamaño minimo del Pez a Capturar, El &quot;SEDAG - BENI&quot; en coordinación con otros organismos,
        sobre aprovechamiento pesquero, autoriza la extracción de peces no menores a las dimensiones
        desde la punta de la cabeza hasta la base de la cola, exceptuando los productos provenientes de la
        piscicultura:
    </div>

    {{-- LA TABLA DE TAMAÑOS MÍNIMOS: va literal, como en el papel. Sale del
         reglamento, no de la base — si cambia por resolución se toca acá. --}}
    <table class="especies">
        <tr>
            <th width="30%">NOMBRE COMÚN</th>
            <th width="42%">NOMBRE CIENTÍFICO</th>
            <th width="28%">TAMAÑO</th>
        </tr>

        @foreach ($especies as $especie)
            <tr>
                <td>{{ $especie[0] }}</td>
                <td>{{ $especie[1] }}</td>
                <td class="tam">{{ $especie[2] }}</td>
            </tr>
        @endforeach
    </table>

    <div class="parrafo">
        El Servicio Departamental Autónomo Agropecuario &quot;SEDAG - BENI&quot;, autoriza el uso de redes según
        dimensiones:
    </div>

    <table width="100%" cellspacing="0" cellpadding="0" class="vinetas" style="padding-top: 4pt;">
        @foreach ($redes as $regla)
            <tr>
                <td class="punto" valign="top">•</td>
                <td>{!! $regla !!}</td>
            </tr>
        @endforeach
    </table>

    {{-- El pie del papel: «Trinidad, __ de ______ de 20__» --}}
    <table cellspacing="0" cellpadding="0" class="cierre" style="margin: 0 auto;">
        <tr>
            <td class="rotulo" valign="bottom">{{ $lugar }},</td>
            <td class="linea" width="46" valign="bottom" align="center">{{ $fecha['dia'] }}</td>
            <td class="rotulo" valign="bottom" style="padding: 0 4pt;">de</td>
            <td class="linea" width="110" valign="bottom" align="center">{{ $fecha['mes'] }}</td>
            <td class="rotulo" valign="bottom" style="padding: 0 4pt;">de 20</td>
            <td class="linea" width="40" valign="bottom" align="center">{{ $fecha['anio'] }}</td>
        </tr>
    </table>

</div>

</body>
</html>
