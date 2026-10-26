# Manual de usuario (videos tutoriales)

> 25/10/2026. Botón **«Ayuda»** (antes «Manual», 26/10/2026) en la barra superior del panel: abre una ventana
> con videos que muestran, paso a paso, cómo hacer un trámite, sin salir de la
> pantalla en la que se está trabajando.

## Cómo funciona

```
Barra superior › «Ayuda» (BotonManual)
   └─ abre VentanaManual (createPortal a <body>)
        └─ GET /panel/manual  (JSON: solo los tutoriales que el rol puede hacer)
             └─ ReproductorTutorial: <video> + lista de pasos; un clic salta a ese segundo
                  └─ GET /panel/manual/{tutorial}.mp4  (con sesión, responde por rangos: 206)
```

| Pieza | Dónde |
| --- | --- |
| Catálogo de tutoriales (título, módulo, permiso, capítulos) | `app/Support/ManualUsuario.php` |
| JSON y archivos | `app/Http/Controllers/Panel/ManualController.php` |
| Rutas `manual.index`, `manual.archivo` | `routes/panel.php`, al final |
| Videos y portadas | `resources/manual/{clave}.mp4` y `.jpg` |
| Botón, ventana (biblioteca de tarjetas), reproductor | `resources/js/components/panel/manual/` |
| Vista previa en bucle de cada tarjeta (`{clave}.previa.mp4`, ruta `manual.archivo` con extensión `previa.mp4`) | `docs/manual/vista-previa.py` |
| Hasta dónde vio cada video (`localStorage`, por computadora) | `components/panel/manual/progreso-manual.ts` |
| Tipos | `resources/js/types/manual.ts` |
| Script de grabación | `docs/manual/` (ver su README) |
| Pruebas | `tests/Feature/Manual/ManualUsuarioTest.php` |

## Decisiones

- **El video NO va en `public/`.** Muestra el panel por dentro (menú, módulos,
  datos de prueba), y la regla 3 dice que lo público no da pistas de la
  estructura interna. Lo sirve `ManualController::archivo()` dentro del grupo
  `auth` + `funcionario`.
- **Sin permiso propio.** Cada tutorial declara el permiso del trámite que
  muestra (`beneficiarios.crear`) y el servidor filtra la lista y el archivo:
  quien no puede registrar beneficiarios no ve ese video.
- **`response()->file()` y no `Storage`.** `BinaryFileResponse` responde los
  pedidos `Range` con 206; sin eso el navegador no puede saltar a un capítulo.
- **Modal en portal a `<body>`.** El encabezado es `sticky z-20`: un `fixed z-50`
  adentro queda atrapado en ese contexto de apilamiento y la barra lateral
  (`z-40`) lo tapa.

- **El progreso va en `localStorage`, no en la base.** Es una ayuda de pantalla
  («Continuar desde 2:15», «Visto»), no un registro de capacitación: no hace falta
  tabla ni ruta. Si algún día la unidad quiere saber quién vio qué, eso sí va a la base.
- **Con un solo video la biblioteca se salta** y abre directo el reproductor.

## Tutoriales

| Clave | Muestra | Permiso |
| --- | --- | --- |
| `registrar-beneficiario` | Buscar y registrar un beneficiario (1:30) | `beneficiarios.crear` |
| `ser-comercializador` | Carnet de comercializador → guía comprándole a un pescador, papeles impresos y cómo baja su cupo (4:28) | `guias.crear` |
| `ser-pescador` | Autorización → carnet → faena, con el pago de cada uno validado en SIREB y el papel impreso de los tres (4:54) | `aprovechamientos.crear` |

## Agregar un tutorial

1. Grabarlo (ver [docs/manual/README.md](../manual/README.md)) y dejar
   `resources/manual/{clave}.mp4` + `{clave}.jpg`.
2. Sumarlo a `ManualUsuario::TUTORIALES` con su permiso y sus capítulos
   (el script deja los segundos en `capitulos.json`).
