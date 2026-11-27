# Seguridad de la API de la app BeniDigital

> Revisado el 19/11/2026; puesto al día el 25/11/2026 (carpetas `ApiApp/`, alcance acotado, pago directo en SIREB, Idempotency-Key, tokens que vencen y tope por cuenta). Ningún sistema es 100 % seguro: esto dice qué está cubierto (con
> prueba automática), qué **hay que configurar sí o sí antes de producción** y qué conviene
> reforzar. Volver a revisarlo cada vez que se agrega un endpoint.

Ver también: [README.md](README.md) § 4 (cómo funcionan los tokens) y [portal.md](portal.md).

---

## 1. Lo que ya está cubierto

| Riesgo | Cómo se cubre | Dónde | Prueba |
| --- | --- | --- | --- |
| **Adivinar contraseñas** | 5 intentos fallidos por C.I. + IP (compartidos con la web) y `throttle:10,1` por IP. Cada intento queda en `accesos` | `Portal\IngresarRequest`, `routes/api-app.php` | `SesionTest`: al sexto intento bloquea |
| **Saber quién tiene cuenta** | El mismo mensaje exista o no la C.I., esté o no desactivada | `IngresarRequest::ERROR` | `SesionTest`: clave errónea = C.I. inexistente |
| **Ver lo de otra persona** | Ninguna ruta recibe un id: todo sale de la cuenta del token | Controladores `ApiApp/` | `EnCursoTest::test_solo_ve_lo_suyo` |
| **Pedir a nombre de otro** | El carnet lo elige Jichi; `carnet_id`, `aprovechamiento_id` y `es_piscicultura` del pedido se ignoran | `PedirFaenaService`, `PedirGuiaService`, Requests del portal | `FaenaTest` y `GuiaTest`: «es siempre el suyo aunque manden otro» |
| **Saltarse reglas de negocio** | Mismas validaciones que ventanilla, en dos capas: el Request y el servicio de emisión | `PedirFaenaRequest`, `PedirGuiaRequest`, `EmitirFaenaService`, `EmitirGuiaService` | Las reglas del panel frenan igual (pendiente de pago, revocada, kilos) |
| **Un funcionario usando la API** | El token solo se emite a cuentas de beneficiario y el middleware lo vuelve a controlar | `IngresarRequest::cuenta()`, `SoloBeneficiarioApp` | `SesionTest`: un funcionario no pasa aunque tenga token |
| **Usar la app con la clave temporal** | Solo `/yo`, `/clave` y salir hasta cambiarla | `SoloBeneficiarioApp` | Pruebas de clave temporal en cada archivo |
| **Celular robado o perdido** | Ventanilla resetea o desactiva y el token muere al instante. Cambiar la clave saca a los otros celulares | `CuentaPortalService` | `SesionTest`: resetear y desactivar sacan a la app |
| **Robo de la base de datos** | Los tokens se guardan como hash SHA-256: con la base no se puede usar ninguno | Sanctum | — |
| **Robo del token en el celular** | Guardado cifrado por el sistema (Keystore en Android, Keychain en iOS) | App: `AlmacenTokenSeguro` | — |
| **Adivinar el código de una autorización** | 16 caracteres al azar de 29 posibles (`CodigoService::ALFABETO`, ≈ 2,5×10²³ combinaciones), solo el código completo, `throttle:20,1` | `AprovechamientoPesq::porCodigo()`, `routes/api-app.php` | `GuiaTest`: código inexistente → 404 |
| **Curiosear el padrón** | Al pescador no se lo busca por nombre ni C.I.; se muestra abreviado («Juan Carlos P. R.») y sin id | `PedirGuiaService` | `GuiaTest`, `GuiaEnLineaTest` |
| **El titular anula su pedido** (27/11/2026, decisión del responsable) | Desde la app **y** el portal web, con la misma regla (`Services\ApiApp\AnularPedidoService`). Solo lo **suyo** (la guía es del comercializador), **pendiente**, pedido **en línea** y sin pago en SIREB: lo demás, 404/422/409. Es el «Eliminar» del panel: primero anula el cobro en SIREB (si ya se pagó, frena) y deja la auditoría con la cuenta y el motivo. `throttle:10,1` | `AnularPedidoService`, `ApiApp\AnularTramiteController`, `Portal\AnularPedidoController` | `AnularTramiteTest`, `Portal\AnularPedidoTest`, `EliminarConPagoTest` |
| **A quién le vendió el pescador** (27/11/2026, decisión del responsable) | «Mis ventas» (`/papeles/{codigo}/ventas`) muestra, solo al dueño de la autorización, las guías que le compraron con el **nombre completo** del comercializador, como el panel: es su propia venta. 404 para lo ajeno o lo que no es una autorización; sin ids | `ApiApp\VentasController`, `VentasDelCupo` | `VentasTest` |
| **La foto del pescador al comercializador** (27/11/2026, decisión del responsable) | Para reconocer a quien le vende. Solo con el código completo de la autorización (el del papel del pescador), con token, con carnet de comercializador vigente y `throttle:20,1` compartido con la búsqueda. Bytes con `Cache-Control: private`, nunca una URL pública | `ApiApp\GuiaController::foto`, `PedirGuiaService::fotoDelPescador()` | `GuiaTest`: foto con código y token, 404 sin foto o sin código, 401 sin token, 403 sin carnet |
| **Exponer datos que la app no usa** | La API solo tiene las rutas del alcance (sesión, inicio, pedir faena y guía, por pagar, estado y «Mi perfil»). `/papeles` y `/pagos` se quitaron el 25/11/2026. Volvieron solo los PDF: el del recibo (26/11/2026) y el de un papel **vigente** suyo, sin carnets (`/papeles/{codigo}/pdf`, 27/11/2026, decisión del responsable): enlace firmado de 5 minutos, 404 para lo ajeno | `routes/api-app.php`, `ApiApp\PapelPdfController` | `EnCursoTest::test_fuera_del_alcance_de_la_app_no_hay_ruta` (404), `PapelPdfTest` |
| **Datos personales en «Mi perfil»** (vuelve el 25/11/2026, decisión del responsable) | `/perfil` entrega C.I., nacimiento, teléfono, correo y dirección: los **suyos**, los mismos que ve en la web. Sin id en la ruta (solo lo del token); la foto va por `/perfil/foto` con el token, nunca por una URL pública. Lo cuidan además el token cifrado que vence a los 90 días sin uso y el tope de 60 pedidos por minuto | `ApiApp\PerfilController`, `PerfilResource` | `PerfilTest`: solo ve lo suyo, sin `foto_url`, foto con token, 401 sin token, 403 con clave temporal |
| **Cookies y CSRF** | La API no acepta cookies (`config/sanctum.php`: `guard => []`): solo el token. El portal web sigue con sesión + CSRF de Laravel | `config/sanctum.php` | — |
| **Asignación masiva** | Solo se usa `$request->validated()`; lo que no está en las reglas no llega al servicio | Controladores | — |
| **Pedidos en cantidad** | `throttle:10,1` al pedir faenas y guías (cada una registra un cobro en SIREB), y **60 por minuto por cuenta** en todo lo que pide token. Cada throttle con su prefijo, para que no compartan contador | `routes/api-app.php` | `SeguridadTest::test_mas_de_60_pedidos_por_minuto_da_429` |
| **Pedidos duplicados** (25/11/2026) | Un doble toque o un reintento tras un corte de red registraba dos faenas o dos guías: dos cobros y, en la guía, los kilos del pescador descontados dos veces, que el titular no puede eliminar. Ahora `Idempotency-Key` es obligatoria en `POST /faenas` y `/guias`: la misma clave con los mismos datos devuelve lo ya registrado (24 h, por cuenta y ruta); con otros datos, `422`; dos a la vez, `409` | `ApiApp\Idempotente` | `SeguridadTest` (5 pruebas), `GuiaTest::test_repetida_con_la_misma_clave_descuenta_los_kilos_una_sola_vez` |
| **Token de un celular perdido que nadie avisa** (25/11/2026) | El token vence a los **90 días sin uso** (`expires_at`); con uso se renueva solo. Los tokens viejos sin vencimiento lo reciben en su próximo uso | `IngresarAppRequest::emitirToken()`, `SoloBeneficiarioApp::renovar()` | `SeguridadTest`: vencido → 401, el uso renueva, el viejo recibe uno |
| **Ver el estado de un trámite ajeno** (25/11/2026) | El `codigo` está impreso en el papel: no es secreto. `GET /tramites/{codigo}/estado` busca con el titular del token (`DocumentoDelPortal::buscar()`): lo ajeno y lo inexistente dan el mismo 404 | `ApiApp\EstadoTramiteController` | `EstadoTramiteTest::test_lo_ajeno_y_lo_inexistente_dan_el_mismo_404` |
| **Aprobar con lo que dice el celular** | La app paga en SIREB, pero aprueba solo este sistema, consultando a SIREB (`ConfirmarPagoService::verificar()`). La API no tiene ruta para simular ni marcar un pago | `CobroQrService::estado()` | `EstadoTramiteTest`: sin pagar sigue pendiente |
| **La credencial de SIREB en el celular** | La app paga con la API **pública** de SIREB (`X-App-Key`, que no autoriza nada de cobro). La credencial `sedag`, que registra y anula liquidaciones, queda solo en el servidor | `.env` (`SIREB_CLIENT_ID`/`SECRET`) | — |

---

## 2. Checklist antes de producción — OBLIGATORIO

| # | Qué | Por qué | Cómo |
| --- | --- | --- | --- |
| 1 | **HTTPS** en Jichi y la app compilada con `JICHI_URL=https://…` | Sin HTTPS, cualquiera en la misma red puede leer contraseñas y tokens | Certificado en el servidor (Coolify/Traefik ya está delante). En la app de producción, `http` está bloqueado: solo el manifiesto de **debug** lo permite (`android/app/src/debug/AndroidManifest.xml`) |
| 2 | **`APP_DEBUG=false`** | Con `true`, un error muestra código, consultas y datos internos a cualquiera | `.env` de producción. Ojo: el `docker-compose.yml` de desarrollo lo pone en `true` por defecto |
| 3 | **`APP_ENV=production`** | Varios comportamientos de Laravel dependen de él | `.env` de producción |
| 4 | **No usar `SERVER_HOST=0.0.0.0` fuera de desarrollo** | Deja el servidor de desarrollo visible a toda la red. Con debug y las claves de prueba (`JICHI_SEED_PASSWORD=password`) es peligroso en un WiFi público | Solo en la PC de desarrollo, en una red de confianza |
| 5 | **Actualizar `symfony/filesystem`** | `composer audit` marca un aviso (GHSA-6prc-h956-hxjq). Es anterior a la API | `composer update symfony/filesystem` y correr las pruebas |
| 6 | **Que el reloj y la cola funcionen** | La verificación de pagos con SIREB depende del scheduler | Cron de Laravel en producción (ver `docs/PENDIENTES.md`) |

---

## 3. Refuerzos pendientes (recomendados)

Los obligatorios de la parte B (tokens que vencen, límite general, pedidos duplicados y el
trámite solo del titular) quedaron hechos el 25/11/2026: ver § 1.

| # | Qué | Por qué | Cómo (propuesta) |
| --- | --- | --- | --- |
| C | **Restringir CORS** | Hoy cualquier origen puede llamar a `api/*`. No es grave (no hay cookies, hace falta el token), pero conviene cerrarlo | Publicar `config/cors.php` y limitar `allowed_origins` al dominio de la app web, si se publica como web. Para el celular no hace falta |
| D | **Ver y cerrar sesiones en el panel** | Hoy, para sacar un celular perdido, ventanilla tiene que resetear la clave | En la ficha del beneficiario: «Celulares con sesión abierta» (nombre del dispositivo y último uso) y «Cerrar sesión en los celulares» |
| E | **Si la app se publica también como web** | En el navegador el token queda en el almacenamiento del navegador, menos seguro que el del celular | Preferir el celular. Si se publica como web: CSP estricta y tokens con vencimiento corto |
| F | **Kilos de un pescador reservados con guías que nunca se pagan** | Sin tope de pendientes, un comercializador con el código de un pescador puede descontarle kilos con guías impagas; solo ventanilla las elimina | **Lo decide el responsable:** un tope de guías en línea pendientes por comercializador, o liberar los kilos cuando vence la liquidación |

---

## 4. Cómo revisar la seguridad de un endpoint nuevo

- [ ] ¿Está en `routes/api-app.php`, dentro del grupo `auth:sanctum` + `beneficiario.app` + `throttle:60,1,app`?
- [ ] ¿La ruta **no** recibe ningún id? ¿Todo sale de `$request->user()`? Si recibe un código,
      ¿se busca con el titular y lo ajeno da el mismo 404 que lo inexistente?
- [ ] Si registra algo que cobra, ¿lleva `idempotente`?
- [ ] ¿Lo que decide Jichi (carnet, autorización, montos, kilos) **no** se toma del pedido?
- [ ] ¿Usa `$request->validated()` y reglas heredadas del portal o del panel?
- [ ] ¿El Resource saca las URLs que piden sesión de navegador y los datos que la app no necesita?
- [ ] ¿Tiene `throttle` **con prefijo** si escribe, cobra, consulta SIREB o recibe una llave?
- [ ] ¿Tiene pruebas de 401 (sin token), 403 (clave temporal), «solo ve lo suyo» y, si cobra, del
      pedido repetido?
- [ ] ¿Está en [portal.md](portal.md) y, si cambia algo de esto, acá?
