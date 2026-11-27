# API del portal — `/api/v1/portal`

La cuenta del portal `/mi-cuenta` desde la app BeniDigital. Es **la misma cuenta**:
la C.I. y la clave temporal que da ventanilla con «Dar acceso al portal». Lo que se
cambia en la app vale en la web, y al revés.

Cómo está armada la API, tokens, reglas comunes, errores y cómo probar:
[README.md](README.md). Seguridad y checklist de producción: [seguridad.md](seguridad.md).
Reglas del portal: [../modulos/PORTAL.md](../modulos/PORTAL.md) y REGLAS-NEGOCIO, Paso 8.

**Este archivo es el contrato con la app.** El JSON de cada respuesta lo arman los Resources
de `app/Http/Resources/ApiApp/`: cambiar un campo sin actualizar la app la rompe. Un
endpoint nuevo se agrega a la tabla y con su sección (ver README § 9).

**Alcance (25/11/2026):** pedir faena y guía, y pagarlas por QR. **El QR se paga directo en
SIREB** (ver [El pago del QR](#el-pago-del-qr-en-sireb-no-acá)); acá solo se confirma la
aprobación. «Mi perfil» y el inicio volvieron el 25/11/2026 (README § 2.3). Papeles y pagos no tienen ruta en
la API: dan 404 (ver README § 2).

Todo lo que pide token tiene además un tope de **60 pedidos por minuto por cuenta**, y el token
**vence a los 90 días sin uso** (README § 5.2 y § 6).

| Método | Ruta | Token | Con clave temporal |
| --- | --- | :-: | :-: |
| `POST` | [`/sesion`](#post-sesion--entrar) | — | — |
| `GET` | [`/yo`](#get-yo--quién-soy) | ✔ | ✔ |
| `PUT` | [`/clave`](#put-clave--cambiar-la-contraseña) | ✔ | ✔ |
| `DELETE` | [`/sesion`](#delete-sesion--salir) | ✔ | ✔ |
| `GET` | [`/perfil`](#get-perfil--mi-perfil-vuelve-el-25112026) | ✔ | ✘ `403 clave_temporal` |
| `GET` | [`/perfil/foto`](#get-perfilfoto--la-foto-del-carnet) | ✔ | ✘ `403 clave_temporal` |
| `GET` | [`/inicio`](#get-inicio--el-inicio-de-la-app-vuelve-el-25112026) | ✔ | ✘ `403 clave_temporal` |
| `GET` | [`/en-curso`](#get-en-curso--lo-pendiente-de-pago) | ✔ | ✘ `403 clave_temporal` |
| `GET` | [`/pagos`](#get-pagos--mis-pagos-vuelve-el-25112026) | ✔ | ✘ `403 clave_temporal` |
| `GET` | [`/recibos/{codigo}/pdf`](#get-reciboscodigopdf--bajar-el-pdf-del-recibo-26112026) | ✔ | ✘ `403 clave_temporal` |
| `GET` | [`/tramites/{codigo}/estado`](#get-tramitescodigoestado--ya-quedó-aprobado) | ✔ | ✘ `403 clave_temporal` |
| `GET` | [`/faenas/nueva`](#get-faenasnueva--antes-de-pedir-un-permiso-de-faena) | ✔ | ✘ `403 clave_temporal` |
| `POST` | [`/faenas`](#post-faenas--pedir-un-permiso-de-faena) | ✔ | ✘ `403 clave_temporal` |
| `GET` | [`/guias/nueva`](#get-guiasnueva--antes-de-pedir-una-guía) | ✔ | ✘ `403 clave_temporal` |
| `GET` | [`/autorizaciones/{codigo}`](#get-autorizacionescodigo--el-pescador-que-le-vendió) | ✔ | ✘ `403 clave_temporal` |
| `GET` | [`/autorizaciones/{codigo}/foto`](#get-autorizacionescodigofoto--la-foto-del-pescador-27112026) | ✔ | ✘ `403 clave_temporal` |
| `POST` | [`/guias`](#post-guias--pedir-una-guía-de-transporte) | ✔ | ✘ `403 clave_temporal` |

---

## El objeto `cuenta`

Lo arma `App\Http\Resources\Api\Portal\CuentaResource`. **Cambiarlo rompe la app.**

```json
{
  "nombre": "Ignacio Molina Guzman",
  "ci": "7633685 BN",
  "debe_cambiar_password": true,
  "ultimo_acceso": "2026-11-19T08:30:00-04:00"
}
```

| Campo | Tipo | Qué es |
| --- | --- | --- |
| `nombre` | string | Nombre completo, como en el padrón |
| `ci` | string | Para mostrar: número, complemento y expedido |
| `debe_cambiar_password` | bool | `true` mientras use la clave temporal: la app manda a cambiarla |
| `ultimo_acceso` | string \| null | El que el panel muestra como «Último ingreso» |

---

## `POST /sesion` — entrar

Sin token. `throttle:10,1` por IP, y 5 intentos por C.I. + IP (los mismos que la web).

```json
{ "ci": "7633685", "password": "STZYX6A4", "dispositivo": "BeniDigital · android" }
```

| Campo | Regla |
| --- | --- |
| `ci` | Obligatorio. Acepta puntos, complemento y expedido (`7.633.685`, `7633685-1A BN`) |
| `password` | Obligatorio |
| `dispositivo` | Opcional, hasta 100. Nombre del token: en qué celular entró |

**201**

```json
{ "token": "1|Xk3f…", "cuenta": { …objeto cuenta… } }
```

**422** — clave errónea, C.I. sin cuenta o cuenta desactivada: **el mismo mensaje** para
los tres, a propósito.

```json
{
  "message": "La cédula o la contraseña no son correctas, o su acceso no está habilitado.",
  "errors": { "ci": ["La cédula o la contraseña no son correctas, o su acceso no está habilitado."] }
}
```

Al sexto intento fallido: `errors.ci[0]` = `"Demasiados intentos. Vuelva a intentar en N segundos."`

## `GET /yo` — quién soy

La app lo pide al abrirse para saber si el token guardado sigue valiendo.

**200** `{ "cuenta": { …objeto cuenta… } }` · **401** si el token ya no vale.

## `PUT /clave` — cambiar la contraseña

`throttle:10,1`. Las reglas son las de la web (`CambiarClaveRequest`).

```json
{ "actual": "STZYX6A4", "password": "pesca2026", "password_confirmation": "pesca2026" }
```

| Campo | Regla |
| --- | --- |
| `actual` | La que tiene ahora (la temporal, la primera vez) |
| `password` | 8 o más, al menos una letra y un número, **solo letras y números**, distinta de la actual |
| `password_confirmation` | Igual a `password` |

**200** `{ "cuenta": { …, "debe_cambiar_password": false } }`. Los **otros** celulares de la
cuenta salen (su token se borra); el que la cambió sigue.

**422** con `errors.actual` o `errors.password`, por ejemplo
`"Use solo letras y números, sin signos ni espacios."`

## `DELETE /sesion` — salir

Borra el token de **ese** celular. **204** sin cuerpo.

## `GET /perfil` — «Mi perfil» (vuelve el 25/11/2026)

Lo mismo que «Mis datos» del portal web: los dos salen de `App\Support\ApiApp\PerfilPortal`.
Lo arma `PerfilResource`. Solo lectura: se corrigen en ventanilla.

**200**

```json
{
  "perfil": {
    "datos": {
      "nombre": "Ignacio Molina Guzman",
      "nombres": "Ignacio",
      "apellido_paterno": "Molina",
      "apellido_materno": "Guzman",
      "apellido_casado": null,
      "documento_identidad": "7633685 BN",
      "expedido": "Beni",
      "tiene_foto": true,
      "fecha_nacimiento": "1997-03-08",
      "edad": 29,
      "genero": "masculino",
      "nacionalidad": "Boliviana",
      "telefono": "67285914",
      "email": "correo@ejemplo.com",
      "direccion": "trinidad",
      "ciudad": "Trinidad",
      "provincia": "Cercado",
      "registrado": "2026-10-26T08:58:00-04:00"
    },
    "actividades": [
      { "actividad": "Pescador", "asociacion": "Asociación …", "vence": "2026-12-31" }
    ],
    "cuenta": { "creada": "2026-11-19T08:30:00-04:00", "ultimo_acceso": "2026-11-19T09:10:00-04:00" }
  }
}
```

- `actividades`: una por **carnet vigente** hoy (`Pescador` o `Comercializador`).
- **No trae `foto_url`**, a propósito: la URL pública sale de `APP_URL` (en local,
  `127.0.0.1`, que el celular no alcanza) y dejaría la foto a la vista de cualquiera.
  Trae `tiene_foto`; la foto se pide aparte con el token.

## `GET /perfil/foto` — la foto del carnet

Los bytes de la imagen (`Content-Type` real, `Cache-Control: private`). Sin id en la URL:
solo la del dueño del token. **404** si no tiene foto.

La app la pide con el mismo token y la dibuja con `Image.memory` (así anda igual en el
celular y en la web, donde `Image.network` no siempre manda encabezados).

## El objeto `documento`

Autorización, carnet, permiso de faena o guía, como lo arma `App\Support\ResumenPortal`
(lo mismo que ve la web). Lo pasa `DocumentoResource`, que **quita** las URLs de la web
(`verificar`, `vista_previa`, `descargar`: piden la sesión del navegador) y agrega
`puede_descargar`. **Estado, vigencia, deuda y textos vienen calculados: la app no decide.**

Comunes a todos:

| Campo | Tipo | Qué es |
| --- | --- | --- |
| `clase` | string | `aprovechamiento`, `carnet`, `faena` o `guia` |
| `tipo` | string | Nombre del documento («Guía Única de Transporte») |
| `codigo` | string \| null | Código de verificación de 16 caracteres (el del QR) |
| `codigo_pago` | string \| null | Con este código se paga en SIREB. Null si ya no cobra |
| `puede_pagar_qr` | bool | Mostrar el botón «Pagar con QR»: liquidación sin pago cargado y con código. El QR se pide con `codigo` (parte B) |
| `pago` | string \| null | Del trámite abierto: `sin_pago`, `revision`, `validado` o `caida` |
| `estado_etiqueta` | string | «Pendiente», «Aprobada», «Sin efecto»… |
| `estado_color` | string | `sky`, `emerald`, `rose`… (la paleta de la web) |
| `origen_tramite` | string \| null | *(26/11/2026)* Desde dónde se registró: `ventanilla`, `portal_web` o `app`. Lo pone el sistema según la puerta del pedido; lo pedido desde la app trae siempre `app` |
| `origen_tramite_etiqueta` | string \| null | El mismo dato para mostrar: «Ventanilla SEDAG», «Portal web» o «App BeniDigital» |
| `vigente` | bool | Vale hoy |
| `en_tramite` | bool | Abierto: espera el pago |
| `siguiente_paso` | string \| null | Qué le falta, dicho para el titular |
| `etapa` | string \| null | `pago` si está abierto |
| `situacion` | string \| null | Solo para la web («Mis papeles»). En `/en-curso` siempre es null |
| `vence_el` | string \| null | Día (guía: momento ISO 8601) |
| `dias_restantes` | int \| null | Solo de lo vigente |
| `motivo_baja` | string \| null | Por qué ya no vale |
| `debe` | number | Lo que falta pagar |
| `puede_descargar` | bool | Vigente hoy. La app no descarga PDF (fuera del alcance): se ignora |

Propios de cada clase: autorización (`escala`, `volumen_total_kg`, `kilos_disponibles`),
carnet (`tipo_actor`, `asociacion`, `cupo_kg`), faena (`numero`, `kilos`, `embarcacion`,
`region`) y guía (`numero`, `ruta`, `kilos`, `pescado_de`, `autorizacion_numero`).

## `GET /inicio` — el inicio de la app (vuelve el 25/11/2026)

Los datos de `App\Support\ApiApp\PantallaPortal::inicio()`, los mismos que la web. Como la web,
encola la consulta de sus pagos a SIREB (`VerificarPagoJob`).

```json
{
  "inicio": {
    "nombre": "Ignacio Molina Guzman",
    "deuda": 50,
    "vigentes": [ { "clase": "carnet", "...": "documento" } ],
    "abiertos": [ { "clase": "guia", "...": "documento" } ],
    "tramites_disponibles": [
      { "clase": "faena", "titulo": "Permiso de Faena", "disponible": true,
        "detalle": "Cada permiso sale por 1.000 kg.", "canal": "en_linea" }
    ],
    "pagado": { "gestion": 2026, "total": 530, "recibos": 2 }
  }
}
```

- `vigentes`: lo que vale hoy, lo que vence antes primero.
- `abiertos`: lo pendiente de pago («Qué hacer ahora»).
- `pagado`: lo pagado en la gestión actual (de `PantallaPortal::pagos`), para la entrada «Mis pagos».
- `tramites_disponibles.canal`: `en_linea` o `ventanilla`, de `App\Support\TramitesDisponibles::EN_LINEA`
  (la misma lista para la web y la app). Hoy: `faena` y `guia` en línea.

- La app arma con esto su inicio, como el de la web: cifras, «Qué hacer ahora», «Vigente hoy»
  (la autorización con `kilos_disponibles` y `volumen_total_kg`) y «Por pagar». Las tarjetas de
  «Pedir en línea» siguen preguntando a `/faenas/nueva` y `/guias/nueva`, que dan el motivo exacto.

## `GET /pagos` — «Mis pagos» (vuelve el 25/11/2026)

Sus recibos de una gestión: `App\Support\ApiApp\PantallaPortal::pagos()`, lo mismo que «Mis pagos»
del portal web. `?gestion=2025` elige el año; una gestión sin recibos, inválida o ausente da la
actual. Solo los suyos (`PagosTest::test_solo_ve_sus_recibos`).

```json
{
  "pagos": {
    "gestion": 2026,
    "gestiones": [2026, 2025],
    "total_pagado": 530,
    "recibos": [
      {
        "numero": "000009",
        "clase": "aprovechamiento",
        "concepto": "Autorización de Pesca para Aprovechamiento Pesquero - 2000 kg",
        "monto_total": 500,
        "numero_boleta": "SIM-AOHOKFXKPCGS",
        "entidad_bancaria": "Pago por QR de prueba",
        "fecha_pago": "2026-11-25",
        "emitido_en": "2026-11-25T14:30:37-04:00",
        "codigo": "R7KQ-2M9X-ABCD-EFGH",
        "verificar": "https://pescador.beni.gob.bo/verificar?code=R7KQ2M9XABCDEFGH"
      }
    ]
  }
}
```

- El más nuevo primero. `fecha_pago` es un **día** (el de la boleta), no un instante.
- `clase`: `aprovechamiento`, `carnet`, `faena` o `guia` (de qué trámite es); `null` si es otro.
- `codigo` y `verificar`: el código legible y la dirección del QR de verificación, **los mismos del
  PDF** (`QrVerificacion::url`). La app dibuja con ellos el recibo digital.
- No lleva `descargar`: la descarga del PDF pide sesión de navegador (`/mi-cuenta`).

## `GET /recibos/{codigo}/pdf` — bajar el PDF del recibo (26/11/2026)

El **PDF oficial** del recibo, el que se guardó en S3 al emitirse (el mismo que imprime ventanilla).
No viajan los bytes: devuelve un **enlace firmado que vence a los 5 minutos** y la app lo abre fuera
(Chrome lo baja; en el celular, el navegador o el visor de PDF). `{codigo}` es el código del recibo
(`codigo` de `GET /pagos`). Tope: **20 por minuto**.

```json
{
  "pdf": {
    "url": "https://gadbeni.sfo3.digitaloceanspaces.com/jichi/dev/documentos/recibos/…?X-Amz-Signature=…",
    "vence_en": "2026-11-26T10:05:00-04:00",
    "nombre": "recibo-000009.pdf"
  }
}
```

- `DocumentoEmitidoService::enlaceDescarga()`: en s3, el enlace firmado del bucket (privado) con
  `Content-Disposition: attachment`; en el disco local (desarrollo), una ruta firmada de este sistema
  (`/documentos/enlace/{id}?signature=…`, middleware `signed`): sin firma o vencida, `403`.
- Si el PDF todavía no estaba guardado, se arma y se guarda en ese momento.
- El token **no** va en el enlace. Recibo ajeno o inexistente: `404` con el mismo mensaje
  (`DocumentoDelPortal::recibo`, la misma búsqueda que «Descargar» del portal web).

## `GET /en-curso` — lo pendiente de pago

Lo que espera el pago: lo que pidió en la app o en la web, y lo que le registraron en ventanilla
(autorización o carnet pendientes). Los mismos datos que «En curso» del portal web
(`PantallaPortal::enCurso()`). Como la web, encola la consulta de sus pagos a SIREB
(`VerificarPagoJob`), así lo ya pagado se aprueba solo.

**200**

```json
{
  "tramites": [
    {
      "clase": "faena", "tipo": "Permiso de Faena", "numero": "000042",
      "codigo": "K7QM-2XPA-9TZR-3B4N", "codigo_pago": "7ZQK-P4M2-9AX",
      "estado_etiqueta": "Pendiente", "estado_color": "sky",
      "en_tramite": true, "etapa": "pago", "pago": "sin_pago", "debe": 15,
      "puede_pagar_qr": true,
      "siguiente_paso": "Falta pagar 15,00 Bs en Recaudaciones con el código 7ZQK-P4M2-9AX. Una vez validado el pago, queda aprobado solo.",
      "…": "los demás campos del objeto documento"
    }
  ]
}
```

- Lista vacía (`"tramites": []`) si no debe nada.
- `codigo` es la llave para pedir el QR de ese trámite (parte B).
- Con `pago: "caida"` (venció o se anuló el cobro), `siguiente_paso` le dice que vaya a ventanilla
  por un código nuevo y `puede_pagar_qr` es `false`.
- Solo trae lo del titular del token (`EnCursoTest::test_solo_ve_lo_suyo`).

## `GET /faenas/nueva` — antes de pedir un permiso de faena

Lo que va a pedir, o por qué hoy no puede. Lo arma `App\Services\PedirFaenaService::datos()`,
el mismo que la página web «Pedir permiso de faena». **Las reglas son las del panel**
(REGLAS-NEGOCIO Paso 8, 19/11/2026).

```json
{
  "faena": {
    "puede": true,
    "motivo": null,
    "carnet": { "numero": "00042", "asociacion": "Asociación …", "vence": "2026-12-31" },
    "kilos": 750,
    "arancel": { "etiqueta": "Permiso de faena", "monto": 15 },
    "vigencia": "30 días desde la aprobación",
    "propietario_sugerido": "Ignacio Molina Guzman"
  }
}
```

- `puede: false` trae `motivo` en palabras del titular: sin carnet de pescador vigente
  (`PedirFaenaService::SIN_CARNET`), el motivo del carnet (`Carnet::motivoSinPermisos()`: pendiente,
  revocado, sin efecto, vencido, autorización sin pagar) o SIREB sin precio
  (`PedirFaenaService::SIN_PRECIO`, sin los detalles internos de Catálogos).
- `kilos`: los libres de su autorización; con esos sale la faena (Paso 4: **no descuenta**).
- `propietario_sugerido`: como el panel, si la embarcación es suya va su nombre.

- **Una faena en línea a la vez**: con un permiso de faena **pendiente de pago** (26/11/2026)
  `puede: false` y `motivo: "Tiene el permiso de faena N° 000013 sin pagar. Páguelo primero;
  después puede pedir otro."`; con uno **aprobado y vigente** (27/11/2026, `PermisoFaena::vigentes`)
  `motivo: "Tiene el permiso de faena N° 000013 vigente hasta el 15/12/2026. Puede pedir otro cuando
  venza."`. Vencido o revocado, vuelve a poder. `POST /faenas` responde `422` con el motivo en
  `errors.faena`. Las guías **no** tienen tope.

## `POST /faenas` — pedir un permiso de faena

`throttle:10,1,faenas` · **`Idempotency-Key` obligatoria** (un UUID por pedido; ver README § 6).
Los siete renglones del talonario, con **las reglas y los mensajes de ventanilla**
(`ApiApp\PedirFaenaRequest` extiende `Panel\ActualizarFaenaRequest`):

```
POST /api/v1/portal/faenas
Authorization: Bearer 15|Xk3f…
Idempotency-Key: 6f1c2a9e-4b7d-4e2a-9c1f-0d3b5a7e8f21
```

```json
{
  "embarcacion": "Canoa San José", "propietario": "Ignacio Molina Guzman",
  "comandante_barco": "Ignacio Molina Guzman", "matricula_naval": "MN-4585",
  "nro_kardex": "456", "region_desde": "Loma Suárez", "region_hasta": "Boca del Mamoré"
}
```

- **El carnet no viaja**: es el de pescador vigente de la cuenta del token. Un `carnet_id`
  en el cuerpo se ignora.
- **El origen no viaja** (26/11/2026): la faena queda con `origen_tramite = app` porque entró
  por esta API. Un `origen_tramite` en el cuerpo se ignora (`OrigenTramiteTest`).
- **201** `{ "faena": { …documento… } }`: `pendiente`, con su `codigo_pago` en SIREB. Con ese
  código la app paga el QR en SIREB; después pregunta en `/tramites/{codigo}/estado`. Aparece en
  `/en-curso` y suma a la deuda.
- **El mismo pedido repetido** (misma clave, mismos datos): el mismo 201 con
  `Idempotent-Replayed: true`. No se registra otra faena.
- **422** por un renglón: `errors.<campo>[0]` (p. ej. «Escriba la embarcación.»).
- **422** por una regla del panel: `errors.faena[0]` con el motivo (el mismo de `/faenas/nueva`
  o el de `EmitirFaenaService`).
- El titular **no** elimina ni corrige lo que pidió: eso sigue en ventanilla.

## `GET /guias/nueva` — antes de pedir una guía

Lo que necesita el formulario, o por qué hoy no puede (`App\Services\PedirGuiaService::datos()`,
el mismo que la página web «Pedir guía de transporte»). **Reglas del panel** (Paso 8).

```json
{
  "guia": {
    "puede": true,
    "motivo": null,
    "carnet": { "numero": "00010", "asociacion": "Asociación …", "vence": "2026-12-31" },
    "productos": [ { "id": "01a0f4e4-…", "nombre": "Surubí", "precio_kg": 0.5 } ],
    "condiciones": [ { "value": "fresco_entero", "label": "Fresco/refrigerado — entero" } ],
    "medios": [ { "value": "terrestre", "label": "Terrestre" } ],
    "tipos_transporte": [ { "value": "automotriz", "label": "Automotriz" } ],
    "vigencia": "5 días desde la aprobación"
  }
}
```

- `productos`: las tarifas **activas** del servicio de la guía en SIREB
  (`EmitirGuiaService::productos()`, el mismo catálogo del panel). El precio es de referencia:
  el que vale lo congela la emisión.
- `puede: false`: sin carnet de comercializador vigente (`PedirGuiaService::SIN_CARNET`), el
  motivo del carnet o SIREB sin productos (`SIN_PRECIO`).
- **La piscicultura no se ofrece**: en el panel la casilla está oculta (`MOSTRAR_PISCICULTURA`).

## `GET /autorizaciones/{codigo}` — el pescador que le vendió

`throttle:20,1` (el código es una llave: no se prueba al azar). Acepta el código como lo tipea
el titular (con o sin guiones, minúsculas) o la URL de verificación que «tipea» un lector de QR
(`AprovechamientoPesq::porCodigo()`, el mismo buscador del panel). **Solo el código completo.**

```json
{
  "autorizacion": {
    "numero": "000123",
    "pescador": "Juan Carlos P. R.",
    "kilos_disponibles": 1000,
    "vence": "2026-12-31",
    "vendible": true,
    "motivo": null,
    "tiene_foto": true
  }
}
```

- `pescador`: **abreviado** (`PedirGuiaService::nombreAbreviado()`): lo justo para reconocer a
  quién le compró, sin entregar el nombre completo a quien tenga un código. Sin `id`.
- `vendible: false` trae `motivo` (`AprovechamientoPesq::motivoSinGuia()`: sin pagar, vencida, revocada).
- `tiene_foto`: si el pescador tiene foto en su carnet (27/11/2026). La foto **no** viene acá:
  se pide aparte con `/autorizaciones/{codigo}/foto`.
- **404** `{ "message": "No encontramos una Autorización de Pesca con ese código. …" }`.

## `GET /autorizaciones/{codigo}/foto` — la foto del pescador (27/11/2026)

Decisión del responsable: el comercializador **ve la cara de quien le vende**, para reconocerlo.
`throttle:20,1,autorizaciones` (la misma cuenta que la búsqueda). Devuelve **los bytes** de la foto
del carnet del pescador (`Content-Type` real, `Cache-Control: private`), nunca una URL pública.

- Solo con el **código completo** de la autorización, el que está en el papel del pescador.
- **403** `{ "message": PedirGuiaService::SIN_CARNET }` sin carnet de comercializador vigente.
- **404** si el código no existe o el pescador no tiene foto.

## `POST /guias` — pedir una guía de transporte

`throttle:10,1,guias` · **`Idempotency-Key` obligatoria**. Las reglas y mensajes de ventanilla
(`ApiApp\PedirGuiaRequest` extiende `Panel\ActualizarGuiaRequest`), con el pescador por
**código** en vez de id:

```json
{
  "origen": "Puerto Almacén", "origen_departamento": "Beni", "origen_provincia": "Cercado", "origen_distrito": "Trinidad",
  "destino": "Santa Cruz de la Sierra", "destino_departamento": "Santa Cruz", "destino_provincia": "Andrés Ibáñez", "destino_distrito": "Santa Cruz",
  "medio_transporte": "terrestre", "tipo_transporte": "automotriz",
  "transporte_nombre": "Camioneta Toyota Hilux", "transporte_placa": "1234-ABC", "transporte_capacidad_kg": 1500,
  "autorizacion_codigo": "K7QM-2XPA-9TZR-3B4N",
  "observaciones": "opcional",
  "detalles": [ { "tarifa_sireb": "01a0f4e4-…", "condicion": "fresco_entero", "cantidad_kg": 300 } ]
}
```

- **No viajan** `carnet_id`, `aprovechamiento_id` ni `es_piscicultura`: el carnet es el de
  comercializador vigente de la cuenta, la autorización sale del código y la piscicultura es
  `false` (como el panel). Si vienen, se ignoran. Tampoco `origen_tramite`: queda `app`
  (26/11/2026). **`origen`** sí viaja y es otra cosa: el lugar de partida del viaje.
- **201** `{ "guia": { …documento… } }`: `pendiente`, con su `codigo_pago`. **Los kilos ya se
  descontaron** de la autorización del pescador (Paso 5). Se aprueba sola al pagarse (Paso 6).
- **El mismo pedido repetido** (misma clave, mismos datos): el mismo 201, sin otra guía ni otro
  descuento (`GuiaTest::test_repetida_con_la_misma_clave_descuenta_los_kilos_una_sola_vez`).
- **422** por un campo: `errors.<campo>[0]` («Indique desde dónde sale la carga.»,
  `detalles.0.cantidad_kg`, `autorizacion_codigo`…).
- **422** por una regla: `errors.guia[0]` — código que no existe, autorización no vendible o
  «solo tiene N kg disponibles» (`EmitirGuiaService`). Nada se guarda ni se descuenta.
- El titular **no** elimina ni corrige lo que pidió: si no paga, ventanilla la elimina y los
  kilos vuelven al pescador.

---

## Lo que corta el token sin que la app haga nada

| En ventanilla o en la web | Efecto en la app |
| --- | --- |
| «Resetear contraseña» | Todos sus tokens se borran → `401` → login con la clave temporal nueva |
| «Desactivar acceso» | Todos sus tokens se borran → `401`; el login da el mensaje de siempre |
| Cambiar la clave en la web | Todos sus tokens se borran → `401` |
| Dar de baja a la persona | `401` con `codigo: acceso_deshabilitado` |
| 90 días sin usar la app | Ese token vence → `401` → login (con uso, se renueva solo) |

## `GET /tramites/{codigo}/estado` — ¿ya quedó aprobado?

`throttle:30,1,estado`. **La app lo pide después de pagar el QR en SIREB** (ver README § 2.1),
y cuando vuelve a abrir un trámite que espera el pago. `{codigo}` es el `codigo` del documento
(16 caracteres, con o sin guiones), no el `codigo_pago`.

Consulta SIREB en ese momento (`CobroQrService::estado()`, como mucho cada 15 s por trámite) y,
si la liquidación está `pagada`, **aprueba el trámite y emite el recibo**
(`ConfirmarPagoService::verificar()`). Nunca aprueba con lo que diga la app.

**200**

```json
{
  "pagado": true,
  "mensaje": "…el texto de ConfirmarPagoService, o null…",
  "tramite": { "clase": "faena", "en_tramite": false, "vigente": true, "…": "objeto documento" }
}
```

| Campo | Qué es |
| --- | --- |
| `pagado` | `true` si ya está pago y aprobado (o ya tenía recibo). `false` si SIREB todavía no lo da por pagado, o si se consultó hace menos de 15 s |
| `mensaje` | Lo que dice el sistema al aprobar; `null` si no hay novedad |
| `tramite` | El documento como queda después de la consulta (objeto `documento`) |

- Con `pagado: false`, la app puede volver a preguntar en unos segundos. Si el titular cierra
  la app, el trámite igual se aprueba (webhook de SIREB o `jichi:verificar-pagos`).
- **404** `{ "message": "No encontramos ese trámite entre los suyos." }`: lo ajeno y lo
  inexistente dan **el mismo** 404, para no confirmar que un código existe
  (`EstadoTramiteTest::test_lo_ajeno_y_lo_inexistente_dan_el_mismo_404`).

## El pago del QR: en SIREB, no acá

La app no pide el QR a este sistema. Con el `codigo_pago` de la faena o la guía usa la API
pública de SIREB (`/api/v1/publico`, encabezado `X-App-Key`), en la pantalla de pago común de la
app:

| Método | Ruta en SIREB | Para qué |
| --- | --- | --- |
| `POST` | `/liquidaciones/{codigo_pago}/qr` | El QR (el mismo mientras siga vigente) |
| `GET` | `/liquidaciones/{codigo_pago}` | ¿Ya está `pagada`? |
| `POST` | `/liquidaciones/{codigo_pago}/qr/simular-pago` | Solo en test, con un QR `simulado` |

Contrato de SIREB: `docs/api/openapi-publico.yaml` en su repositorio. Si SIREB responde
`503 PAGO_QR_NO_DISPONIBLE` (producción sin pasarela), la pantalla común ofrece pagar en
ventanilla con el código.

## Fuera del alcance (sin ruta)

`GET /papeles` existió del 19 al 25/11/2026. Se quitó porque la app no lo usa (`/inicio`,
`/perfil`, `/perfil/foto` y `/pagos` también se quitaron ese día y volvieron a pedido del
responsable).
Su código está en la rama `rescate/api-portal`. El PDF (`/descargar`) no se hace.
