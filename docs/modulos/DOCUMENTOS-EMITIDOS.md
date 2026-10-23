# Documentos emitidos — el PDF guardado tal como salió

> 23/10/2026. Antes, cada «Imprimir» volvía a armar el PDF con los datos de ese
> momento. Ahora el PDF de cada papel se guarda al emitirse y se reimprime ese
> mismo archivo.

## Qué se guarda

Los 8 papeles: el documento y el recibo de cada trámite.

| Trámite | Documento | Recibo |
| --- | --- | --- |
| Autorización | `AutorizacionPescaController::documento()` | `ReciboController::documento()` |
| Carnet | `CarnetImpresionController::documento()` | ídem |
| Faena | `PermisoFaenaImpresionController::documento()` | ídem |
| Guía | `GuiaImpresionController::documento()` | ídem |

Una fila por papel en `documentos_emitidos` (polimórfica: `emitible`), con disco,
ruta, SHA-256, tamaño y `generado_en`. Ver [MER.md](../MER.md).

## Cuándo

- **Al aprobarse** (`ConfirmarPagoService`, con `defer()`: después de responder y
  fuera de la transacción) se guardan el documento y su recibo
  (`DocumentoEmitidoService::emitirAlAprobar()`). Si falla, se loguea y la
  aprobación queda igual.
- **Al imprimir** (`respuesta()`): Jichi revisa permiso y estado y **redirige a un
  enlace firmado de S3 que vale 5 minutos**: el navegador abre el archivo
  directamente de Spaces, y la dirección sin firma responde 403 (comprobado el
  23/10/2026). Si no hay PDF o el archivo falta, se arma, se guarda y se redirige.
  Para no bajar el archivo en cada impresión no se recalcula la huella ahí; la
  compara `contenido()`. En el disco `local` (sin s3) lo entrega Jichi.
- **Portal**: descarga con `imprimir(…, descarga: true)` → el mismo enlace con
  `attachment`.
- **No hay comando para generar lo viejo** (se quitó a pedido): la base de trabajo
  se rearma vacía, así que todo se aprueba con esto ya puesto. Si algo quedara sin
  PDF, la primera impresión lo genera.

## Los botones abren S3 directo

La ficha recibe el **enlace firmado** de cada PDF guardado (`resumen()`, válido
30 minutos): «Autorización»/«Carnet»/«Faena»/«Guía», «Recibo» e «Imprimir recibo»
abren el archivo de Spaces **sin pasar por la dirección de Jichi**. Si todavía no
hay PDF (la primera vez) o el enlace venció porque la ficha quedó abierta, el botón
va por la ruta de Jichi, que lo genera o firma uno nuevo y redirige.

**Los listados también** (23/10/2026): autorizaciones, carnets, faenas, recibos,
la ficha del recibo y las pestañas del beneficiario mandan `pdf_enlace` /
`recibo_enlace` por fila (`DocumentoEmitidoService::enlace()`, con
`documentoEmitido` precargado: sin N+1), y el `<a>` lleva
`onClick={abrirDirecto(…)}` (de `comunes/ficha.tsx`). Antes iban por la ruta de
Jichi y se veía el salto de URL, aunque el PDF ya estaba guardado y no se
generaba otro.

## Sin «Regenerar»

Se probó un botón «Regenerar PDF» y **se quitó a pedido** (23/10/2026): lo emitido
no se vuelve a armar a mano. Solo se regenera solo, si el archivo falta en el
disco; el anterior queda dado de baja como historial con su `motivo`.

## Reglas que se mantienen

- Revocado o sin efecto **no se imprime**, aunque tenga PDF: lo controlan los
  `imprimir()` antes de pedir el archivo.
- El portal descarga a través de los mismos `imprimir()`, así que entrega el PDF
  guardado. La **vista previa** del portal («NO VÁLIDO») sigue armándose al vuelo:
  el guardado tiene que estar limpio.
- El archivo lo escribe solo `StorageController::pdf()` (regla 11): **privado**,
  en s3 si es el disco del sistema o en el disco `local` (no el público).

## Lo que conviene revisar en producción

- Que el bucket acepte la ACL `private` (las subidas actuales usan `public`, así
  que el bucket tiene ACL habilitadas).
- `jichi:verificar-pagos` corre por el cron: el `defer()` se ejecuta al terminar
  el comando, así que los PDF de lo que se aprueba solo también se guardan.

> Para llevar este esquema a otro sistema: [guias/PDF-PRIVADOS-S3.md](../guias/PDF-PRIVADOS-S3.md).
