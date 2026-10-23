<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $titulo }}</title>
    {{--
        El carnet, CR80 apaisado (243 × 153 pt), en dos carillas. Diseño del
        23/10/2026, a partir de la credencial de la Gobernación: frente blanco con
        banda verde y foto redonda; dorso verde con el reglamento y la firma.
        DomPDF no tiene flexbox, grid ni transform: todo va con coordenadas, y el
        alto de cada renglón DECLARADO (line-height no manda en DomPDF). Las
        medidas de los textos las calcula CarnetImpresionController::datos().
    --}}
    <style>
        @page { margin: 0; }

        body {
            margin: 0;
            font-family: 'DejaVu Sans', sans-serif;
            color: #1f2a24;
        }

        .carilla {
            position: relative;
            width: 243pt;
            height: 153pt;
            overflow: hidden;
        }

        .dorso { page-break-before: always; }

        .abs { position: absolute; }

        .verde { color: #0c6b32; }
        .gris { color: #5f6b64; }

        /* ---------------------------------------------------------- Frente */

        .banda { left: 0; top: 0; width: 22pt; height: 153pt; background: #0c6b32; }
        .filete { left: 22pt; top: 0; width: 1.6pt; height: 153pt; background: #c9a84c; }

        .sedag { left: 150pt; top: 8pt; width: 87pt; text-align: right; }
        .sedag div { height: 4.6pt; font-size: 3.6pt; }
        .sedag .fuerte { height: 8pt; font-size: 7pt; font-weight: bold; color: #0c6b32; }

        .raya { left: 28pt; top: 36pt; width: 209pt; height: 0.8pt; background: #0c6b32; }

        .titulo { left: 98pt; top: 40pt; width: 139pt; height: 9pt; font-size: 7.4pt; font-weight: bold; color: #0c6b32; letter-spacing: 0.3pt; }

        .nombre { left: 98pt; width: 139pt; font-weight: bold; overflow: hidden; }

        .rotulo { height: 4.2pt; font-size: 3.6pt; font-weight: bold; color: #5f6b64; letter-spacing: 0.3pt; }
        .valor { font-weight: bold; overflow: hidden; }
        .vacio { color: #9aa39e; font-weight: normal; font-style: italic; }

        .cedula { left: 28pt; top: 102pt; width: 64pt; height: 8pt; text-align: center; font-weight: bold; }

        .franja { left: 98pt; top: 124pt; width: 139pt; height: 23pt; background: #e7f2ea; border-radius: 3pt; }
        .franja table { width: 100%; border-collapse: collapse; }
        .franja td { padding: 3.5pt 4pt 0 4pt; vertical-align: top; }
        .franja .r { height: 4.6pt; font-size: 3.4pt; font-weight: bold; color: #5f6b64; }
        .franja .v { height: 8pt; font-size: 5.8pt; font-weight: bold; color: #0c6b32; }

        /* ---------------------------------------------------------- Dorso */

        .fondo-verde { left: 0; top: 0; width: 243pt; height: 153pt; background: #0c6b32; }
        .blanco { color: #ffffff; }
        .reverso-titulo { left: 14pt; top: 37pt; width: 215pt; text-align: center; }
        .reverso-titulo div { height: 6.6pt; font-size: 5.2pt; font-weight: bold; }
        .reglas { left: 18pt; top: 53pt; width: 207pt; }
        .reglas div { height: 6.3pt; font-size: 4.4pt; }
        .tarjeta { left: 10pt; top: 102pt; width: 223pt; height: 45pt; background: #ffffff; border-radius: 6pt; }
        .codigo { font-family: 'DejaVu Sans Mono', monospace; font-weight: bold; font-size: 5.4pt; color: #0c6b32; height: 7pt; }
    </style>
</head>
<body>

{{-- ================================================================ FRENTE --}}
<div class="carilla">
    <div class="abs banda"></div>
    <div class="abs filete"></div>
    {{-- «GOBERNACIÓN» girada: imagen hecha con GD (DomPDF no rota texto). Medida en style, no en width. --}}
    <img class="abs" src="{{ $banda['imagen'] }}" alt=""
         style="left: {{ (22 - $banda['medida'][0]) / 2 }}pt; top: {{ (153 - $banda['medida'][1]) / 2 }}pt; width: {{ $banda['medida'][0] }}pt; height: {{ $banda['medida'][1] }}pt;">

    {{-- El encabezado de la Gobernación: escudo, línea y el texto del logo. --}}
    @if ($escudo !== '')
        <img class="abs" src="{{ $escudo }}" alt="" style="left: 28pt; top: 5pt; width: 23.5pt; height: 26.8pt;">
    @endif
    <div class="abs" style="left: 54.5pt; top: 7pt; width: 0.7pt; height: 23pt; background: #0c6b32;"></div>
    @if ($beni !== '')
        <img class="abs" src="{{ $beni }}" alt="" style="left: 58pt; top: 8pt; width: 38.5pt; height: 22pt;">
    @endif

    <div class="abs sedag">
        <div class="gris">SERVICIO DEPARTAMENTAL</div>
        <div class="gris">AGROPECUARIO</div>
        <div class="fuerte">SEDAG - BENI</div>
        <div class="gris">Unidad de Pesca</div>
    </div>

    <div class="abs raya"></div>

    {{-- La foto redonda (recortada en GD) sobre dos anillos: verde y blanco. --}}
    <div class="abs" style="left: 31pt; top: 41pt; width: 58pt; height: 58pt; border-radius: 29pt; background: #0c6b32;"></div>
    <div class="abs" style="left: 32.5pt; top: 42.5pt; width: 55pt; height: 55pt; border-radius: 27.5pt; background: #ffffff;"></div>
    @if ($foto !== '')
        <img class="abs" src="{{ $foto }}" alt="" style="left: 34pt; top: 44pt; width: 52pt; height: 52pt;">
    @endif

    <div class="abs cedula" style="font-size: {{ $documento['cuerpo'] }}pt;">
        {{ $documento['valor'] !== '' ? $documento['valor'] : $documento['molde'] }}
    </div>

    @if ($qr !== '')
        <img class="abs" src="{{ $qr }}" alt="QR de verificación" style="left: 42pt; top: 111pt; width: 36pt; height: 36pt;">
    @endif

    <div class="abs titulo">{{ $titulo }}</div>

    {{-- El nombre manda. Si no entra en una línea pasa a dos, con el alto que eso pide. --}}
    @php
        // Altos MEDIDOS en el PDF: cada línea avanza ~1,53 veces su cuerpo. Con menos, la última se corta.
        $altoNombre = $nombre['lineas'] > 1 ? 18 : 12;
        $y = 49 + $altoNombre + 1.5;
        $altoDe = fn (array $t) => $t['lineas'] > 1 ? 13 : 8.5;
        $mitadAlta = max($altoDe($ciudad), $altoDe($provincia));
    @endphp
    <div class="abs nombre" style="top: 49pt; height: {{ $altoNombre }}pt; font-size: {{ $nombre['cuerpo'] }}pt;">
        {{ $nombre['valor'] !== '' ? $nombre['valor'] : $nombre['molde'] }}
    </div>

    @foreach ([['ASOCIACIÓN', $asociacion, 98, 139, $altoDe($asociacion)], null, ['DIRECCIÓN', $direccion, 98, 139, $altoDe($direccion)]] as $fila)
        @if ($fila === null)
            {{-- Ciudad y provincia comparten renglón: van cortas. --}}
            <div class="abs" style="left: 98pt; top: {{ $y }}pt; width: 66pt;">
                <div class="rotulo">CIUDAD</div>
                <div class="valor {{ $ciudad['valor'] === '' ? 'vacio' : '' }}" style="height: {{ $mitadAlta }}pt; font-size: {{ $ciudad['cuerpo'] }}pt;">
                    {{ $ciudad['valor'] !== '' ? $ciudad['valor'] : $ciudad['molde'] }}
                </div>
            </div>
            <div class="abs" style="left: 171pt; top: {{ $y }}pt; width: 66pt;">
                <div class="rotulo">PROVINCIA</div>
                <div class="valor {{ $provincia['valor'] === '' ? 'vacio' : '' }}" style="height: {{ $mitadAlta }}pt; font-size: {{ $provincia['cuerpo'] }}pt;">
                    {{ $provincia['valor'] !== '' ? $provincia['valor'] : $provincia['molde'] }}
                </div>
            </div>
            @php($y += 4.2 + $mitadAlta + 1)
        @else
            @php([$rotulo, $t, $x, $ancho, $alto] = $fila)
            <div class="abs" style="left: {{ $x }}pt; top: {{ $y }}pt; width: {{ $ancho }}pt;">
                <div class="rotulo">{{ $rotulo }}</div>
                <div class="valor {{ $t['valor'] === '' ? 'vacio' : '' }}" style="height: {{ $alto }}pt; font-size: {{ $t['cuerpo'] }}pt;">
                    {{ $t['valor'] !== '' ? $t['valor'] : $t['molde'] }}
                </div>
            </div>
            @php($y += 4.2 + $alto + 1)
        @endif
    @endforeach

    {{-- Lo que no cambia después de impreso: registro, cupo (si lleva) y vigencia. --}}
    <div class="abs franja">
        <table>
            <tr>
                @foreach ($franja as $celda)
                    <td>
                        <div class="r">{{ $celda['rotulo'] }}</div>
                        <div class="v">{{ $celda['valor'] }}</div>
                    </td>
                @endforeach
            </tr>
        </table>
    </div>
</div>

{{-- ================================================================ DORSO --}}
<div class="carilla dorso">
    <div class="abs fondo-verde"></div>

    @if ($escudo !== '')
        <img class="abs" src="{{ $escudo }}" alt="" style="left: 14pt; top: 6pt; width: 21pt; height: 24pt;">
    @endif
    <div class="abs" style="left: 38.5pt; top: 8pt; width: 0.7pt; height: 20pt; background: #ffffff;"></div>
    @if ($beniBlanco !== '')
        <img class="abs" src="{{ $beniBlanco }}" alt="" style="left: 42pt; top: 8.5pt; width: 33.6pt; height: 19.2pt;">
    @endif
    <div class="abs blanco" style="left: 120pt; top: 11pt; width: 109pt; text-align: right;">
        <div style="height: 8pt; font-size: 7pt; font-weight: bold;">SEDAG - BENI</div>
        <div style="height: 5pt; font-size: 3.8pt;">Unidad de Pesca</div>
    </div>
    <div class="abs" style="left: 14pt; top: 33pt; width: 215pt; height: 0.8pt; background: #c9a84c;"></div>

    <div class="abs reverso-titulo blanco">
        @foreach ($reverso['titulo'] as $linea)
            <div>{{ $linea }}</div>
        @endforeach
    </div>

    <div class="abs reglas blanco">
        @foreach ($reverso['reglas'] as $i => $regla)
            <div>{{ $i + 1 }}. {{ $regla }}</div>
        @endforeach
    </div>

    {{-- La tarjeta blanca: QR y código a un lado; la firma, a mano sobre el plástico, al otro. --}}
    <div class="abs tarjeta"></div>
    @if ($reverso['qr'] !== '')
        <img class="abs" src="{{ $reverso['qr'] }}" alt="QR de verificación" style="left: 15pt; top: 106pt; width: 37pt; height: 37pt;">
    @endif
    <div class="abs" style="left: 56pt; top: 112pt; width: 80pt;">
        <div class="gris" style="height: 4.6pt; font-size: 3.6pt; font-weight: bold;">CÓDIGO DEL CARNET</div>
        <div class="codigo">{{ $reverso['codigo'] }}</div>
        <div class="gris" style="height: 9pt; font-size: 3.6pt;">Escanee el QR o escriba el código en la página de verificación.</div>
    </div>

    <div class="abs" style="left: 142pt; top: 130pt; width: 84pt; height: 0.5pt; background: #5f6b64;"></div>
    <div class="abs" style="left: 140pt; top: 131.5pt; width: 88pt; text-align: center;">
        @if ($reverso['firmante']['nombre'] !== '')
            <div style="height: 5.2pt; font-size: 4.2pt; font-weight: bold;">{{ $reverso['firmante']['nombre'] }}</div>
        @endif
        @if ($reverso['firmante']['cargo'] !== '')
            <div class="gris" style="height: 9pt; font-size: 3.5pt;">{{ $reverso['firmante']['cargo'] }}</div>
        @endif
    </div>
</div>

@include('documentos.partes.marca-no-valido', ['ancho' => 243, 'alto' => 153, 'cubrir' => true])
</body>
</html>
