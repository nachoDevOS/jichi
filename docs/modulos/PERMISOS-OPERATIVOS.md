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
| Además exige | Autorización de pesca **aprobada**, en fecha y con kilos libres | Nada más que el carnet vigente |
| Arancel | Precio de SIREB (fila `faena` de Catálogos › Aranceles), copiado en `permisos_faena.monto` | **El total del cuadro D** (kilos × precio por kilo de SIREB de cada producto), **la mitad si es piscicultura**, guardado en `guias_movimiento.monto` |
| Número | `numero_faena`, correlativo continuo del sistema | `numero_guia`, correlativo continuo del sistema |
| Tiene detalle | No | Sí, `guia_detalles`: una fila por producto del catálogo |
| Kilos contra la autorización | **Reserva** al registrarse, **descuenta** al aprobarse | No toca ningún cupo |
| Vale | 30 días desde la firma | 5 días desde la firma (se cuentan con hora) |
| Termina en | `aprobado` (no se registra la vuelta) | `aprobado` (no se registra la llegada), o `anulada` |
| Se imprime | `PermisoFaenaImpresionController` | `GuiaImpresionController` |

---

## 1. El circuito

**Los dos papeles siguen el mismo circuito que el carnet y la autorización**, y
es a propósito: el operador aprende uno solo.

```
PENDIENTE ──[enviar]──▶ EN REVISIÓN ──[aprobar]──▶ APROBADO ──▶ (faena: vencido / revocado)
(borrador)  ▲                │                        │
   │        └──[rechazar]────┘                        └──▶ (guía: anulada)
   │                 └── al enviar sale el RECIBO, uno por trámite
   └──[eliminar, con motivo]──▶ baja lógica
```

| | Editar | Eliminar | Pagar | Enviar | Aprobar / Rechazar | Imprimir |
| --- | :-: | :-: | :-: | :-: | :-: | :-: |
| **Pendiente** | ✔ | ✔ | ✔ | ✔ | ✘ | ✘ |
| **En revisión** | ✘ | ✘ | ✘ | ✘ | ✔ | ✘ |
| **Aprobado** | ✘ | ✘ | ✘ | ✘ | ✘ | ✔ |

Lo dictan los enums —`EstadoFaena` y `EstadoGuia`— y **nada más**: el servicio
pregunta, el controlador no decide y React recibe la respuesta ya resuelta en
los campos `puede_*` de la ficha.

- **Rechazar devuelve a PENDIENTE**, con el motivo en `auditorias`. Los pagos y
  el recibo quedan como estaban: el papel ya está en manos de la persona, y un
  reenvío no emite un segundo recibo.
- **EDITAR Y ELIMINAR PIDEN DOS COSAS:** estado PENDIENTE y **ningún depósito
  cargado** —lo suman `puedeEditarse()` y `puedeEliminarse()` en los dos
  modelos—. Un depósito significa que la persona pagó por ESTE papel, y mover
  los kilos o la piscicultura después cambiaría lo que se cobró. Primero se da
  de baja el depósito.
- **El CARNET no se edita.** Cambiar de titular no es corregir una salida, es
  emitir otra: el formulario muestra a la persona fija.
- **EL RECIBO SALE AL ENVIAR, y es UNO por trámite**, no uno por boleta.
  `CobrarService::emitirRecibo()` solo toma los pagos que quedaron sueltos, así
  que un reenvío no emite otro. Ver [PAGOS.md](PAGOS.md) y [RECIBOS.md](RECIBOS.md).
- **Aprobar exige** el arancel cubierto y **todas las boletas validadas**
  (`sinValidar()` cuenta también las observadas).
- **LAS FECHAS DE VIGENCIA LAS ESCRIBE LA APROBACIÓN.** Mientras es borrador
  están en NULL: el plazo corre desde la firma, no desde que se cargó. La faena
  sale ese día y desembarca 30 días después (`PermisoFaena::desembarqueDesde()`);
  la guía vale 5 días contados con hora (`dateTime`).
- **Eliminar no devuelve el número.** La baja es lógica y el correlativo sigue
  donde estaba: la serie queda con un hueco, y eso es lo que el motivo
  obligatorio explica.

### Lo que solo tiene la guía: anular

- **No hay cierre** *(28/09/2026)*: se quitó «Registrar llegada» con su ruta,
  su permiso `guias.cerrar` y el estado `cerrada`. La guía aprobada vale sus 5
  días y vence; vencer es su final normal, no un trabajo pendiente.
- **Anular** (`EmitirGuiaService::anular()`) da de baja una guía **ya firmada**,
  cuyo papel está en la calle, con motivo obligatorio. Es de supervisión
  (`guias.anular`) y no se revierte. **Anular no es eliminar**: eliminar es
  sobre el borrador, donde nunca hubo papel. Las dos queman el número.

Anular lo dice `EstadoGuia::permiteAnulacion()`
—solo `aprobado`—, y los usan el servicio (también sobre la fila bloqueada) y la
ficha. Hasta el 27/09/2026 el servicio aceptaba anular un borrador o una guía en
revisión aunque la pantalla no lo ofreciera.

**La faena no se anula**: no tiene ese estado. Una faena aprobada solo deja de
valer por fecha (`vencido`) o porque revocaron su autorización (`revocado`).

---

## 2. Los kilos de la faena contra la autorización

**Descuenta** solo la faena APROBADA (y la completada): recién firmada autoriza
a pescar, y recién ahí sus kilos pasan a «consumido». Lo dice
`EstadoFaena::consumeCupo()`.

**Reserva** la faena PENDIENTE y la EN REVISIÓN: no resta del saldo ni agota la
autorización, pero aparta sus kilos. Lo dice `EstadoFaena::reservaCupo()`.

```
libre para una faena nueva = otorgado − consumido − reservado
                             └── saldoKg() ──┘
                             └────────── libreKg() ─────────┘
```

**En modo estricto una faena nueva solo puede pedir lo libre.** Con 150 kg de
saldo y una pendiente de 150, lo libre es 0 y la siguiente no se registra hasta
que se elimine la pendiente (una en revisión se rechaza primero, y vuelve a
pendiente). El mensaje dice cuántos kilos están reservados:
`PermisoOperativoException::excedeLibre()`. En modo flexible la reserva se
muestra y no frena. El ejemplo completo está en
[REGLAS-NEGOCIO.md](../REGLAS-NEGOCIO.md), paso 4.

Una reserva **no agota** la autorización —todavía se puede eliminar—: `agotado`
lo decide solo lo consumido, en `sincronizarEstadoPorSaldo()`.

**Dónde se aplica** —los tres con la fila de la autorización bloqueada—:

| Momento | Qué se mide |
| --- | --- |
| `EmitirFaenaService::emitir()` | kilos ≤ libre |
| `EmitirFaenaService::editar()` | kilos ≤ libre + lo que esta misma faena reservaba |
| `RevisarFaenaService::aprobar()` | kilos ≤ saldo. Con la reserva no puede fallar en estricto; queda por las faenas nacidas en flexible |

**La pantalla recibe los números resueltos**: `saldo_kg`, `reservado_kg` y
`libre_kg` en `Carnet::resumenParaEmitir()` (formulario de faena), `libre_kg` en
`FaenaController::edit()`, y `kilos_reservados` / `libre_kg` en la ficha de la
autorización, que muestra «Reservado» y «Libre para faena» cuando hay reservas.
El formulario usa `libre_kg` en estricto y `saldo_kg` en flexible, y propone
como kilos todo lo disponible.

Los lugares que enumeran los estados, y que tienen que decir lo mismo:

| Dónde | Para qué |
| --- | --- |
| `EstadoFaena::consumeCupo()` / `reservaCupo()` | El filtro en memoria, cuando las faenas ya están cargadas |
| `AprovechamientoPesq::faenasQueConsumen()` / `faenasQueReservan()` | El mismo filtro en SQL, para el `withSum` de los listados |

⚠️ **Toda consulta que precarga `withSum('faenasQueConsumen', …)` lleva también
`withSum('faenasQueReservan', …)`**, o `kilosReservados()` hace una consulta por
fila en silencio.

**Historia:** hasta el 21/09/2026 la pendiente descontaba; entre el 21 y el 27
no reservaba nada, y tres solicitudes por el volumen entero pasaban las tres y
chocaban al firmar la segunda, con el arancel ya cobrado. Desde el 27/09/2026
reserva sin descontar.

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

**El producto sale del catálogo** `productos_hidrobiologicos` —nombre, precio
por kilo y estado, en Catálogos → Productos— desde el 27/09/2026. El formulario
manda solo `producto_id`, condición y kilos; `EmitirGuiaService::normalizarDetalle()`
copia el nombre y el precio del producto y calcula el importe. Lo que llegue del
formulario como precio se ignora. Un producto inactivo no se elige en una guía
nueva, pero al corregir una guía que ya lo tenía se conserva.

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
| `POST /panel/faenas/{faena}/pagos` | `caja.cobrar` | Cargar depósitos desde la ficha |
| `POST /panel/faenas/{faena}/enviar` | `faenas.enviar` | A revisión; sale el recibo |
| `PATCH /panel/faenas/{faena}/aprobar` · `/rechazar` | `faenas.aprobar` | La firma, o la devolución con motivo |
| `GET /panel/faenas/{faena}/editar` · `PATCH /panel/faenas/{faena}` | `faenas.editar` | Corregir el borrador. `editar` va ANTES de `/{faena}` o se toma como id |
| `DELETE /panel/faenas/{faena}` | `faenas.eliminar` | Baja con motivo |
| `GET /panel/faenas/{faena}/imprimir` | `faenas.imprimir` | El «Permiso por Faena» en PDF. Solo aprobada |
| `GET /panel/faenas/{faena}` | `faenas.ver` | Ficha |
| `/panel/guias/…` | `guias.*` | Lo mismo para guías, más `PATCH …/anular` (`guias.anular`) |

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
  `aprobado` y sus kilos cuentan como consumidos. `Completado` sigue en el enum,
  pero hoy nada lleva a ese estado.
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
| `app/Enums/EstadoFaena.php` / `EstadoGuia.php` | El circuito, y qué descuenta o reserva |
| `app/Enums/CondicionProducto.php`, `MedioTransporte.php`, `TipoTransporte.php` | Las casillas del talonario de la guía |
| `app/Services/EmitirFaenaService.php` | Emitir, corregir y eliminar la faena; controla los kilos libres |
| `app/Services/RevisarFaenaService.php` | Enviar, aprobar y rechazar la faena |
| `app/Services/EmitirGuiaService.php` | Emitir, corregir, eliminar y anular la guía |
| `app/Services/RevisarGuiaService.php` | Enviar, aprobar y rechazar la guía |
| `app/Http/Controllers/Panel/FaenaController.php` / `GuiaController.php` | Listado, formulario, ficha y circuito |
| `resources/js/pages/panel/faenas/`, `resources/js/pages/panel/guias/` | Las pantallas |
| `app/Models/ProductoHidrobiologico.php`, `ProductoHidrobiologicoController.php` | El catálogo del cuadro D |

---

Ver también: [REGLAS-NEGOCIO.md](../REGLAS-NEGOCIO.md) · [MER.md](../MER.md) ·
[ARQUITECTURA.md](../ARQUITECTURA.md) · [CARNETS.md](CARNETS.md) ·
[PAGOS.md](PAGOS.md)
