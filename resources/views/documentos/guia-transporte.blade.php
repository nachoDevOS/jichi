{{--
    GUÍA ÚNICA DE TRANSPORTE DE PRODUCTOS ICTÍCOLAS — calco del talonario.

    Al revés que el permiso de faena, este papel NO es texto que fluye: es una
    grilla de cuadros, así que se maqueta con tablas y anchos declarados. En
    CSS el padding SUMA al width, así que cada ancho de acá es lo que la celda
    ocupa MENOS su relleno. Ver CLAUDE.md.
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Guía de transporte {{ $numero }}</title>

    <style>
        * { margin: 0; padding: 0; }

        @page { margin: 0; }

        /* DejaVu Sans es la única que DomPDF trae con acentos y «ñ» completos. */
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 7pt;
            color: #1b5e20;
            width: 612pt;
            height: 792pt;
            position: relative;
        }

        /* El sello va declarado ANTES del cuerpo y con z-index negativo:
           DomPDF respeta el apilado solo si lo de atrás se declara primero.
           Sin `opacity` —poco confiable acá—: la atenuación viene horneada en
           `faena-sello.png`. Para aclararlo se REGENERA el PNG, no con CSS. */
        /* CENTRADO EN LA HOJA, a pedido: (612-220)/2 = 196 y (792-220)/2 = 286.
           Se mide contra la PÁGINA y no contra `.hoja` —que arranca en 34,26—
           porque el `body` es el contenedor posicionado.

           ⚠️ Ahí cae detrás del cuadro D, que es la parte más densa del papel.
           Por eso el PNG viene mezclado contra blanco al 11%: la atenuación va
           HORNEADA en `faena-sello.png`, no con `opacity`, que DomPDF no
           respeta de forma confiable. Para aclararlo se REGENERA el PNG. */
        .sello {
            position: absolute;
            top: 286pt;
            left: 196pt;
            width: 220pt;
            height: 220pt;
            z-index: -1;
        }
        .sello img { width: 220pt; height: 220pt; }

        .hoja { position: absolute; top: 26pt; left: 34pt; width: 544pt; }

        /* --- Encabezado --- */
        .entidad { text-align: center; line-height: 1.3; }
        .entidad .l1 { font-size: 8pt; font-weight: bold; }
        .entidad .l2,
        .entidad .l3 { font-size: 7.2pt; font-weight: bold; }
        .entidad .l4 { font-size: 7.5pt; font-weight: bold; padding-top: 1pt; }

        .titulo {
            font-size: 14pt;
            font-weight: bold;
            color: #2e7d32;
            white-space: nowrap;
        }

        /* El número va en rojo, como en el talonario: es lo único con otra
           tinta, para que salte a la vista al archivar. */
        .numero { color: #c62828; font-size: 12pt; font-weight: bold; letter-spacing: 1pt; }

        /* --- La grilla ---
           Un solo borde de 0.6pt por celda con `border-collapse`, o las líneas
           compartidas salen del doble de grueso que las del papel.

           ⚠️ EL RELLENO SUMA AL ANCHO, y acá se paga por columna: el cuadro D
           tiene QUINCE, así que sus 2pt de cada lado son 60pt que hay que
           descontar de los 544 de la hoja. Sin descontarlos el cuadro cerraba
           en 603 —25pt fuera del papel— y los tres cuadros de arriba igual.
           Los anchos declarados de abajo son el total MENOS relleno y bordes. */
        table.cuadro { border-collapse: collapse; }
        table.cuadro td {
            border: 0.6pt solid #2e7d32;
            padding: 1pt 2pt;
            font-size: 6.2pt;
        }

        /* El número de referencia del formulario —el 3, el 6, el 14—: va en su
           propia celdita a la izquierda del rótulo, como en el papel. */
        .ref { text-align: center; font-size: 6.2pt; }

        /* Los rótulos impresos del talonario. */
        .rot { font-weight: normal; }
        .rot-c { text-align: center; }

        /* Lo que se llena: negro y un punto más grande, para que se distinga
           de la letra verde preimpresa aunque las dos salgan en la misma
           impresora. */
        .dato {
            color: #111111;
            font-size: 7.2pt;
            height: 11pt;
        }
        .dato-c { text-align: center; }
        .dato-r { text-align: right; }

        /* La casilla de tilde del cuadro D y del casillero 10. */
        .tilde { text-align: center; color: #111111; font-size: 8pt; font-weight: bold; }

        /* --- Los rótulos girados del cuadro D ---
           DomPDF no tiene `transform` ni `writing-mode`, así que van dibujados
           como PNG con GD —ver App\Support\TextoVertical— y entran acá como
           imagen. La medida va en el `style` y NUNCA en el atributo `width`,
           que se mide en píxeles y saldría un 25% más chico. Ver CLAUDE.md. */
        .vertical { text-align: center; padding: 1pt 0; }

        .seccion {
            font-size: 7.5pt;
            font-weight: bold;
            padding: 5pt 0 1.5pt;
        }

        /* OBSERVACIONES: un recuadro solo. El alto es el mínimo para que la
           caja se vea aunque venga vacía; con texto largo crece sola. */
        .obs td { height: 26pt; }

        .fe { font-size: 6.8pt; padding-top: 6pt; }

        .firma {
            font-size: 7pt;
            text-align: center;
            border-top: 0.6pt solid #2e7d32;
            padding-top: 2pt;
        }

        /* --- El bloque de verificación ---
           El andamio lo pone `partes/qr-verificacion`; acá van color y cuerpo.
           El QR va SOBRE BLANCO: necesita su zona de silencio clara, y sobre el
           sello de agua las cámaras dejan de engancharlo. */
        .qr-bloque { text-align: center; }
        .qr-caja { background-color: #ffffff; }
        .qr-rotulo { font-size: 5pt; font-weight: bold; letter-spacing: 0.2pt; color: #1f3d13; }
    </style>
</head>

<body>

@if ($selloSedag)
    <div class="sello"><img src="{{ $selloSedag }}" alt=""></div>
@endif

<div class="hoja">

    {{-- Encabezado: escudo a la izquierda, entidad centrada, peces a la
         derecha. Igual que en el talonario. --}}
    <table width="100%" cellspacing="0" cellpadding="0">
        <tr>
            {{-- La columna mide 14 pt más que el emblema: el resto es el aire
                 que lo separa del texto de la entidad. --}}
            <td width="94" valign="top">
                @if ($escudo)
                    <img src="{{ $escudo }}" style="width: 80pt;" alt="">
                @endif
            </td>

            <td class="entidad" valign="middle">
                <div class="l1">GOBIERNO AUTÓNOMO DEPARTAMENTAL DEL BENI</div>
                <div class="l2">SECRETARIA DE DESARROLLO PRODUCTIVO Y ECONOMIA PLURAL</div>
                <div class="l3">PROGRAMA: FOMENTO A LA ACTIVIDAD PISCÍCOLA Y PESQUERA DPTO. DEL BENI</div>
                <div class="l4">SEDAG - BENI</div>
            </td>

            <td width="94" align="right" valign="top">
                @if ($peces)
                    <img src="{{ $peces }}" style="width: 80pt;" alt="">
                @endif
            </td>
        </tr>
    </table>

    <div style="text-align: center; padding-top: 4pt;">
        <span class="titulo">Guía Única de Transporte de Productos Ictícolas</span>
    </div>

    {{-- LA FILA DE ARRIBA: el cuadro de la fecha, el del recibo y el número
         rojo del talonario, los tres en una tabla para que caigan alineados
         sin márgenes calculados a mano. --}}
    <table width="100%" cellspacing="0" cellpadding="0" style="padding-top: 4pt;">
        <tr>
            <td width="240" valign="top">
                <table class="cuadro" width="240">
                    <tr>
                        <td width="14" class="ref">1</td>
                        <td width="68" class="rot rot-c">Día</td>
                        <td width="68" class="rot rot-c">Mes</td>
                        <td width="68" class="rot rot-c">Año</td>
                    </tr>
                    <tr>
                        <td class="dato"></td>
                        <td class="dato dato-c">{{ $fecha['dia'] }}</td>
                        <td class="dato dato-c">{{ $fecha['mes'] }}</td>
                        <td class="dato dato-c">{{ $fecha['anio'] }}</td>
                    </tr>
                </table>
            </td>

            <td width="164" valign="top" style="padding-left: 10pt;">
                <table class="cuadro" width="154">
                    <tr>
                        <td width="14" class="ref">2</td>
                        <td width="126" class="rot rot-c">N<sup>o</sup> Recibo</td>
                    </tr>
                    <tr>
                        <td class="dato"></td>
                        <td class="dato dato-c">{{ $recibo }}</td>
                    </tr>
                </table>
            </td>

            <td valign="middle" align="right">
                <span class="numero">N<sup>o</sup> {{ $numero }}</span>
            </td>
        </tr>
    </table>

    {{-- ══ A.- INTERESADO ══ --}}
    <div class="seccion">A.- INTERESADO</div>

    <table class="cuadro" width="544">
        <tr>
            <td width="12" class="ref">3</td>
            <td width="120" class="rot">a) Comerciante</td>
            <td width="12" class="ref">4</td>
            <td width="234" class="rot rot-c">Nombre y Apellido o Razón Social</td>
            <td width="12" class="ref">5</td>
            <td width="126" class="rot rot-c">Documento de Identidad</td>
        </tr>
        <tr>
            <td class="dato"></td>
            <td class="dato"></td>
            <td class="dato" colspan="2">{{ $comerciante }}</td>
            <td class="dato" colspan="2">{{ $documento }}</td>
        </tr>
    </table>

    {{-- ══ B.- UBICACIÓN ══ --}}
    <div class="seccion">B.- UBICACIÓN</div>

    <table class="cuadro" width="544">
        <tr>
            <td width="12" class="ref">6</td>
            {{-- «Lugar» va sobre la columna del VALOR y no sobre la del rótulo:
                 es la que encabeza en el papel. --}}
            <td width="84" class="rot"></td>
            <td width="118" class="rot rot-c">Lugar</td>
            <td width="12" class="ref">7</td>
            <td width="90" class="rot rot-c">Departamento</td>
            <td width="12" class="ref">8</td>
            <td width="84" class="rot rot-c">Provincia</td>
            <td width="12" class="ref">9</td>
            <td width="78" class="rot rot-c">Distrito o Cuenca</td>
        </tr>
        <tr>
            <td class="ref">a</td>
            <td class="rot">Producción u origen</td>
            <td class="dato">{{ $origen['lugar'] }}</td>
            <td class="dato" colspan="2">{{ $origen['departamento'] }}</td>
            <td class="dato" colspan="2">{{ $origen['provincia'] }}</td>
            <td class="dato" colspan="2">{{ $origen['distrito'] }}</td>
        </tr>
        <tr>
            <td class="ref">b</td>
            <td class="rot">Destino</td>
            <td class="dato">{{ $destino['lugar'] }}</td>
            <td class="dato" colspan="2">{{ $destino['departamento'] }}</td>
            <td class="dato" colspan="2">{{ $destino['provincia'] }}</td>
            <td class="dato" colspan="2">{{ $destino['distrito'] }}</td>
        </tr>
    </table>

    {{-- EL CASILLERO 10 — por dónde viaja. Va centrado y suelto, como en el
         papel: no pertenece ni al bloque de arriba ni al de abajo. --}}
    <div style="text-align: center; padding-top: 5pt;">
        <table class="cuadro" width="334" style="margin: 0 auto;">
            <tr>
                <td width="14" class="ref">10</td>
                <td width="12" class="ref">a</td>
                <td width="66" class="rot rot-c">Fluvial</td>
                <td width="18" class="tilde">{{ $medio === 'fluvial' ? 'X' : '' }}</td>
                <td width="12" class="ref">b</td>
                <td width="60" class="rot rot-c">Aérea</td>
                <td width="18" class="tilde">{{ $medio === 'aerea' ? 'X' : '' }}</td>
                <td width="12" class="ref">c</td>
                <td width="58" class="rot rot-c">Terrestre</td>
                <td width="18" class="tilde">{{ $medio === 'terrestre' ? 'X' : '' }}</td>
            </tr>
        </table>
    </div>

    {{-- ══ C.- TRANSPORTE ══ --}}
    <div class="seccion">C.- TRANSPORTE</div>

    <table class="cuadro" width="544">
        <tr>
            <td width="170" class="rot"></td>
            <td width="12" class="ref">11</td>
            <td width="140" class="rot rot-c">Nombre Tipo</td>
            <td width="12" class="ref">12</td>
            <td width="86" class="rot rot-c">Placa</td>
            <td width="12" class="ref">13</td>
            <td width="80" class="rot rot-c">Cap. Máxima</td>
        </tr>

        {{-- Los tres renglones van SIEMPRE, marcados o no: el papel los trae
             impresos y un control lee la fila que tiene datos. --}}
        @foreach ([
            'embarcacion' => 'a) Embarcaciones',
            'chata_absorbente' => 'b) Chata Absorbente',
            'automotriz' => 'c) Automotriz',
        ] as $clave => $rotulo)
            @php($marcado = $tipoTransporte === $clave)
            <tr>
                <td class="rot">{{ $rotulo }}</td>
                <td class="dato" colspan="2">{{ $marcado ? $transporte['nombre'] : '' }}</td>
                <td class="dato dato-c" colspan="2">{{ $marcado ? $transporte['placa'] : '' }}</td>
                <td class="dato dato-r" colspan="2">{{ $marcado ? $transporte['capacidad'] : '' }}</td>
            </tr>
        @endforeach
    </table>

    {{-- ══ D.- PRODUCTOS HIDROBIOLÓGICOS ══ --}}
    <div class="seccion">D.- PRODUCTOS HIDROBIOLÓGICOS</div>

    <table class="cuadro" width="544">
        {{-- Encabezado en dos renglones: los grupos arriba y las diez columnas
             de tilde abajo, con el texto apilado letra por letra. --}}
        <tr>
            <td width="12" rowspan="2" class="ref" valign="top">14</td>
            <td width="100" rowspan="2" class="rot rot-c" valign="top">Especie</td>

            <td colspan="2" class="rot rot-c">Fresco o Refrigerado</td>
            <td colspan="3" class="rot rot-c">Congelado</td>

            @foreach (['Seco', 'Sal Preso', 'Vivos', 'A Granel', 'Otros'] as $suelta)
                <td width="16" rowspan="2" class="vertical" valign="bottom">
                    <img src="{{ $rotulos[$suelta]['src'] }}"
                         style="width: {{ $rotulos[$suelta]['ancho'] }}pt; height: {{ $rotulos[$suelta]['alto'] }}pt;"
                         alt="{{ $suelta }}">
                </td>
            @endforeach

            <td width="70" rowspan="2" class="rot rot-c" valign="middle">CANT. ADQUIRIDA<br>EN Kg.<br>Descarga o trasbordo</td>
            <td width="66" rowspan="2" class="rot rot-c" valign="middle">PRECIO<br>Pagado Kg.<br>en lugar de origen</td>
            <td width="66" rowspan="2" class="rot rot-c" valign="middle">IMP. TOTAL<br>Pagado</td>
        </tr>

        <tr>
            @foreach (['Entero', 'Eviscerado', 'Entero', 'Eviscerado', 'Fileteado'] as $sub)
                <td width="16" class="vertical" valign="bottom">
                    <img src="{{ $rotulos[$sub]['src'] }}"
                         style="width: {{ $rotulos[$sub]['ancho'] }}pt; height: {{ $rotulos[$sub]['alto'] }}pt;"
                         alt="{{ $sub }}">
                </td>
            @endforeach
        </tr>

        {{-- Los renglones. El controlador ya los completó hasta cinco: el
             cuadro impreso mide siempre lo mismo. --}}
        @foreach ($renglones as $i => $fila)
            <tr>
                <td class="ref">{{ $i + 1 }}</td>
                <td class="dato">{{ $fila['especie'] }}</td>

                @foreach ($columnas as $columna)
                    <td class="tilde">{{ $fila['condicion'] === $columna->value ? 'X' : '' }}</td>
                @endforeach

                <td class="dato dato-r">{{ $fila['cantidad'] }}</td>
                <td class="dato dato-r">{{ $fila['precio'] }}</td>
                <td class="dato dato-r">{{ $fila['importe'] }}</td>
            </tr>
        @endforeach

        {{-- LOS TOTALES. Los kilos salen de `peso_total_kg`, que es la suma ya
             guardada del detalle; el importe se suma al imprimir porque es
             dato declarativo y no se usa en ningún otro lado. --}}
        <tr>
            <td colspan="12" class="rot" style="text-align: right; padding-right: 4pt;">TOTALES</td>
            <td class="dato dato-r" style="font-weight: bold;">{{ $totales['kilos'] }}</td>
            <td></td>
            <td class="dato dato-r" style="font-weight: bold;">{{ $totales['importe'] }}</td>
        </tr>
    </table>

    {{-- ══ OBSERVACIONES ══ --}}
    <div class="seccion">OBSERVACIONES</div>

    {{-- UN SOLO RECUADRO, no los tres renglones del talonario preimpreso —a
         pedido—. El `height` es un MÍNIMO: si la observación es larga, DomPDF
         agranda la celda y el texto se acomoda en varias líneas adentro de la
         misma caja, en vez de cortarse. --}}
    <table class="cuadro obs" width="544">
        <tr><td class="dato">{{ $observaciones }}</td></tr>
    </table>

    {{-- EL QR VA ARRIBA Y A LA DERECHA, a pedido: al lado del renglón de los
         firmantes y no en el pie, así queda a la vista sin tener que llegar al
         final de la hoja. Va SIN el código escrito —también a pedido—, igual
         que el recibo y la autorización.

         ⚠️ Sin el código, este papel SOLO se verifica escaneando: con la hoja
         gastada o poca luz no queda alternativa. El carnet y el permiso de
         faena sí lo siguen llevando. --}}
    <table width="100%" cellspacing="0" cellpadding="0">
        <tr>
            <td valign="top">
                <div class="fe">Los firmantes dan fe de los datos de su competencia y se responsabilizan de los mismos</div>
            </td>

            {{-- 66 pt y no 52: el QR de esta guía sale de una URL larga, así
                 que su matriz es de 37 módulos y con margen son 45. A 52 pt
                 cada módulo medía 0,41 mm —al borde de lo que engancha una
                 cámara, y con los bordes irregulares que deja reescalar 45
                 módulos a una grilla que no los divide—. A 66 son 0,52 mm,
                 en línea con los otros cuatro documentos. --}}
            {{-- El relleno de arriba es lo que lo sube o lo baja. El tope es
                 0: ahí queda a 0,3 pt del recuadro de observaciones, pegado.
                 Lo que se le saque acá hay que sumárselo al pie, o las firmas
                 se mueven con él. --}}
            <td width="80" align="right" valign="top" style="padding-top: 12pt;">
                @include('documentos.partes.qr-verificacion', [
                    'verificacion' => $verificacion,
                    'lado' => 66,
                    'vertical' => true,
                ])
            </td>
        </tr>
    </table>

    {{-- EL PIE: las dos firmas del papel, bien separadas del QR — a pedido.
         El relleno es lo único que las baja: son la última fila de la hoja, así
         que lo que se le sume acá sale del margen de abajo y de ningún lado
         más. Lo que se le saca de relleno al QR se le suma acá: si no, subir
         el QR se llevaba las firmas con él. --}}
    <table width="100%" cellspacing="0" cellpadding="0" style="padding-top: 52pt;">
        <tr>
            <td width="200" valign="bottom">
                <div class="firma">Firma Cliente</div>
            </td>

            <td></td>

            <td width="200" valign="bottom">
                <div class="firma">Sello Firma Encargado</div>
            </td>
        </tr>
    </table>


</div>

</body>
</html>
