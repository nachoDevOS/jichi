# Pendientes

Qué falta construir, en qué orden, y qué problemas conocidos quedan abiertos.

---

## Módulos que faltan

Los cuatro aparecen en gris en el menú lateral. **El patrón a copiar es
Solicitantes**, que está comentado paso a paso justamente para eso. El
procedimiento completo está en [GUIA-INERTIA.md](GUIA-INERTIA.md#7-agregar-un-módulo-nuevo-paso-a-paso).

> **El circuito de estados ya está construido.** La ficha del trámite
> (`TramiteController::show()`), los pasos de revisar, aprobar, rechazar,
> emitir y entregar, y la emisión del documento con su correlativo y su código
> de verificación (`EmisionDocumentoService`). Lo que sigue faltando de esta
> lista es lo marcado más abajo: la checklist de requisitos por tipo, las
> exenciones, el PDF del documento y el módulo de cobro.

### 1. Trámites ← empezar por acá

Es el corazón del sistema: todo lo demás depende de que exista un trámite.

---

#### LAS REGLAS DEL NEGOCIO — leer esto antes de escribir una línea

Confirmadas con el responsable del sistema el 10/09/2026. Son la razón de ser
del módulo: sin ellas el CRUD que sigue no sirve de nada.

**El orden no se puede saltear.**

```
  Solicitante registrado
          ↓
  Credencial (Cédula de Pescador)    ← vigente
          ↓
  ┌───────────────┬────────────────────────────┐
  │ Permiso por   │ Guía Única de Transporte   │
  │ Faena         │                            │
  └───────────────┴────────────────────────────┘
```

**1. Nadie pide nada sin estar en el padrón.** La ficha de solicitante es el
punto de partida. Eso ya está construido.

**2. La credencial es la llave.** Ningún otro servicio se puede solicitar sin
ella. Para emitirla hay que adjuntar tres papeles, imagen o PDF, y son
bloqueantes:

- Certificación emitida por su asociación
- Fotocopia de carnet simple
- Comprobante de pago de la cédula

(Ya construido: ver `formulario-cedula-pescador.tsx` y la validación de
`TramiteController::store()`.)

**3. La credencial vale por GESTIÓN, no por días corridos.** Vence al terminar
el año en que se emitió, sin importar el mes: sacada en enero dura casi doce
meses, sacada en diciembre dura unas semanas. Las dos vencen el mismo día.

> ✅ **CONSTRUIDO.** `tipos_tramite` tiene ahora `vigencia_tipo`
> (`dias | gestion | sin_vencimiento`), y la cuenta vive en el enum
> `App\Enums\VigenciaTipo`. La credencial figuraba como 730 días —dos años— y
> se corrigió a `gestion`.
>
> Si la gestión de la Gobernación no coincide con el año calendario, se cambia
> en un solo lugar: el `case Gestion` de ese enum.

**3.bis La credencial pasa por revisión antes de habilitar.** Pedirla no
alcanza: el trámite entra, pasa a *En Revisión*, un supervisor lo aprueba y
recién ahí se emite el documento. Entre medio el pescador no puede sacar faena
ni guía.

> ✅ **CONSTRUIDO.** La compuerta busca un documento EMITIDO, así que un
> trámite en revisión no habilita nada. Y se distingue «nunca la sacó» de «ya
> la pidió y está en revisión», porque son dos acciones distintas en
> ventanilla: en el primer caso hay que cargarla, en el segundo cargarla otra
> vez sería duplicar el trámite.

**4. La compuerta.** Al iniciar un trámite de faena o de guía, el sistema tiene
que preguntar una sola cosa:

> ¿Este solicitante tiene credencial **vigente hoy**?

- **Sí** → se registra únicamente el servicio pedido. No se toca la credencial.
- **No** → el servicio no se puede registrar todavía. Primero se le emite o
  renueva la credencial; recién después el servicio.

Cada faena es un trámite nuevo. Haber tenido faenas antes no habilita nada: lo
único que habilita es la credencial vigente **al momento de pedir**.

> ✅ **CONSTRUIDA.** Vive en `TipoTramite::habilitacionPara()` y devuelve un
> `App\Support\Habilitacion`, no un booleano: el operador tiene al pescador
> enfrente y necesita saber qué falta y cómo resolverlo.
>
> Se comprueba en TRES lugares del servidor —al listar el catálogo, al entrar
> al formulario de cada servicio y al guardar—, porque las tarjetas viven en el
> navegador del operador y una dirección escrita a mano las saltea enteras.
>
> El alta de trámite empieza ahora eligiendo al SOLICITANTE y no al servicio:
> hasta no saber de quién se trata, el sistema no puede decir qué puede pedir.

**5. Vigencia de cada servicio.**

| Servicio | Vigencia | Uso |
|---|---|---|
| Cédula de Pescador | la gestión (hasta el 31 de diciembre) | habilita a pedir los otros |
| Permiso por Faena | 1 mes desde la emisión | **una sola vez** |
| Guía Única de Transporte | *(pendiente de definir)* | *(pendiente)* |

La faena se agota por lo que pase primero: que se use, o que pase el mes.

La guía sigue **exactamente el mismo flujo** que la faena —también exige
credencial vigente—, solo cambia su vigencia, que está pendiente de definir.

**6. Los dos servicios ya están sembrados.** `PPF` (Permiso por Faena) y `GUT`
(Guía Única de Transporte) se agregaron a `AreaSeeder` y el catálogo de la
pantalla sale ahora de la tabla `tipos_tramite`.

Lo único que quedó escrito a mano en el controlador son los membretes, el
encabezado legal y las leyendas al pie: son textos impresos en los talonarios
del SEDAG y solo sirven para dibujar la vista previa del documento.

#### Preguntas abiertas sobre estas reglas

Ninguna impide empezar, pero todas cambian el código:

- **El mes de la faena:** ¿30 días exactos, o el mismo día del mes siguiente?
  (Emitida el 31 de enero, ¿vence el 28 de febrero o el 2 de marzo?)
- **El uso único de la faena:** ¿alguien marca en el sistema que se usó, o en la
  práctica solo vence al mes y el "una sola vez" es una regla del papel?
- **Renovar la credencial:** ¿es otro trámite del mismo tipo, con los dos
  papeles otra vez, o el trámite de renovación pide menos?
- **Credencial vencida con faena vigente:** si la credencial vence mientras el
  permiso por faena sigue vigente, ¿la faena sigue valiendo? (Lo razonable es
  que sí: se emitió cuando correspondía.)

---

#### Lo que hay que construir

- CRUD completo siguiendo el patrón de Solicitantes
- **Máquina de estados.** Ya está escrita en `app/Enums/EstadoTramite.php` pero
  nadie la usa todavía. Antes de cambiar el estado de un trámite hay que
  preguntarle:

  ```php
  if (! $tramite->estado->puedePasarA($nuevoEstado)) {
      abort(422, 'Transición de estado no permitida.');
  }
  ```

- Numeración con `CorrelativoService::siguiente('TRA-'.$area->codigo)`
- Checklist de requisitos: el tipo de trámite los trae en su columna `requisitos`
- Aplicar exenciones al calcular el monto (`Exencion::descuentoSobre()`)
- Aprobar y rechazar: solo con permiso `tramites.aprobar` / `tramites.rechazar`

El cobro de la tasa también entra acá, porque ya no hay un módulo de Caja
aparte:

- Registrar el pago contra el trámite como fila de la tabla `pagos`, con
  permiso `pagos.registrar`
- QR y transferencia exigen número de referencia: lo dice
  `FormaPago::requiereReferencia()`
- Comprobante de pago en PDF
- Anular pagos (permiso `pagos.anular`) y recalcular con
  `Tramite::recalcularPagado()`

> **El pago de la cédula ya se captura, pero todavía no es una fila de
> `pagos`.** El alta de la Cédula de Pescador acepta uno o varios comprobantes
> —cada uno con su forma, su número de transacción, su banco, su monto y su
> archivo— y los guarda como JSON en `tramites.requisitos_validados['pagos']`,
> además de escribir la suma en `tramites.monto_pagado`. Alcanza para que no se
> pierda un pago en ventanilla, pero no reemplaza al módulo de cobro: falta el
> número de comprobante correlativo, el recibo en PDF y la anulación. Ver
> `TramiteController::registrarPagos()`.
>
> **Hoy solo se acepta transferencia bancaria.** El efectivo y el pago por QR
> están fuera de `FormaPago::disponibles()` hasta que se defina cómo se rinde la
> caja del día y quién concilia el QR. Los tres casos siguen en el enum para
> poder leer pagos viejos; habilitarlos es agregarlos a esa lista, sin tocar la
> pantalla ni las reglas de validación.
>
> **La fotografía del titular no es un adjunto del trámite.** Es un dato
> personal del padrón y vive en `solicitantes.foto`. Si la ficha ya la tiene, el
> formulario de la cédula ni la muestra. Si no la tiene, el campo aparece,
> es obligatorio, y lo que se cargue se guarda en la FICHA —misma carpeta que el
> alta del solicitante—, no en la carpeta del trámite. Reemplazar una foto ya
> cargada sigue siendo cosa de la ficha: desde el trámite se completa, nunca se
> pisa. Ver `TramiteController::completarFichaDelSolicitante()`.
>
> **Lo mismo vale para el domicilio.** Ciudad, provincia y dirección se
> muestran de solo lectura en el formulario de la cédula y se imprimen tal como
> figuran en la ficha: al guardar, `TramiteController::datosDeLaFicha()` los
> vuelve a leer del padrón y pisa lo que haya llegado en la petición, junto con
> el nombre y la cédula de identidad. Si la ficha viene incompleta, los campos
> que falten se piden en el trámite y se guardan en `solicitantes`.

> **Caja se eliminó del sistema, y ya no queda nada de ella.** Además de
> haber salido del menú y de los permisos (`caja.abrir`, `caja.cerrar` y
> `caja.arquear` se quitaron de `RolSistema`), se borraron el enum
> `EstadoCaja`, el modelo `CierreCaja`, la migración de `cierres_caja`, la
> columna `pagos.cierre_caja_id`, `FormaPago::columnaCierre()`,
> `User::cierresCaja()` y `User::cajaAbierta()`.
>
> Las migraciones se editaron en su lugar en vez de agregar una que deshace,
> porque el sistema todavía no está en producción y la base se vuelve a
> sembrar entera. Si en algún momento hace falta arqueo, se construye de
> nuevo: no quedó media implementación estorbando.
>
> Las cuatro configuraciones `caja.*` también se fueron, con una excepción:
> la moneda y su símbolo pasaron al grupo `general` (`general.moneda` y
> `general.simbolo_moneda`), porque no eran del arqueo sino de todo el
> sistema — `HandleInertiaRequests` lee el símbolo para escribir «Bs» en los
> montos del panel.

### 2. Documentos

Acá entran las tres librerías que hoy están instaladas y sin usar.

- Emitir el documento cuando el trámite llega a "aprobado"
- Numeración con `CorrelativoService::siguiente($tipo->serieDocumento())`
- Generar el PDF con **dompdf**, usando la plantilla que devuelve
  `CategoriaDocumento::plantilla()`. Faltan crear esas vistas Blade:

  ```
  resources/views/documentos/certificacion.blade.php
  resources/views/documentos/credencial.blade.php
  resources/views/documentos/permiso.blade.php
  resources/views/documentos/licencia.blade.php
  ```

  Las credenciales van en formato tarjeta CR80 — el tamaño ya lo devuelve
  `CategoriaDocumento::formatoPagina()`.

- Insertar el QR con **simple-qrcode**, apuntando a
  `$documento->url_verificacion`
- Guardar `datos_snapshot`: la copia inmutable de lo que se imprimió
- Calcular el hash SHA-256 del PDF y guardarlo en `hash_pdf`
- Anular documentos (permiso `documentos.anular`)

### 3. Reportes

- Recaudación por período, por área y por operador
- Trámites por estado
- Documentos emitidos y por vencer
- Exportar a Excel con **maatwebsite/excel** (permiso `reportes.exportar`)

### 4. Configuración

- Editar los valores de la tabla `configuraciones` desde el panel
- Gestión de usuarios: alta, baja, cambio de rol
- ABM de áreas, tipos de trámite y tarifas
- Consulta de la bitácora de auditoría (permiso `auditoria.ver`)

---

## Falta un comando de vencimiento

**Es lo más urgente después de Trámites.**

Hoy ningún proceso marca los documentos como vencidos. La consecuencia:
`Documento::estadoEfectivo()` calcula bien el estado al mostrar un documento
suelto, pero los conteos del panel usan la columna `estado`, que nunca se
actualiza. El indicador "Documentos vencidos" del dashboard reporta de menos.

Hay que crear:

```
app/Console/Commands/MarcarDocumentosVencidos.php
```

```php
Documento::query()
    ->where('estado', EstadoDocumento::Vigente)
    ->whereNotNull('fecha_vencimiento')
    ->whereDate('fecha_vencimiento', '<', now())
    ->update(['estado' => EstadoDocumento::Vencido]);
```

Y programarlo en `routes/console.php` para que corra todos los días:

```php
Schedule::command('documentos:marcar-vencidos')->dailyAt('00:30');
```

---

## Problemas conocidos, sin arreglar

Ordenados por importancia. Ninguno rompe nada hoy, pero todos van a morder
cuando crezca el sistema.

### `Pago` y `Tramite` usan textos sueltos en vez de un enum

`app/Models/Pago.php` y `Tramite::recalcularPagado()` comparan contra los textos
`'pagado'` y `'anulado'` escritos a mano:

```php
public function scopeVigentes(Builder $query): Builder
{
    return $query->where($query->qualifyColumn('estado'), 'pagado');
}
```

Todo el resto del sistema usa enums. Falta crear `app/Enums/EstadoPago.php` con
los casos `Pagado` y `Anulado`, castearlo en el modelo y reemplazar los textos.
Con un texto suelto, un `'pagdo'` mal escrito no da error: devuelve cero filas
en silencio, y en el cobro de tasas eso significa plata que no aparece.

### `Configuracion::guardar()` falla sin avisar

```php
public static function guardar(string $clave, mixed $valor): void
{
    static::query()->where('clave', $clave)->update([...]);
}
```

Si la clave no existe, actualiza cero filas y no dice nada. Debería usar
`updateOrCreate()` o lanzar una excepción. Importa cuando se construya el módulo
de Configuración.

### `WithoutModelEvents` se aplica de forma despareja

`DatabaseSeeder` usa el trait `WithoutModelEvents`, que apaga los eventos de
modelo durante todo el sembrado. Las pruebas, en cambio, llaman a los seeders
sueltos y SÍ disparan eventos.

Hoy no rompe nada —`Solicitante` ya no tiene ningún hook `saving`: el nombre
completo pasó a calcularse al vuelo—, pero sigue siendo frágil: cualquier
seeder nuevo que dependa de un evento de modelo va a fallar solo en
producción, porque en las pruebas sí se dispara.

### `Tramite::documento()` no hace lo que dice su comentario

```php
/**
 * Documento vigente emitido por este trámite (el último no anulado).
 */
public function documento(): HasOne
{
    return $this->hasOne(Documento::class)->latestOfMany();
}
```

El comentario promete filtrar los anulados; el código no lo hace. Hay que
agregar `->whereNot('estado', EstadoDocumento::Anulado)` o corregir el
comentario. Importa cuando exista el módulo de Documentos.

---

## Ya corregido (para referencia)

Cuatro problemas que sí se arreglaron, por si aparecen dudas de por qué el
código está escrito así:

| Problema | Dónde está la corrección |
| --- | --- |
| Los índices únicos no bloqueaban nada por incluir `deleted_at` (se podían crear dos solicitantes con el mismo CI, y dos cajas abiertas el mismo día) | migración `2026_09_08_100000` |
| `veces_verificado` era `smallint`: se desbordaba a las 32.767 consultas y tumbaba la página pública | migración `2026_09_08_100100` |
| Cada escaneo de QR escribía además una fila de auditoría, y la ruta pública no tenía límite de peticiones | `Documento::registrarVerificacion()` y `routes/publico.php` |
| `UsuarioSeeder` leía `env()` fuera de `config/`: con `config:cache` los cuatro usuarios institucionales quedaban con la contraseña por defecto sin avisar | `config/jichi.php` |

Cada uno tiene su explicación completa comentada en el archivo correspondiente.

---

## Antes de poner esto en producción

- [ ] Cambiar `JICHI_SEED_PASSWORD` y las contraseñas de los cuatro usuarios
- [ ] `APP_DEBUG=false` y `APP_ENV=production`
- [ ] `JICHI_URL_VERIFICACION` apuntando al dominio real (es lo que se
      imprime dentro del QR)
- [ ] Actualizar `sistema.url_verificacion` en la tabla `configuraciones`
- [ ] `php artisan storage:link` en el servidor
- [ ] Verificar que `DemoSeeder` NO corra: `DatabaseSeeder` ya lo bloquea con
      `app()->isProduction()`, pero conviene confirmarlo
- [ ] Configurar el respaldo de la base de datos
- [ ] Programar el comando de vencimiento (ver más arriba)
