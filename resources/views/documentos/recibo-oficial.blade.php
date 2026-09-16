{{--
================================================================================
  RECIBO OFICIAL — calco del talonario verde del SEDAG
================================================================================

  Reproduce el formulario impreso renglón por renglón: el mismo encabezado, los
  mismos campos, las mismas seis casillas de DESCRIPCIÓN, las dos firmas, el pie
  de tres copias y la nota legal. Quien recibe el papel está acostumbrado a ese
  formato; una versión «mejorada» se lee como si fuera otro documento.

  --------------------------------------------------------------------------
  POR QUÉ TODO ESTÁ POSICIONADO EN ABSOLUTO
  --------------------------------------------------------------------------

  Porque esto no es una página web que se acomoda al ancho del que mira: es un
  papel de medida fija que tiene que salir SIEMPRE igual, y cada bloque va donde
  está en el talonario. Con el flujo normal del documento, un nombre más largo
  que otro corre todo lo que viene abajo y dos recibos salen distintos.

  Además lo dibuja DomPDF, que no es un navegador: no entiende flexbox ni grid
  ni variables CSS. Lo que sí entiende bien es `position: absolute`, tablas y
  bordes. Queda anticuado en el código y exacto en el papel, que es lo que se
  busca acá.

  --------------------------------------------------------------------------
  EL SISTEMA DE COORDENADAS
  --------------------------------------------------------------------------

  La hoja es de 612 x 396 puntos —media carta apaisada, el tamaño del
  talonario— y el marco vive a 10pt de cada borde, o sea 592 x 376 útiles.
  Todos los `top` y `left` de abajo son puntos DENTRO de ese marco.

  El tamaño del papel NO está acá sino en ReciboController: es una decisión de
  impresión, no de diseño. Si la unidad manda a hacer el talonario en otro
  formato se cambia un número allá — pero entonces estas coordenadas hay que
  revisarlas, porque están calculadas para estos 592 x 376.
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Recibo Oficial {{ $recibo->numeroImpreso() }}</title>

    <style>
        * { margin: 0; padding: 0; }

        @page { margin: 0; }

        /*
         * DejaVu Sans es la única tipografía que DomPDF trae con los acentos y
         * la «ñ» completos. Con Helvetica, «PISCÍCOLA» sale partida.
         */
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 6.5pt;
            color: #1b5e20;
            width: 612pt;
            height: 396pt;
            position: relative;
        }

        /* ---------------------------------------------------------------
           EL SELLO DE AGUA

           Va declarado ANTES que el marco y con z-index negativo. DomPDF
           respeta el apilado solo si lo de atrás se declara primero;
           invertirlo tapa el recibo con el sello.

           NO LLEVA `opacity`: el archivo `recibo-sello.png` ya viene atenuado.
           Es a propósito — `opacity` es de lo menos confiable que tiene DomPDF y
           cuando lo ignora el sello sale a pleno color tapando el texto. Ver el
           comentario de ReciboController::pdf().

           Centrado sobre el marco: (592-190)/2 = 201, más los 10pt del
           margen = 211. Lo mismo en vertical.
        --------------------------------------------------------------- */
        .sello {
            position: absolute;
            top: 103pt;
            left: 211pt;
            width: 190pt;
            height: 190pt;
            z-index: -1;
        }
        .sello img { width: 190pt; height: 190pt; }

        /* --- El recuadro del formulario ------------------------------- */
        .marco {
            position: absolute;
            top: 10pt;
            left: 10pt;
            width: 592pt;
            height: 376pt;
            border: 1pt solid #2e7d32;
        }

        /* Cada bloque se planta en su coordenada. Ver el encabezado. */
        .bloque { position: absolute; }

        /* --- Encabezado ------------------------------------------------ */
        .entidad { text-align: center; line-height: 1.3; }
        .entidad .l1 { font-size: 7.5pt; font-weight: bold; }
        .entidad .l2 { font-size: 7pt; font-weight: bold; }
        .entidad .l3 { font-size: 7pt; font-weight: bold; }
        .entidad .l4 { font-size: 7pt; font-weight: bold; padding-top: 1pt; }

        .titulo {
            font-size: 17pt;
            font-weight: bold;
            color: #2e7d32;
            letter-spacing: 0.4pt;
        }

        /*
         * El número va en rojo, como en el talonario: es lo único que el papel
         * imprime con otra tinta, justamente para que salte a la vista al
         * archivar.
         */
        .rotulo-numero { color: #c62828; font-size: 10pt; font-weight: bold; }
        .caja-numero {
            border: 0.8pt solid #444;
            padding: 3pt 10pt;
            font-size: 12pt;
            font-weight: bold;
            color: #c62828;
            text-align: center;
        }

        /* --- Renglones -------------------------------------------------- */
        .rotulo { font-size: 7pt; white-space: nowrap; }

        /*
         * El renglón punteado del formulario. Es un borde inferior y no una
         * fila de puntos escritos: así el dato se apoya ENCIMA de la línea,
         * como queda cuando se rellena a mano.
         */
        .linea {
            border-bottom: 0.6pt dotted #2e7d32;
            padding: 0 4pt 1.5pt;
            color: #111;
            font-size: 7.5pt;
        }
        .linea.chica { font-size: 6.5pt; }

        /* --- Casilleros DIA | MES | AÑO --------------------------------- */
        .cab-fecha {
            font-size: 5pt;
            text-align: center;
            border: 0.6pt solid #2e7d32;
            border-bottom: none;
            padding: 1pt 0 0.5pt;
        }
        .celda-fecha {
            border: 0.6pt solid #2e7d32;
            text-align: center;
            font-size: 8pt;
            font-weight: bold;
            color: #111;
            padding: 2.5pt 0;
        }

        /* --- Casillas de forma de pago ---------------------------------- */
        .casilla {
            border: 0.7pt solid #2e7d32;
            width: 15pt;
            height: 12pt;
            text-align: center;
            font-size: 9pt;
            font-weight: bold;
            color: #c62828;
            line-height: 12pt;
        }

        /* --- Cuadrícula de importes ------------------------------------- */
        .importe-caja { border: 1pt solid #2e7d32; }
        .importe-cab {
            font-size: 6.5pt;
            font-weight: bold;
            text-align: center;
            border-bottom: 0.7pt solid #2e7d32;
            padding: 3pt 0;
        }
        /*
         * Las celdas del importe. El monto va alineado a la DERECHA contra la
         * línea que separa los centavos, que es como se lee una cifra de dinero:
         * centrado, el ojo no encuentra dónde termina.
         */
        .celda-importe {
            height: 15pt;
            font-size: 9.5pt;
            font-weight: bold;
            color: #111;
            padding: 0 4pt;
        }
        /*
         * La descripción del cobro va más chica y sin negrita: es la referencia
         * —«Depósito 6CF39608ECB2»— y no tiene que competir con la cifra, que es
         * lo que el ojo busca en este cuadro.
         */
        .celda-importe.concepto {
            font-size: 6pt;
            font-weight: normal;
            color: #1b5e20;
        }
        /*
         * El monto va alineado a la DERECHA, que es como se leen las cifras de
         * dinero en columna: las unidades quedan una debajo de otra y se pueden
         * sumar de un vistazo. Centrado, cada renglón arranca en un lugar
         * distinto y la columna deja de leerse.
         */
        .celda-importe.monto {
            border-left: 0.6pt solid #2e7d32;
            text-align: right;
            padding-right: 8pt;
        }
        .fila-total .celda-importe,
        .fila-total .rotulo-total { border-top: 0.7pt solid #2e7d32; }
        .rotulo-total {
            font-size: 7.5pt;
            font-weight: bold;
            text-align: right;
            padding-right: 5pt;
            height: 16pt;
        }

        /* --- Descripción ------------------------------------------------- */
        .desc-titulo { font-size: 7pt; font-weight: bold; text-decoration: underline; }
        .desc-item { font-size: 6.8pt; line-height: 1.62; }
        /*
         * El hueco del paréntesis cuando la casilla NO va marcada. Se dibuja con
         * un ancho fijo y no con un espacio duro escrito como entidad HTML:
         * Blade escapa lo que sale por su interpolación de dos llaves, y en el
         * recibo se imprimiría el texto literal «(&nbsp;)».
         */
        .hueco { display: inline-block; width: 5pt; }
        /* La cruz de la casilla marcada, en rojo como la pone el cajero. */
        .cruz { color: #c62828; font-weight: bold; }

        /* --- Firmas ------------------------------------------------------ */
        .firma {
            border-top: 0.7pt solid #333;
            text-align: center;
            font-size: 6.5pt;
            padding-top: 2pt;
        }

        /* --- Pie ---------------------------------------------------------- */
        .copias { font-size: 6pt; }
        .nota { font-size: 5.2pt; line-height: 1.4; text-align: justify; }
    </style>
</head>

<body>

{{-- El sello del SEDAG, atenuado. Declarado antes que el marco por el z-index. --}}
@if ($selloSedag)
    <div class="sello"><img src="{{ $selloSedag }}" alt=""></div>
@endif

<div class="marco">

    {{-- ==============================================================
         ENCABEZADO — escudo a la izquierda, entidad centrada
         ============================================================== --}}
    @if ($escudo)
        <div class="bloque" style="top: 6pt; left: 8pt; width: 54pt;">
            <img src="{{ $escudo }}" style="width: 50pt;" alt="">
        </div>
    @endif

    <div class="bloque entidad" style="top: 8pt; left: 62pt; width: 468pt;">
        <div class="l1">GOBIERNO AUTÓNOMO DEPARTAMENTAL DEL BENI</div>
        <div class="l2">SECRETARÍA DE DESARROLLO PRODUCTIVO Y ECONOMÍA PLURAL</div>
        <div class="l3">PROGRAMA: FOMENTO A LA ACTIVIDAD PISCÍCOLA Y PESQUERA DPTO. DEL BENI</div>
        <div class="l4">SEDAG - BENI</div>
    </div>

    {{-- ==============================================================
         TÍTULO + NÚMERO
         ============================================================== --}}
    <div class="bloque" style="top: 60pt; left: 14pt;">
        <table cellspacing="0" cellpadding="0">
            <tr>
                <td class="titulo" valign="middle">RECIBO OFICIAL</td>
                <td class="rotulo-numero" valign="middle" style="padding-left: 14pt;">N°:</td>
                <td valign="middle" style="padding-left: 4pt;">
                    <div class="caja-numero">{{ $recibo->numeroImpreso() }}</div>
                </td>
            </tr>
        </table>
    </div>

    {{-- ==============================================================
         COLUMNA IZQUIERDA — los renglones que se rellenan
         ============================================================== --}}

    {{-- Lugar y Fecha + casilleros DIA | MES | AÑO --}}
    <div class="bloque" style="top: 98pt; left: 14pt; width: 348pt;">
        <table width="100%" cellspacing="0" cellpadding="0">
            <tr>
                <td class="rotulo" valign="bottom" width="62">Lugar y Fecha:</td>
                <td class="linea" valign="bottom">{{ $recibo->lugar }}</td>
                <td width="10"></td>
                <td width="114" valign="bottom">
                    <table width="100%" cellspacing="0" cellpadding="0">
                        <tr>
                            <td class="cab-fecha" width="33%">DIA</td>
                            <td class="cab-fecha" width="33%">MES</td>
                            <td class="cab-fecha" width="34%">AÑO</td>
                        </tr>
                        <tr>
                            <td class="celda-fecha">{{ $fecha['dia'] }}</td>
                            <td class="celda-fecha">{{ $fecha['mes'] }}</td>
                            <td class="celda-fecha">{{ $fecha['anio'] }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </div>

    {{-- Nombre y Apellido --}}
    <div class="bloque" style="top: 132pt; left: 14pt; width: 348pt;">
        <table width="100%" cellspacing="0" cellpadding="0">
            <tr>
                <td class="rotulo" valign="bottom" width="84">Nombre y Apellido:</td>
                <td class="linea" valign="bottom">{{ $recibo->beneficiario_nombre }}</td>
            </tr>
        </table>
    </div>

    {{-- La suma de ... -00/100
         El «-00/100» del talonario es la forma clásica de cerrar un importe
         escrito a mano para que nadie le agregue centavos después. Acá ya viene
         dentro del texto en letras. Ver Recibo::montoEnLetras(). --}}
    <div class="bloque" style="top: 154pt; left: 14pt; width: 348pt;">
        <table width="100%" cellspacing="0" cellpadding="0">
            <tr>
                <td class="rotulo" valign="bottom" width="50">La suma de:</td>
                <td class="linea chica" valign="bottom">{{ $recibo->montoEnLetras() }}</td>
            </tr>
        </table>
    </div>

    {{-- Concepto --}}
    <div class="bloque" style="top: 176pt; left: 14pt; width: 348pt;">
        <table width="100%" cellspacing="0" cellpadding="0">
            <tr>
                <td class="rotulo" valign="bottom" width="42">Concepto:</td>
                <td class="linea" valign="bottom">{{ $recibo->concepto }}</td>
            </tr>
        </table>
    </div>

    {{-- Depósito Bancario / Efectivo / N° --}}
    <div class="bloque" style="top: 198pt; left: 14pt; width: 348pt;">
        <table width="100%" cellspacing="0" cellpadding="0">
            <tr>
                <td class="rotulo" valign="middle" width="72">Depósito Bancario:</td>
                <td valign="middle" width="19">
                    <div class="casilla">{{ $esDeposito ? 'X' : '' }}</div>
                </td>
                <td class="rotulo" valign="middle" width="42" style="padding-left: 5pt;">Efectivo</td>
                <td valign="middle" width="19">
                    <div class="casilla">{{ $esDeposito ? '' : 'X' }}</div>
                </td>
                <td class="rotulo" valign="bottom" width="18" style="padding-left: 5pt;">N°:</td>
                <td class="linea chica" valign="bottom">{{ $recibo->nro_deposito }}</td>
            </tr>
        </table>
    </div>

    {{-- ==============================================================
         DESCRIPCIÓN — las seis casillas del talonario

         Se dibujan SIEMPRE las seis, aunque el sistema solo sepa cobrar dos:
         el recibo tiene que salir igual al papel. Cuál queda marcada lo
         decide App\Enums\ConceptoRecibo.
         ============================================================== --}}
    <div class="bloque" style="top: 226pt; left: 14pt; width: 330pt;">
        <div class="desc-titulo" style="padding-bottom: 2pt;">DESCRIPCIÓN:</div>

        @foreach ($casillas as $casilla)
            <div class="desc-item">
                (@if ($recibo->marca($casilla))<span class="cruz">X</span>@else<span class="hueco"></span>@endif)
                {{ $casilla->etiqueta() }}
            </div>
        @endforeach
    </div>

    {{-- ==============================================================
         COLUMNA DERECHA — IMPORTE A PAGAR
         ============================================================== --}}
    <div class="bloque" style="top: 60pt; left: 378pt; width: 200pt;">
        {{-- ==========================================================
             EL CUADRO DE IMPORTES — UNO SOLO, QUE CRECE HACIA ABAJO
             ==========================================================

             Un renglón por cobro, en el orden en que entraron, y el TOTAL al
             pie. Con dos depósitos el cuadro se estira; con uno solo se
             completa con renglones en blanco para conservar el alto del
             talonario. Ver ReciboController::RENGLONES_MINIMOS.

             DOS COLUMNAS: la descripción del cobro y el MONTO, entero en una
             sola celda. La fila del pie repite esa división: TOTAL a la
             izquierda, la cifra a la derecha.

             LA CIFRA NO SE PARTE. Se probaron las dos formas anteriores y las
             dos se leían mal: un dígito por casillero —«1|2|0|00»— parecía
             12000, y separar bolivianos de centavos —«120|00»— obligaba al ojo
             a juntar dos cifras para entender una. Ver
             ReciboController::importeFormateado().
             ========================================================== --}}
        <table width="100%" cellspacing="0" cellpadding="0" class="importe-caja">
            <tr>
                <td class="importe-cab" colspan="2">IMPORTE A PAGAR Bs.</td>
            </tr>

            @foreach ($renglones as $renglon)
                <tr>
                    <td class="celda-importe concepto" width="58%">{{ $renglon['descripcion'] }}</td>
                    <td class="celda-importe monto" width="42%">{{ $renglon['monto'] }}</td>
                </tr>
            @endforeach

            {{-- Los renglones vacíos que el talonario deja libres. --}}
            @for ($i = 0; $i < $blancos; $i++)
                <tr>
                    <td class="celda-importe concepto">&nbsp;</td>
                    <td class="celda-importe monto"></td>
                </tr>
            @endfor

            <tr class="fila-total">
                <td class="rotulo-total">TOTAL</td>
                <td class="celda-importe monto">{{ $total }}</td>
            </tr>
        </table>
    </div>

    {{-- Firmas. Van a esta altura porque es donde están en el talonario: a la
         derecha, más o menos al nivel del final de la lista de DESCRIPCIÓN. --}}
    <div class="bloque" style="top: 268pt; left: 378pt; width: 200pt;">
        <table width="100%" cellspacing="0" cellpadding="0">
            <tr>
                <td class="firma" width="46%">Firma Cliente</td>
                <td width="8%"></td>
                <td class="firma" width="46%">Enc. Emisión de Guías</td>
            </tr>
        </table>
    </div>

    <div class="bloque" style="top: 296pt; left: 378pt; width: 120pt;">
        <table width="100%" cellspacing="0" cellpadding="0">
            <tr>
                <td class="rotulo" valign="bottom" width="24">C.I.:</td>
                <td class="linea chica" valign="bottom">{{ $recibo->beneficiario_ci }}</td>
            </tr>
        </table>
    </div>

    {{-- ==============================================================
         PIE — las tres copias y la nota legal

         El talonario de papel es autocopiativo y cada hoja dice a quién le
         toca. El PDF sale de a una, pero la leyenda se conserva: es lo que
         Contabilidad y Archivo buscan cuando reciben su copia impresa.
         ============================================================== --}}
    <div class="bloque" style="top: 330pt; left: 14pt; width: 564pt;">
        <table width="100%" cellspacing="0" cellpadding="0">
            <tr>
                <td class="copias" width="33%">Original: Cliente</td>
                <td class="copias" width="34%" style="text-align: center;">Copia Amarilla: Contabilidad</td>
                <td class="copias" width="33%" style="text-align: right;">Copia Verde: Archivo</td>
            </tr>
        </table>
    </div>

    <div class="bloque nota" style="top: 346pt; left: 14pt; width: 564pt;">
        <strong>NOTA:</strong> Este Comprobante de pago carece de valor si no tiene firma y sello
        autorizados, el cual deberá ser exhibido a las autoridades de origen y destino.
        Cualquier alteración o enmienda será sancionado conforme a ley.
    </div>

</div>

</body>
</html>
