# Autenticación con Ibare

Ibare es el servidor de autenticación centralizado del GAD Beni (OAuth2,
repositorio `gadbeni/ibare`). Jichi es un **cliente** de Ibare: no guarda la
contraseña del funcionario ni consulta mamoré. Ibare autentica y revisa el
contrato vigente; Jichi solo resuelve **qué usuario suyo** es.

Probado de punta a punta el 24/09/2026 con Ibare en Docker y Jichi con
`composer run dev`.

---

## 1. Encendido y apagado

Se controla con `IBARE_ACTIVO` en el `.env` (ver `config/jichi.php` → `jichi.ibare`).

| `IBARE_ACTIVO` | `/login` muestra | Login por correo |
| --- | --- | --- |
| `false` | el formulario de siempre | todos los usuarios activos |
| `true` | botón «Ingresar con Ibare» + «Acceso de emergencia» plegado | **solo administradores** |

El acceso de emergencia existe porque, si Ibare o mamoré se caen y no hay login
local, nadie entra ni siquiera para apagar la bandera. Con Ibare encendido, un
usuario que no es administrador y entra por correo recibe «Ingrese con
“Ingresar con Ibare”…» (ver `LoginRequest::authenticate()`).

Con `IBARE_ACTIVO=false`, `/auth/ibare` y `/auth/ibare/callback` responden 404.

---

## 2. Las piezas

```
┌──────────────────────┐        ┌──────────────────────────┐        ┌──────────────────────────┐
│      NAVEGADOR       │        │          JICHI           │        │          IBARE           │
│  (Chrome del usuario)│        │  http://127.0.0.1:8000   │        │  http://localhost:8001   │
│                      │        │  composer run dev        │        │  Docker: ibare-backend-1 │
│ cookies:             │        │  app/AuthIbare/          │        │                          │
│  jichi-session       │        │  base: jichi1 (users)    │        │  base: ibare             │
│  ibare-session       │        │                          │        │  (funcionarios,          │
└──────────────────────┘        └──────────────────────────┘        │   clientes_oauth,        │
                                                                    │   codigos_autorizacion)  │
                                                                    └────────────┬─────────────┘
                                                                                 │ solo si MAMORE_FAKE=false
                                                                    ┌────────────▼─────────────┐
                                                                    │          MAMORÉ          │
                                                                    │  ¿contrato vigente hoy?  │
                                                                    └──────────────────────────┘
```

**El código de Jichi vive entero en `app/AuthIbare/`.** Afuera quedan solo la
config, la columna `users.mamore_id`, la restricción de `LoginRequest` y el
botón de la pantalla de login.

| Archivo | Qué hace |
| --- | --- |
| `app/AuthIbare/AuthIbareServiceProvider.php` | Carga `rutas.php` con `web` + `guest` y registra el comando. Sacarlo de `bootstrap/providers.php` desconecta el módulo |
| `app/AuthIbare/rutas.php` | `GET /auth/ibare` y `GET /auth/ibare/callback`, con `throttle:10,1` |
| `app/AuthIbare/IbareController.php` | Redirige a Ibare, recibe la vuelta, inicia la sesión y anota en `accesos` |
| `app/AuthIbare/IbareService.php` | Arma la URL con PKCE, canjea el código, valida el JWT y busca el usuario |
| `app/AuthIbare/IbareException.php` | Los mensajes que ve el funcionario |
| `app/AuthIbare/VincularIbareCommand.php` | `php artisan jichi:vincular-ibare` |
| `config/jichi.php` → `ibare` | `IBARE_ACTIVO`, `IBARE_URL`, `IBARE_CLIENT_ID`, `IBARE_CLIENT_SECRET`, `IBARE_TIMEOUT` |
| `resources/js/pages/auth/login.tsx` | Botón «Ingresar con Ibare» y el acceso de emergencia |

---

## 3. El recorrido completo, paso a paso

```
 NAVEGADOR                         JICHI (127.0.0.1:8000)                    IBARE (localhost:8001)
     │                                    │                                          │
 ①   │── GET /login ─────────────────────▶│ AuthenticatedSessionController::create   │
     │◀── pantalla con botón ─────────────│ prop ibare = true (IBARE_ACTIVO)         │
     │    «Ingresar con Ibare»            │                                          │
     │                                    │                                          │
 ②   │── clic: GET /auth/ibare ──────────▶│ IbareController::redirigir               │
     │                                    │ IbareService::urlAutorizacion()          │
     │                                    │  ├ state       = 40 letras al azar       │
     │                                    │  ├ verificador = 64 letras al azar       │
     │                                    │  ├ challenge   = base64url(sha256(verif))│
     │                                    │  └ guarda en SU sesión:                  │
     │                                    │      ibare = { state, verificador }      │
     │◀── 302 Location: localhost:8001/oauth/authorize?... ──                        │
     │                                    │                                          │
 ③   │── GET /oauth/authorize ───────────────────────────────────────────────────────▶│ AuthorizeController::show
     │     ?response_type=code                                                       │  ├ ¿existe cliente "jichi"?
     │     &client_id=jichi                                                          │  ├ ¿redirect_uri == la registrada?
     │     &redirect_uri=http://127.0.0.1:8000/auth/ibare/callback                   │  └ guarda la solicitud en
     │     &state=mLc1bCaD9g7v...                                                    │    ibare-session
     │     &code_challenge=JvFqiUSptIbYB1MS8...                                      │
     │     &code_challenge_method=S256                                               │
     │◀── formulario verde «Ibare — Iniciar sesión» ─────────────────────────────────│
     │                                    │                                          │
 ④   │── POST /oauth/authorize ──────────────────────────────────────────────────────▶│ FuncionarioAutenticador
     │     login=ignacio                                                             │  ├ busca funcionarios.login
     │     password=••••••••                                                         │  ├ Hash::check(contraseña)
     │                                                                               │  └ ¿contrato vigente?
     │                                                                               │     MAMORE_FAKE=true → sí
     │                                                                               │     (real: pregunta a mamoré
     │                                                                               │      por el funcionario 43)
     │                                                                               │ genera CÓDIGO de un solo uso
     │                                                                               │  (vence en 5 min, lleva
     │                                                                               │   adentro el challenge)
     │◀── 302 Location: 127.0.0.1:8000/auth/ibare/callback?code=def50200…&state=mLc1b… ─│
     │                                    │                                          │
 ⑤   │── GET /auth/ibare/callback ───────▶│ IbareController::callback                │
     │     ?code=def50200…&state=mLc1b…   │ IbareService::usuarioDesdeCallback()     │
     │                                    │  ├ saca {state, verificador} de la       │
     │                                    │  │ sesión (pull: sirve UNA vez)          │
     │                                    │  └ ¿state recibido == state guardado?    │
     │                                    │                                          │
 ⑥   │        (el navegador espera)       │── POST /oauth/token ────────────────────▶│ TokenController::issueToken
     │                                    │   SERVIDOR A SERVIDOR (el navegador      │  ├ ¿client_secret correcto?
     │                                    │   no ve esto)                            │  ├ ¿código válido, sin usar,
     │                                    │   grant_type=authorization_code          │  │  sin vencer?
     │                                    │   client_id=jichi                        │  ├ ¿misma redirect_uri?
     │                                    │   client_secret=(IBARE_CLIENT_SECRET)    │  ├ ¿sha256(verificador)
     │                                    │   redirect_uri=http://127.0.0.1:8000/…   │  │  == challenge del paso ②?
     │                                    │   code=def50200…                         │  └ quema el código
     │                                    │   code_verifier=(las 64 letras)          │
     │                                    │◀── 200 JSON ─────────────────────────────│ firma el JWT con su
     │                                    │   { token_type: "Bearer",                │ clave privada RSA
     │                                    │     expires_in: 600,                     │
     │                                    │     access_token: "eyJ0eXAi…",           │
     │                                    │     refresh_token: "def502…" }  ← jichi  │
     │                                    │                                  lo ignora│
     │                                    │                                          │
 ⑦   │                                    │── GET /.well-known/jwks.json ───────────▶│ DiscoveryController::jwks
     │                                    │   (solo la primera vez; queda en         │
     │                                    │    caché 1 hora)                         │
     │                                    │◀── { keys: [{ kid: "40881592c7e3331a",   │
     │                                    │      kty: RSA, n: …, e: … }] }           │
     │                                    │                                          │
 ⑧   │                                    │ JWT::decode(access_token, clave pública) │
     │                                    │  ├ ¿firma RS256 válida?                  │
     │                                    │  ├ ¿no vencido? (exp)                    │
     │                                    │  ├ ¿aud == "jichi"?                      │
     │                                    │  ├ ¿tipo_sujeto == "funcionario"?        │
     │                                    │  └ sub = "43"  ← el mamore_id            │
     │                                    │                                          │
 ⑨   │                                    │ SELECT * FROM users                      │
     │                                    │  WHERE mamore_id = '43' AND activo       │
     │                                    │  → admin@admin.com                       │
     │                                    │ Auth::login() + regenerate()             │
     │                                    │ ultimo_acceso_at = ahora                 │
     │                                    │ accesos: evento = 'login'                │
     │◀── 302 /panel (dashboard) ─────────│                                          │
     │    cookie jichi-session nueva      │                                          │
     │                                    │                                          │
 ⑩   │── cualquier pantalla del panel ───▶│ sesión normal de Laravel.                │
     │                                    │ El token ya no se usa ni se guarda.      │
     │                                    │ Ibare no vuelve a intervenir.            │
```

Solo el paso ⑥ (y el ⑦ la primera vez) va **de servidor a servidor**. Por eso el
`client_secret` y las 64 letras del verificador nunca pasan por el navegador.

Desde el paso ⑩ es la sesión de siempre de Laravel: roles, permisos y logout no
cambian. **El token no se guarda** ni se usa el refresh: Jichi solo lo necesita
para saber quién es la persona.

---

## 4. Qué trae el token

El `access_token` es un JWT: tres partes separadas por punto,
`cabecera.contenido.firma`. Decodificado:

```
CABECERA                          CONTENIDO (payload)
{                                 {
  "typ": "JWT",                     "aud": "jichi",           ← para quién es: SOLO jichi
  "alg": "RS256",                   "jti": "a7f3…",           ← id único del token
  "kid": "40881592c7e3331a"         "iat": 1790000000,        ← cuándo se emitió
}                                   "nbf": 1790000000,        ← válido desde
                                    "exp": 1790000600,        ← vence (+10 minutos)
FIRMA                               "sub": "43",              ← QUIÉN es: mamore_id
  RSA-SHA256 con la clave           "scopes": [],
  PRIVADA de Ibare                  "tipo_sujeto": "funcionario"
                                  }
```

El token **no trae** nombre, correo ni CI. Por eso existe `users.mamore_id`: es
el único puente entre el funcionario de Ibare y el usuario de Jichi.

---

## 5. Para qué sirve cada seguro

| Seguro | Se crea en | Se comprueba en | Protege contra |
| --- | --- | --- | --- |
| **state** | Jichi, paso ② | Jichi, paso ⑤ | Que otra página meta en tu navegador un código que no pediste |
| **PKCE** (verificador / challenge) | Jichi, paso ② | Ibare, paso ⑥ | Que quien robó el código de la URL lo canjee: sin las 64 letras no sirve |
| **client_secret** | Ibare, al registrar | Ibare, paso ⑥ | Que otro sistema se haga pasar por Jichi |
| **redirect_uri registrada** | Ibare, al registrar | Ibare, pasos ③ y ⑥ | Que Ibare mande el código a una dirección ajena |
| **Firma RS256** | Ibare, paso ⑥ | Jichi, paso ⑧ | Que alguien fabrique un token con otro `sub` |
| **aud = jichi** | Ibare, paso ⑥ | Jichi, paso ⑧ | Que un token emitido para SIREB sirva en Jichi |
| **Código de un solo uso** | Ibare, paso ④ | Ibare, paso ⑥ | Que recargar el callback dé acceso otra vez |

---

## 6. Dónde puede fallar y qué ve el usuario

| Paso | Falla | Qué se ve |
| --- | --- | --- |
| ③ | `redirect_uri` distinta de la registrada | JSON de Ibare `invalid_client` / `Client authentication failed` |
| ④ | Usuario o contraseña mal | Ibare: «Usuario o contraseña incorrectos.» |
| ④ | Sin contrato (con mamoré real) | El mismo mensaje: Ibare no dice cuál de las dos falló |
| ⑤ | `state` perdido o distinto | Jichi: «La solicitud de ingreso venció…» |
| ⑥ | Ibare apagado | Jichi: «Ibare no responde…» |
| ⑥ | Código ya usado o vencido | Jichi: «La respuesta de Ibare no es válida…» |
| ⑧ | Firma, `aud` o vencimiento mal | Jichi: «La respuesta de Ibare no es válida…» |
| ⑨ | Nadie en Jichi con ese `mamore_id` | Jichi: «Su cuenta de funcionario (id 43) no tiene acceso a Jichi…» |

Todo fallo del lado de Jichi queda en `accesos` con `evento = 'fallido'` y
`email = 'ibare'`. Los detalles técnicos (respuesta de Ibare, error del JWT) van
a `storage/logs/laravel.log`.

---

## 7. Puesta en marcha (lo que se hizo el 24/09/2026)

### 7.1 En Ibare — solo datos, su código no se toca

Desde `D:\garoto\gadbeni\ibare`, con los contenedores levantados:

```sh
# 1. Registrar Jichi como cliente. Muestra el client_secret UNA sola vez.
docker compose exec backend php artisan ibare:registrar-cliente jichi "Jichi" http://127.0.0.1:8000/auth/ibare/callback

# Perdido el secreto, se regenera (invalida el anterior):
docker compose exec backend php artisan ibare:registrar-cliente jichi "Jichi" http://127.0.0.1:8000/auth/ibare/callback --force
```

```sh
# 2. Dar de alta un funcionario (con tinker: ver la trampa de ADMIN_API_TOKEN abajo)
docker compose exec backend php artisan tinker
> App\Models\Funcionario::create(['mamore_id' => 43, 'login' => 'ignacio', 'password_hash' => Hash::make('...')]);
> exit
```

Con `MAMORE_FAKE=true` (y `APP_DEBUG=true`, o Ibare no arranca) el contrato da
siempre vigente, así que el `mamore_id` puede ser cualquier número.

### 7.2 En Jichi

`.env`:

```
APP_URL=http://127.0.0.1:8000
IBARE_ACTIVO=true
IBARE_URL=http://localhost:8001
IBARE_CLIENT_ID=jichi
IBARE_CLIENT_SECRET=<el que mostró registrar-cliente>
```

Vincular el usuario de Jichi con el funcionario:

```sh
php artisan jichi:vincular-ibare admin@admin.com 43
php artisan jichi:vincular-ibare admin@admin.com --quitar     # desvincular
```

Después, **cortar `composer run dev` entero (Ctrl+C) y volver a lanzarlo**, y
entrar siempre por `http://127.0.0.1:8000/login`.

Quien entra por Ibare sin usuario vinculado —o con el usuario dado de baja— es
**rechazado, no creado**: los roles los asigna un administrador.

---

## 8. Trampas — todas mordieron en la primera prueba

- **`invalid_client` en el paso ③ = la `redirect_uri` no coincide.** Tiene que ser
  idéntica, carácter por carácter, a la registrada en Ibare. Jichi la arma con
  `APP_URL` + `/auth/ibare/callback` —no con `route()` absoluta, que usaría el
  host de la petición—. Pasó con `APP_URL=http://jichi.test`: Jichi mandaba
  `jichi.test` y en Ibare estaba registrado `127.0.0.1:8000`.
- **Cambiar el `.env` no alcanza: hay que cortar `composer run dev` entero.**
  `artisan serve` reinicia solo al proceso hijo, que hereda las variables viejas
  del padre. Síntoma: el `.env` dice `127.0.0.1` y la URL que sale sigue diciendo
  `jichi.test`. Se comprueba con `php artisan tinker --execute 'echo config("app.url");'`
  (proceso nuevo) contra lo que muestra el navegador (proceso viejo).
- **Jichi en `127.0.0.1` e Ibare en `localhost`, nunca los dos en `localhost`.** El
  navegador comparte cookies entre puertos del mismo host, y las dos apps
  escriben `XSRF-TOKEN`: Inertia respondería 419 al volver del login.
- **`ibare:crear-admin` NO crea un funcionario.** Crea un administrador del panel
  de Ibare (`localhost:5174`), que es otra tabla. La pantalla verde de login
  valida contra `funcionarios`; con un admin da «Usuario o contraseña incorrectos».
- **`POST /admin/funcionarios` devuelve 401 si `ADMIN_API_TOKEN` está vacío** en
  el `.env` de Ibare, que es como viene. Por eso el alta se hizo con tinker.
- **`column "mamore_id" does not exist` al volver de Ibare** = la base de trabajo
  no tiene la columna. Está en la migración de campos institucionales de
  `users`; en una base ya armada se agregó sin borrar datos con
  `Schema::table('users', fn ($t) => $t->string('mamore_id', 50)->nullable()->unique())`.
  Ese error NO es de mamoré: Ibare ya había aceptado al funcionario.
- **No recargar la página del callback.** El código es de un solo uso y el
  `state` también: recargar da «La solicitud de ingreso venció». Se vuelve a
  empezar desde `/login`.
- **El discovery de Ibare anuncia un `issuer` equivocado** (`localhost:8000`, por
  su propio `APP_URL`) y el JWT no trae `iss`: por eso Jichi no valida el
  emisor; sí la firma y el `aud`.
- **Ejecutar `key:generate` en Ibare invalida los logins a medias** (cambia la
  clave de sus sesiones). A los tokens no les afecta: usan las claves RSA y
  `OAUTH_ENCRYPTION_KEY`.

---

## 9. Límites conocidos

- **El contrato se revisa solo al entrar.** Un funcionario dado de baja en mamoré
  sigue adentro de Jichi hasta que venza su sesión (`SESSION_LIFETIME`, hoy 120
  minutos).
- **No hay pantalla para vincular usuarios**: se hace con `jichi:vincular-ibare`
  hasta que exista el módulo de Usuarios.
- **Problemas de Ibare que Jichi no puede arreglar:** su login de funcionarios no
  tiene límite de intentos, y su refresh token no vuelve a consultar el contrato
  (Jichi no usa refresh, así que no le afecta). Anotados en `PENDIENTES.md`.
