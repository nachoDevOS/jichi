# API para la app BeniDigital (ApiApp)

> Escrito el 19/11/2026; reorganizado el 25/11/2026 en las carpetas `ApiApp/`, con el alcance
> de la app acotado. Mantenerlo al día: **un endpoint nuevo no está terminado hasta que figura
> acá y en [portal.md](portal.md)**.

Esta carpeta documenta la **única API del sistema**: la que consume la **app móvil BeniDigital**
del GAD para que el **beneficiario** (pescador o comercializador) pida trámites y los pague desde
el celular, con la misma cuenta del portal web `/mi-cuenta`.

| Documento | Para qué |
| --- | --- |
| **README.md** (este) | Qué es, el alcance, la carpeta `ApiApp/`, cómo entra la app, errores, cómo probar y cómo agregar un endpoint |
| **[portal.md](portal.md)** | El **contrato**: cada endpoint con su pedido, su respuesta en JSON y sus errores |
| **[seguridad.md](seguridad.md)** | Qué protege la API, qué hay que configurar antes de producción y qué falta reforzar |

Reglas de negocio relacionadas: [REGLAS-NEGOCIO.md](../REGLAS-NEGOCIO.md), Paso 8 (portal del
beneficiario y trámites en línea). Módulo del portal: [modulos/PORTAL.md](../modulos/PORTAL.md).
Cobro por QR: [modulos/PAGO-QR.md](../modulos/PAGO-QR.md).

---

## 1. Qué es y qué no es

- **Es** la puerta de la app BeniDigital. Devuelve **solo datos (JSON)**; la app dibuja sus
  pantallas.
- **No es** la de las pantallas del sistema: el panel, la página pública y el portal web usan
  Inertia (React) con sesión de navegador y **no pasan por acá**.
- **No es** una API para otros sistemas. Cada pedido es de **una persona** con su cuenta del
  portal; no hay claves de sistema (API keys). El webhook de SIREB (`routes/api.php`) no es
  parte de esta API.

---

## 2. Alcance de la app (decidido el 25/11/2026)

En la app el beneficiario **solo pide faena y guía, y las paga por QR**. Todo lo demás se
consulta en el portal web o se hace en ventanilla.

| Puede | Endpoint | Estado |
| --- | --- | --- |
| Entrar con la C.I. y la clave de ventanilla, cambiar la clave temporal, salir | `POST /sesion`, `GET /yo`, `PUT /clave`, `DELETE /sesion` | ✅ |
| Ver su inicio, como el de la web: vigente hoy, qué hacer ahora, por pagar | `GET /inicio` | ✅ |
| Ver lo que tiene por pagar | `GET /en-curso` | ✅ |
| **Ver «Mi perfil»**: sus datos, actividades y la foto del carnet (§ 2.3) | `GET /perfil`, `GET /perfil/foto` | ✅ |
| **Ver «Mis pagos»**: sus recibos por gestión, con el código y el QR de verificación de cada uno | `GET /pagos?gestion=` | ✅ |
| **Bajar el PDF oficial de un recibo** (el guardado en S3): un enlace firmado de 5 minutos | `GET /recibos/{codigo}/pdf` | ✅ |
| **Pedir un permiso de faena** (pescador) | `GET /faenas/nueva`, `POST /faenas` | ✅ |
| **Pedir una guía de transporte** (comercializador) | `GET /guias/nueva`, `GET /autorizaciones/{codigo}`, `POST /guias` | ✅ |
| **Pagar por QR** lo que pidió | **No pasa por acá**: la app paga directo en SIREB (§ 2.1) | ✅ (en SIREB) |
| Saber si ya quedó aprobado, después de pagar | `GET /tramites/{codigo}/estado` | ✅ |

### 2.1 El pago: la app paga directo en SIREB (opción B, 25/11/2026)

**El sistema no cobra ni genera el QR para la app.** Registra el trámite, y al registrarlo
crea su liquidación en SIREB con su propia credencial (`sedag`, que **nunca** sale del
servidor). La app paga con la **API pública de SIREB** (`/api/v1/publico`, clave de app
`X-App-Key`), en la misma pantalla de pago que usa para cualquier cobro de la Gobernación. Es la
regla de la app («los sistemas especializados cobran a través de SIREB», decisión D7 de
BeniDigital).

```
   APP                          ESTE SISTEMA                    SIREB / PAITITI
    │── POST /faenas ─────────────►│                                │
    │   (Idempotency-Key)          │── registra la liquidación ────►│ (credencial sedag)
    │◄── 201 faena: codigo,        │◄── codigo_publico ─────────────│
    │    codigo_pago, debe         │                                │
    │                              │                                │
    │── POST /publico/liquidaciones/{codigo_pago}/qr  (X-App-Key) ─►│
    │◄── imagen del QR ─────────────────────────────────────────────│
    │   el titular paga con la app de su banco ───────────────────►│ liquidación = pagada
    │── GET /publico/liquidaciones/{codigo_pago} (cada 5 s) ───────►│
    │◄── pagada ────────────────────────────────────────────────────│
    │                              │                                │
    │── GET /tramites/{codigo}/estado ►│                            │
    │                              │── GET /liquidaciones/{id} ────►│
    │                              │◄── pagada ─────────────────────│
    │                              │── APRUEBA + recibo             │
    │◄── pagado: true, tramite ────│                                │
```

- **Solo este sistema aprueba**, y solo cuando SIREB dice `pagada`
  (`ConfirmarPagoService::verificar()`). Lo que diga el celular no aprueba nada.
- Si el titular cierra la app antes de preguntar, el trámite igual se aprueba: por el webhook
  de SIREB (`/api/sireb/aviso`) o por `jichi:verificar-pagos` cada 10 minutos.
- Por eso **no** hay `/pagar-qr` en esta API (el portal web sí lo tiene, con sesión).
- Pagar el código de otro no es un riesgo: solo salda esa deuda. Lo que se cuida es que nadie
  **vea** trámites ajenos (`/tramites/{codigo}/estado` busca con el titular del token).

### 2.2 El QR en la web y en la app: dos caminos, la misma aprobación

| | Portal web `/mi-cuenta` | App BeniDigital |
| --- | --- | --- |
| Quién habla con SIREB para el QR | El **servidor** de este sistema (`/mi-cuenta/pagar-qr/{codigo}`) | El **celular**, directo (`/api/v1/publico`) |
| Con qué credencial | `sedag`, que no sale del servidor | `X-App-Key` pública de la app |
| ¿Ya pagó? | La ventana pregunta cada 10 s a `/mi-cuenta/pagar-qr/{codigo}/estado` | La pantalla común pregunta a SIREB cada 5 s; después, `/api/v1/portal/tramites/{codigo}/estado` |
| Quién aprueba | Este sistema (`CobroQrService::estado()` → `ConfirmarPagoService`) | Este sistema, con el mismo `CobroQrService::estado()` |
| Por qué así | La página es de este sistema: pasar por su servidor es lo natural, y el navegador no puede llevar la credencial | La app ya tiene su pantalla de pago común con SIREB (Recaudaciones, Canchas): un solo flujo para toda la app |

Detalle del camino web: [modulos/PAGO-QR.md](../modulos/PAGO-QR.md).

### 2.3 «Mi perfil» (vuelve el 25/11/2026)

A pedido del responsable, la app vuelve a mostrar **«Mi perfil»**: los mismos datos que «Mis
datos» del portal web (`PerfilPortal::de()`), solo para leer, y la foto del carnet.

| Puede | Endpoint |
| --- | --- |
| Ver sus datos personales, de contacto y sus actividades (carnets vigentes) | `GET /perfil` |
| Ver la foto de su carnet | `GET /perfil/foto` (con el token; la URL pública no viaja) |

Son **sus propios datos**, los mismos que ve en la web con la misma cuenta. Lo que los cuida: el
token cifrado en el celular, que vence a los 90 días sin uso, el tope de 60 pedidos por minuto y
que ninguna ruta reciba un id (solo ve lo suyo: `PerfilTest::test_solo_ve_lo_suyo`).

**Fuera del alcance (sin ruta en la API):** mis papeles y el PDF de los papeles (el del **recibo** sí, desde el 26/11/2026).
`/papeles` y `/pagos` existieron del 19 al 25/11/2026 y se quitaron porque la app no los usaba
(`/inicio` también, y volvió ese mismo día para el inicio de la app, como el de la web).
`/pagos` volvió el 25/11/2026 para «Mis pagos» de la app; el recibo se ve como recibo digital con
su QR de verificación, sin PDF. Están en la rama `rescate/api-portal` por si se vuelven a necesitar. Una prueba controla
que den 404 (`EnCursoTest::test_fuera_del_alcance_de_la_app_no_hay_ruta`).

**Qué no puede hacer**, igual que en el portal web (REGLAS-NEGOCIO, Paso 8): sacar la
Autorización de Pesca o los carnets (solo en ventanilla), eliminar o corregir lo que pidió, ni
cambiar sus datos personales.

---

## 3. Cómo está armada: un Laravel, una lógica, tres entradas

El sistema es **un solo Laravel** con una sola base de datos. El panel de ventanilla, el portal
web del beneficiario y esta API son tres **entradas** delgadas a la **misma lógica**:

```
 Panel (funcionarios)     Portal web (/mi-cuenta)      API (/api/v1/portal)
 sesión + permisos        sesión del beneficiario      token del celular
 devuelve pantallas       devuelve pantallas           devuelve JSON
        │                          │                           │
        │                          └──────────┬────────────────┘
        │                                     ▼
        │                 LÓGICA QUE COMPARTEN APP Y WEB — carpetas ApiApp/
        │                 PedirFaenaService · PedirGuiaService
        │                 PantallaPortal · ResumenPortal
        │                 PedirFaenaRequest · PedirGuiaRequest
        │                                     │
        └──────────────┬──────────────────────┘
                       ▼
      LÓGICA BASE DEL SISTEMA — app/Services y app/Support
      EmitirFaenaService · EmitirGuiaService · CuentaPortalService
      ConfirmarPagoService · CobroQrService · ExpedienteBeneficiario
                       │
                       ▼
           Base de datos (PostgreSQL)  ·  SIREB (cobros)
```

**Regla de oro: la API no tiene reglas propias.** Una faena pedida desde la app la emite el
mismo `EmitirFaenaService` que usa ventanilla, con las mismas validaciones. Si una regla
cambia, cambia en las tres entradas a la vez. Una API que copie lógica es un error.

---

## 4. La carpeta `ApiApp/`: qué es de la app

**Todo lo que usa la app vive en carpetas `ApiApp/`** (decidido el 25/11/2026), en cada capa de
Laravel. Así se ve de un vistazo qué toca a la app: **si cambiás algo dentro de `ApiApp/`,
puede cambiar lo que ve o lo que manda el celular**.

Hay dos clases de archivos adentro, y el comentario de cada clase dice cuál es:

- **Solo de la app:** los usa únicamente la API.
- **Compartidos con la web:** los usan la API y el portal web `/mi-cuenta` (los dos piden faena
  y guía, y muestran lo mismo). Tocarlos cambia **las dos**.

```
routes/api-app.php                       Las 14 rutas de la app. La incluye routes/api.php
                                         (Laravel les pone el prefijo /api).

app/Http/Controllers/ApiApp/             SOLO APP — reciben el pedido, llaman a la lógica, responden JSON
  ├── SesionController.php               POST /sesion (entrar) · DELETE /sesion (salir)
  ├── CuentaController.php               GET /yo · PUT /clave
  ├── InicioController.php               GET /inicio (como el inicio de la web, + lo pagado en el año)
  ├── PagosController.php                GET /pagos?gestion= («Mis pagos», sin `descargar`)
  ├── ReciboPdfController.php            GET /recibos/{codigo}/pdf (enlace firmado de 5 min al PDF de S3)
  ├── EnCursoController.php              GET /en-curso
  ├── EstadoTramiteController.php        GET /tramites/{codigo}/estado (después de pagar en SIREB)
  ├── FaenaController.php                GET /faenas/nueva · POST /faenas
  ├── GuiaController.php                 GET /guias/nueva · GET /autorizaciones/{codigo} · POST /guias
  └── PerfilController.php               GET /perfil · GET /perfil/foto («Mi perfil»)

app/Http/Requests/ApiApp/
  ├── IngresarAppRequest.php             SOLO APP — hereda Portal\IngresarRequest; emite el token
  ├── CambiarClaveAppRequest.php         SOLO APP — hereda Portal\CambiarClaveRequest
  ├── PedirFaenaRequest.php              COMPARTIDO — los 7 renglones (hereda Panel\ActualizarFaenaRequest)
  └── PedirGuiaRequest.php               COMPARTIDO — ruta, transporte, cuadro D y código del pescador

app/Http/Resources/ApiApp/               SOLO APP — EL CONTRATO: el JSON exacto que recibe la app
  ├── CuentaResource.php                 El objeto `cuenta`
  ├── DocumentoResource.php              Un documento sin las URLs de la web; con `puede_descargar`
  └── PerfilResource.php                 «Mi perfil» sin la URL de la foto; con `tiene_foto`

app/Http/Middleware/ApiApp/
  ├── SoloBeneficiarioApp.php            SOLO APP — alias `beneficiario.app`: solo cuentas de
  │                                      beneficiario, activas y sin clave temporal (salvo yo,
  │                                      clave y salir). Renueva el vencimiento del token
  └── Idempotente.php                    SOLO APP — alias `idempotente`: Idempotency-Key en
                                         POST /faenas y POST /guias

app/Services/ApiApp/                     COMPARTIDO — los trámites en línea
  ├── PedirFaenaService.php              Qué va a pedir o por qué no; emite con EmitirFaenaService
  └── PedirGuiaService.php               Busca al pescador por código; emite con EmitirGuiaService

app/Support/ApiApp/                      COMPARTIDO — lo que ven app y web
  ├── PantallaPortal.php                 Inicio, en curso, papeles y pagos (la app usa inicio y en curso)
  ├── PerfilPortal.php                   «Mis datos» / «Mi perfil»: datos, actividades y cuenta
  ├── ResumenPortal.php                  Cómo se muestra cada documento (estado, deuda, qué falta)
  └── DocumentoDelPortal.php             Un documento por su código, SOLO si es del titular
                                         (lo ajeno da null igual que lo inexistente)

tests/Feature/ApiApp/                    Las pruebas de la API
  ├── SesionTest.php · EnCursoTest.php · FaenaTest.php · GuiaTest.php
  └── EstadoTramiteTest.php · SeguridadTest.php · PerfilTest.php · InicioTest.php

config/sanctum.php                       Tokens: `guard => []` (solo token, nunca cookies)
database/migrations/
  2026_11_19_084048_create_personal_access_tokens_table.php   Una fila por celular con sesión
```

**Lógica base que usa la API, fuera de `ApiApp/`** (la usa también el panel, así que no es
«de la app»):

| Archivo | Qué hace para la app |
| --- | --- |
| `app/Http/Requests/Portal/IngresarRequest.php` | `cuentaValida()`: tope de intentos, C.I. y clave (también la web) |
| `app/Services/CuentaPortalService.php` | Dar acceso, resetear, desactivar, cambiar clave; **borra los tokens** |
| `app/Services/EmitirFaenaService.php` / `EmitirGuiaService.php` | Las reglas de verdad de faena y guía |
| `app/Services/CobroQrService.php` · `ConfirmarPagoService.php` | `CobroQrService::estado()`: consulta el pago en SIREB (como mucho cada 15 s por trámite) y aprueba con `ConfirmarPagoService` |
| `app/Support/ExpedienteBeneficiario.php` | Los documentos de una persona |
| `app/Support/TramitesDisponibles.php` | «Puede tramitar» y su `canal` (lo usa el inicio web) |
| `app/Models/AprovechamientoPesq.php` | `porCodigo()`: la autorización por su código de verificación |

**Pruebas de la web que comparten lógica con la app:** `tests/Feature/Portal/FaenaEnLineaTest.php`
y `GuiaEnLineaTest.php`. Datos de prueba: `tests/Concerns/PescadorDePrueba.php` y
`tests/Concerns/SirebFalso.php` (SIREB simulado: ninguna prueba sale a la red).

---

## 5. Cómo entra la app: tokens de Sanctum

La autenticación es con **tokens de Laravel Sanctum** (paquete `laravel/sanctum` v4.5, en
`composer.json`). **No** es JWT, ni OAuth (Passport), ni el modo de Sanctum con cookies.

### 5.1 El recorrido

```
1. En VENTANILLA (panel, ficha del beneficiario) se toca «Dar acceso al portal»:
   CuentaPortalService::darAcceso() crea la cuenta (users.beneficiario_id) con una
   CLAVE TEMPORAL. Es la misma cuenta del portal web: no hay cuentas aparte para la app.

2. En el CELULAR, la app solo conoce la DIRECCIÓN del sistema (al compilarla).
   El beneficiario escribe su C.I. y la clave:
      POST /api/v1/portal/sesion  {ci, password, dispositivo}

3. El sistema valida con las reglas del portal web (IngresarRequest::cuentaValida()) y
   CREA un token para ese celular:  $cuenta->createToken('BeniDigital · android', ['portal'])
   Responde el token en claro UNA sola vez:  {"token": "15|Xk3f…", "cuenta": {…}}
   En la base guarda solo su HASH (SHA-256) en personal_access_tokens.

4. La app lo guarda cifrado en el celular (flutter_secure_storage: Keystore / Keychain).

5. Cada pedido lleva:   Authorization: Bearer 15|Xk3f…   y   Accept: application/json
   Sanctum busca la fila 15, compara el hash y carga la cuenta (actualiza last_used_at).
   El middleware beneficiario.app controla que sea un beneficiario activo.

6. Con la clave temporal, solo funcionan /yo, /clave y salir (403 clave_temporal en lo demás):
   la app obliga a cambiarla, igual que la web.
```

**No hay pantalla para generar tokens y no hace falta:** cada token nace solo cuando un
beneficiario inicia sesión en su celular y es solo de él. En ventanilla solo existe lo de
siempre: «Dar acceso al portal», «Resetear contraseña» y «Desactivar acceso».

**No hay una clave fija en la app (API key):** quien descarga la app podría sacarla de
adentro. La seguridad está en la cuenta de cada persona.

### 5.2 Cuándo muere un token

| Lo que pasa | Qué tokens se borran | Dónde |
| --- | --- | --- |
| Sale de la app | El de ese celular | `ApiApp\SesionController::destroy()` |
| Ventanilla toca «Resetear contraseña» | Todos los de la cuenta | `CuentaPortalService::resetear()` |
| Ventanilla toca «Desactivar acceso» | Todos | `CuentaPortalService::desactivar()` |
| Cambia la clave en la app | Todos **menos** el del celular que la cambió | `CuentaPortalService::cambiarClave()` |
| Cambia la clave en el portal web | Todos | `CuentaPortalService::cambiarClave()` |
| Dan de baja a la persona en el padrón | El token deja de servir (401 `acceso_deshabilitado`) | `ApiApp\SoloBeneficiarioApp` |
| Pasan **90 días sin usar la app** | El de ese celular (401: vuelve al ingreso) | `expires_at` del token (25/11/2026) |

**Vencimiento deslizante (25/11/2026):** el token nace con `expires_at` a 90 días
(`IngresarAppRequest::emitirToken()`) y, mientras se use, `SoloBeneficiarioApp` lo vuelve a
llevar a 90 días (cuando le quedan menos de 83, para escribir una vez por semana y no en cada
pedido). Un token de antes, sin `expires_at`, lo recibe en su próximo uso. `config/sanctum.php`
sigue con `'expiration' => null` a propósito: ese contaría desde la creación y cortaría a quien
usa la app todos los días.

### 5.3 Por qué Sanctum y no JWT

Un token de Sanctum es una **fila en la base**: se revoca al instante (resetear en ventanilla
saca al celular en el próximo pedido). Un JWT vale hasta que vence aunque reseteen la cuenta,
salvo que se arme una lista negra aparte. El costo de Sanctum es una consulta por pedido,
despreciable para el padrón del SEDAG.

---

## 6. Reglas comunes de todos los endpoints

- **Base:** `{dirección del sistema}/api/v1/portal`. En desarrollo `http://127.0.0.1:8000/api/v1/portal`.
- **Siempre** `Accept: application/json`. Sin eso, un error puede volver como redirección HTML.
- **Token** en `Authorization: Bearer …` en todo, salvo `POST /sesion`.
- **Sin ids en las rutas.** Todo sale de la cuenta del token: no hay número que cambiar para
  ver lo de otro. Lo único que viaja es un **código de verificación** (16 caracteres), que es una
  llave, no un id, y se busca siempre junto con el titular del token cuando es de un trámite.
- **Lo que decide el sistema no viaja desde la app:** el carnet, la autorización (va el
  código), la piscicultura, los kilos, el precio de la faena y el **origen del trámite**. Si la
  app los manda, se ignoran.
- **Desde dónde se registró** (26/11/2026): todo lo que se pide por esta API queda con
  `origen_tramite = app` (lo pone `ApiApp\FaenaController` / `ApiApp\GuiaController`; los
  `Pedir*Service` compartidos con la web lo reciben como parámetro). Cada trámite del JSON trae
  `origen_tramite` y `origen_tramite_etiqueta` ([portal.md](portal.md#el-objeto-documento)).
  **La app no necesitó cambios**: lee el JSON clave por clave y las nuevas no le molestan;
  quedan listas para mostrarlas cuando se quiera.
- **Versión en la URL** (`/v1`). Un cambio que rompa la app va en `/v2`; la app vieja sigue
  andando con `/v1` hasta que se actualice. Agregar un campo nuevo a una respuesta no rompe.
- **Fechas** en ISO 8601 (`2026-11-19T08:30:00-04:00`); un día suelto, `2026-11-19`.
- **Montos** en número (`120.5`), en bolivianos. **Kilos** en número.
- **Textos para el titular** (`motivo`, `siguiente_paso`, mensajes de error) ya vienen redactados:
  la app los muestra tal cual.

- **`Idempotency-Key` obligatoria en `POST /faenas` y `POST /guias`** (25/11/2026): un UUID nuevo
  por cada pedido, el mismo en los reintentos de ese pedido. Con la misma clave y los mismos
  datos, el sistema devuelve lo ya registrado (encabezado `Idempotent-Replayed: true`) sin otro
  cobro ni otro descuento de kilos. Se recuerda 24 h, por cuenta y por ruta. Solo se guarda lo
  registrado: con un `422` el titular corrige y reintenta con la misma clave.

**Límites de pedidos (throttle).** Cada uno lleva su prefijo (`throttle:10,1,faenas`): sin él,
todos los de una cuenta compartirían el mismo contador.

| Ruta | Límite | Por qué |
| --- | --- | --- |
| Todo lo que pide token | **60 por minuto por cuenta**, además del propio | Nadie usa la app así: frena un token robado o un error de la app |
| `POST /sesion` | 10 por minuto por IP, y 5 intentos fallidos por C.I. + IP | La C.I. se adivina |
| `PUT /clave` | 10 por minuto | |
| `GET /autorizaciones/{codigo}` | 20 por minuto | El código es una llave: no se prueba al azar |
| `POST /faenas`, `POST /guias` | 10 por minuto | Cada pedido registra un cobro en SIREB |
| `GET /tramites/{codigo}/estado` | 30 por minuto | Consulta SIREB (como mucho cada 15 s por trámite) |

---

## 7. Errores y cómo reacciona la app

| HTTP | Cuándo | Cuerpo | La app |
| --- | --- | --- | --- |
| `401` | Sin token, o token borrado (salió, reseteo, desactivación, cambio de clave) | `{"message": "..."}`; `"codigo": "acceso_deshabilitado"` si la cuenta está desactivada o dada de baja | Borra el token y vuelve al ingreso |
| `403` | Clave temporal fuera de `/yo`, `/clave` y salir; o token que no es de beneficiario | `{"message": "...", "codigo": "clave_temporal" \| "no_beneficiario"}` | `clave_temporal` → pantalla de cambiar la clave |
| `400` | `POST /faenas` o `/guias` sin `Idempotency-Key` (o con una de menos de 16 caracteres) | `{"message": "...", "codigo": "idempotency_key_requerida"}` | Error de la app: siempre la manda |
| `404` | Código de autorización que no existe; trámite que no es suyo o no existe; ruta fuera del alcance | `{"message": "..."}` | Muestra el mensaje |
| `409` | La misma `Idempotency-Key` llegó dos veces a la vez | `{"message": "...", "codigo": "pedido_en_curso"}` | Espera unos segundos y reintenta con la misma clave |
| `422` | Un campo no sirve, o una regla del panel frena el trámite | `{"message": "...", "errors": {"campo": ["..."]}}`. Las reglas llegan en `errors.faena` o `errors.guia` | El error de un campo va debajo de ese campo; el de una regla, arriba del botón |
| `422` | La `Idempotency-Key` ya se usó con otros datos | `{"message": "...", "codigo": "idempotency_key_reusada"}` | Error de la app: un pedido nuevo lleva una clave nueva |
| `429` | Se pasó del límite de pedidos | `{"message": "Too Many Attempts."}` | «Demasiados intentos. Esperá un minuto…» |
| `500` | Error del sistema | En producción (`APP_DEBUG=false`), sin detalles | «El SEDAG no respondió bien…» |

---

## 8. Probar en local

1. En el `.env`: `SERVER_HOST=0.0.0.0` (ya está). Arrancar **solo** con `composer run dev`:
   levanta el servidor, la cola, Vite y el scheduler. **No** correr además `php artisan serve`.
   El host se lee al arrancar: si se cambia, reiniciar.
2. Migrar la tabla de tokens si la base es nueva: `php artisan migrate`.
3. Dar acceso a un beneficiario en el panel («Dar acceso al portal») y anotar la clave temporal.
4. Entrar y usar el token:

   ```sh
   # Entrar: devuelve el token
   curl -X POST http://127.0.0.1:8000/api/v1/portal/sesion \
     -H "Accept: application/json" -H "Content-Type: application/json" \
     -d '{"ci":"7633685","password":"LA_CLAVE","dispositivo":"curl"}'

   # La primera vez, cambiar la clave temporal
   curl -X PUT http://127.0.0.1:8000/api/v1/portal/clave \
     -H "Accept: application/json" -H "Content-Type: application/json" \
     -H "Authorization: Bearer 15|Xk3f…" \
     -d '{"actual":"LA_CLAVE","password":"pesca2026","password_confirmation":"pesca2026"}'

   # Qué puede pedir y qué tiene por pagar
   curl http://127.0.0.1:8000/api/v1/portal/faenas/nueva \
     -H "Accept: application/json" -H "Authorization: Bearer 15|Xk3f…"
   curl http://127.0.0.1:8000/api/v1/portal/en-curso \
     -H "Accept: application/json" -H "Authorization: Bearer 15|Xk3f…"
   ```

5. La app, desde `beni-digital`:
   - Emulador de Android: `flutter run` (usa `http://10.0.2.2:8000`).
   - Celular en el mismo WiFi: `flutter run --dart-define=JICHI_URL=http://<IP-de-la-PC>:8000`.
     Para comprobar que el celular llega al sistema, abrir esa dirección en su navegador.
   - Chrome o escritorio: `flutter run -d chrome` (usa `http://127.0.0.1:8000`).
6. Pruebas automáticas: `php artisan test tests/Feature/ApiApp` (y `tests/Feature/Portal` para
   la web del portal). Todas corren con SIREB simulado.

---

## 9. Cómo agregar un endpoint (sin romper nada)

1. **¿Entra en el alcance?** (§ 2). Si no, se decide con el responsable antes de escribirlo.
2. **La lógica primero, compartida.** Si el portal web hace lo mismo, sacar el armado del
   controlador web a `app/Services/ApiApp/` (reglas) o `app/Support/ApiApp/` (datos) y que **los
   dos** la llamen. Ejemplos: `PedirFaenaService`, `PantallaPortal`. Nunca copiar.
3. **La ruta** en `routes/api-app.php`, dentro del grupo `auth:sanctum` + `beneficiario.app` +
   `throttle:60,1,app`, con nombre `api.portal.…`; su propio `throttle` **con prefijo** si escribe,
   cobra, consulta SIREB o recibe una llave; e `idempotente` si registra algo que cobra.
4. **El controlador** en `app/Http/Controllers/ApiApp/`: corto, solo llama a la lógica.
   **Ningún id en la ruta**: lo de la persona sale de `$request->user()`.
5. **La validación:** reusar el Request del portal o del panel (heredarlo) en
   `app/Http/Requests/ApiApp/` en vez de escribir reglas nuevas.
6. **El JSON** en un Resource de `app/Http/Resources/ApiApp/`. Sacar lo que solo sirve en la web
   (URLs que piden sesión de navegador) y los datos que la app no usa. El Resource **es el
   contrato**: cambiarlo puede romper la app.
7. **Las pruebas** en `tests/Feature/ApiApp/`: el caso bueno; sin token (401); con clave temporal
   (403); que solo ve lo suyo (lo ajeno da el mismo 404 que lo inexistente); el límite (429).
   Romper el código a propósito y ver que la prueba falla.
8. **La documentación:** el endpoint en [portal.md](portal.md) (tabla de arriba + sección con
   ejemplo), la lista de [seguridad.md](seguridad.md) § 4 y, si toca algo de esta guía, acá.
   Registrar el trabajo en `docs/sesiones/`.
9. **La app:** el método en el repositorio que corresponda, en
   `beni-digital/lib/features/pescador/data/`, y en su simulador de pruebas.

---

## 10. Estado y lo que falta (al 25/11/2026)

| Parte | Qué | Estado |
| --- | --- | --- |
| A | Ingreso, en curso, faena y guía en línea; todo en `ApiApp/`; fuera lo que la app no usa | ✅ 25/11/2026 |
| B | Pago: la app paga directo en SIREB; acá, `GET /tramites/{codigo}/estado` | ✅ 25/11/2026 |
| B | Seguridad: `Idempotency-Key` en los dos POST, tokens que vencen a los 90 días sin uso, 60 pedidos por minuto por cuenta | ✅ 25/11/2026 |
| C | «Mi perfil» vuelve: `GET /perfil` y `GET /perfil/foto` (pedido del responsable) | ✅ 25/11/2026 |
| C | El inicio vuelve: `GET /inicio`, para que la app se parezca al portal web | ✅ 25/11/2026 |
| C | «Mis pagos» vuelve: `GET /pagos`, con `clase`, `codigo` y `verificar` por recibo; `inicio.pagado` | ✅ 25/11/2026 |
| C | Descargar el PDF del recibo: `GET /recibos/{codigo}/pdf` → enlace firmado de 5 minutos (S3 o ruta firmada local) | ✅ 26/11/2026 |
| C | Origen del trámite: lo pedido por la app queda `app`; `origen_tramite` y `origen_tramite_etiqueta` en cada documento | ✅ 26/11/2026 |

**Falta:**
- Configurar producción ([seguridad.md](seguridad.md) § 2): HTTPS, `APP_DEBUG=false`, etc.
- Confirmar en test que la API pública de SIREB cobra las liquidaciones que registra el SEDAG.
- Que SIREB dé de alta el webhook (`/api/sireb/aviso`): sin él, si el titular cierra la app,
  el trámite se aprueba con la consulta de cada 10 minutos.
- Refuerzos recomendados: [seguridad.md](seguridad.md) § 3.
