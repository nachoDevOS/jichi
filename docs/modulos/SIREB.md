# SIREB — los precios vienen de Recaudaciones

> 30/09/2026: los cuatro documentos —autorización, carnet, faena y guía— toman
> el precio de SIREB al emitirse y lo congelan. Ninguna tarifa se escribe a mano.

## La regla

SIREB (el Sistema de Recaudaciones del GAD) es la fuente de verdad del arancel.
Su regla para los sistemas que se conectan es que **ninguno guarda sus propios
precios**. Jichi guarda **qué servicio de SIREB corresponde a cada cosa** y le
pide el monto al momento de emitir.

## Cómo se conecta

```
Jichi ──client_credentials (sedag)──▶ Ibare ──token──▶ Jichi
Jichi ──GET /api/v1/catalogo/servicios + token──▶ SIREB
      ◀── solo servicios del SEDAG (la dependencia sale del token) ──
```

- Credencial de máquina **aparte** de la del login: `SIREB_CLIENT_ID` (`sedag`)
  y `SIREB_CLIENT_SECRET`. El token lo emite `SIREB_IBARE_URL`, propio: ya no
  cae en el `IBARE_URL` del login si falta. **Tiene que ser el Ibare en el que confía ese SIREB**:
  contra `test.sireb` va `test.ibare`, o SIREB responde `TOKEN_INVALIDO`.
- El token dura 10 minutos y lo renueva `SirebService` solo. El `Idempotency-Key`
  es otra cosa: va solo al registrar liquidaciones (ver «La liquidación y el pago»).
- En SIREB, `sedag` tiene que estar dado de alta como sistema consumidor y
  `activo`, o responde `403 SISTEMA_NO_HABILITADO`.
- El catálogo va en **caché 10 minutos** (`SIREB_CACHE_MINUTOS`), y el token
  hasta un minuto antes de vencer. Si un código no aparece, se descarga de nuevo
  una vez antes de fallar: puede ser un alta reciente.

### Probar la conexión

```sh
php artisan jichi:sireb
```

Pide un token nuevo a Ibare y baja el catálogo sin caché (es el `curl` de
`/oauth/token` más `GET /api/v1/catalogo/servicios`). Muestra el token, su
vencimiento y el JSON del catálogo **tal como lo manda SIREB**. Con `--resumen`,
en vez del JSON, una tabla con lo que Jichi entiende de cada servicio y si sirve
para la escala: solo sirve con **una** tarifa general.
Si falla, muestra el mismo mensaje que vería ventanilla al otorgar. Vive en
`app/Console/Commands/ProbarSirebCommand.php`.

## Qué ofrece `SirebService`

*(30/09/2026)* Todo va con el token de Ibare. SIREB filtra por ese token: **solo
llegan servicios del SEDAG**, nunca los de otras dependencias.

| Método | Endpoint | Devuelve | Caché | Si SIREB no responde |
| --- | --- | --- | --- | --- |
| `catalogoCrudo($pagina)` | `GET /api/v1/catalogo/servicios?pagina=N&por_pagina=100&tarifas=todas` | **Una página**, tal cual: `data` + `meta` | No | `SirebException` |
| `servicios($refrescar)` | El mismo, **una llamada por página** | **Todos** los servicios con sus tarifas, en una lista | 10 min | `SirebException` |
| `serviciosSiResponde()` | Igual que `servicios()` | La lista, o `null` | 10 min | `null` |
| `servicio($id)` | `GET /api/v1/catalogo/servicios/{id}` | **Un** servicio con sus `tarifas`, o `null` si SIREB responde 404. Hoy no lo usa la emisión | No | `SirebException` |
| `tarifa($servicio, $tarifa)` | `GET /api/v1/catalogo/servicios/{servicio}/tarifas/{tarifa}` | **Una** tarifa con su `estado`, `monto` y el `servicio` adentro, o `null` si SIREB responde 404 | No: estado y precio de ahora | `SirebException` |
- **Mostrar** precios (escala, formularios) → `serviciosSiResponde()`: la
  pantalla abre igual con SIREB caído.
- **Cobrar u otorgar** → `PrecioSireb::de()`, que pide `tarifa($servicio_sireb,
  $tarifa_sireb)` y exige **tarifa Y servicio en `activo`** (02/10/2026; antes
  solo miraba el servicio y cobraba una tarifa dada de baja). Si SIREB no
  responde tiene que fallar, o se otorgaría un cupo sin precio. Pedirla **bajo**
  ese servicio evita cobrar con la tarifa de otro.
- **SIREB NO filtra por estado** (coordinado con Recaudaciones, 30/09/2026):
  manda los servicios activos y los dados de baja, cada uno con su `estado`.
  Decidir si se cobra es de Jichi: **solo `estado === 'activo'` es cobrable**, y
  cualquier otro valor —también uno que SIREB agregue después— no.
- **El catálogo trae TODAS las tarifas** (`tarifas=todas`, 01/10/2026). Sin el
  parámetro SIREB manda solo las liquidables; con él llegan también las
  inactivas, cada una con `estado` (`activo` / `inactivo`), `tarifario_estado` y
  `liquidable`. Jichi decide con **`liquidable === true`** y el servicio
  `activo`: así un tramo con tarifa dada de baja se sigue viendo con su precio,
  en vez de desaparecer.
- El id se pasa a minúscula antes de pedirlo: SIREB los manda así.
- `get()` devuelve `null` en un **404** (`SERVICIO_NO_ENCONTRADO`) en vez de
  tratarlo como «no responde»: son dos cosas distintas para quien llama.

## La escala

| Columna | Qué es |
| --- | --- |
| `kilos_min` / `kilos_max` | **Se quedan**: son del cupo (cuántos kg), no del precio |
| `servicio_sireb` | Id (uuid) del servicio en SIREB. **El mismo para todos los tramos** |
| `tarifa_sireb` | Id (uuid) de la tarifa del tramo. **Una por tramo**: de ahí sale el precio |

*(30/09/2026)* En SIREB la escala es **un solo servicio** («Autorización de Pesca
para Aprovechamiento Pesquero», código `p`, `modo_tarifa: categoria`) con **una
tarifa por tramo**. Los ids se guardan completos y en minúscula, tal como los
manda SIREB: la columna vieja de 30 caracteres los cortaba y ya no coincidían.
Los `rango_desde` / `rango_hasta` de SIREB llegan en null, así que los kilos y
la modalidad siguen siendo de Jichi.

El listado muestra la tarifa de cada tramo por su NOMBRE —servicio y etiqueta
de SIREB, con los ids en el `title`— y su precio de referencia, con
`VistaSireb::tarifasPorId()` + `describir()`. Si SIREB no responde o ya no tiene
la tarifa, vuelven los ids y el precio sale «—». El que vale es el que congela
cada cupo al otorgarse.

*(01/10/2026)* Debajo de la etiqueta va una insignia con el **estado de la
tarifa tal como lo manda SIREB**: **Activa** (verde) o **Inactiva** (gris)
—`sireb_estado` de `describir()`, prop `estado` de `CeldaSireb`—. El precio se
muestra aunque esté inactiva. Lo mismo en Tipos de carnet, Productos y
Aranceles.

`tarifasPorId()` da por cada tarifa `servicio`, `etiqueta`, `estado`, `monto`
(siempre el de SIREB) y `liquidable`. **El precio y si se puede cobrar van
separados**: `preciosPorTarifa()` —el de los formularios de carnet, faena y
guía— filtra por `liquidable`, y `describir()` manda las dos cosas
(`precio`, `sireb_liquidable`).

### El formulario del tramo

*(30/09/2026)* Alta y edición van en su **propia pantalla**
(`escala-formulario.tsx`; rutas `…/crear` y `…/{categoria}/editar`, con
`permiso:catalogos.editar`); la escala queda solo con la tabla. Los ids ya no
se escriben a mano: un **select agrupado por
servicio** (`<optgroup>`) lista cada tarifa con su etiqueta y su precio, y elegir
una llena `tarifa_sireb` **y** `servicio_sireb` (el de su grupo). Así no se puede
combinar una tarifa con un servicio ajeno. Lo arma
`CategoriaAprovechamientoController::formulario()` con `serviciosSiResponde()`,
recortado a `id`, `codigo`, `nombre`, `activo` y `tarifas` (`id`, `etiqueta`,
`monto`). *(01/10/2026)* **Solo llegan tarifas liquidables y servicios activos
que tengan alguna**: el filtro va en `serviciosParaSelect()`, no en React, y
vale igual para la escala, tipos de carnet, productos y aranceles.

| Caso | Qué hace el select |
| --- | --- |
| Tarifa que ya usa otro tramo | Deshabilitada, con «(ya la usa: 201 Kg Hasta 300 Kg)» —la capacidad, nunca el número de escala— (prop `tarifasUsadas`) |
| Servicio dado de baja o tarifa no liquidable | No aparece (lo descarta `serviciosParaSelect()`) |
| Tarifa guardada que ya no llega o dejó de ser liquidable | Opción «Tarifa actual (no está en SIREB)»: editar otro campo no la borra |
| SIREB no responde | Select deshabilitado con aviso; al editar se conservan los ids actuales |

Se muestran todos los servicios del SEDAG, sin filtrar por `modo_tarifa`.

### Qué ids tuvo antes un tramo

*(30/09/2026)* Al cambiar el servicio o la tarifa de un tramo, el par anterior
se agrega a `categorias_aprovechamiento.sireb_historial` (JSON), con `desde`,
`hasta` y `cambiado_por`. Lo hace el evento `updating` de
`CategoriaAprovechamiento`, así que vale para cualquier camino que edite el
tramo. Editar texto, kilos o régimen no agrega nada. Guarda solo **ids**: si
Recaudaciones borra una tarifa vieja, qué era (etiqueta, precio) ya no se puede
reconstruir desde Jichi. Lo cobrado con ella sí: cada cupo congela su `monto` y
su `sireb_tarifa_id`.

En la escala, el botón **Historial** (celeste, junto a editar) lleva a una
**vista aparte**, `GET /panel/catalogos/categorias-aprovechamiento/{categoria}`
(`CategoriaAprovechamientoController::show()`, pantalla `escala-historial.tsx`,
permiso `catalogos.ver`): la tarifa actual arriba y las anteriores debajo, cada
una con fechas y quién la cambió. La etiqueta y el precio de HOY de cada tarifa
los resuelve el servidor con `serviciosSiResponde()`; si ya no está en SIREB
dice «Ya no está en SIREB». Es una vista y no un panel sobre la tabla porque el
formulario de alta/edición ya ocupa ese lugar y los dos se pisaban.

### Tipos de carnet

*(30/09/2026)* Tienen lo mismo que la escala, salvo el alta, que no existe a
propósito (la lista sale de la resolución): `servicio_sireb` y `tarifa_sireb`
(nullable: un tipo sin tarifa lo marca «Sin tarifa»; `CatalogoSeeder` ya siembra los dos con la suya),
historial, edición en `tipos-carnet-formulario.tsx` y vista de historial en
`tipos-carnet-historial.tsx`.

**El precio del carnet ya viene de SIREB** (30/09/2026): `tipos_carnet` no tiene
`precio_bs`. `EmitirCarnetService::precioDe()` pide `servicio($servicio_sireb)`
—**fuera** de la transacción— y congela el monto en `carnets.monto` junto con
`sireb_tarifa_id`, al emitir y al corregir el borrador. `Carnet::montoACobrar()`
lee esa columna, así que los listados, el tablero y las fichas no llaman a SIREB.

| Situación | Qué pasa al emitir |
| --- | --- |
| El tipo no tiene tarifa | No se emite: «Elíjala en Catálogos › Tipos de carnet» |
| SIREB no responde | No se emite: «Recaudaciones no responde» |
| El servicio no existe o está de baja | No se emite |
| La tarifa ya no está en ese servicio | No se emite |
| Todo bien | Se emite con el monto de SIREB, congelado |

Las pantallas de crear/editar carnet muestran el precio de referencia con
`VistaSireb::preciosPorTarifa()` («—» si no hay); el que vale es el que congela
la emisión.

Los listados de Tipos de carnet, Productos —precio por kilo— y Aranceles muestran la tarifa igual que la escala: nombre, etiqueta y precio de referencia.

### Productos hidrobiológicos

*(30/09/2026)* Igual que la escala —con alta, edición e historial en pantallas
aparte (`productos-formulario.tsx`, `productos-historial.tsx`)— y sin
`precio_kg`: la tarifa **por kilo** sale de SIREB. Como en la escala, **una
tarifa por producto**: el select deshabilita las ocupadas y el Request las
rechaza (30/09/2026; antes se podían compartir). La diferencia es que el precio
lo congela la **guía**: `EmitirGuiaService` lo pide por
producto con `PrecioSireb` y lo guarda en `guia_detalles.precio_kg` +
`sireb_tarifa_id`. Sin precio de algún producto, la guía no se emite. El
formulario de la guía muestra el precio de referencia con
`VistaSireb::preciosPorTarifa()`, o «—».

### Aranceles: la faena

*(30/09/2026)* Los cobros que no cuelgan de un catálogo tienen su fila en
`aranceles_sireb`, una por `ConceptoArancel` —hoy solo `faena`—, y se editan en
**Catálogos › Aranceles** (sin alta ni baja: las filas las pone el seeder, `faena` ya con su tarifa). Cada
una lleva el select de tarifa y el historial de siempre.

- **Faena:** `EmitirFaenaService` pide el precio con `PrecioSireb` sobre la fila
  `faena`, **fuera** de la transacción, y lo congela en `permisos_faena.monto` +
  `sireb_tarifa_id`. Sin tarifa o sin SIREB no se emite. Se retiró
  `JICHI_FAENA_TARIFA_BASE` y `PermisoFaena::tarifaVigente()`.
- **La guía no tiene fila:** cobra por kilo con la tarifa de SIREB de cada
  producto (ver «Productos hidrobiológicos»).

**`App\Sireb\PrecioSireb`** es la consulta de precio al emitir, compartida por
autorización, carnet, faena y guía: pide `servicio()` una vez por servicio en la petición, exige el
servicio `activo` y la tarifa dentro de él, y si algo falla lanza
`SinPrecioException` con el motivo, que cada servicio envuelve en su excepción.

**Lo compartido entre los catálogos:** el trait `App\Traits\HistorialSireb`
(anota el cambio), `App\Sireb\VistaSireb` (arma el select, el historial y la
celda del listado) y los componentes `components/panel/catalogos/selector-tarifa-sireb.tsx`,
`historial-sireb.tsx` y `celda-sireb.tsx`. Un catálogo nuevo con tarifa de SIREB usa estas cinco
piezas y no copia nada. Un período «sin tarifa» también queda en el historial,
con sus fechas.

## Al otorgar

`OtorgarCupoService::otorgar()` pide el precio del tramo con
`PrecioSireb::de($servicio_sireb, $tarifa_sireb, exigirLiquidable: true)`
—directo a SIREB, sin caché— y lo **congelan** en `aprovechamientos_pesq.monto`,
junto con `sireb_tarifa_id`. La verificación completa está en
[Validación de la tarifa al emitir](#validación-de-la-tarifa-al-emitir).
`montoACobrar()` lee esa columna, así que los listados y el tablero no
llaman a SIREB. `editar()` no lo pide: desde el 03/10/2026 solo corrige la
embarcación, y el tramo y el monto quedan como se otorgaron.

| Situación | Qué pasa |
| --- | --- |
| Tarifa y servicio activos, tarifa liquidable | Se otorga con ese monto |
| La tarifa o el servicio no existen (404) | No se otorga: «su tarifa ya no está en SIREB» |
| El servicio está de baja | No se otorga: «su servicio está dado de baja en SIREB» |
| La tarifa está de baja | No se otorga: «su tarifa está dada de baja en SIREB» |
| La tarifa está activa pero no es liquidable | No se otorga: «su tarifa no se puede cobrar hoy en SIREB (tarifario no vigente)» |
| SIREB o Ibare no responden | No se otorga: «Recaudaciones no responde» |
| Se corrige el borrador | Se vuelve a pedir el precio |

*(02/10/2026)* **Ventanilla no ve esos motivos**: ve uno de dos avisos rojos,
arriba de la pantalla. Si SIREB no contestó: «no responde en este momento,
espere unos minutos». Si contestó que no se cobra (cualquiera de las otras
filas): «La escala de la autorización «1 Kg Hasta 100 Kg» no está habilitada para cobrar en este
momento. Elija otra escala o consulte con el encargado del sistema». El motivo
exacto va a `storage/logs` («SIREB rechazó el precio del tramo»). Lo distingue
`SinPrecioException::$sinRespuesta`.

El error nombra el tramo por su texto («601 Kg Hasta 800 Kg»), nunca por su
número. El select de crear muestra «texto del tramo — precio» con el
precio de referencia de `tarifasPorId()` (caché 10 min); el que vale es el que
pide el servicio al guardar.

*(01/10/2026)* Un tramo cuya tarifa **no es liquidable** en SIREB se lista igual,
con su precio, pero **deshabilitado** y con «(tarifa no disponible)»: el texto
es para ventanilla, no nombra a SIREB. Lo decide el campo `liquidable` de
`AprovechamientoController::tramosElegibles()` (`null` si SIREB no respondió:
ahí no se deshabilita nada). Editar no muestra el select: el tramo es fijo.

## Validación de la tarifa al emitir

*(02/10/2026)* Antes de guardar cualquiera de los cuatro documentos, Jichi
pregunta a SIREB por **esa** tarifa y decide si se puede cobrar. Todo pasa en
`PrecioSireb::de()`; los servicios de emisión solo lo llaman y traducen el error
a su excepción.

### El endpoint

```
GET /api/v1/catalogo/servicios/{servicio_sireb}/tarifas/{tarifa_sireb}
Authorization: Bearer <token de Ibare>     (lo pone SirebService::get())
```

Primero el id del servicio, después el de la tarifa: si la tarifa no pertenece a
ese servicio, SIREB responde 404, así que no se puede cobrar con la tarifa de
otro servicio. Respuesta (recortada):

```json
{"data": {
  "id": "01a0f096-…", "monto": "55.00", "etiqueta": "1 kg Hasta 100 kg",
  "estado": "activo", "tarifario_estado": "vigente", "liquidable": true,
  "servicio": {"id": "01a0f088-…", "codigo": "p", "estado": "activo",
               "estado_tarifario": "liquidable", …}
}}
```

Una sola llamada trae el estado de la tarifa **y** el de su servicio. Va sin
caché: lo que se congela en el documento es el precio y el estado de ese momento.

### El flujo

```
Funcionario aprieta «Registrar»
  └─ Controller ─▶ OtorgarCupoService::otorgar()
                     (o EmitirCarnetService, EmitirFaenaService, EmitirGuiaService)
       └─ DB::transaction
            ├─ bloquea al beneficiario, revisa reglas propias (una bolsa por persona…)
            └─ PrecioSireb::de(servicio, tarifa, exigirLiquidable?)
                 ├─ ids en null ............................ ✗ «no tiene tarifa de SIREB elegida»
                 ├─ SirebService::tarifa() ── GET … ──▶ SIREB
                 │    ├─ 401 → pide token nuevo y reintenta una vez
                 │    ├─ 401/403 otra vez, 5xx, sin red ... ✗ «Recaudaciones (SIREB) no responde»
                 │    └─ 404 → null ....................... ✗ «su tarifa ya no está en SIREB»
                 ├─ servicio.estado ≠ 'activo' ............ ✗ «su servicio está dado de baja en SIREB»
                 ├─ tarifa.estado ≠ 'activo' .............. ✗ «su tarifa está dada de baja en SIREB»
                 ├─ exigirLiquidable y liquidable ≠ true .. ✗ «su tarifa no se puede cobrar hoy…»
                 └─ ✓ {monto, tarifa_id}
            └─ create(): monto y sireb_tarifa_id CONGELADOS → estado PENDIENTE
```

Cada ✗ es una `SinPrecioException`. El servicio de emisión la envuelve en su
propia excepción (en la autorización: `CupoInvalidoException::sinPrecio()`, que
nombra el tramo y manda a Catálogos › Escala), la transacción se deshace y
**no queda nada escrito**. Ni en Jichi ni en SIREB: hoy Jichi solo **lee** de
SIREB.

### Qué exige cada documento

| Documento | Tarifa `activo` | Servicio `activo` | `liquidable` |
| --- | --- | --- | --- |
| Autorización de Pesca para Aprovechamiento Pesquero | Sí | Sí | **Sí** |
| Carnet | Sí | Sí | No (por ahora) |
| Permiso de faena | Sí | Sí | No (por ahora) |
| Guía (cada producto) | Sí | Sí | No (por ahora) |

`liquidable` es lo que SIREB calcula sobre el tarifario: una tarifa puede estar
`activo` y no ser cobrable si su tarifario no está `vigente`. Exigirlo a los
otros tres es cambiar el `false` por defecto en su llamada a `de()`.

### Detalles que importan

- **Solo `'activo'` vale**, por igualdad estricta (`VistaSireb::SERVICIO_ACTIVO`,
  `TARIFA_ACTIVA`): un estado nuevo que SIREB agregue mañana cuenta como no
  cobrable, que es el lado seguro.
- **Sin SIREB no se emite**, a propósito: la alternativa sería cobrar un precio
  viejo o inventado.
- **Caché por petición**: `PrecioSireb` recuerda cada par servicio|tarifa
  durante la petición, así que una guía que repite un producto pide una vez. Pero
  **cada tarifa distinta es una llamada** (~3 s contra `test.sireb`). Ver
  [PENDIENTES.md](../PENDIENTES.md).
- La llamada corre **dentro** de la transacción, con la fila del beneficiario
  bloqueada: si SIREB tarda, el bloqueo dura lo mismo. No molesta con una
  ventanilla, pero es lo primero que hay que mirar si aparecen esperas.
- El select del formulario ya deshabilita las tarifas no liquidables (caché de
  10 min); esta verificación es la que manda, porque en esos 10 minutos la tarifa
  pudo cambiar.

## La liquidación y el pago — los cuatro documentos

*(02/10/2026)* **El pago se hace en SIREB.** Autorización, carnet, faena y guía
registran su liquidación al crearse y se aprueban solos cuando SIREB la da por
pagada. Jichi no valida pagos —puede cargarlos, ver abajo—; no hay tabla
`pagos`, Caja ni `en_revision`. Si la liquidación vence, el documento sigue
pendiente y se genera otra (08/10/2026).

### Registrar la liquidación

Sigue la regla de la guía de integración de SIREB: **una clave por liquidación,
guardada ANTES de llamar, la misma en todos los reintentos**. Por eso va en dos
tiempos, y la llamada queda **fuera** de la transacción:

```
① verificar la tarifa (GET …/tarifas/{t})   ── no activa o SIREB caído → no se guarda nada
② TRANSACCIÓN: el documento + código + sireb_idempotency_key = Str::uuid()
               y sireb_estado = por_enviar → COMMIT
③ POST /liquidaciones con esa clave        ── red/timeout/5xx → 2 reintentos, 1 s, MISMA clave
④ 201/200 → sireb_liquidacion_id y sireb_codigo_publico, estado registrada
   sigue fallando → queda por_enviar: aviso amarillo; «Verificar pago» la reintenta
```

```
POST /api/v1/liquidaciones
Idempotency-Key: 9f3c2a1e-7b4d-4e8a-b1c2-5d6e7f8a9b0c
{"cliente": {"ci_nit": "6326340", "nombre_completo": "Óscar Lucas Aguilera Laureano"},
 "referencia_externa": "EFGT-96R4-CJ42-AHYJ",
 "items": [{"tarifa_id": "<sireb_tarifa_id congelada>", "cantidad": 1}]}
```

| Documento | `items` |
| --- | --- |
| Autorización, carnet, faena | Un ítem: su tarifa, cantidad 1 |
| Guía | Un ítem por renglón del cuadro D: la tarifa por kilo del producto, cantidad = kilos. El 50% de piscicultura no viaja (ver PENDIENTES) |

- **`cliente` va en línea**: SIREB lo busca por C.I. y lo crea si no existe.
  `ci_nit` es C.I. + complemento, sin expedido.
- **La referencia es el código de verificación** del documento, único global.
- **La clave NO es el código**: el código es uno por documento y la clave una por
  liquidación (corregir anula y registra otra, con clave nueva).
- **Por qué fuera de la transacción**: adentro, un timeout deshacía todo —clave
  incluida— aunque SIREB sí hubiera creado la liquidación, y el reintento salía
  con clave nueva: **una segunda deuda en SIREB**.
- **Vive en columnas `sireb_*` de cada documento** (trait `LiquidableSireb`).
  `sireb_envio` es un JSON de constancia: la clave, lo enviado, cuándo, la
  respuesta o el error, el pago confirmado y, al anular, el motivo. Corregir pisa
  esas columnas; la versión anterior queda en `auditorias`.
- **Corregir el borrador** con otra tarifa, otros kilos (guía) u otro monto:
  primero anula en SIREB (`PATCH …/anular`, con motivo) y después corrige y
  registra otra. **Eliminar**: primero anula. Si SIREB no anula —ya tiene un
  pago—, no se toca nada (aviso rojo, `SirebException::paraVentanilla()`).
- **Anular una liquidación `por_enviar`** la manda antes con su clave: pudo haber
  llegado aunque no volvió respuesta. Si SIREB contesta 422, con esa clave nunca
  la creó: se marca anulada sin llamar a anular.

### Cargar el pago desde Jichi

*(03/10/2026)* La ficha de los cuatro documentos ofrece **«Cargar pago»**
(`POST /panel/{documento}/{id}/cargar-pago`, permiso `{documento}.cargar-pago`).
`CargarPagoService::cargar()` **solo carga**: valida un encargado de SIREB y
aprueba `ConfirmarPagoService`, como siempre.

1. Consulta `GET /liquidaciones/{id}`. Se carga **solo** si está `pendiente` —ni
   vencida, ni pagada, ni anulada— **y sin ningún pago**. Si no, no carga y corre
   `verificar()` para poner la ficha al día (muestra el pago o aprueba).
2. `POST /liquidaciones/{id}/pago-manual` con `numero_boleta` (el «N° de
   transacción», 50), `entidad_bancaria` (100) y, si se adjuntó, `comprobante_url`
   (09/10/2026): el archivo se sube antes con `StorageController::file()` a `pagos/`
   y va su URL completa (`Archivos::url()`), no la ruta; si SIREB no lo toma, se
   borra. La ruta queda en `sireb_envio.comprobante`. Monto y fecha los pone SIREB. Sin `Idempotency-Key`: reintentar es
   seguro porque un segundo pago da `422 PAGO_YA_EXISTE`.
3. 201 → guarda el pago en `sireb_envio.pago` y la ficha lo muestra «Por validar».
   422 o sin respuesta → no carga y vuelve a consultar.

`puede_cargar_pago` (`LiquidableSireb::puedeCargarPago()`) mira la última consulta
guardada; la que manda es la del paso 1.

### Antes de anular: ¿ya está pagada?

*(03/10/2026)* `LiquidarSirebService::anular()` —el paso por el que pasan eliminar y
corregir de los cuatro documentos— consulta `GET /liquidaciones/{id}` antes de
pedir la anulación. Con `estado = pagada` o `pago.estado = confirmado` lanza
`SirebException::liquidacionPagada()` (código `LIQUIDACION_PAGADA`, propio de
Jichi); con cualquier otro pago cargado —en revisión—, `pagoEnRevision()`
(`PAGO_EN_REVISION`). No toca nada; el manejador de `bootstrap/app.php` lo muestra en rojo con
`paraVentanilla()`. Una `vencida` sin pago no frena: no se le pide la anulación a
SIREB —responde 422: ya no cobra nada— y se elimina. Al **eliminar**, los cuatro `destroy()` capturan esos códigos (`frenaPorPago()`) y
llaman a `ConfirmarPagoService::alNoPoderEliminar()`: corre la verificación
—aprueba y emite el recibo— y vuelven a la ficha con «No se eliminó: el pago ya
fue validado… quedó aprobado y se emitió la autorización» (o el carnet, el permiso de faena, la guía). Con el pago en revisión, la ficha queda mostrándolo
como «Por validar» y el aviso dice que todavía está en revisión. Si SIREB no
responde, también frena.

### Confirmar el pago

`ConfirmarPagoService::verificar()` pide `GET /liquidaciones/{id}`:

| SIREB dice | Jichi |
| --- | --- |
| `pendiente`, sin pago | Sigue pendiente: «todavía no se registró ningún pago» (con el código) |
| `pendiente`, pago `pendiente` | Sigue pendiente: «falta que lo validen en Recaudaciones» |
| `vencida` | **Sigue pendiente** (08/10/2026; antes pasaba a `no_pagado`) y la ficha ofrece **«Generar nueva liquidación»** (ver abajo). Nunca trae pago: vence porque no se pagó. Sigue ocupando el lugar y los kilos |
| `anulada` | Igual que `vencida`: sigue pendiente y ofrece «Generar nueva liquidación» |
| `pagada` | **Aprueba** (`Revisar*Service::aprobar()`, con sus reglas) **y emite el recibo** con el pago (N° de transacción, banco, fecha), en una transacción |

Cada consulta guarda el `pago` que manda SIREB en `sireb_envio.pago` —solo si
cambió: el comando corre cada 10 min y cada escritura audita— y la tarjeta lo
muestra como «Pago informado» (n° de transacción, banco, fecha, monto, «Por validar» /
«Validado») hasta que sale el recibo (03/10/2026). Sin pago, la tarjeta dice «Pago cargado: No» si ya se
preguntó (`pago_consultado`: la clave `pago` existe en `sireb_envio`) o «Sin
verificar» si nunca; con recibo suma «Validado el». **La imagen del comprobante
NO se puede mostrar:** el contrato de SIREB (`/docs/api/openapi.yaml`) no la
expone; el `Pago` trae solo `tipo_pago`, `monto_pagado`, `estado`, `fecha_pago`,
`numero_boleta`, `entidad_bancaria` y `fecha_validacion`.

Si una regla de Jichi lo frena al aprobar (autorización revocada, kilos), queda
pendiente con ese motivo. Lo disparan el botón **«Verificar pago»** de la ficha
(`POST /panel/{documento}/{id}/verificar-pago`, permiso `{documento}.verificar-pago`) y
`jichi:verificar-pagos` cada 10 minutos (`routes/console.php`; necesita el cron
de Laravel). *(08/10/2026)* Y el **portal**: al abrir el inicio o «En curso», el
titular dispara `VerificarPagoJob` por cada trámite abierto con liquidación viva —como
mucho una vez cada 2 min por trámite (candado en caché)—, con `dispatchAfterResponse()`:
corre en el mismo php-fpm después de mandar la página, sin trabajador de cola (en
Coolify hay un solo contenedor). El refresco de «En curso» trae el resultado. En desarrollo, `composer run dev` levanta
el programador (`schedule:work`, registrado en `AppServiceProvider`).

**Para no cargar el servidor** (08/10/2026):

| Protección | Dónde |
|---|---|
| Un **candado por trámite**: el botón, el comando y el portal no lo consultan a la vez | `ConfirmarPagoService::verificar()` (`candado-pago:*`, 120 s) |
| **Una consulta automática cada 2 min por trámite**, compartida entre el portal y el comando | `ConfirmarPagoService::reservarConsulta()` (`consulta-pago:*`) |
| **Interruptor**: si SIREB no conecta, lo automático no insiste por 2 min (el botón manual sí) | `SirebService::sinRespuestaReciente()`; además `connectTimeout(5)` |
| **Topes del comando**: 150 consultas (`--maximo`) o 8 min por pasada, de a 100 filas (`lazyById`); `withoutOverlapping(15)` | `VerificarPagosCommand`, `routes/console.php` |
| **El job del portal**: después de la respuesta, sin cola; acotado por el timeout de SIREB (5 s para conectar, 10 en total) | `VerificarPagoJob` |

Probado con SIREB simulado: el tope consulta 1 con `--maximo=1`; con SIREB caído,
0; lo recién consultado por el portal se saltea; con el candado tomado, la segunda
consulta no llega a SIREB.

| Pieza | Qué hace |
| --- | --- |
| `SirebService::registrarLiquidacion()` / `liquidacion()` / `anularLiquidacion()` | El HTTP. Las escrituras reintentan ante red, timeout o 5xx con `Http::retry()` (`REINTENTOS = 2`, 1 s; un 4xx no). Todo pasa por `enviar()` (token + reintento ante 401) |
| `LiquidarSirebService` | `preparar()` (dentro de la transacción), `enviar()`, `enviarSinFrenar()`, `anularSiCambia()` y `anular()` (fuera) |
| Trait `LiquidableSireb` | Columnas y casts, `titularSireb()`, `itemsSireb()`, `registradoEnSireb()`, `porPagar()`, `resumenSireb()` y la relación `recibo` |
| `ConfirmarPagoService` | Consulta, aprueba y emite el recibo; guarda en `sireb_envio.consulta` el estado de SIREB |
| `RenovarLiquidacionService` | «Generar nueva liquidación»: la vencida al historial y otra con la tarifa vigente del catálogo |
| `EstadoLiquidacionSireb` | `por_enviar` / `registrada` / `anulada` |
| `TarjetaRecaudaciones` (React) | La tarjeta de las cuatro fichas: estado, código de pago, recibo y «Verificar pago» |
| Flash `aviso` | El toast amarillo: se hizo, pero falta algo |

### Generar nueva liquidación (08/10/2026)

Una vencida sin pago no se cobra más. El botón **«Generar nueva liquidación»** de la
tarjeta (`POST /panel/{documento}/{id}/renovar-liquidacion`, permiso
`{documento}.renovar-liquidacion`) la cierra y pide otra. Lo hace
`RenovarLiquidacionService::renovar()`, **solo a mano**: el comando no renueva, o un
trámite abandonado generaría una deuda nueva cada 5 días.

1. Vuelve a consultar SIREB. Pagada → aprueba en vez de renovar. Sin vencer ni
   anular → no renueva.
2. **La tarifa se relee del CATÁLOGO, no del trámite**: si SIREB dio de baja la
   tarifa y creó otra, la unidad la elige en el catálogo y el trámite toma la nueva.
   Cada servicio la busca como al emitir (`OtorgarCupoService::precioDe()`,
   `EmitirCarnetService::precioDe()`, `EmitirFaenaService::precioDeLaFaena()`,
   `EmitirGuiaService::precioDelProducto()` por renglón). De baja → no toca nada y
   el aviso dice qué catálogo corregir.
3. En una transacción, con la fila bloqueada: la vigente pasa a `vencida` en
   `sireb_historial`, se escriben el `monto` y el `sireb_tarifa_id` nuevos (en la
   guía, `precio_kg`, `sireb_tarifa_id` e `importe_total` de cada renglón y el
   monto vuelto a sumar) y `preparar()` deja la clave nueva.
4. Después del commit, `enviar()`. Si SIREB no responde, queda «por enviar» y
   «Verificar pago» la reintenta con la misma clave.

**Antes de confirmar, la ventana muestra el monto**: al abrirse pide la prop
`cotizacion_renovacion` (`Inertia::optional`, partial reload: la ficha no consulta
a SIREB al cargar) y `RenovarLiquidacionService::cotizar()` la calcula con el MISMO
`precioVigente()` que cobra —lo que se ve es lo que se cobra—. **Solo lee**: no
escribe nada. Si la tarifa ya no está vigente, muestra el motivo y «Sí, generar»
queda deshabilitado.

El estado del trámite no cambia nunca. La vencida no se pide anular en SIREB.

**Decide SOLO el estado de la liquidación** (08/10/2026): `pagada` aprueba —sin
exigir que el pago venga `confirmado`; sin detalle del pago, el recibo sale con el
monto de la liquidación—; `vencida` y `anulada` no tocan el estado y ofrecen generar
otra. **Una vencida nunca tiene pago**: en SIREB vence porque no se pagó, así que
generar otra no puede cobrar dos veces.

**Con la liquidación vencida o anulada la ficha ofrece este botón y «Verificar
pago»**, pero no «Cargar pago» ni el QR (`liquidacionCaida()`, `puedeCargarPago()`).
«Verificar pago» sí vuelve a consultar a SIREB; el comando de cada 10 minutos las
saltea. Cargar pago lo frena además el servidor: solo carga sobre una
liquidación `pendiente` y sin pago.

**El historial** (`sireb_historial`) lo escribe `LiquidarSirebService`: `enviar()`
agrega la entrada al registrarse —lo que SIREB no creó no se anota— y la cierran
`anular()` (`anulada`, o `vencida` si ya había vencido), la renovación (`vencida`) y
`ConfirmarPagoService` al aprobar (`pagada`). La tarjeta muestra las cerradas en
«Liquidaciones anteriores».

Probado el 08/10/2026 en una SQLite descartable con SIREB simulado en memoria:
173 comprobaciones sobre los cuatro trámites (pedido, vencida con y sin pago,
tarifa de baja, tarifa nueva, doble clic, segunda renovación, pago y recibo,
SIREB caído al consultar y al registrar, eliminar).

Probado el 02/10/2026 con SIREB simulado (`Http::fake`) dentro de transacciones
deshechas: los cuatro documentos registran su liquidación, se aprueban al darlos
por pagados, emiten su recibo con la boleta y no duplican al reintentar; corregir
y eliminar anulan. La consulta real (`GET /liquidaciones/{id}`) se probó contra
test.sireb. **Falta pagar una de punta a punta en test.sireb.**

## El registro de cada llamada — `storage/logs/sireb-AAAA-MM-DD.log`

Desde el 09/10/2026 cada pedido a SIREB **y el del token a Ibare** queda en un
archivo propio, uno por día, que se guarda 90 días (`LOG_SIREB_DIAS`). Lo escribe
`SirebService::registrar()`, llamado desde `pedir()` y `token()`: no hay forma de
hablar con SIREB sin pasar por ahí.

| Nivel | Cuándo | Ejemplo |
| --- | --- | --- |
| `INFO` | contestó bien (2xx) | `OK GET …/liquidaciones/7f3a… → 200 en 840 ms` |
| `WARNING` | contestó que no (4xx/5xx) | `ERROR POST …/oauth/token → 401 en 202 ms` |
| `ERROR` | no contestó (timeout, sin conexión) | `SIN RESPUESTA POST …/oauth/token en 2203 ms` |

Cada línea lleva `quien` (el funcionario, o «automático» si fue un comando), lo
`enviado` y la `respuesta` de SIREB tal cual —o el `error` de cURL si no hubo—.
`client_secret`, `access_token`, `refresh_token` y `password` salen como `***`;
una respuesta de más de 20.000 caracteres (una página del catálogo) se recorta.
Además van ahí los dos avisos con contexto del trámite: `LiquidarSirebService`
(«NO SE REGISTRÓ la liquidación de …») y `OtorgarCupoService` («SIN PRECIO para
el tramo …»).

Un reintento de `Http::retry()` no deja una línea por intento: queda la respuesta
final, con el tiempo total.

**Se lee desde el panel**: Seguridad › Registro SIREB (`RegistroSirebController`,
permiso `sireb.ver`; descargar el archivo, `sireb.exportar`). `App\Sireb\RegistroSireb`
parte cada línea; si cambia el formato del mensaje en `registrar()`, cambia
también su expresión regular, o las filas pasan a salir como «Aviso».

## Lo que falta

- Probar de punta a punta contra test.sireb: cargar el pago desde Jichi, validarlo
  allá, ver la aprobación; y dejar vencer una liquidación sin pago.
- Confirmar con Recaudaciones qué pasa si la liquidación vence con un pago en
  revisión: ¿sigue `pendiente` o pasa a `vencida`? Hoy Jichi la deja pendiente.
- Una liquidación anulada **desde SIREB** deja el documento pendiente sin
  salida automática: se corrige o se elimina a mano.
- El plazo de pago (`fecha_vencimiento` de la liquidación) no se muestra en la
  ficha: está en `sireb_envio.respuesta`.
- El cron de Laravel en el servidor, o nada se aprueba solo.
- La guía de piscicultura: su 50% no viaja a SIREB.
- Ver la imagen del comprobante: SIREB tiene que exponerla (un `comprobante_url`
  en el `Pago` o un endpoint de descarga). Pedirlo al equipo de Recaudaciones.
