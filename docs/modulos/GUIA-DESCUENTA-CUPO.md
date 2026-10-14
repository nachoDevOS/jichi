# La guía descuenta el cupo del pescador — plan de implementación

> **Estado: IMPLEMENTADO el 13/10/2026.** Decidido en reunión con la unidad. Las
> reglas están también en [REGLAS-NEGOCIO.md](../REGLAS-NEGOCIO.md), paso 5; este
> archivo es el detalle del módulo. Lo que quedó distinto del plan está en §10.

---

## 1. La decisión

**El permiso de faena NO descuenta del cupo. Lo que descuenta es la GUÍA ÚNICA
DE TRANSPORTE**, porque es el papel que dice de qué pescador sale el pescado que
se vende.

El comercializador que saca la guía declara **a quién le compró**: un pescador y
su Autorización de Pesca para Aprovechamiento Pesquero. Los kilos de la guía se
descuentan del cupo de esa autorización.

### El ejemplo

| Día | Qué pasa | `kilos_disponibles` de Juan |
| --- | --- | --- |
| Lunes | Juan (pescador) saca su autorización de 801 a 1000 kg y la paga | **1000** |
| Martes | Juan saca un permiso de faena: **no descuenta**. La faena sale con los kilos disponibles de ese momento | 1000 (la faena dice 1000) |
| Jueves | Nacho (comercializador) le compra **300 kg** y registra su guía (pendiente) | **700** |
| Jueves | Juan saca otra faena | 700 (la faena dice 700) |
| Sábado | Pedro (comercializador) registra una guía por **500 kg** de Juan | **200** |
| Domingo | Pedro no pagó: su guía pendiente **se elimina** | **700** (vuelven los 500) |
| Lunes | Nacho **corrige** su guía pendiente: 250 kg en vez de 300 | **750** (vuelve la diferencia) |
| Martes | La guía de Nacho se aprueba y después **se revoca** | **750** (no vuelve nada) |
| Miércoles | Alguien quiere una guía de 800 kg de Juan | **Se frena**: «Juan solo tiene 750 kg disponibles» |

---

## 2. Las reglas

| # | Regla |
| --- | --- |
| 1 | **Se descuenta al REGISTRAR la guía** (queda reservado aunque no esté pagada). Así dos comercializadores no pueden anotar el mismo pescado |
| 2 | **Una guía = un solo pescador.** Todo el cuadro D sale de la misma autorización |
| 3 | **Si los kilos de la guía superan `kilos_disponibles`, se frena.** No hay modo «dejar pasar con aviso» |
| 4 | **Revocar una guía NO devuelve los kilos**: el pescado ya viajó |
| 5 | **Eliminar una guía PENDIENTE sí los devuelve**: nunca se pagó, nunca salió |
| 6 | **Corregir una guía pendiente** devuelve los kilos viejos y descuenta los nuevos (o pasa los kilos a otra autorización si cambió el pescador) |
| 7 | **Solo se descuenta de una autorización APROBADA y EN FECHA.** Pendiente, vencida o revocada: no se puede elegir |
| 8 | **Si revocan la autorización mientras la guía espera el pago, la guía NO se aprueba** (queda frenada, como la faena) |
| 9 | **La faena no descuenta**, y sale con los `kilos_disponibles` que tenga la autorización ese día |
| 10 | **La guía impresa muestra el pescador y el N° de autorización** |
| 11 | **Piscicultura no lleva pescador** ni descuenta. Hoy la casilla está oculta, así que **toda guía pide pescador** *(a confirmar)* |

Los kilos que se descuentan son el **total del cuadro D** (`peso_total_kg`).

---

## 3. La base de datos

### `aprovechamientos_pesq.kilos_disponibles` — vuelve a ser un SALDO

| Columna | Qué guarda |
| --- | --- |
| `volumen_total_kg` | Lo autorizado (`rango_hasta` de la escala). **No cambia nunca** |
| `kilos_disponibles` | **Lo que le queda.** Arranca igual a `volumen_total_kg` y lo mueven las guías |

Es un número **guardado**, no calculado (decisión de la unidad: así se ve de un
vistazo cuánto cupo tiene). Por eso **todo** movimiento pasa por un solo lugar y
con la fila bloqueada (ver §4).

### `guias_movimiento.aprovechamiento_id` — columna nueva

```php
// En la migración original de guias_movimiento (regla 12: sin parches).
// Null = piscicultura. Índice ANTES de constrained(): ver CLAUDE.md.
$table->foreignId('aprovechamiento_id')->nullable()->index()
    ->constrained('aprovechamientos_pesq')->restrictOnDelete()
    ->comment('De qué autorización sale el pescado; null si es de criadero');
```

- **No reemplaza a `carnet_id`**: el carnet es del comercializador (quien lleva
  la guía); la autorización es del pescador (de quién es el pescado). Son dos
  personas distintas.
- **RESTRICT**: una autorización con guías no se borra.

**Hay que volver a migrar** (`migrate:fresh --seed`).

---

## 4. El backend

### Un solo lugar mueve el saldo

Métodos nuevos en `AprovechamientoPesq`, que solo se llaman **dentro de una
transacción con la fila bloqueada** (`lockForUpdate()`):

| Método | Hace |
| --- | --- |
| `descontar(float $kilos)` | Resta. Si no alcanza, lanza `PermisoOperativoException::sinKilosDisponibles()` |
| `devolver(float $kilos)` | Suma, sin pasarse de `volumen_total_kg` |

### `EmitirGuiaService`

| Operación | Qué hace con el cupo |
| --- | --- |
| `emitir()` | Valida la autorización (aprobada y en fecha), la **bloquea**, recalcula los kilos del cuadro D y `descontar()`. Todo en la misma transacción que crea la guía |
| `editar()` (pendiente) | Bloquea la autorización vieja y la nueva (en orden de id, para no trabarse entre dos ventanillas); `devolver()` los kilos viejos y `descontar()` los nuevos |
| `eliminar()` (pendiente) | `devolver()` los kilos, en la misma transacción que la baja |
| `revocar()` | **No toca** el cupo |

### `RevisarGuiaService::aprobar()`

Nuevo control: si la autorización está **revocada**, no se aprueba
(`PermisoOperativoException::cupoRevocado()`). El saldo no se toca: ya se
descontó al registrar.

### `EmitirFaenaService`

Vuelve a leer `kilos_disponibles` (hoy copia `volumen_total_kg`). No descuenta.

### `OtorgarCupoService`

Sin cambios: al otorgar ya escribe `kilos_disponibles = volumen_total_kg`.

### `EmitirGuiaRequest`

```php
'aprovechamiento_id' => [
    Rule::requiredIf(fn () => ! $this->boolean('es_piscicultura')),
    Rule::prohibitedIf(fn () => $this->boolean('es_piscicultura')),
    'nullable', 'integer',
    Rule::exists('aprovechamientos_pesq', 'id')->whereNull('deleted_at'),
],
```

Que esté **aprobada y en fecha** y que alcancen los kilos lo decide el servicio,
dentro de la transacción. El Request solo filtra lo obvio.

---

## 5. La pantalla «Registrar guía» (`/panel/guias/crear`)

Hoy tiene: **A. Interesado** (comercializador + carnet), **Ubicación y
transporte**, **D. Productos hidrobiológicos** y el resumen **«Lo que se va a
registrar»**.

Se agrega un bloque nuevo entre el interesado y la ubicación:

```
┌─ A. Interesado ──────────────────────────────────────────────────────────┐
│ Comercializador   [ Nacho Pérez · 1234567 BN               ✕ ]           │
│ Carnet            [ Carnet de comercializador N° 000010     ]            │
└──────────────────────────────────────────────────────────────────────────┘
┌─ Procedencia del pescado ─────────────────────────────────── NUEVO ─────┐
│ Pescador que vende  [ Buscar por cédula o nombre…              ]        │
│                     → Juan Gómez · 7654321 BN                           │
│                                                                         │
│ Autorización        ┌─────────────────────────────────────────────────┐ │
│                     │ N° 000012 · 801 kg hasta 1000 kg · vence 31/12  │ │
│                     │ Disponibles: 700 kg de 1000          [Aprobada] │ │
│                     └─────────────────────────────────────────────────┘ │
│                                                                         │
│  ⚠ Juan Gómez no tiene una autorización aprobada y vigente.             │ ← si no tiene
└─────────────────────────────────────────────────────────────────────────┘
┌─ Ubicación y transporte ─┐   (igual que hoy)
┌─ D. Productos ───────────┐   (igual que hoy: el total suma los kilos)

┌─ Lo que se va a registrar ────────────┐
│ FECHA DE SOLICITUD   13/10/2026       │
│ PESCADO DE           Juan Gómez       │  ← NUEVO
│                      Aut. N° 000012   │
│ KILOS                300 kg           │
│ LE QUEDAN A JUAN     700 → 400 kg     │  ← NUEVO: cómo queda el saldo
│ A COBRAR             120,00 Bs        │
│ [ Registrar guía ]                    │
│                                       │
│ ⚠ Se pasa por 50 kg: Juan tiene 700.  │  ← si el cuadro D supera lo disponible
└───────────────────────────────────────┘
```

**Cómo se comporta:**

1. El buscador de pescador es el mismo de beneficiarios (`BuscadorBeneficiario`),
   pero la respuesta trae sus autorizaciones **aprobadas y en fecha** con
   `kilos_disponibles`.
2. Por la regla de una autorización vigente por persona, casi siempre hay **una**:
   va elegida sola. Si no tiene ninguna, aviso rojo y **no se puede registrar**.
3. Mientras se carga el cuadro D, el resumen muestra **cómo queda el saldo**. Si
   el total se pasa, aviso rojo y el botón queda deshabilitado.
4. Es **de referencia**: el servidor vuelve a medir con la autorización
   bloqueada. Si otra ventanilla gastó esos kilos en el medio, el servidor
   rechaza con el mensaje de saldo.
5. Si es de piscicultura (hoy oculto), el bloque «Procedencia» no aparece.

**Props nuevas de `GuiaController::create()`:** ninguna fija; la autorización del
pescador llega con el buscador (`cupos_elegibles` del beneficiario, filtrado a
aprobadas y en fecha, con `kilos_disponibles`).

### «Corregir guía» (`/panel/guias/{guia}/editar`)

Mismo bloque «Procedencia», con el pescador y la autorización ya elegidos. Se
puede cambiar mientras la guía esté pendiente. Al medir el saldo, los kilos de
la propia guía cuentan como disponibles (se devuelven antes de descontar).

---

## 6. Lo que se ve en el resto del sistema

| Lugar | Qué muestra |
| --- | --- |
| **Ficha de la guía** | «Pescado de: Juan Gómez — Autorización N° 000012», con enlace (si tiene `aprovechamientos.ver`) |
| **Ficha de la autorización** | Autorizado / **disponible** (con barra) y la **lista de guías** que le compraron: N°, comercializador, kilos, estado |
| **Listado de autorizaciones** | Columna «Disponible» |
| **Guía impresa** | En el cuadro **A. Interesado**, un renglón nuevo: «Procedencia: pescador — C.I. — Autorización N°» *(confirmar en el papel dónde va)* |
| **Faena** | «Kilos del permiso» = `kilos_disponibles` de ese momento |
| **Portal del pescador** | «Disponible: 700 de 1000 kg» en su autorización |
| **Verificación pública** | Sin cambios (no expone saldo) |

---

## 7. Archivos que se tocan

| Archivo | Cambio |
| --- | --- |
| Migración `create_guias_movimiento_table` | `aprovechamiento_id` |
| `app/Models/AprovechamientoPesq.php` | `descontar()`, `devolver()`, relación `guias()` |
| `app/Models/GuiaMovimiento.php` | Fillable + relación `aprovechamiento()` |
| `app/Services/EmitirGuiaService.php` | Descontar / devolver en emitir, editar y eliminar |
| `app/Services/RevisarGuiaService.php` | No aprobar con la autorización revocada |
| `app/Services/EmitirFaenaService.php` | La faena sale con `kilos_disponibles` |
| `app/Exceptions/PermisoOperativoException.php` | `sinKilosDisponibles()`, `autorizacionNoVigente()` |
| `app/Http/Requests/Panel/EmitirGuiaRequest.php` | `aprovechamiento_id` |
| `app/Http/Controllers/Panel/GuiaController.php` | Ficha y edición con la procedencia |
| `app/Http/Controllers/Panel/AprovechamientoController.php` | Ficha con saldo y guías |
| `app/Http/Controllers/Panel/BeneficiarioController.php` | El buscador trae las autorizaciones vendibles |
| `app/Http/Controllers/Panel/GuiaImpresionController.php` + `guia-transporte.blade.php` | Renglón de procedencia |
| `resources/js/pages/panel/guias/crear.tsx`, `editar.tsx`, `ver.tsx` | Bloque «Procedencia» y saldo en el resumen |
| `resources/js/pages/panel/aprovechamientos/ver.tsx`, `index.tsx` | Saldo y guías |
| `resources/js/types/guias.ts`, `aprovechamientos.ts` | Tipos |
| Docs | REGLAS-NEGOCIO (cambia la especificación), MER, ARQUITECTURA, PERMISOS-OPERATIVOS, CLAUDE.md (el diagrama del dominio) |

---

## 8. Cómo se prueba

Contra **test.sireb**, en una SQLite descartable (nunca sobre la base de trabajo):

1. Autorización de 1000 kg → guía de 300 → `kilos_disponibles` = 700.
2. Faena → sale con 700.
3. Guía de 800 → **rechazada** con el mensaje de saldo.
4. Eliminar la guía pendiente de 300 → vuelve a 1000; su liquidación queda anulada en SIREB.
5. Corregir una guía pendiente de 300 a 250 → 750.
6. Aprobar y revocar → no vuelve nada.
7. Revocar la autorización con una guía pendiente → la guía no se aprueba.
8. Elegir una autorización pendiente o vencida → rechazada.
9. Dos registros a la vez sobre los mismos kilos → uno pasa, el otro se frena.

Y la pantalla, a mano: el bloque, el aviso de saldo y el botón deshabilitado.

---

## 9. Lo que falta confirmar

- [ ] **Piscicultura:** con la casilla oculta, toda guía pide pescador. ¿Está bien?
- [ ] **Dónde va la procedencia en el papel impreso:** se propone un renglón en el
      cuadro A; confirmar contra el talonario.

---

## 10. Cómo quedó (13/10/2026)

- **Impresión:** en el cuadro A, un renglón nuevo sin número de casilla: «b) Pescador
  (procedencia) · Nombre y Apellido · C.I. · Autorización N°». Vacío si es de criadero.
- **Componente:** `components/panel/guias/bloque-procedencia.tsx`, compartido por
  «Registrar» y «Corregir». El buscador de beneficiarios trae
  `autorizaciones_vendibles` (aprobadas y en fecha, con `kilos_disponibles`).
- **Corregir:** la autorización de la propia guía llega con sus kilos sumados
  (`GuiaController::pescadorParaEditar()`). Si en la corrección se vuelve a buscar
  al mismo pescador con el buscador, la pantalla muestra el saldo sin esos kilos
  (de referencia); el servidor igual los devuelve antes de descontar.
- **Listado de autorizaciones y portal:** todavía no muestran el saldo (queda en
  la ficha de la autorización, con barra y la lista de guías).
- **Probado** contra test.sireb en SQLite descartable: los casos 1 a 8 de §8 y el
  cambio de pescador al corregir. **No probado:** dos ventanillas a la vez (caso
  9): el bloqueo está, pero SQLite no lo puede ejercitar; probarlo en PostgreSQL.
- **Buscar por código** (13/10/2026): en «Procedencia» el buscador acepta además el
  **código de verificación** de la autorización —con o sin guiones, en minúsculas o
  la URL que tipea un lector de QR— y la deja elegida. `BeneficiarioController::
  autorizacionPorCodigo()` busca en `codigos` con `whereHas('codigo')` desde
  `AprovechamientoPesq`, que filtra por `codigable_type`: un código de carnet, faena,
  guía o recibo no encuentra nada. Solo el código completo. Solo con `?autorizacion=1`
  (prop `porCodigoDeAutorizacion` del buscador): los demás buscadores no cambian. El
  N° del talonario no se busca: se confundiría con la C.I.

