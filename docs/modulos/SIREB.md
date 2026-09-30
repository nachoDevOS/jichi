# SIREB — los precios vienen de Recaudaciones

> ⚠️ **30/09/2026 — DESCONECTADO.** `SirebService` quedó solo con `token()` y
> `catalogoCrudo()` (la respuesta de SIREB tal cual), para probar la conexión con
> `php artisan jichi:sireb`. El precio NO se pide: otorgar o corregir una
> autorización falla con «La conexión con Recaudaciones (SIREB) está en ajuste»,
> y la escala y el formulario muestran los tramos sin precio. Lo de abajo describe
> cómo funcionaba el 29/09 y sirve de guía para volver a conectarlo.
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

## La escala

| Columna | Qué es |
| --- | --- |
| `kilos_min` / `kilos_max` | **Se quedan**: son del cupo (cuántos kg), no del precio |
| `servicio_sireb` | El código del servicio en SIREB. **Uno por tramo** |

Cada tramo es **un servicio propio** en SIREB con **una sola tarifa general**
(sin sucursal ni categoría). Si un servicio tiene cero o varias, Jichi no elige:
falla con un mensaje que dice cuál y por qué. El Request de la escala comprueba
el código contra SIREB al guardar; si SIREB no responde, deja guardar y el
control de verdad lo hace el otorgamiento.

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
`catalogoSiResponde()`, que devuelve null en vez de fallar, y abren igual.

## Lo que falta

- Carnet (`tipos_carnet.precio_bs`), faena (`JICHI_FAENA_TARIFA_BASE`) y guía
  (`productos_hidrobiologicos.precio_kg`) con el mismo esquema.
- El cobro sigue en Jichi (pagos, boletas, recibo). Registrar la liquidación en
  SIREB y usar su `codigo_publico` es otra etapa: ver el análisis de las
  diferencias (una boleta por liquidación, vencimiento, quién valida).
