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

        /* 13 y no 14: centrado, a 14 pt el renglón más largo mide 249 y cierra
           a 7 pt de «Bs.» — pegado. A 13 mide 231 y quedan 16. */
        .titulo {
            font-size: 13pt;
            font-weight: bold;
            color: #2e7d32;
            line-height: 1.2;
            white-space: nowrap;
        }

        /* El número va en rojo, como en el talonario: es lo único con otra
           tinta, para que salte a la vista al archivar. */
        .rotulo-numero { color: #c62828; font-size: 11pt; font-weight: bold; }
        /* ACHICADA a pedido —era 150 × 15 a 10 pt—: el hueco que deja se lo
           lleva el QR, que ahora va al lado. Ver la fila del título.

           SIN `padding-top`, y el alto entero en `height`: con 2,5 de relleno
           el número caía 3,1 pt bajo el centro del recuadro y se le salía por
           abajo —medido: su caja llegaba a 146,2 y el recuadro cierra en
           145,8—. El total sigue siendo el mismo, 15,5 pt. */
        .caja-bs {
            border: 0.9pt solid #444;
            border-radius: 9pt;
            height: 15.5pt;
            text-align: center;
            font-size: 9.5pt;
            font-weight: bold;
            color: #111;
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

        /* ---------------------------------------------------------------
           EL BLOQUE DE VERIFICACIÓN — QR + código escrito
           --------------------------------------------------------------- */
        /*
         * El andamio lo pone `partes/qr-verificacion`; acá van el color y el
         * cuerpo. El QR va SOBRE BLANCO: necesita su zona de silencio clara, y
         * sobre el sello de agua las cámaras dejan de engancharlo.
         */
        /*
         * ABAJO A LA DERECHA, a pedido. Va absoluto y no en el flujo: el
         * contenido de este papel cierra en y=745 de una hoja de 792, y apilado
         * debajo del pie se salía de la página.
         *
         * OJO: SE POSICIONA DENTRO DE `.hoja`, que está en top 28 / left 34, así
         * que lo declarado no es donde cae: hay que sumarle ese desfase. Con
         * `top: 612 / left: 430` el bloque aterriza en x 464..580 y el QR de 62
         * —centrado— en x 491..553, y 640..702.
         *
         * SIN EL CÓDIGO ESCRITO, a pedido, el bloque perdió su último renglón y
         * sobraban 30 pt hasta el pie: bajó 20 y volvió a subir 10, que es
         * donde quedó — 35 pt de aire arriba y 20 hasta «Trinidad - Beni…».
         *
         * LOS DOS TOPES, medidos en el PDF:
         *   arriba  y=605  — ahí cierra la tabla de especies, cuyo borde
         *                    derecho llega a x=510 y se le encimaría.
         *   abajo   y=731  — el renglón «Trinidad - Beni…» del pie, cuya
         *                    última línea punteada cierra en x=471; el código
         *                    del QR arranca en 470,5 y la rozaba.
         * Entre los dos la columna derecha está libre: el párrafo de redes
         * cierra en x=487 y las viñetas en 439.
         */
        .qr-bloque {
            position: absolute;
            top: 612pt;
            left: 430pt;
            width: 116pt;
            text-align: center;
        }
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

    </style>
</head>

<body>

@if ($selloSedag)
    <div class="sello"><img src="{{ $selloSedag }}" alt=""></div>
@endif

<div class="hoja">

    {{-- Encabezado: escudo a la izquierda, entidad centrada, peces a la derecha --}}
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

            {{-- El emblema de peces del talonario —un surubí y un pacú—, no el
                 logo del SEDAG: ese ya está en el sello de agua del fondo. Es
                 el mismo del permiso de faena, y al mismo cuerpo. --}}
            <td width="80" align="right" valign="top">
                @if ($peces)
                    <img src="{{ $peces }}" style="width: 62pt;" alt="">
                @endif
            </td>
        </tr>
    </table>

    <div class="raya"></div>

    {{-- TÍTULO CENTRADO, con el N° y la caja de Bs. a la derecha.

         LAS DOS COLUMNAS LATERALES MIDEN LO MISMO, y tienen que medirlo: es lo
         único que deja el título centrado en la hoja y no corrido. Mismo patrón
         que el permiso de faena.

         140 y no más: el título va en `nowrap` y su renglón más largo mide
         231 pt a 13 pt, así que al medio tienen que quedarle 544 − 2×140 = 264.
         Y no menos: a la derecha entran «Bs.» (24) más la caja (112). --}}
    <table width="100%" cellspacing="0" cellpadding="0">
        <tr>
            <td width="140"></td>

            <td class="titulo" align="center" valign="top">
                <div>AUTORIZACIÓN DE PESCA PARA</div>
                <div>APROVECHAMIENTO PESQUERO</div>
            </td>

            <td width="140" align="right" valign="top">
                {{-- CENTRADO sobre el grupo «Bs. + cuadro», no pegado a la
                     derecha: el grupo arranca en x=443 y el número heredaba el
                     `align="right"` de la celda, así que quedaba corrido contra
                     el margen mientras el resto empezaba 70 pt antes. --}}
                <div class="rotulo-numero" style="text-align: center;">N<sup>o</sup> {{ $numero }}</div>

                {{-- `width="100%"` y no `align="right"`: con la tabla ajustada a
                     su contenido la caja cerraba en x=573,5 y el N° en 578 —los
                     4,5 pt se veían—. Al 100% la columna del monto cae contra el
                     margen, alineada con el número de arriba. --}}
                <table width="100%" cellspacing="0" cellpadding="0" style="padding-top: 6pt;">
                    <tr>
                        <td valign="middle" align="right"
                            style="font-size: 11pt; font-weight: bold; padding-right: 4pt;">Bs.</td>
                        <td width="112" valign="middle">
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

    @include('documentos.partes.qr-verificacion', ['lado' => 62, 'vertical' => true, 'codigo' => false])

</div>

</body>
</html>
