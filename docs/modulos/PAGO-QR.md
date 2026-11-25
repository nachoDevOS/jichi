# Pago por QR — el QR lo genera SIREB/Paititi

> 23/11/2026. Reemplaza al QR «de muestra» (`qr-simulado.tsx`, 08/10/2026), que no
> se podía leer ni cobraba. Ver también [SIREB.md](SIREB.md) (liquidaciones y
> confirmación del pago) y [PORTAL.md](PORTAL.md).
>
> **La app BeniDigital no usa este QR** (25/11/2026): paga directo con la API pública de SIREB
> (`/api/v1/publico`, con el `codigo_pago` del trámite) y después pregunta
> `GET /api/v1/portal/tramites/{codigo}/estado`, que usa el mismo `CobroQrService::estado()` de
> esta página para aprobar. Ver [api/README.md](../api/README.md) § 2.1.

## La idea

**SIREB genera el QR; Jichi lo pide, lo muestra tal cual y se entera del pago.**
Qué banco o pasarela cobra (AGETIC, SINTESIS u otra) es asunto de SIREB: el día
que Recaudaciones cambie de pasarela, o la active en producción, **Jichi no se
toca**. Lo dice el propio contrato de SIREB: *«Cuando llegue la pasarela, este
mismo endpoint devuelve el QR real con `simulado: false`; el contrato no cambia.»*

Para que eso se cumpla, Jichi sigue cinco reglas, y conviene no romperlas:

1. **La dirección de SIREB va solo en el `.env`** (`SIREB_URL`, `SIREB_IBARE_URL` y
   las credenciales). Pasar de test a Paititi es cambiar esas líneas.
2. **No se dibuja el QR**: se muestra la `imagen` (PNG en base64) que manda SIREB.
3. **Real o de prueba lo dice la respuesta** (`simulado`), no una opción de Jichi.
4. **El `503 PAGO_QR_NO_DISPONIBLE` no es un error**: se ofrece el depósito.
5. **Se programa contra el `codigo` de los errores**, nunca contra el mensaje, y se
   ignoran los campos que SIREB agregue.

## Entornos

| Entorno | `SIREB_IBARE_URL` | `SIREB_URL` | QR hoy (23/11/2026) |
|---|---|---|---|
| Test | `https://test.ibare.beni.gob.bo` | `https://test.sireb.beni.gob.bo` | De prueba (`simulado: true`) y se puede simular el pago |
| Producción | `https://ibare.beni.gob.bo` | `https://paititi.beni.gob.bo` | `503`: falta la pasarela. Se paga por depósito |

Las credenciales (`SIREB_CLIENT_ID=sedag`, `SIREB_CLIENT_SECRET`) y el secreto del
webhook (`SIREB_WEBHOOK_SECRETO`) son **uno por entorno** y los entrega el equipo
de SIREB.

## El flujo

```
Ventanilla o titular abre «Pagar por QR»
  └─ GET /panel/{doc}/{id}/consultar-qr   ó   GET /mi-cuenta/pagar-qr/{codigo}
       └─ CobroQrService::qr()
            ├─ ConfirmarPagoService::puedePagarPorQr()  GET /liquidaciones/{id}
            │     pendiente y sin pago → sigue; si no → el motivo («ya pagado», «vencida»…)
            └─ SirebService::qrDePago()                POST /liquidaciones/{id}/qr
                  ├─ 201 nuevo / 200 el mismo ........ se muestra la imagen + cuenta regresiva
                  ├─ 503 PAGO_QR_NO_DISPONIBLE ....... «pague por depósito con el código …»
                  └─ 422 PAGO_YA_EXISTE / NO_PAGABLE . el motivo; la ficha se recarga

El titular paga con la app de su banco → SIREB deja la liquidación `pagada`

Jichi se entera por cualquiera de estos cuatro caminos (el primero que llegue aprueba):
  ① la ventana abierta pregunta cada 10 s → GET …/estado-qr → CobroQrService::estado()
       (consulta a SIREB como mucho cada 15 s por trámite)
  ② el webhook de SIREB → POST /api/sireb/aviso → VerificarPagoJob
  ③ el portal al abrir el inicio → VerificarPagoJob (cada 2 min por trámite)
  ④ jichi:verificar-pagos cada 10 min
       └─ todos terminan en ConfirmarPagoService::verificar(): aprueba y emite el recibo
```

**Ningún camino aprueba con lo que dice el aviso o el navegador**: todos vuelven a
preguntar `GET /liquidaciones/{id}` y solo `estado = pagada` aprueba, como siempre.

## Lo que se usa de SIREB

| Endpoint | Para | Notas |
|---|---|---|
| `POST /api/v1/liquidaciones/{id}/qr` | Pedir el QR | Sin `Idempotency-Key`: mientras el QR siga vigente devuelve el mismo (`200`). Vigencia de 30 min, nunca más allá del vencimiento de la liquidación |
| `POST /api/v1/liquidaciones/{id}/qr/simular-pago` | Pagar un QR de prueba | Solo en test. En producción: `404 QR_SIMULADO_DESHABILITADO` |
| `GET /api/v1/liquidaciones/{id}` | Saber si se pagó | El de siempre. El `pago` de un QR trae `medio: "QR"`, `transaccion_id` y `simulado` |
| Webhook `liquidacionCambio` | Que SIREB avise | Ver «El webhook», abajo |

La respuesta del QR:

```json
{"data": {
  "transaccion_id": "SIM-7K2Q9XWB4M1D",
  "codigo_publico": "7ZQK-P4M2-9AX",
  "monto": "120.00",
  "imagen": "iVBORw0KGgo…",
  "contenido": "…",
  "vence_en": "2026-11-23T16:40:00-04:00",
  "simulado": true
}}
```

| Código | HTTP | Qué hace Jichi |
|---|---|---|
| — | 200 / 201 | Muestra el QR. Guarda `transaccion_id`, `vence_en` y `simulado` en `sireb_envio.qr` (sin la imagen) |
| `PAGO_QR_NO_DISPONIBLE` | 503 | `disponible: false`: aviso «pague por depósito con el código …». En el panel no recarga la ficha |
| `PAGO_YA_EXISTE` | 422 | «Ya tiene un pago registrado; en unos minutos queda aprobado» |
| `LIQUIDACION_NO_PAGABLE` | 422 | Pagada, vencida o anulada: en el panel «use Verificar pago»; en el portal «actualice la página» |
| `LIQUIDACION_FUERA_DE_DEPENDENCIA` | 403 | Lo trata `SirebService::enviar()` como credencial rechazada |
| `QR_VENCIDO` / `QR_INVALIDO` | 422 | Al simular: «vuelva a abrir el QR para generar otro» |
| `QR_SIMULADO_DESHABILITADO` | 404 | Al simular: «solo se puede en el entorno de prueba» |

`qrDePago()` va **sin reintentos**: el `503` es una respuesta («no hay QR») y no
una caída, y reintentarlo solo demoraría el aviso.

## Las piezas en Jichi

| Pieza | Qué hace |
|---|---|
| `App\Sireb\SirebService::qrDePago()` / `simularPagoQr()` | El HTTP, por el mismo `enviar()` de siempre (token, reintento ante 401, registro en `sireb-AAAA-MM-DD.log`) |
| `App\Services\CobroQrService` | `qr()` (pide y anota), `estado()` (¿ya se pagó?, con espera de 15 s por trámite en caché) y `simularPago()` |
| `Panel\{Aprovechamiento,Carnet,Faena,Guia}Controller` | `consultarQr()`, `estadoQr()` y `simularPagoQr()` |
| `Portal\PagoQrController` | `__invoke()` (el QR), `estado()` y `simular()` (25/11/2026: el pago de prueba, en JSON), con el trámite buscado por código **y** titular (`DocumentoDelPortal`) |
| `Sireb\AvisoSirebController` + `routes/api.php` | El webhook |
| `ConfirmarPagoService::comprobante()` / `medio()` / `diaDelPago()` | Cómo se copia un pago al recibo y a la ficha, sea depósito o QR |
| `components/comunes/qr-sireb.tsx` | La imagen, la cuenta regresiva (`useCuentaRegresiva`) y la espera del pago (`useEsperaDePago`) |
| `components/panel/pagos/modal-pago-qr.tsx` | La ventana del panel, con «Simular pago (prueba)» si el QR es de prueba |
| `components/portal/ventana-pago-qr.tsx` | La ventana del portal, con «Descargar QR» (la imagen de SIREB con monto y código debajo) y, con un QR de prueba, «Simular pago (prueba)» (`fetch` con `X-XSRF-TOKEN` de la cookie) |

### Rutas y permisos

| Ruta | Permiso | Límite |
|---|---|---|
| `GET /panel/{doc}/{id}/consultar-qr` | `{doc}.cargar-pago` | 20/min |
| `GET /panel/{doc}/{id}/estado-qr` | `{doc}.cargar-pago` | 30/min |
| `POST /panel/{doc}/{id}/simular-pago-qr` | `{doc}.cargar-pago` | 10/min |
| `GET /mi-cuenta/pagar-qr/{codigo}` y `…/estado` | Titular con sesión | 20/min y 30/min |
| `POST /api/sireb/aviso` | Firma HMAC | 120/min |

`{doc}` es `aprovechamientos`, `carnets`, `faenas` o `guias`. **No hay permiso
nuevo**: ofrecer el QR ya pedía `cargar-pago`, y simular el pago de prueba es
cargar un pago. Así no hace falta volver a correr `RolPermisoSeeder`.

## Real o de prueba

| | `simulado: true` (test) | `simulado: false` (pasarela real) |
|---|---|---|
| Panel | Insignia «QR de prueba: no se paga con el banco» y botón **«Simular pago (prueba)»** | Insignia «Vence en m:ss», sin botón de simular |
| Portal | «QR de prueba: no lo pague con su banco» y botón **«Simular pago (prueba)»** (25/11/2026) | Solo «Vence en m:ss» |
| QR descargado | Lleva la leyenda de prueba | Sin leyenda |
| Recibo | Banco: **«Pago por QR de prueba»** | Banco: **«Pago por QR»** |

**Simular el pago solo se ofrece con un QR que SIREB dio por `simulado`**
(`sireb_envio.qr.simulado === true`): con uno real, `CobroQrService::simularPago()`
ni llama a SIREB. Y aunque se llamara, Paititi responde `404`.

## Cuando vence el QR

La ventana cuenta hacia atrás con `vence_en`. Al llegar a cero, atenúa el QR, deja
de preguntar por el pago y ofrece **«Generar otro»**, que vuelve a pedirlo (SIREB
da uno nuevo con otro `transaccion_id`). En el portal, «Descargar QR» se
deshabilita y la imagen descargada lleva «Vale hasta …».

## El recibo de un pago QR

Un pago QR no tiene boleta ni banco, así que el recibo copia la **transacción** en
`numero_boleta` y **«Pago por QR»** en `entidad_bancaria`. No hizo falta una columna
nueva: el recibo impreso ya muestra «N° de depósito (banco)», y ahí se lee
«SIM-7K2Q… (Pago por QR de prueba)».

De paso se corrigió la **fecha del recibo** de los depósitos: SIREB manda
`fecha_deposito` (el día de la boleta) aparte de `fecha_pago` (cuándo se cargó en
SIREB). Ahora el recibo y la ficha usan `fecha_deposito` si viene
(`ConfirmarPagoService::diaDelPago()`).

## El webhook

SIREB avisa con un `POST` firmado a **`{APP_URL}/api/sireb/aviso`** cuando una
liquidación de Jichi se paga (`pago_confirmado`), se observa su depósito
(`pago_rechazado`), vence (`liquidacion_vencida`) o se anula
(`liquidacion_anulada`). La dirección y el secreto los da de alta el equipo de
SIREB en su panel; el secreto va en `SIREB_WEBHOOK_SECRETO`.

`AvisoSirebController`:

1. **Verifica la firma con el cuerpo crudo**: `X-Paititi-Firma` debe ser `sha256=` +
   HMAC-SHA256(secreto, `X-Paititi-Timestamp` + "." + cuerpo), comparada con
   `hash_equals`. Sin secreto configurado, rechaza todo. → `401`.
2. **Rechaza avisos de más de 5 minutos** (`X-Paititi-Timestamp`). → `401`.
3. **Descarta repetidos** por `X-Paititi-Entrega` (caché de 3 días: SIREB reintenta
   hasta 24 h). → `200 {"repetido": true}`.
4. Busca el trámite por `sireb_liquidacion_id` en los cuatro documentos y, si sigue
   pendiente, **lanza `VerificarPagoJob` después de responder**: SIREB espera un 2xx
   en menos de 10 s.
5. **No cree en el aviso**: el job vuelve a consultar a SIREB. Un aviso que diga
   «pagada» de una liquidación que SIREB todavía tiene pendiente no aprueba nada
   (hay una prueba para eso).

Cada aviso queda en `storage/logs/sireb-AAAA-MM-DD.log` («AVISO pago_confirmado de
la liquidación …», o «AVISO RECHAZADO» con la IP) y se ve en Seguridad › Registro SIREB.

**Sin webhook todo funciona igual**, solo que más lento: el titular que pagó con la
ventana cerrada espera al portal o al comando de cada 10 minutos.

Va en `routes/api.php` (grupo `api`, prefijo `/api`): sin sesión ni CSRF, porque lo
llama una máquina y no una persona. No es del panel, ni del público, ni del portal.

## Cómo probarlo en test

1. `.env` apuntando a test (`SIREB_URL=https://test.sireb.beni.gob.bo`,
   `SIREB_IBARE_URL=https://test.ibare.beni.gob.bo`) y `php artisan jichi:sireb` en
   verde: **el catálogo tiene que responder**, no solo el token.
2. Registrar un carnet o una faena: queda pendiente con su liquidación.
3. En la ficha, «Pagar por QR»: aparece el QR con «QR de prueba».
4. «Simular pago (prueba)»: SIREB lo da por pagado, Jichi aprueba y emite el recibo
   con la transacción y «Pago por QR de prueba».
5. En el portal (25/11/2026) el mismo botón: «Pagar con QR» → «Simular pago (prueba)», y la
   ventana muestra «¡Pago recibido!» al instante. Para probar que la ventana se entera SOLA,
   simular desde el panel en otra pestaña: en menos de 25 s el portal lo muestra.

Las pruebas automáticas están en `tests/Feature/Pagos/PagoQrTest.php` (16 —dos del botón del portal: solo el titular, y nunca con un QR real—, con
SIREB simulado en `tests/Concerns/SirebFalso.php`: `$qrDisponible`, `$qrSimulado`).
Se comprobó que fallan rompiendo el código a propósito: la firma del webhook, el
descarte de repetidos, el freno de la simulación con un QR real, la fecha del recibo,
el freno de `estado()` y la consulta vieja. La validación del UUID del aviso **no la
ve SQLite** (no reclama por un id mal formado): solo se nota en PostgreSQL.

## Protecciones para producción (revisión del 23/11/2026)

| Riesgo | Protección |
|---|---|
| **Una consulta vieja pisa la liquidación nueva.** Una corrección anula la liquidación y SIREB avisa «anulada» mientras Jichi registra la nueva; la consulta del aviso escribía `consulta: anulada` sobre el trámite ya corregido. Quedaba «caído» y **el comando dejaba de revisarlo**: un pago real no se detectaba solo | `LiquidableSireb::anotarEnvioSireb()` relee la fila **bloqueada** y escribe solo si sigue siendo la misma liquidación. Lo usan `ConfirmarPagoService` (al anotar cada consulta) y `CobroQrService` |
| **La ventana del portal registra otra deuda.** `verificar()` prepara y registra una liquidación nueva si la del trámite está anulada; el portal preguntaba cada 10 s | `CobroQrService::estado()` no consulta sin una liquidación viva (igual que `VerificarPagoJob`) |
| **Un teléfono con la hora corrida** da el QR por vencido (o vigente de más) | El servidor manda `segundos_restantes`; la ventana descuenta desde ahí, sin la hora del teléfono |
| **Un QR grande achicado sin suavizado** pierde módulos y no se lee | La imagen se muestra con el suavizado del navegador; al descargar, sin suavizado solo si se agranda |
| **Un aviso que falla a medias** quedaba marcado como recibido y el reintento de SIREB se descartaba | Se marca recibido recién al final |
| **Un id que no es UUID** en el aviso: en PostgreSQL, 500 y reintentos durante 24 h | `Str::isUuid()` antes de consultar |
| SIREB responde sin `imagen` | «No devolvió la imagen del QR»: no se muestra una ventana vacía |

## Lo que hay que pedirle al equipo de SIREB

| Qué | Para qué | Estado (23/11/2026) |
|---|---|---|
| Reactivar la credencial `sedag` en test | Test respondía `403 SISTEMA_NO_HABILITADO` aunque el token salía bien | ✅ 24/11/2026: reactivada; pago simulado probado de punta a punta |
| Dar de alta `{APP_URL}/api/sireb/aviso` y entregar el secreto, en test y en producción | El webhook | Pendiente |
| Credenciales de `sedag` en Paititi | Pasar a producción | Pendiente |
| Integrar la pasarela real (AGETIC/SINTESIS) en Paititi | Que producción dé un QR real en vez de `503` | Trabajo de SIREB; en Jichi no cambia nada |
| Confirmar que `/api/v1` del QR ya no cambia | Mientras SIREB no esté en producción, su contrato puede cambiar sin versión nueva | Pendiente |
