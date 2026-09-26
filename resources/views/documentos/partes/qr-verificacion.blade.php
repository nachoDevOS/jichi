{{--
  EL BLOQUE DE VERIFICACIÓN DE UN DOCUMENTO IMPRESO

  El QR y, justo debajo, el código escrito en letra chica: el QR es lo rápido y
  el código es lo que funciona con un papel gastado, poca luz o un teléfono
  viejo. La pantalla de /verificar acepta las dos entradas.

  @param array|null $verificacion  Lo que devuelve App\Support\QrVerificacion::de()
  @param int        $lado          Lado del QR EN PUNTOS. Ver la nota de abajo.
  @param bool       $vertical      Rótulo DEBAJO del QR, para una columna angosta.

  ⚠️ EL LADO VA EN `style`, NO EN EL ATRIBUTO `width`: el atributo se mide en
  PÍXELES, así que un `width="44"` salía de 33 pt —un 25% más chico— y el módulo
  del QR quedaba en 0,31 mm, al borde de lo que una cámara engancha.

  ⚠️ EL CÓDIGO MIDE 19 CARACTERES × 0,602 × cuerpo: a 5 pt son 57 pt, y tiene que
  caber en el lado del QR. Con un QR de menos de 58 pt hay que bajar el cuerpo.

  El andamio lo pone este archivo; el color y el cuerpo los pone la hoja de
  estilos de quien lo incluye, con .qr-caja, .qr-rotulo y .qr-codigo.
--}}
@if (($verificacion['qr'] ?? '') !== '')
    @php($lado = $lado ?? 56)
    @php($medidas = "width: {$lado}pt; height: {$lado}pt;")

    @if ($vertical ?? false)
        {{-- Apilado, para una columna angosta. --}}
        <div class="qr-bloque">
            <img class="qr-caja" src="{{ $verificacion['qr'] }}" style="{{ $medidas }}" alt="">
            <div class="qr-codigo">{{ $verificacion['codigo'] }}</div>
            <div class="qr-rotulo">ESCANEE PARA VERIFICAR</div>
        </div>
    @else
        <table cellspacing="0" cellpadding="0" class="qr-bloque">
            <tr>
                {{-- Sobre blanco: un QR necesita su zona de silencio clara, y
                     sobre el sello de agua las cámaras dejan de engancharlo. --}}
                <td width="{{ $lado }}" valign="top" class="qr-caja">
                    <img src="{{ $verificacion['qr'] }}" style="{{ $medidas }}" alt="">
                    <div class="qr-codigo">{{ $verificacion['codigo'] }}</div>
                </td>

                <td valign="middle" class="qr-datos">
                    <div class="qr-rotulo">ESCANEE PARA VERIFICAR</div>
                </td>
            </tr>
        </table>
    @endif
@endif
