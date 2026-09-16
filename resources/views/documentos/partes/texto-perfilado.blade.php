{{--
================================================================================
  UN TEXTO CON CONTORNO, PARA DomPDF
================================================================================

  DomPDF NO TIENE `-webkit-text-stroke` NI `text-shadow`. Las dos son la forma
  normal de perfilar un texto en un navegador, y acá no existe ninguna: lo que
  se escriba con ellas se dibuja sin contorno y sin avisar.

  Así que el contorno se hace a mano. El mismo texto se dibuja CINCO veces:
  cuatro copias del color del borde corridas hacia cada esquina, y la quinta
  —la cara— encima, sin corrimiento. El ojo lee el relleno de la última y el
  asomo de las otras cuatro como un perfilado.

  Parece un truco y lo es, pero es el único que sale igual en todas las
  versiones de DomPDF: con `opacity` o con filtros la tarjeta salía distinta
  según el servidor.

  --------------------------------------------------------------------------
  QUÉ PONE ESTA PARTE Y QUÉ PONE QUIEN LA INCLUYE
  --------------------------------------------------------------------------

  Acá está SOLO el andamio: las cinco copias y sus clases. El color, el cuerpo,
  el ancho y cuánto se corren las esquinas los pone la hoja de estilos del
  bloque que la incluye, porque no son los mismos en los dos usos —el título va
  perfilado en dorado a 11 pt y corrido 0,5; los rótulos van perfilados en verde
  oscuro a 4,6 y corridos 0,3—.

  El contenedor tiene que ser `position: absolute` o `relative`: las cinco
  copias se miden contra ÉL, y sin contenedor posicionado aterrizan contra la
  página.

  --------------------------------------------------------------------------
  EL CORRIMIENTO SE ELIGE CONTRA EL CUERPO, NO CONTRA EL GUSTO
  --------------------------------------------------------------------------

  Un contorno de medio punto sobre un texto de 4,6 pt no perfila: engorda la
  letra hasta cerrarle los huecos —la «O» se llena, la «E» se vuelve una
  mancha— y a ese tamaño el rótulo deja de leerse. La proporción que funciona
  es de más o menos un 6% del cuerpo.

  @param string $texto  Lo que se dibuja, cinco veces.
--}}
<span class="borde e1">{{ $texto }}</span>
<span class="borde e2">{{ $texto }}</span>
<span class="borde e3">{{ $texto }}</span>
<span class="borde e4">{{ $texto }}</span>
<span class="cara">{{ $texto }}</span>
