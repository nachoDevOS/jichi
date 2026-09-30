# SIREB — los precios vienen de Recaudaciones

> ⚠️ **30/09/2026 — EN RECONEXIÓN.** `SirebService` ya lee servicios y tarifas
> (ver «Qué ofrece `SirebService`»), pero todavía **no está enchufado** a la
> escala ni al otorgamiento: otorgar o corregir una autorización falla con «La
> conexión con Recaudaciones (SIREB) está en ajuste», y la escala muestra los
> tramos sin precio. «Al otorgar», más abajo, describe cómo funcionaba el 29/09 y
> sirve de guía para volver a conectarlo.
>
> 29/09/2026. Hoy lo usa **solo la autorización**. Carnet, faena y guía siguen
> con su precio propio en Jichi, y pasan a este esquema en las etapas siguientes.

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
  es otra cosa: va solo al registrar liquidaciones, que Jichi todavía no hace.
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
| `catalogoCrudo($pagina)` | `GET /api/v1/catalogo/servicios?pagina=N&por_pagina=100` | **Una página**, tal cual: `data` + `meta` | No | `SirebException` |
| `servicios($refrescar)` | El mismo, **una llamada por página** | **Todos** los servicios con sus tarifas, en una lista | 10 min | `SirebException` |
| `serviciosSiResponde()` | Igual que `servicios()` | La lista, o `null` | 10 min | `null` |
| `servicio($id)` | `GET /api/v1/catalogo/servicios/{id}` | **Un** servicio con sus `tarifas`, o `null` si SIREB responde 404 | No: precio de ahora | `SirebException` |
- **Mostrar** precios (escala, formularios) → `serviciosSiResponde()`: la
  pantalla abre igual con SIREB caído.
- **Cobrar u otorgar** → `servicio($servicio_sireb)` y buscar `tarifa_sireb`
  en sus `tarifas`: si SIREB no responde tiene que fallar, o se otorgaría un cupo
  sin precio. Buscarla **dentro** de ese servicio evita cobrar con la tarifa de
  otro.
- **SIREB NO filtra por estado** (coordinado con Recaudaciones, 30/09/2026):
  manda los servicios activos y los dados de baja, cada uno con su `estado`.
  Decidir si se cobra es de Jichi: **solo `estado === 'activo'` es cobrable**, y
  cualquier otro valor —también uno que SIREB agregue después— no. Hoy la
  **tarifa no trae estado**: queda por confirmar con Recaudaciones que lo mande.
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

### El formulario del tramo

*(30/09/2026)* Alta y edición van en su **propia pantalla**
(`escala-formulario.tsx`; rutas `…/crear` y `…/{categoria}/editar`, con
`permiso:catalogos.gestionar`); la escala queda solo con la tabla. Los ids ya no
se escriben a mano: un **select agrupado por
servicio** (`<optgroup>`) lista cada tarifa con su etiqueta y su precio, y elegir
una llena `tarifa_sireb` **y** `servicio_sireb` (el de su grupo). Así no se puede
combinar una tarifa con un servicio ajeno. Lo arma
`CategoriaAprovechamientoController::formulario()` con `serviciosSiResponde()`,
recortado a `id`, `codigo`, `nombre`, `activo` y `tarifas` (`id`, `etiqueta`,
`monto`).

| Caso | Qué hace el select |
| --- | --- |
| Tarifa que ya usa otra escala | Deshabilitada, con «(escala N)» (prop `tarifasUsadas`) |
| Servicio dado de baja | Su grupo dice «— de baja» y sus tarifas no se pueden elegir |
| Tarifa guardada que ya no llega | Opción «Tarifa actual (no está en SIREB)»: editar otro campo no la borra |
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

## Al otorgar

`OtorgarCupoService::otorgar()` y `editar()` piden el precio con
`SirebService::precioDe()` y lo **congelan** en `aprovechamientos_pesq.monto`,
junto con `sireb_tarifa_id`. `montoACobrar()` lee esa columna, así que los
listados, la Caja y el tablero no llaman a SIREB.

| Situación | Qué pasa |
| --- | --- |
| SIREB da un precio | Se otorga con ese monto |
| El código no existe en SIREB | No se otorga: «no está en el catálogo del SEDAG» |
| El servicio tiene varias tarifas | No se otorga: «necesita exactamente una» |
| SIREB o Ibare no responden | No se otorga: «Recaudaciones no responde» |
| Se corrige el borrador | Se vuelve a pedir el precio |

**Sin SIREB no se otorga, a propósito**: ninguna tarifa se escribe a mano. Las
pantallas que solo muestran el precio (la escala y los formularios) usan
`serviciosSiResponde()`, que devuelve null en vez de fallar, y abren igual.
`SirebService::precioDe()` ya no existe; el `precioDe()` privado de
`OtorgarCupoService` falla a propósito («en ajuste»). Al reconectar, el precio
sale de la tarifa `tarifa_sireb` dentro de `servicio($servicio_sireb)`.

## Lo que falta

- Carnet (`tipos_carnet.precio_bs`), faena (`JICHI_FAENA_TARIFA_BASE`) y guía
  (`productos_hidrobiologicos.precio_kg`) con el mismo esquema.
- El cobro sigue en Jichi (pagos, boletas, recibo). Registrar la liquidación en
  SIREB y usar su `codigo_publico` es otra etapa: ver el análisis de las
  diferencias (una boleta por liquidación, vencimiento, quién valida).
