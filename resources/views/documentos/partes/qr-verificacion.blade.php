{{--
  EL BLOQUE DE VERIFICACIÓN DE UN DOCUMENTO IMPRESO

  Por defecto van el QR Y el código escrito: el QR es lo rápido y el código es
  lo que funciona con un papel gastado, poca luz o un teléfono viejo. La
  pantalla de /verificar acepta las dos entradas.

  El código se puede apagar con `codigo => false` —así están el recibo y la
  autorización, a pedido—, y ahí el documento SOLO se verifica escaneando.

  @param array|null $verificacion  Lo que devuelve App\Support\QrVerificacion::de()
  @param int        $lado          Lado del QR EN PUNTOS. Ver la nota de abajo.
  @param bool       $vertical      Código DEBAJO del QR, para una columna angosta.
  @param bool       $pie           El renglón de instrucción. Se apaga donde no entra.
  @param bool       $codigo        El código escrito. Apagarlo deja SOLO el QR.

  ⚠️ EL LADO VA EN `style`, NO EN EL ATRIBUTO `width`: el atributo se mide en
  PÍXELES, así que un `width="44"` salía de 33 pt —un 25% más chico— y el módulo
  del QR quedaba en 0,31 mm, al borde de lo que una cámara engancha.

  El andamio lo pone este archivo; el color y el cuerpo los pone la hoja de
  estilos de quien lo incluye, con .qr-caja, .qr-rotulo, .qr-codigo y .qr-pie.
--}}
@if (($verificacion['qr'] ?? '') !== '')
    @php($lado = $lado ?? 56)
    @php($medidas = "width: {$lado}pt; height: {$lado}pt;")

    @if ($vertical ?? false)
        {{-- Apilado, para una columna angosta. --}}
        <div class="qr-bloque">
            <img class="qr-caja" src="{{ $verificacion['qr'] }}" style="{{ $medidas }}" alt="">
            <div class="qr-rotulo">ESCANEE PARA VERIFICAR</div>

            {{-- Sin el código escrito el documento SOLO se verifica escaneando:
                 con el papel rayado o poca luz no queda alternativa. Se apaga
                 donde se pidió, no por defecto. --}}
        </div>
    @else
        <table cellspacing="0" cellpadding="0" class="qr-bloque">
            <tr>
                {{-- Sobre blanco: un QR necesita su zona de silencio clara, y
                     sobre el sello de agua las cámaras dejan de engancharlo. --}}
                <td width="{{ $lado }}" valign="top" class="qr-caja">
                    <img src="{{ $verificacion['qr'] }}" style="{{ $medidas }}" alt="">
                </td>

                <td valign="middle" class="qr-datos">
                    <div class="qr-rotulo">ESCANEE PARA VERIFICAR</div>

                </td>
            </tr>
        </table>
    @endif
@endif
