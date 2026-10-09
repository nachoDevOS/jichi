# Portal del beneficiario — `/mi-cuenta`

> Creado el 28/09/2026. La tercera mitad del sistema, al lado del panel
> (funcionarios) y del público (sin sesión).

El pescador o comercializador entra con su **C.I.** y una contraseña, y desde el
celular **consulta**: sus papeles vigentes, lo que tiene en trámite, sus recibos y
sus datos. **No hace trámites**: nada del portal escribe en el dominio.

## Las tres mitades

| | Panel | Público | **Portal** |
| --- | --- | --- | --- |
| Quién | Funcionario del SEDAG | Cualquiera | El beneficiario, con su cuenta |
| Rutas | `routes/panel.php` · `/panel` | `routes/publico.php` · `/`, `/verificar` | `routes/portal.php` · `/mi-cuenta` |
| Controladores | `Controllers/Panel/` | `Controllers/Publico/` | `Controllers/Portal/` |
| Pantallas | `pages/panel/` | `pages/publico/` | `pages/portal/` |
| Componentes | `components/panel/` | `components/publico/` | `components/portal/` |
| Layout | `layout-panel.tsx` | `layout-institucional.tsx` / `layout-publico.tsx` | `layout-portal.tsx` |

## La cuenta es una fila de `users` con `beneficiario_id`

**La columna es la categoría:** con `beneficiario_id` la cuenta es del portal y
**nunca** entra al panel; sin él, es un funcionario y nunca entra al portal. Se
eligió `users` y no una tabla aparte para que `accesos` y `auditorias` lo
registren sin nada nuevo. El precio es que el aislamiento depende de estas
cinco cosas, y **no hay que romper ninguna**:

1. **`funcionario` en el GRUPO entero de `/panel`** (`SoloFuncionario`), antes
   que `permiso:`. Una cuenta del portal con un rol puesto por error igual no pasa.
2. **`beneficiario` en el grupo de `/mi-cuenta`** (`SoloBeneficiario`), que
   además corta la sesión de una cuenta desactivada y obliga a cambiar la clave
   temporal.
3. **El login del panel rechaza cuentas del portal** (`LoginRequest`), y el del
   portal solo busca cuentas colgadas de un beneficiario (`IngresarRequest`).
4. **Una cuenta del portal nunca lleva roles.** Ni el seeder ni la futura
   pantalla de Usuarios se los asignan. Toda lista de personal parte de
   `User::funcionarios()`.
5. **Ninguna ruta del portal recibe un id.** Todo sale de
   `$request->user()->beneficiario`: no hay número que cambiar en la URL para
   ver lo de otro.

Un funcionario que además pesca necesita **dos cuentas**. Es a propósito: la misma
cuenta no aprueba trámites y es titular de ellos.

## El circuito de la cuenta

```
ficha del panel ──[Dar acceso]──▶ clave temporal (se ve UNA vez) ──▶ comprobante impreso
                                        │
portal: ingresar con C.I. ──▶ obligado a cambiar la clave ──▶ inicio
                                        │
ventanilla ──[Resetear]──▶ clave temporal nueva (también reactiva)
           └─[Desactivar]──▶ no entra más; su sesión abierta se corta en la próxima visita
```

- La tarjeta está en la pestaña **Datos** de la ficha; en el encabezado va el
  atajo «Portal: habilitado / desactivado / sin acceso», que lleva ahí.
- La lógica vive en `CuentaPortalService` (dar acceso, resetear, desactivar y
  cambiar la clave); el panel la llama desde `Panel/CuentaPortalController`, con
  el permiso `beneficiarios.portal`.
- **La clave temporal no se guarda legible en ningún lado.** Viaja por flash
  (`flash.cuenta_portal`) y se muestra una sola vez. Perdida antes de
  entregarla, se resetea.
- Ocho caracteres **sin confundibles** (sin 0/O ni 1/I/L): se dicta y se copia
  a mano desde un papel.
- **No hay «olvidé mi contraseña»**: muchos pescadores no tienen correo, así que
  se resetea en ventanilla con la cédula.
- **La clave propia: 8 o más, solo letras y números, con los dos** (08/10/2026,
  `CambiarClaveRequest`): sin signos ni espacios, para que se escriba fácil en
  cualquier celular. La ñ y las tildes cuentan como letras. La pantalla
  (`portal/clave.tsx`) tilda las reglas mientras se escribe; el servidor decide.

## Seguridad del ingreso

- La C.I. se adivina (7 dígitos): `throttle:10,1` por IP en la ruta, y 5
  intentos por C.I. + IP en `IngresarRequest`.
- **El mismo mensaje exista o no la cuenta**, para no revelar quién tiene una.
- **Cómo se lee la C.I. tipeada** (04/10/2026): sin puntos de miles, se busca
  primero tal cual —la C.I. se guarda como se cargó, guion incluido— y después
  solo el número, sin complemento ni expedido («1234567-1A BN» → `1234567`). El
  tope de intentos va por ese número, así que las variantes comparten contador.
- Cada intento queda en `accesos`, con `CI 1234567` en la columna `email`.

## Qué muestra

| Pantalla | Controlador | Qué |
| --- | --- | --- |
| Inicio | `InicioController` | Lo vigente hoy, la vitrina de trámites con requisitos (marca lo que puede pedir con `App\Support\TramitesDisponibles`), si debe algo y cuántos trámites tiene en curso |
| En curso | `EnCursoController` | Lista de trámites abiertos —todos esperan el pago en SIREB— y, en cada fila (`fila-tramite.tsx`), la línea de avance Solicitado → Pago en Recaudaciones → Aprobado y el **código de pago** de SIREB. El paso lo da `etapa` y el texto `siguiente_paso`, los dos de `ResumenPortal` a partir del enum: React no decide. **Muestra en qué está el pago** (`pago`: sin pago / en revisión / validado / caída, de `ResumenPortal::estadoPago()`; con la liquidación vencida o anulada oculta el código de pago y manda a ventanilla por uno nuevo). **Se refresca sola cada minuto** (`usePoll`, solo la prop `tramites`, pausado con la pestaña oculta; 08/10/2026). No es «tiempo real»: el estado lo mueve la verificación con SIREB, cada 10 min |
| Mis papeles | `PapelesController` | Todo lo que se aprobó alguna vez, con filtros **Vigentes** (abre acá) / **Vencidos** (incluye la autorización agotada) / **Revocados** (dados de baja: incluye anulado, «sin efecto» y **no pagado** —venció el plazo de pago en SIREB, con su motivo—) / Todos. **Vencidos** es por fecha: un aprobado fuera de vigencia sigue `aprobado`. `situacion`, `dias_restantes` y `motivo_baja` los calcula `ResumenPortal` desde el enum y `estaVigente()`; «vence pronto» a 30 días. Lo abierto va en «En curso» |
| Mis pagos | `PagosController` | Por **gestión** (`?gestion=`, informe de aportes del **Art. 13**): sus **recibos**, cada uno con el pago tal como lo validó SIREB (N° de transacción, banco, fecha). Cada recibo se **descarga** (`/mi-cuenta/recibos/{codigo}/descargar`, `DescargarReciboController`, el mismo PDF del panel como `attachment`); lo ajeno da 404. El pago se hace en SIREB (02/10/2026) |
| Mis datos | `PerfilController` | Sus datos, **solo para leer**: se corrigen en ventanilla |

Cada documento lleva su código y un enlace a `/verificar` (en pestaña aparte),
la misma página que abre el QR.

### El pago se hace en SIREB (02/10/2026)

**Pagar por QR, de muestra (08/10/2026).** Los cuatro trámites (autorización,
carnet, faena y guía) ofrecen «Pagar por QR» en «En curso» cuando la última
consulta dice `sin_pago` (`puede_pagar_qr`, de `ResumenPortal`). Al abrirlo,
`GET /mi-cuenta/pagar-qr/{codigo}` (`Portal\PagoQrController`, `throttle:20,1`)
**vuelve a preguntar a SIREB** con `ConfirmarPagoService::puedePagarPorQr()`: el QR sale
solo con la liquidación `pendiente` y **sin ningún pago cargado**; si no, la ventana
dice por qué. El QR es `components/comunes/qr-simulado.tsx`, el mismo del panel:
**no se puede leer ni cobra**, es para probar el circuito. «Descargar QR» lo baja
en PNG con el monto y el código de pago debajo, armado en el navegador (canvas).

Un trámite pendiente muestra además el **código de
pago** de su liquidación en SIREB (`codigo_pago`, de `ResumenPortal`) y el texto
de qué falta; cuando Recaudaciones valida el pago, el documento se aprueba solo.

**Las consultas son las del panel:** `App\Support\ExpedienteBeneficiario` tiene
los `with()` que usa también `BeneficiarioController::show()`. Lo que se muestra
lo arma `App\Support\ResumenPortal`: sin ids y sin banderas de funcionario. La
vigencia (`estaVigente()`, «sin efecto») sale de los mismos métodos del modelo.

## Diseño

Claro siempre, como la portada: no usa los tokens del panel porque cambian en modo
oscuro, y el portal se abre en teléfonos de todo tipo. Por eso tiene sus propias
piezas en `components/portal/piezas.tsx` en lugar de `Badge` e `Input`. En el
celular el menú va abajo; en pantallas anchas, como pestañas arriba.

### Descargar el PDF desde el portal (28/09/2026)

Cada papel **vigente hoy** trae «Descargar PDF» → `/mi-cuenta/descargar/{codigo}`
(`Portal/DescargarController`), que lo baja como archivo (`Content-Disposition:
attachment`). En el celular imprimir no sirve; descargar deja el papel guardado. Es **el
mismo PDF del panel**: el controlador pasa el documento a
`AutorizacionPescaController`, `PermisoFaenaImpresionController` o
`GuiaImpresionController`, que no escriben
nada, en lugar de copiar un armado lleno de medidas a mano.

- **Solo lo vigente hoy** (`estaVigente()`): pendiente, vencido, revocado o sin
  efecto responde 404. Un papel que ya no vale no se reimprime desde casa.
- **El carnet NO se descarga desde el portal** (decidido con el responsable el
  28/09/2026): perdido, se revoca y se emite otro en ventanilla con código nuevo
  —la reposición—. Bajarlo de nuevo desde casa saltearía ese trámite. Se descargan la
  autorización, la faena y la guía.
- El control de dueño: `App\Support\DocumentoDelPortal`.

### Vista previa «NO VÁLIDO» de lo abierto (28/09/2026)

Un trámite **pendiente** trae «Vista previa» →
`/mi-cuenta/vista-previa/{codigo}` (`Portal/VistaPreviaController`): el mismo PDF
del panel con **«NO VÁLIDO»** cruzado en cada hoja. Lo aprobado no la tiene: se
descarga limpio.

- **La marca** es un PNG con transparencia (`App\Support\MarcaAgua`), puesto
  con `position: fixed` por `documentos/partes/marca-no-valido.blade.php` en las
  cuatro plantillas. Imagen y no CSS: DomPDF no rota texto y su `opacity` no es
  confiable. En el carnet apaisado usa `cubrir` para que se lea.
- **El PDF sale de `documento()`** de cada controlador de impresión del panel
  (`CarnetImpresionController`, `AutorizacionPescaController`,
  `PermisoFaenaImpresionController`, `GuiaImpresionController`), que arma el
  archivo SIN controles de estado y con `$marcaAgua` opcional. `imprimir()` sigue
  validando igual que antes y llama a `documento()`.
- **Se dibuja con pdf.js en `<canvas>`** (`components/portal/visor-vista-previa.tsx`),
  no en el visor del navegador, que trae descargar e imprimir y en Android baja el
  archivo. Sin clic derecho y con `.sin-imprimir` en `@media print`. pdf.js va en
  un chunk aparte que se baja recién al abrir la primera vista previa.
- **Límite honesto:** nada impide una captura de pantalla. Lo que protege es la
  marca, horneada en el PDF: capturado o guardado, el papel dice «NO VÁLIDO».

## La lista de cuentas en el panel (05/10/2026)

**Seguridad › Usuarios** (`Panel/UsuarioController`, permiso `usuarios.ver`) lista todas las
cuentas —los funcionarios y, con rol «Beneficiario», los beneficiarios—: estado (activa, clave temporal, desactivada), último ingreso y
desde cuándo, con filtro por estado. Es solo para controlar quién tiene acceso; cada fila lleva a la pestaña Datos de
la ficha, donde se resetea o desactiva. La pantalla no dice «portal»: se llama Usuarios.

## Lo que falta

- Trámites desde el portal (pedir una faena, subir una boleta).
- Marcar la copia impresa desde el portal (por ejemplo «COPIA DEL TITULAR»), si
  la unidad quiere distinguirla del papel entregado en ventanilla.

## «Lo que puede tramitar», en el inicio

*(08/10/2026: el cuadro «Puede tramitar» se quitó; su dato vive en la vitrina
`components/portal/catalogo-tramites.tsx`: el sello «Usted puede pedirlo» y, en
faena y guía, los kilos que le quedan o por qué no puede. La vitrina muestra
además, por trámite, para qué sirve, qué hay que llevar y cuánto vale.)*

`App\Support\TramitesDisponibles::para()` dice qué documento puede pedir hoy en
ventanilla y, si no, por qué. **No tiene reglas propias:** pregunta a los mismos
métodos que usa el panel para dejar emitir —`puedeEmitirFaenas()`,
`puedeEmitirGuias()`, `motivoSinPermisos()`, el scope `enCurso()`—.

- Faena y guía aparecen solo con el carnet vigente de la actividad.
- La autorización, solo si no hay otra en curso (una agotada libera el lugar).
- Un carnet, solo si no tiene uno vigente ni en trámite; el de pescador pide
  la autorización.
- **En modo flexible** (`APROVECHAMIENTO_ESTRICTO=false`) la faena sale
  disponible aun sin kilos libres, porque el panel la emite igual; el detalle
  lo dice («ya no tiene kilos libres: consulte en ventanilla»).
