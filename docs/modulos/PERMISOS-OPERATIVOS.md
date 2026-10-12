# Faenas y guías — los permisos operativos

> **Al día con el núcleo del 18/09/2026** (reescrito el 27/09/2026). El QUÉ lo
> manda [REGLAS-NEGOCIO.md](../REGLAS-NEGOCIO.md), pasos 4 y 5; las columnas y el
> porqué de cada una están en [MER.md](../MER.md). Acá va el CÓMO: dónde vive
> cada regla y qué hay que saber antes de tocar el módulo.

> **El carnet es la llave anual; con él solo no se sale a trabajar.**

De cada carnet cuelgan los papeles con los que la persona trabaja de verdad, y
son muchos por gestión:

```
beneficiario ──< aprovechamiento_pesq (la bolsa madre, en kg)
             ──< carnet (pescador)        ──< permiso_faena    (una por salida)
             ──< carnet (comercializador) ──< guia_movimiento  (una por traslado)
                                                  └──< guia_detalle (una por especie)
```

| | PERMISO DE FAENA | GUÍA ÚNICA DE TRANSPORTE |
| --- | --- | --- |
| Autoriza | UNA salida de pesca | UN traslado de carga |
| Dice | Embarcación, propietario, comandante, matrícula, kardex, región y kilos | Origen y destino, medio y vehículo, y la carga especie por especie |
| Sale del carnet de | **Pescador** (`TipoActor::emiteFaenas()`) | **Comercializador** (`TipoActor::emiteGuias()`) |
| Además exige | Autorización de pesca **aprobada** y en fecha | Nada más que el carnet vigente |
| Arancel | La tarifa que elige ventanilla del servicio de la fila `faena` de Catálogos › Aranceles, copiada en `permisos_faena.monto` | **El total del cuadro D** (kilos × precio por kilo de SIREB de cada producto), **la mitad si es piscicultura**, guardado en `guias_movimiento.monto` |
| Número | `permisos_faena.nro`, correlativo continuo del sistema | `guias_movimiento.nro`, correlativo continuo del sistema |
| Tiene detalle | No | Sí, `guia_detalles`: una fila por producto del catálogo |
| Kilos contra la autorización | Sale con `kilos_disponibles`, fijo; **no descuenta** | No toca ningún cupo |
| Vale | Configurable: N días o hasta el 31/12 (de inicio 30 días) | Configurable: N días con hora o hasta el 31/12 (de inicio 5 días) |
| Termina en | `aprobado` (no se registra la vuelta), o `revocado` | `aprobado` (no se registra la llegada), o `revocado` |
| Se imprime | `PermisoFaenaImpresionController` | `GuiaImpresionController` |

---

## 1. El circuito

**Los dos papeles siguen el mismo circuito que el carnet y la autorización**, y
es a propósito: el operador aprende uno solo.

```
            ┌─(pagada en SIREB)──▶ APROBADO ──▶ (guía: anulada)
PENDIENTE ──┤                         └── al aprobar sale el RECIBO
(borrador)  └─(vencida en SIREB)──▶ sigue PENDIENTE hasta eliminarla (08/10/2026)
   └──[eliminar, con motivo]──▶ baja lógica (consulta SIREB y anula la liquidación)
```

*(02/10/2026: el pago se hace en SIREB; no hay `en_revision`, ni firma, ni
depósitos en Jichi. 03/10/2026: «Cargar pago» desde la ficha. 08/10/2026: sin
`no_pagado`: la liquidación vencida no cambia el estado. Ver [SIREB.md](SIREB.md).)*

| | Editar | Eliminar | Cargar pago | Verificar pago | Imprimir |
| --- | :-: | :-: | :-: | :-: | :-: |
| **Pendiente**, sin pago en SIREB | ✔ | ✔ | ✔ | ✔ | ✘ |
| **Pendiente**, pago en revisión | ✘ (*) | ✘ | ✘ | ✔ | ✘ |
| **Aprobado** | ✘ | ✘ | ✘ | ✘ | ✔ |

(*) Si la corrección cambia lo que se cobra: hay que anular la liquidación, y con
un pago cargado SIREB no lo permite.

Lo dictan los enums —`EstadoFaena` y `EstadoGuia`— y **nada más**: el servicio
pregunta, el controlador no decide y React recibe la respuesta ya resuelta en
los campos `puede_*` de la ficha.

- **EDITAR Y ELIMINAR, solo en PENDIENTE**, y anulan antes la liquidación en
  SIREB (corregir registra otra si cambia lo que se cobra). **Antes de anular,
  Jichi consulta la liquidación**: con un pago en revisión no se toca nada; con el
  pago validado, al eliminar se aprueba en su lugar. Ver [PAGOS.md](PAGOS.md).
- **El CARNET no se edita.** Cambiar de titular no es corregir una salida, es
  emitir otra: el formulario muestra a la persona fija.
- **SE APRUEBA SOLA CUANDO SIREB LA DA POR PAGADA** (`ConfirmarPagoService`), y
  ahí sale el recibo, uno por documento. La faena se vuelve a medir contra los
  kilos libres y contra su autorización: si una regla la frena, queda pendiente
  con el motivo. Ver [PAGOS.md](PAGOS.md) y [RECIBOS.md](RECIBOS.md).
- **LAS FECHAS DE VIGENCIA LAS ESCRIBE LA APROBACIÓN.** Mientras es borrador
  están en NULL: el plazo corre desde la aprobación, no desde que se cargó. La faena
  sale ese día y desembarca al cumplirse su vigencia (`PermisoFaena::desembarqueDesde()`);
  la guía vence contada con hora (`dateTime`). Las dos las da `App\Support\Vigencia`.
- **Eliminar no devuelve el número.** La baja es lógica y el correlativo sigue
  donde estaba: la serie queda con un hueco, y eso es lo que el motivo
  obligatorio explica.

### Revocar: la baja de lo ya aprobado *(05/10/2026)*

- **No hay cierre** *(28/09/2026)*: se quitó «Registrar llegada» con su ruta,
  su permiso `guias.cerrar` y el estado `cerrada`. La guía aprobada vale sus 5
  días y vence; vencer es su final normal, no un trabajo pendiente.
- **Revocar** (`EmitirFaenaService::revocar()`, `EmitirGuiaService::revocar()`) da
  de baja una faena o una guía **ya aprobada**, cuyo papel está en la calle, con
  motivo obligatorio. Es de supervisión (`faenas.revocar`, `guias.revocar`) y no
  se revierte. **Revocar no es eliminar**: eliminar es sobre el borrador, donde
  nunca hubo papel. Las dos queman el número.
- **La guía se «anulaba»** hasta el 05/10/2026 (`guias.anular`, estado `anulada`).
  Hacía lo mismo que revocar en el carnet y la autorización, así que se unificó:
  los cuatro trámites dicen **Eliminar** (borrador) y **Revocar** (aprobado).
- **La faena revocada NO devuelve sus kilos al cupo**: puede ser una sanción, y
  devolverlos le regalaría cupo al infractor. `consumeCupo()` cuenta aprobadas,
  completadas y revocadas, y `faenasQueConsumen()` dice lo mismo en SQL. La
  completada no se revoca: ya cerró su salida.

Lo dice `permiteRevocacion()` de cada enum —solo `aprobado`—, y lo usan el
servicio (también sobre la fila bloqueada) y la ficha. Una faena aprobada además
deja de valer por fecha —sigue `aprobado`; la vigencia la dice
`fecha_desembarque`— o porque quedó sin efecto (revocaron su autorización, o su
carnet sin reemplazo).

---

## 2. Los kilos de la faena contra la autorización

**Fijos, y no descuentan** *(12/10/2026)*. Al emitirse, la faena copia los
`kilos_disponibles` de la autorización en `kilos_extraidos`
(`EmitirFaenaService::emitir()`). Ventanilla no los tipea ni los corrige, y
ninguna faena baja nada de la autorización: no hay saldo, reserva, estado
`agotado` ni modo estricto (`APROVECHAMIENTO_ESTRICTO` se quitó).

**Historia:** hasta el 12/10/2026 la faena reservaba sus kilos al registrarse y
los descontaba al aprobarse, con un modo estricto que frenaba lo que no
entraba. Está en el historial de git.

---

## 3. Dónde vive cada regla

| Regla | Quién la hace cumplir |
| --- | --- |
| Cada actor emite solo lo suyo | `TipoActor::emiteFaenas()` / `emiteGuias()`, en `EmitirFaenaService` / `EmitirGuiaService` |
| El carnet está VIGENTE | Los mismos servicios, con `Carnet::estaVigente()` (estado **y** fecha) |
| La faena exige autorización aprobada, en fecha y no revocada | `EmitirFaenaService::emitir()` y otra vez en `RevisarFaenaService::aprobar()` |
| Los kilos entran en lo libre | `EmitirFaenaService::exigirKilosLibres()` |
| El número no se repite | `CorrelativoService::siguienteContinuo()` dentro de la transacción, y el índice único |
| El arancel no cambia después de emitido | `monto` copiado al crear; `montoACobrar()` lee la columna, no `config()` |
| El descuento de piscicultura | `GuiaMovimiento::factorArancel()`, en un solo lugar |

**Ninguna está en el controlador ni en React.** Los modelos contestan
`Carnet::puedeEmitirFaenas()` y `motivoSinPermisos()` —sí o no, y por qué, para
mostrar el botón o el aviso—; los servicios IMPIDEN y explican cuál de las
condiciones falló.

> **El error más probable del módulo es elegir el carnet equivocado.** La misma
> persona tiene normalmente los DOS carnets. El formulario lista los carnets
> vigentes de la persona (`BeneficiarioController::buscar()`) y deshabilita los
> que no pueden emitir ese papel, con el motivo. El servicio igual lo vuelve a
> comprobar: el filtro es comodidad, no seguridad.

---

## 4. El número

**Lo genera el sistema en los dos papeles**: un correlativo **global y
continuo** de seis dígitos que no reinicia por gestión, reservado con
`CorrelativoService::siguienteContinuo()` dentro de la transacción del servicio.
El operador no lo ve al cargar; lo lee del PDF.

Antes lo tipeaba el operador y era correlativo DENTRO DEL CARNET. Las dos cosas
estaban mal: el talonario de papel es **uno solo para toda la unidad** —la hoja
real dice `N° 002190`—, y tipearlo abría la puerta a dos ventanillas cargando el
mismo.

El número y el **código de verificación** conviven: el número es consecutivo
porque Contabilidad audita sus huecos; el código de 16 caracteres es al azar
porque es la llave de `/verificar`. Ver [MER.md](../MER.md), tabla `codigos`.

---

## 5. La carga de la guía

Vive en `guia_detalles`, una fila por especie, y calca el **cuadro D** del
talonario: producto, condición (`CondicionProducto`, las diez columnas de tilde
del papel), kilos, precio e importe.

**El producto es una tarifa de SIREB** *(12/10/2026; antes el catálogo
`productos_hidrobiologicos`)*: las del servicio que guarda la fila `guia` de
Catálogos › Aranceles. El formulario manda solo `tarifa_sireb`, condición y
kilos; `EmitirGuiaService::normalizarDetalle()` pide cada tarifa a SIREB, copia
su etiqueta (`especie`) y su precio y calcula el importe. Lo que llegue del
formulario como precio se ignora. Una tarifa que SIREB no deja cobrar se ve
deshabilitada; al corregir, la que la guía ya tenía aparece aunque SIREB ya no
la mande. Ver [SIREB.md](SIREB.md).

**El precio ES lo que se cobra** (27/09/2026): la guía cobra la suma de los
importes de su cuadro D, con el descuento de piscicultura, y la guarda en
`guias_movimiento.monto` al emitir o corregir (`EmitirGuiaService::importeDe()`).
El catálogo exige 0,20 Bs/kg como mínimo: en cero, la guía saldría gratis.

`peso_total_kg` de la guía es la suma del cuadro D, guardada. Al corregir el
borrador el detalle se **reemplaza entero**. La FK es CASCADE, pero la baja
lógica no la dispara: `EmitirGuiaService::eliminar()` baja el detalle a mano.

---

## 6. Pantallas y rutas

| Ruta | Permiso | Qué hace |
| --- | --- | --- |
| `GET /panel/faenas` | `faenas.ver` | Listado, con filtro por estado |
| `GET /panel/faenas/crear` | `faenas.crear` | Formulario. Acepta `?beneficiario=` y `?carnet=` para llegar con todo elegido |
| `POST /panel/faenas` | `faenas.crear` | Alta, nace pendiente |
| `POST /panel/faenas/{faena}/verificar-pago` | `faenas.verificar-pago` | Pregunta a SIREB; si está pagada, la aprueba y emite el recibo |
| `POST /panel/faenas/{faena}/cargar-pago` | `faenas.cargar-pago` | Carga el pago en SIREB; validarlo es de Recaudaciones |
| `GET /panel/faenas/{faena}/editar` · `PATCH /panel/faenas/{faena}` | `faenas.editar` | Corregir el borrador. `editar` va ANTES de `/{faena}` o se toma como id |
| `DELETE /panel/faenas/{faena}` | `faenas.eliminar` | Baja con motivo |
| `GET /panel/faenas/{faena}/imprimir` | `faenas.imprimir` | El «Permiso por Faena» en PDF. Solo aprobada |
| `GET /panel/faenas/{faena}` | `faenas.ver` | Ficha |
| `PATCH /panel/faenas/{faena}/revocar` | `faenas.revocar` | Baja de la aprobada, con motivo; sus kilos siguen descontados |
| `/panel/guias/…` | `guias.*` | Lo mismo para guías, con `PATCH …/revocar` (`guias.revocar`) |

Los pasos del circuito van por **PATCH o POST, nunca GET**: un verbo de lectura
que escribe se dispara solo con que el navegador precargue el enlace.

En el menú lateral los dos van en el grupo **Ventanilla**, como «Faenas» y
«Guías», junto a Beneficiarios, Aprov. Pesquero y Carnets. La faena también se
emite desde la ficha de la autorización, que ya trae el carnet aprobado.

**La ficha del carnet lista sus papeles, y desde ahí se emite el siguiente**: el
de pescador muestra «Faenas emitidas» y el de comercializador «Guías emitidas»
(27/09/2026), cada una con su botón. El de la guía abre el formulario con
`?carnet=`, que preelige a la persona y el carnet si está vigente
(`GuiaController::create()`).

---

## 7. Lo que hay que saber antes de tocar el módulo

- **Los modelos son `PermisoFaena` y `GuiaMovimiento`**, sobre
  `permisos_faena` y `guias_movimiento`. Los viejos `Faena`, `Guia`,
  `FaenaService` y `GuiaService` ya no existen.
- **La faena guarda los renglones del papel**: embarcación, propietario,
  comandante, matrícula naval, kardex y la región desde/hasta. Todos nullable y
  texto libre —el formulario se llena a mano y llega incompleto—; los kilos son
  lo único obligatorio.
- **No se registra la vuelta de la faena** (retirado el 25/09/2026): termina en
  `aprobado` y sus kilos cuentan como consumidos. `Completado` se quitó del enum
  el 08/10/2026.
- **Revocar el carnet NO reescribe sus faenas ni sus guías, pero dejan de valer
  mientras el titular no tenga OTRO carnet vigente de la actividad** (27/09/2026):
  figuran «Sin efecto» y vuelven a valer solas cuando se aprueba el nuevo. Lo
  decide `Carnet::amparaSusPapeles()` —el carnet propio vigente, o cualquier otro
  del titular— y lo usan `estaVigente()`, `sinEfecto()` y `scopeVigentes()` de
  `PermisoFaena` y `GuiaMovimiento` (en SQL, `Carnet::conCarnetVigenteDelTitular()`).
  **Toda consulta que muestre el estado de una faena o una guía trae el carnet
  con `estado` y `fecha_vencimiento` en el select**: sin ellos, `estaVigente()` del
  carnet lee null y la pantalla se cae. **Revocar la AUTORIZACIÓN** no
  reescribe las faenas: quedan `aprobado` pero **sin efecto** —no vigentes, no se
  imprimen— porque su vigencia mira al padre. Ver
  [REGLAS-NEGOCIO.md](../REGLAS-NEGOCIO.md), Regla 5. Las consultas de faenas que
  muestren el estado precargan `carnet.aprovechamiento` —con `aprovechamiento_id`
  en el select del carnet— o «sin efecto» da falso en silencio.
- **Los valores por defecto van también en `protected $attributes`** del modelo:
  un `default` de la base no llega al objeto que devuelve `create()`.
- **Pedir columnas sueltas en un `with()` rompe los métodos del modelo en
  silencio**: si `puedeEmitirFaenas()` o `documento_identidad` leen una columna,
  esa columna va en el select.
- **El PDF de la faena** lo dibuja `PermisoFaenaImpresionController` con
  `documentos/permiso-faena.blade.php`. Calca el talonario y sale recién
  aprobada. Su sello de agua es `public/image/faena-sello.png`, horneado en el
  PNG —para aclararlo se regenera, nunca con `opacity`—; arriba a la derecha van
  los peces (`faena-peces.png`), el mismo PNG que usa la autorización.
- **El PDF de la guía** lo dibuja `GuiaImpresionController` con
  `documentos/guia-transporte.blade.php`. DomPDF no rota texto: los rótulos
  verticales del cuadro D se apilan letra por letra.

**Los archivos del módulo:**

| Archivo | Qué hace |
| --- | --- |
| `app/Enums/EstadoFaena.php` / `EstadoGuia.php` | El circuito |
| `app/Enums/CondicionProducto.php`, `MedioTransporte.php`, `TipoTransporte.php` | Las casillas del talonario de la guía |
| `app/Services/EmitirFaenaService.php` | Emitir, corregir y eliminar la faena; copia los kilos de la autorización |
| `app/Services/RevisarFaenaService.php` | Aprobar la faena cuando SIREB la da por pagada (fechas, kilos libres) |
| `app/Services/EmitirGuiaService.php` | Emitir, corregir, eliminar y revocar la guía |
| `app/Services/RevisarGuiaService.php` | Aprobar la guía cuando SIREB la da por pagada (vigencia de 5 días) |
| `app/Http/Controllers/Panel/FaenaController.php` / `GuiaController.php` | Listado, formulario, ficha y circuito |
| `resources/js/pages/panel/faenas/`, `resources/js/pages/panel/guias/` | Las pantallas |
| `app/Sireb/TarifarioSireb.php` | Las tarifas del servicio de la guía: los productos del cuadro D |

---

Ver también: [REGLAS-NEGOCIO.md](../REGLAS-NEGOCIO.md) · [MER.md](../MER.md) ·
[ARQUITECTURA.md](../ARQUITECTURA.md) · [CARNETS.md](CARNETS.md) ·
[PAGOS.md](PAGOS.md)
