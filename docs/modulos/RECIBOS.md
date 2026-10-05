# Módulo Recibos — el RECIBO OFICIAL del SEDAG

Reemplaza al talonario verde que la unidad venía llenando a mano. Es el
comprobante de que un documento se pagó: **uno por documento pagado**
(autorización, carnet, faena o guía).

> Reescrito el 03/10/2026 contra el código. El recibo del modelo viejo —armado al
> vuelo desde `tramites` y `pagos`, con depósitos cargados en Jichi y «enviar a
> revisión»— ya no existe; está en el historial de git. El cobro está explicado
> en [SIREB.md](SIREB.md).

---

## 1. Cuándo nace

```
PENDIENTE ──(SIREB: liquidación «pagada», pago «confirmado»)──▶ APROBADO
                                                      └── en la MISMA transacción sale el RECIBO
```

**Al aprobarse el documento, y solo ahí.** Lo emite
`ConfirmarPagoService::emitirRecibo()`, dentro de la transacción que aprueba
(«Verificar pago», el comando `jichi:verificar-pagos`, o al intentar eliminar un
trámite que ya estaba pagado). Un documento sin aprobar no tiene recibo; uno «No
pagado» tampoco, nunca.

Es una fila de la tabla **`recibos`**, polimórfica: `recibible_type` +
`recibible_id` apuntan al documento pagado, con **único** sobre el par —dos
recibos no pueden respaldar el mismo documento—.

---

## 2. El número

`numero_recibo`: correlativo **continuo** de seis dígitos —`000001`, `000002`…—
de la serie `REC` bajo el año 0 (`CorrelativoService::siguienteContinuo()`). No
reinicia en enero: el talonario de papel tampoco. **Contabilidad audita sus
huecos**, y por eso es aparte del código de verificación, que es al azar.

Lo guardado **es** lo que va impreso (`ReciboImpreso::numeroImpreso()`): no hay
prefijo que recortar.

**Reimprimir da el MISMO número.** Imprimir no escribe nada: el PDF se arma cada
vez desde la fila.

---

## 3. Todo lo que dice está congelado

| Columna | Qué guarda |
| --- | --- |
| `monto_total` | Lo pagado en SIREB (`monto_pagado`, o el monto de la liquidación) |
| `concepto` | Cómo se nombra el documento, tal como se imprime: «Cédula de Pescador - 300 Kg», «Autorización de Pesca para Aprovechamiento Pesquero - 101 Kg Hasta 200 Kg», «Faena N° 000003 - 120 kg», «Guía N° 000308» |
| `numero_boleta` | El **N° de transacción** del pago, tal como lo validó SIREB |
| `entidad_bancaria` | El banco |
| `fecha_pago` | El DÍA del pago (`toDateString()`: es un día, no un instante) |

Copiado y no leído por relación: el catálogo cambia por resolución y lo ya
entregado no puede cambiar retroactivamente.

> **Lo que NO está congelado:** el nombre y la C.I. del titular se leen del padrón
> al imprimir (`ReciboImpreso::__get()`). Corregir un apellido mal tipeado cambia
> la reimpresión, a propósito: hay un solo lugar donde se corrige.

---

## 4. Qué se imprime en cada campo

| Campo del papel | De dónde sale |
| --- | --- |
| `N°` (rojo, arriba) | `numero_recibo` |
| Lugar | `configuraciones` → `documentos.lugar_emision` («Trinidad - Beni») |
| DIA \| MES \| AÑO | `ReciboImpreso::fechaEnCasilleros()` — el día en que se emitió |
| Nombre y Apellido, C.I. | Del padrón, al imprimir |
| La suma de … 00/100 | `ReciboImpreso::montoEnLetras()` |
| Concepto | `recibos.concepto` |
| N° (del depósito) | `numero_boleta (entidad_bancaria)` |
| DESCRIPCIÓN (6 casillas) | `ConceptoRecibo::desdeDocumento()` — se imprimen las seis y se marca la del documento pagado |
| IMPORTE A PAGAR Bs. | Un renglón —el documento— y el TOTAL |
| QR + código | `QrVerificacion::de($recibo)`: el recibo también se verifica en `/verificar` |

### El monto en letras

`Number::spell($entero, locale: 'es')` de Laravel, que usa la extensión `intl`:
`80.00 → «OCHENTA 00/100 BOLIVIANOS»`. El `00/100` es la forma clásica de cerrar
un importe escrito para que nadie le agregue centavos.

### Las seis casillas de DESCRIPCIÓN

```
( ) Permiso por Faena                                      ← faena
( ) Solicitud de Importe de Alevines
( ) Autorización de Pesca para Aprovechamiento Pesquero     ← autorización
( ) Guía única de Transporte                               ← guía
( ) Cédulas                                                ← carnet
( ) Otros
```

**Se imprimen siempre las seis**, aunque el sistema no cobre alevines: el recibo
tiene que salir igual al papel. La cruz la decide **el documento pagado**
(`ConceptoRecibo::desdeDocumento()`), no la actividad de la persona: un pescador
que paga su carnet lleva la cruz en «Cédulas», no en «Permiso por Faena».

---

## 5. El PDF

### No se guarda en disco

Se arma al vuelo cada vez que alguien imprime, desde la fila de `recibos`: los
datos están congelados y el PDF de mañana sale idéntico al de hoy. Por eso
`ReciboController` no pasa por `StorageController`: no escribe nada.

### La maqueta

`resources/views/documentos/recibo-oficial.blade.php`, un calco del talonario.
**Media carta apaisada: 612 × 396 puntos**, fijado en `ReciboController`. El
marco vive a 10 pt de cada borde —**592 × 376 útiles**— y todo va en
`position: absolute`: es un papel de medida fija que tiene que salir siempre
igual, y lo dibuja DomPDF, que no es un navegador.

### Trampas de DomPDF que ya costaron tiempo

| Trampa | Solución aplicada |
| --- | --- |
| No entiende flexbox, grid ni variables CSS | Tablas y `position: absolute` |
| `opacity` es poco confiable | La atenuación va **horneada** en `recibo-sello.png` |
| Una ruta `/image/...` termina en un recuadro vacío en producción | Imágenes **embebidas en base64** |
| Los PNG del panel daban un PDF de **5,4 MB por recibo** | Copias a medida: `recibo-escudo.png` y `recibo-sello.png` |
| Las fuentes completas pesaban 734 KB por PDF | `enable_font_subsetting` |
| Solo DejaVu Sans trae acentos y «ñ» completos | `font-family: 'DejaVu Sans'` |
| Blade escapa `&nbsp;` y sale impreso como texto | Un `<span class="hueco">` de ancho fijo |

### La cuadrícula de importes

Un cuadro con **un renglón** —el documento pagado— y el TOTAL al pie, completado
con renglones en blanco hasta `ReciboController::RENGLONES_MINIMOS` para
conservar el alto del talonario. El monto va alineado a la derecha, con punto
para los miles y coma para los decimales (`1.250,50`).

**La cifra no se parte.** Se probó un dígito por casillero —se leía `12000`— y
bolivianos | centavos —obligaba a juntar dos cifras—: va en una sola celda. Es
un comprobante, y lo único que importa es que el número se lea de una.

---

## 6. Las rutas y la pantalla

| Ruta | Permiso | Qué hace |
| --- | --- | --- |
| `GET /panel/recibos` | `recibos.ver` | El libro de recibos, con búsqueda y rango de fechas |
| `GET /panel/recibos/{recibo}` | `recibos.ver` | La ficha: documento pagado, pago de SIREB, enlace al documento |
| `GET /panel/recibos/{recibo}/imprimir` | `recibos.imprimir` | El PDF. GET porque no escribe nada |

Además, la tarjeta «Pago en Recaudaciones» de las cuatro fichas muestra el recibo
—N°, N° de transacción, banco, fecha y «Validado el»— con su botón «Imprimir
recibo». El portal del beneficiario lo descarga en «Mis pagos»
(`DescargarReciboController`).

---

## 7. Archivos del módulo

| Archivo | Qué es |
| --- | --- |
| `app/Models/Recibo.php` | La fila; `recibible` polimórfica (precargar con `morphWith`) |
| `app/Services/ConfirmarPagoService.php` | `emitirRecibo()` y `concepto()` |
| `app/Enums/ConceptoRecibo.php` | Las seis casillas y `desdeDocumento()` |
| `app/Support/ReciboImpreso.php` | El recibo como lo lee la plantilla |
| `app/Http/Controllers/Panel/ReciboController.php` | Libro, ficha y PDF |
| `resources/views/documentos/recibo-oficial.blade.php` | La maqueta |
| `resources/js/pages/panel/recibos/{index,ver}.tsx` | El libro y la ficha |
| `public/image/recibo-escudo.png`, `recibo-sello.png` | Copias a medida |

---

## 8. Lo que quedó afuera

- **Anular un recibo.** Hoy un recibo emitido no se anula: el documento que
  respalda se pagó en SIREB y quedó aprobado.
- **Varios documentos en un recibo.** Uno por documento, porque en SIREB cada
  documento es una liquidación aparte.
