# Reglas de negocio y funcionales — Sistema Jichi

> **Definidas por el responsable del proyecto el 20/09/2026.** Es la
> especificación: cuando el código y este documento no coincidan, **manda este
> documento** y lo que falta es trabajo por hacer —anotado al final—.
>
> El esquema que las sostiene está en [MER.md](MER.md).

---

## Paso 1 — El registro base (Beneficiarios)

- Todo trámite del sistema nace a partir de una persona registrada en la tabla
  **`beneficiarios`**.
- Se identifica de manera única por su número de carnet de identidad (`ci`), que
  incluye de forma opcional su **complemento** y el departamento de expedición
  (**`expedido`**).
- Almacena de forma rigurosa los datos personales —nombres, apellidos, fecha de
  nacimiento, género y nacionalidad— y los datos de contacto o ubicación
  —dirección, ciudad, provincia, teléfono y correo—.
- Cuenta con **borrado lógico** (`deleted_at`) para preservar la integridad
  histórica de los registros ante auditorías o trámites pasados.

## Paso 2 — La extracción y la «Bolsa Madre» (Aprovechamiento Pesquero)

- Este módulo aplica **exclusivamente para pescadores**.
- Está regulado por escalas predefinidas en **`categorias_aprovechamiento`**, que
  establecen un número de escala, un rango de kilos mínimos y un **techo máximo
  de extracción** (`kilos_max`), además del costo oficial en bolivianos
  (`valor_bs`).
- Cuando un pescador solicita un cupo se genera un registro en
  **`aprovechamientos_pesq`** que **hereda** la modalidad y el volumen total de
  kilos de la escala elegida.
- Su ciclo de vida pasa por los estados `pendiente`, `en_revision`, `aprobado`,
  `vencido`, `agotado` o `revocado`, dependiendo de la vigencia de la gestión.

### Una autorización vigente por persona, y la revocación — 27/09/2026

El documento se llama siempre **Autorización de Pesca para Aprovechamiento
Pesquero** (nunca «autorización de pesca» a secas).

**Regla 1 — Una sola a la vez.** Una persona no puede tener dos autorizaciones
que la ocupen al mismo tiempo. Ocupa el lugar la que está `pendiente`,
`en_revision` o `aprobado` **y** todavía en fecha. Mientras exista una así, no
se otorga otra.

**Regla 2 — Qué libera el lugar.** Se puede otorgar una nueva cuando la
anterior está:

| Estado de la anterior | ¿Se otorga otra? | Por qué |
| --- | :-: | --- |
| `pendiente` / `en_revision` en fecha | ✘ | Es un trámite en curso: se corrige o se rechaza, no se duplica |
| `aprobado` en fecha | ✘ | Está vigente |
| `aprobado` con la fecha pasada / `vencido` | ✔ | Ya no está vigente, aunque diga «aprobado» en el historial |
| `agotado` (pescó todos los kilos) | ✔ | No le queda nada que autorizar |
| `revocado` (aunque siga en fecha) | ✔ | La unidad la dio de baja |

**Regla 3 — Revocar.** La unidad puede **revocar** una autorización `aprobado`
o `agotado` antes de su vencimiento. Pide permiso `aprovechamientos.revocar`
(supervisión), motivo escrito de al menos 10 caracteres y la casilla de
confirmación; el motivo queda en `auditorias`. **No se revierte.** Una
`pendiente` se elimina, una `en_revision` se rechaza y una vencida ya no
autoriza nada: ninguna de las tres se revoca.

**Regla 4 — Qué deja de poder hacerse con una autorización revocada:**

- **No se emiten carnets con ella.** Deja de aparecer para elegir en el
  formulario del carnet, y si la revocan mientras un carnet espera la firma,
  ese carnet ya no se aprueba.
- **No se emiten faenas** con ella, ni siquiera en modo flexible
  (`APROVECHAMIENTO_ESTRICTO=false`): ese modo afloja el tope de kilos, no una
  baja. Si una faena esperaba la firma, ya no se aprueba.
- **No se imprime** la autorización (mismo criterio que el carnet revocado).
- La verificación pública la muestra como **no vigente: revocada**.

**Regla 5 — Revocar arrastra lo que todavía vale** *(decidido el 27/09/2026)*.
En la misma operación, con el mismo motivo, se revocan:

- sus **carnets `aprobado` y vigentes** (fecha de vencimiento de hoy en
  adelante) → pasan a `revocado`;
- sus **faenas `aprobado` y vigentes** (desembarque de hoy en adelante), de
  cualquier carnet de esa autorización, también de uno repuesto antes → pasan a
  `revocado` y la salida se corta.

**Lo que NO se toca**, porque ya no autoriza nada: carnets o faenas vencidos,
cerrados o ya revocados. Revocar lo vencido no tendría sentido. Los carnets
`pendiente` / `en_revision` de esa autorización tampoco se revocan (revocar es
solo para lo firmado), pero **ya no se pueden aprobar**: ventanilla los elimina
o los rechaza. Las faenas `pendiente` / `en_revision`, igual.

La auditoría de cada fila arrastrada dice «Revocado junto con su Autorización
de Pesca para Aprovechamiento Pesquero: *motivo*». Los kilos de una faena
revocada vuelven al saldo, como los de una vencida (`consumeCupo()` no la
cuenta); sobre una autorización revocada ese saldo ya no se puede usar. Los
pagos quedan como estaban. Ni la faena ni el carnet revocados se imprimen.

**Regla 6 — Volver a pescar.** Hace falta una autorización **nueva** y un
carnet **nuevo** colgado de ella. Como el carnet viejo ya quedó revocado, no
traba la regla de «un carnet vigente por actividad».

**Ejemplos (Olga Acevedo):**

1. Tiene la de 300 kg aprobada, vence el 31/12/2026. El 15/10 pide otra →
   **rechazada**: «ya tiene un aprovechamiento vigente… hay que esperar a que
   venza o revocarlo».
2. La de 2026 venció el 31/12. El 10/01/2027 pide la de 2027 → **se otorga**.
3. El 20/10/2026 revocan la de 300 kg (vigente hasta el 31/12). En ese momento
   tiene el carnet N° 00001 aprobado y la faena 000001 en el agua hasta el
   27/10 → los **tres** quedan revocados. Una faena 000000 que desembarcó el
   30/09 queda **aprobado** como estaba: ya había terminado.
4. Con la revocada, ventanilla intenta emitir un carnet o una faena →
   **rechazado**.
5. Ese mismo día pide una autorización **nueva** → se otorga; la paga, la
   firman, y emite un carnet nuevo sobre ella → se permite.

**Dónde vive:** `EstadoAprovechamiento::Revocado` y `permiteRevocacion()`,
`RevisarCupoService::revocar()`, `RevocarCupoRequest`, la ruta
`PATCH /panel/aprovechamientos/{id}/revocar`, y los controles en
`RevisarCupoService::revocarLoQueCuelga()` (la cascada), `EstadoFaena::Revocado`,
los controles en `OtorgarCupoService` (scope `enCurso()`),
`EmitirCarnetService::cupoUtilizable()`, `RevisarCarnetService::aprobar()`,
`EmitirFaenaService`, `RevisarFaenaService::aprobar()`,
`AprovechamientoPesq::puedeEmitirFaena()` / `puedeImprimirse()`,
`PermisoFaena::puedeImprimirse()` y `Carnet::motivoSinPermisos()`. Probado el 27/09/2026 contra los servicios reales
dentro de una transacción deshecha con `rollBack()`.

## Paso 3 — La identificación oficial (Carnets)

- El carnet es el **documento habilitante** que define el rol del actor mediante
  el campo **`tipo_actor`**: `pescador` o `comercializador`.
- **Regla para el pescador:** su registro debe estar vinculado
  **obligatoriamente** a un `aprovechamiento_id`. A partir de una misma «Bolsa
  Madre» el sistema permite gestionar y emitir los carnets necesarios.
- **Regla para el comercializador:** su `aprovechamiento_id` se registra en
  **NULL**, porque no realiza actividades extractivas.
- Todo carnet se clasifica según su `tipo_carnet_id` y cuenta con el **aval
  obligatorio** de una asociación (`asociacion_id`) y un **código único global**
  (`codigo_carnet`).
- **Circuito:** nace `pendiente` → se cobra → `en_revision` → alguien lo firma
  → `aprobado`. Recién aprobado se imprime y habilita a trabajar. Un carnet
  pendiente se **elimina**; uno en revisión se **rechaza**.
- **Revocar** es dar de baja un carnet `aprobado` antes de su vencimiento, con
  motivo escrito (queda en la auditoría). No se revierte. Al escanearlo, la
  verificación pública lo informa como **no vigente**. *(25/09/2026)*
- **Revocar el carnet NO toca sus permisos de faena ni sus guías:** lo ya
  emitido y aprobado sigue valiendo hasta su propia fecha. Lo que se da de baja
  es la credencial, no las salidas que ya autorizó. *(25/09/2026)*
- **Una persona no tiene dos carnets vigentes de la misma actividad.** Para
  reponer uno perdido o dañado se revoca el actual y se emite otro con **la
  misma autorización de pesca**: no hace falta tramitar un cupo nuevo, y los
  kilos ya usados siguen descontados. Ver «Reposición», más abajo.

## Paso 4 — La operativa del pescador (Permisos de Faena)

- Depende **estrictamente del carnet** del pescador: cada permiso se vincula de
  forma directa **únicamente a un `carnet_id`**.
- Controla la salida mediante un **número de faena correlativo** y registra los
  `kilos_extraidos` de cada salida.
- Esos kilos impactan **indirectamente** en el volumen total del aprovechamiento
  raíz al que está asociado el carnet.
- Vigencia **máxima de 30 días** por salida: al aprobarla, `fecha_salida` es
  ese día y `fecha_desembarque` salida + 30. Estados `pendiente`,
  `en_revision`, `aprobado`, `completado` o `vencido`.
- **Calca el talonario «PERMISO POR FAENA».** El Área de Fiscalización y
  Control de la Actividad Pesquera autoriza a: la embarcación, de propiedad de,
  comandante de barco, matrícula naval N°, N° kardex, la región desde / hasta y
  la cantidad autorizada de pescado extraído en kg. Todos texto libre y
  opcionales menos los kilos. *(25/09/2026)*
- **Las fechas NO las escribe ventanilla: las pone la aprobación.** La salida
  es el día en que se firma el permiso y el desembarque, 30 días después. Así
  el plazo empieza a correr cuando el permiso realmente vale, no cuando se
  cargó. Aprobar exige además que la autorización de pesca siga en fecha.
  *(25/09/2026)*
- **Los kilos proponen todo el saldo disponible** de la autorización de pesca;
  ventanilla los baja si la salida autoriza menos. *(25/09/2026)*
- **Los kilos se descuentan desde la firma.** Una faena pendiente o en revisión
  es una solicitud y no resta. Con `APROVECHAMIENTO_ESTRICTO=true` una faena que
  no entra en el saldo se rechaza al crearla y, de nuevo, al aprobarla; con
  `false` se emite igual, se sigue sumando y el exceso queda registrado.
- **No se registra la vuelta.** La faena termina en `aprobado`: los kilos
  autorizados cuentan como consumidos. *(25/09/2026)*
- **Circuito:** pendiente → se cobra el arancel (15 Bs) → en revisión → firma →
  aprobado. Se imprime recién aprobada. Se puede emitir desde la ficha de la
  autorización de pesca, que ya trae el carnet aprobado del pescador.

## Paso 5 — La operativa del comercializador (Guías Únicas de Transporte)

- Aplica **exclusivamente** al traslado de producto por parte de los
  comercializadores.
- La guía (**`guias_movimiento`**) se vincula de forma **directa al carnet** del
  comercializador (`carnet_id`) y a su respectiva asociación (`asociacion_id`).
- Controla las rutas especificando `origen`, `destino`, `peso_total_kg` y un
  indicador booleano de si proviene de **piscicultura**.
- Vigencia de transporte de **máximo 5 días**, con los estados `pendiente`,
  `en_revision`, `aprobado`, `cerrada` o `anulada`.

## Paso 6 — El ciclo financiero y contable (Recibos y Pagos)

- **Emisión de recibos.** Se emite un recibo a nombre del beneficiario con un
  número único (`numero_recibo`), **congelando de forma definitiva** el monto
  total y el concepto tarifario al momento de generarse.
- **Pagos polimórficos.** La tabla `pagos` usa una estructura polimórfica
  (`pagable_type` + `pagable_id`) que permite amortizar o cancelar los costos
  asociados indistintamente a un **Aprovechamiento**, a un **Carnet** o a una
  **Guía de Movimiento**.
- **Validación en ventanilla.** Cada transacción financiera registra el
  comprobante de depósito, la fecha, el número de transacción **único a nivel
  global**, el usuario de ventanilla que registró (`registrado_por`), el
  supervisor que validó (`validado_por`) y el estado de la verificación
  (`pendiente`, `validado` u `observado`).

## Paso 7 — La verificación pública (código de 16 caracteres)

- Cada documento que se entrega —carnet, autorización de pesca, permiso de
  faena, guía y recibo— lleva un **código de verificación de 16 caracteres** y
  su QR. Cualquier persona lo consulta en `/verificar` sin iniciar sesión.
- **Es información pública a propósito:** el código solo sirve para consultar,
  no da acceso a nada más. La página muestra el tipo de documento, el titular,
  la cédula **enmascarada**, el estado de HOY y la vigencia. Nunca dirección,
  teléfono ni la cédula completa.
- **Por qué un código al azar y no el número de registro:** con el id o el
  correlativo (`/verificar/1`, `/verificar/2`…) cualquiera recorrería la base
  entera. El código no se puede adivinar ni deducir de otro: 29 símbolos sin
  confundibles (`0 O 1 I L S 5` fuera) en 16 posiciones = 2,5 × 10²³
  combinaciones, generado con `random_int()`, único global y con 60 consultas
  por minuto por IP. No se repite nunca: se verifica al generarlo y la base lo
  impide con un índice único.
- **16 alcanza; 32 no suma seguridad práctica** y achica el QR del carnet hasta
  el límite de lo que lee una cámara. Decidido el 25/09/2026.
- **Lo que el código NO cubre es la fotocopia:** un QR válido pegado en un papel
  falso da «vigente». Quien controla compara el nombre de la pantalla con el
  C.I. de la persona.
- El **correlativo** (N° de faena, N° de recibo, N° de registro) sigue existiendo
  aparte: es el que Contabilidad audita por huecos. Conviven, no compiten.

## Nombres de los estados

Un documento **firmado** se guarda como `aprobado` en los cuatro: autorización
de pesca, carnet, permiso de faena y guía de transporte (antes `activo` /
`activa`, cambiado el 25/09/2026). En pantalla la guía dice «Aprobada».

## Reposición de un carnet perdido — IMPLEMENTADA el 25/09/2026

Propuesta aceptada el 25/09/2026:

1. En la ficha de la autorización de pesca, la fila del carnet `aprobado` tiene
   un botón **«Reponer»**.
2. Se pide el motivo por escrito —mínimo 10 caracteres, la misma ventana que
   «Revocar»— y la casilla de confirmación.
3. En una sola operación el carnet actual queda **revocado** con ese motivo, y
   se abre «Registrar carnet» con la persona, el tipo, la asociación y la
   **misma autorización de pesca** ya elegidos.
4. El nuevo sigue el circuito normal y sale con código, QR y número de registro
   nuevos. Las faenas del anterior siguen vigentes.

**Se revoca primero y no al aprobar el nuevo** porque el carnet perdido puede
estar en manos de otro: vigente hasta la firma del reemplazo, serviría en un
control.

**Dónde vive:** `EmitirCarnetService::reponer()` (revoca con el motivo
«Reposición: …»), `ReponerCarnetRequest` (las reglas de revocar), la ruta
`PATCH /panel/carnets/{carnet}/reponer` —pide `carnets.revocar` y
`carnets.crear`— y `CarnetController::create()` con `?reemplaza=`, que precarga
el formulario solo si ese carnet ya está revocado.

**Tres decisiones tomadas por defecto, a confirmar con el responsable:**

- [ ] **Precio:** hoy se cobra lo mismo que una emisión (el `precio_bs` del tipo,
      80 Bs). Si la reposición lleva tarifa propia —el sistema anterior tenía
      «reposición por pérdida, Bs 50»—, hace falta un precio de reposición en
      `tipos_carnet`.
- [ ] **Adjuntos:** hoy se vuelven a subir la cédula y el documento de la
      asociación, como en cualquier emisión.
- [ ] **Vínculo entre los dos carnets:** hoy queda solo en el motivo de la
      auditoría del revocado. Guardarlo en `carnets.reemplaza_a_id` dejaría decir
      «Reposición del 00002» / «Reemplazado por 00003» en las fichas; exige
      rearmar `carnets`, que tiene faenas y guías colgando.

---

## El flujo, en un vistazo

```
                    ┌──────────────────────────────────────────────┐
                    │              BENEFICIARIO                    │
                    └───────────────┬──────────────┬───────────────┘
                     pescador       │              │   comercializador
                                    ▼              ▼
              APROVECHAMIENTO PESQUERO        CARNET (aprovechamiento_id = NULL)
              (la bolsa madre, en kilos)               │
                          │                            ▼
                          ▼                     GUÍA DE MOVIMIENTO
                  CARNET (con cupo)             (máx. 5 días, por traslado)
                          │
                          ▼
                  PERMISO DE FAENA
              (máx. 30 días, por salida;
               descuenta kilos del cupo
               a través del carnet)

   Los tres documentos se cobran con la MISMA tabla `pagos`, y el papel que
   se entrega es el RECIBO.
```

---

## Estado de la implementación

**Los seis pasos están implementados tal como se describen arriba** (al
20/09/2026). Dónde se hace cumplir cada regla que no se ve en el esquema:

| Regla | Dónde se hace cumplir |
| --- | --- |
| El tipo de carnet y la actividad coinciden | `tipos_carnet.tipo_actor` + `EmitirCarnetService`; el formulario filtra la lista |
| Pescador SIN cupo no saca carnet | `EmitirCarnetService::emitir()` → `pescadorSinCupo()` |
| El carnet se cobra antes de valer | `EstadoCarnet` + `RevisarCarnetService`; imprimir exige `yaFueAprobado()` |
| Los dos adjuntos del carnet, 3 MB | `EmitirCarnetRequest` + `StorageController` |
| Comercializador NUNCA lleva cupo | `EmitirCarnetService` + `EmitirCarnetRequest` (`prohibitedIf`) |
| La faena solo cuelga del carnet | `permisos_faena.carnet_id` es la única FK; el cupo llega por `hasManyThrough` |
| Los kilos descuentan del cupo raíz | `AprovechamientoPesq::kilosConsumidos()` / `saldoKg()` |
| Faena: máximo 30 días | `PermisoFaena::DIAS_VIGENCIA` + `EmitirFaenaRequest` |
| La guía solo cuelga del carnet | `guias_movimiento.carnet_id` es la única FK hacia la persona |
| Guía: máximo 5 días | `GuiaMovimiento::DIAS_VIGENCIA` + `EmitirGuiaRequest` |
| Cada actor emite solo lo suyo | `TipoActor::emiteFaenas()` / `emiteGuias()`, en los dos servicios |
| Fechas de la faena al aprobar | `RevisarFaenaService::aprobar()` + `PermisoFaena::desembarqueDesde()` |
| Solo se revoca un carnet aprobado | `EstadoCarnet::permiteRevocacion()` + `EmitirCarnetService::revocar()` |
| Una autorización vigente por persona | `OtorgarCupoService` con el scope `enCurso()` (pendiente, en revisión o aprobada, en fecha) |
| Solo se revoca una autorización aprobada o agotada | `EstadoAprovechamiento::permiteRevocacion()` + `RevisarCupoService::revocar()` |
| Autorización revocada: ni carnets ni faenas | `enCurso()` / `habilita()`, `EmitirFaenaService`, `RevisarFaenaService::aprobar()`, `RevisarCarnetService::aprobar()`, `puedeEmitirFaena()` |
| Revocar la autorización revoca sus carnets y faenas aprobados y vigentes | `RevisarCupoService::revocarLoQueCuelga()` |
| Revocar no toca las faenas | `PermisoFaena::estaVigente()` mira su estado y su fecha, no el carnet |
| Código de verificación | `CodigoService` (generación) + `VerificacionController` (lo que se muestra) |
| Qué carnet emite la próxima faena desde el cupo | `AprovechamientoController::show()` → `carnetParaFaena` |

> **Una lectura que conviene dejar por escrito.** «A partir de una misma bolsa
> madre se emiten los carnets necesarios» se implementa así: el mismo cupo
> respalda todos los carnets que hagan falta a lo largo de la gestión
> —renovación, reposición por pérdida— pero **no dos VIGENTES a la vez de la
> misma actividad**. Para reponer un plástico hay que revocar el actual. Si la
> intención era otra, es una línea en `EmitirCarnetService`.
