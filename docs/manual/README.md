# Grabar un video del manual

Dos tiempos: `grabar-*.cjs` maneja el panel con Playwright y guarda cuadros a 4K
con un registro de eventos; `componer.py` arma el MP4 1920×1080 a 30 fps y dibuja
encima la **cámara que se acerca al foco**, el recuadro que oscurece el resto, el
cursor, la onda del clic y los subtítulos. Ver [docs/modulos/MANUAL.md](../modulos/MANUAL.md).

## Pasos

Con `composer run dev` corriendo y el usuario `admin@admin.com`:

```sh
SALIDA=/tmp/jichi-manual node docs/manual/grabar-registrar-beneficiario.cjs   # ~9 min
python3 docs/manual/componer.py /tmp/jichi-manual resources/manual/registrar-beneficiario.mp4

ffmpeg -y -ss 1.5 -i resources/manual/registrar-beneficiario.mp4 -frames:v 1 -q:v 3 \
  -vf scale=1280:-1 resources/manual/registrar-beneficiario.jpg
```

Los segundos de cada paso quedan en `/tmp/jichi-manual/capitulos.json`: se
copian a `ManualUsuario::TUTORIALES`.

### «Ser pescador» (autorización → carnet → faena)

`grabar-ser-pescador.cjs` usa al beneficiario `CI_DEMO`, que tiene que estar en el
padrón y **sin autorización en curso**. Cada trámite carga su boleta desde Jichi y
la grabación **se pausa** hasta que alguien valida el pago en Paititi (SIREB):
`pago-validado.php` consulta la liquidación cada 8 s (solo lee). La espera se
descuenta del video. La consola dice qué validar en cada momento (`### VALIDAR`).

```sh
SALIDA=<carpeta> node docs/manual/grabar-ser-pescador.cjs
python docs/manual/componer.py <carpeta> resources/manual/ser-pescador.mp4
```

En Windows es `python`, no `python3`.

**Si se corta a la mitad** (SIREB de test tarda o se cae), lo grabado se guarda igual
en `eventos.json`. Se retoma desde el carnet ya registrado con
`DESDE=carnet CARNET_ID=<id>` en otra carpeta, se compone cada parte y se unen con
`ffmpeg -f concat -c copy`. Recortar antes la cola de la primera parte (lo posterior
a su último clic) y, si se movió la carpeta, corregir las rutas de `frames` en su
`eventos.json`. Los capítulos de la segunda parte van corridos por lo que dura la primera.
Así se armó el video del 26/10/2026.

### Mostrar el papel impreso

`grabar-impresion.cjs` graba un clip desde la ficha de un documento **aprobado**: el
botón de imprimir y el PDF. El Chromium sin pantalla no muestra PDF, así que baja el
PDF real con la sesión, lo pasa a PNG (`pdf-a-png.py`, PyMuPDF) y lo muestra en un
visor dibujado sobre la página; la hoja vertical se recorre por zonas (datos, QR).
No escribe en la base.

```sh
DOC=autorizacion ID=2 SALIDA=<carpeta> node docs/manual/grabar-impresion.cjs   # o carnet / faena
```

Cada clip se compone igual y se inserta con `ffmpeg` (`trim` + `concat`, recodificando)
justo después de la aprobación de su documento.

### «Ser comercializador» (carnet → guía)

`grabar-ser-comercializador.cjs`: carnet de comercializador de `CI_DEMO` (5489008) y su
guía, comprándole a la autorización `CODIGO_AUTORIZACION` (la de Ignacio). Muestra los dos
papeles impresos dentro del mismo grabador y cierra en la ficha de esa autorización
(`AUTORIZACION_ID`) con las tarjetas y el gráfico de cómo bajó el disponible. Se retoma con
`DESDE=guia CARNET_ID=n`, `DESDE=pago-guia GUIA_ID=n` o `DESDE=cupo`.

### Vista previa de la tarjeta

`python docs/manual/vista-previa.py <clave>` arma `resources/manual/<clave>.previa.mp4`:
1,8 s de cada capítulo (menos la presentación), 640×360, ~200 KB. La tarjeta del Manual
la repite en bucle; sin ese archivo muestra la portada con un paneo lento. Regenerarla
cada vez que se regraba el video o cambian sus capítulos.

## Ojo al grabar

- **No tocar NINGÚN archivo del proyecto mientras se graba.** `@tailwindcss/vite` revisa
  todos los archivos (también PHP) y al cambiar uno recarga la página: el formulario a medio
  llenar se vacía. Pasó dos veces el 26/10/2026.
- **`php artisan serve` atiende de a una petición.** Si otra queda colgada (SIREB lento,
  un PDF, un video del Manual abierto en otra pestaña), la grabación espera. Las esperas
  del grabador son de 60–180 s por eso.

## Por qué así

- **4K de verdad necesita `--force-device-scale-factor=2`.** Con solo
  `deviceScaleFactor: 2` en el contexto, el screencast y `captureScreenshot`
  salen a 1920×1080. Pedirlos a 2× con `clip.scale` tarda ~460 ms por cuadro.
- **Se graba en cámara lenta** (`LENTO=5`): a 4K el screencast da ~3 cuadros/s.
  Todo espera y escribe 5× más lento y `componer.py` divide los tiempos: el
  contenido queda a ~10 cuadros/s y lo que se mueve (cámara, cursor, foco) a 30,
  porque se dibuja en la composición.
- **El zoom no se hace en la página**: un `transform` sobre el contenido rompe la
  barra lateral `fixed`. Se recorta del cuadro 4K, así que acercar hasta 1,9× no pierde nitidez.
- `-movflags +faststart`: el video arranca sin bajarse entero.

## Ojo

- **Escribe en la base de trabajo**: registra al beneficiario de demostración
  (Ignacio Molina Guzmán, C.I. 7633685, `CI_DEMO`). La cédula es única: para regrabar, darlo de baja antes.
- Las animaciones propias del panel (avisos, transiciones) salen 5× más rápidas.
