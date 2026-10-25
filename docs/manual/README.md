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
