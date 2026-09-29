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

## Seguridad del ingreso

- La C.I. se adivina (7 dígitos): `throttle:10,1` por IP en la ruta, y 5
  intentos por C.I. + IP en `IngresarRequest`.
- **El mismo mensaje exista o no la cuenta**, para no revelar quién tiene una.
- Cada intento queda en `accesos`, con `CI 1234567` en la columna `email`.

## Qué muestra

| Pantalla | Controlador | Qué |
| --- | --- | --- |
| Inicio | `InicioController` | Lo vigente hoy, qué puede tramitar (`App\Support\TramitesDisponibles`), si debe algo y cuántos trámites tiene en curso |
| En curso | `EnCursoController` | Lista de trámites abiertos con filtros (Todos / Pendientes / En revisión) y, en cada fila (`fila-tramite.tsx`), la línea de avance Solicitado → Pago → Revisión → Aprobado. El paso lo da `etapa` y el texto `siguiente_paso`, los dos de `ResumenPortal` a partir del enum: React no decide |
| Mis papeles | `PapelesController` | Todo lo que se aprobó alguna vez, con filtros **Vigentes** (abre acá) / **Vencidos** (incluye la autorización agotada) / **Revocados** (incluye anulado y «sin efecto») / Todos. `situacion`, `dias_restantes` y `motivo_baja` los calcula `ResumenPortal` desde el enum y `estaVigente()`; «vence pronto» a 30 días. Lo abierto va en «En curso» |
| Mis pagos | `PagosController` | Por **gestión** (`?gestion=`, informe de aportes del **Art. 13**). Pestaña **Pagos**: **una fila por trámite** con el total, sus recibos y el peor estado de sus boletas (observado > en control > validado); al tocarla, cada boleta con número, fecha, monto, estado, motivo si está observada y **«Ver comprobante»**. Un trámite puede pagarse con una o varias boletas. Pestaña **Recibos**. Cada recibo se **descarga** (botón con ícono, `/mi-cuenta/recibos/{codigo}/descargar`, `DescargarReciboController`, el mismo PDF del panel como `attachment`): prueba el pago y no habilita nada, así que se baja siempre; lo ajeno da 404. El reglamento no lo menciona; lo respalda el Art. 13. No muestra quién validó |
| Mis datos | `PerfilController` | Sus datos, **solo para leer**: se corrigen en ventanilla |

Cada documento lleva su código y un enlace a `/verificar` (en pestaña aparte),
la misma página que abre el QR.

### «Pagar con QR» es una DEMOSTRACIÓN, apagada fuera de local (28/09/2026)

Una tarjeta con saldo pendiente muestra «Pagar … con QR», que abre el **modal
global** del portal (`components/portal/modal-pago.tsx`): `ProveedorPago` va
montado en `LayoutPortal` y cualquier tarjeta lo abre con `useModalPago()`. El
concepto, el monto y el código salen de la tarjeta; el QR es una **imagen**
(`/mi-cuenta/pagar/{codigo}/qr`, `PagoSimuladoController`), porque el sistema no
usa `fetch`.

**No está conectado a ningún banco y no registra nada**, pero a pedido del
responsable la pantalla ya no lo dice: muestra el texto del pago automático
(«no necesita llevar ningún comprobante a ventanilla»). Por eso vive detrás de
**`jichi.portal.pago_qr`** (`PORTAL_PAGO_QR`), que por defecto está encendido
**solo con `APP_ENV=local`**: en producción el botón no aparece y la ruta da
404. **No se enciende en producción hasta conectar un cobro real**: un
beneficiario creería haber pagado y su trámite seguiría pendiente. El
*contenido* del QR sigue diciendo `SIMULACION - SIN VALOR DE PAGO`, así que una
app de banco no lo toma como cobro.

La ruta usa el **código público**, no un id, y el controlador responde **404**
si el documento no es de quien tiene la sesión, si no se cobra o si no falta
plata: 404 y no 403, para no confirmar que el código existe. Conectarlo a un
cobro real (QR interbancario) sería otro módulo: pagos que entran solos, sin
boleta, y un control que hoy es manual.

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
- El control de dueño es el mismo de «Pagar»: `App\Support\DocumentoDelPortal`.

### Vista previa «NO VÁLIDO» de lo abierto (28/09/2026)

Un trámite **pendiente o en revisión** trae «Vista previa» →
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

## Lo que falta

- Trámites desde el portal (pedir una faena, subir una boleta).
- Marcar la copia impresa desde el portal (por ejemplo «COPIA DEL TITULAR»), si
  la unidad quiere distinguirla del papel entregado en ventanilla.

## «Puede tramitar», en el inicio

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
