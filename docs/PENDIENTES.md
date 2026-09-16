# Qué falta y qué sigue abierto

Estado al **15 de septiembre de 2026**, después de agregar la impresión del carnet.

---

## Lo que ya funciona

| Módulo | Estado | Dónde mirar |
| --- | --- | --- |
| Acceso y bitácora | Completo | `AuthenticatedSessionController`, tabla `accesos` |
| Panel principal | Completo | `DashboardController` |
| **Beneficiarios** | Completo — es la plantilla del sistema | `BeneficiarioController` |
| **Trámites** | Completo: solicitud → aprobación → impresión → entrega | `SolicitudCarnetService` |
| **Pagos** | Completo: 1 a N depósitos por trámite | `PagoTramiteService` |
| **Recibos** | Completo: el talonario del SEDAG en PDF | `ReciboTramiteService` |
| **Carnets** | Completo: consulta, suspensión, anulación, **impresión del plástico**. Uno por persona, rubro y gestión | `CarnetController`, `CarnetImpresionController` |
| **Rubros** | Completo: catálogo con tarifa vigente | `RubroController` |
| Verificación pública | Completo, con firma de validación | `VerificacionController` |

---

## Módulo 1 — Reportes

Aparece en gris en el menú. No existe ni la ruta ni el controlador.

**Qué debería tener:**

- Recaudación por rubro y por periodo, exportable a Excel.
- Padrón de carnets vigentes de una gestión, para imprimir.
- Trámites rechazados con su motivo: sirve para detectar qué requisito falla más
  seguido y corregir el instructivo de ventanilla.
- Carnets suspendidos, por actividad.

**Por dónde empezar:** copiar el patrón de `BeneficiarioController::index()`
—filtros + `Paginacion` + `through()`— y agregar una acción de exportación con
`maatwebsite/excel`, que ya está instalado.

---

## Módulo 2 — Configuración

Aparece en gris en el menú. La tabla `configuraciones` existe y está sembrada;
falta la pantalla para editarla.

**Qué debería tener:**

- Editar los valores de la tabla `configuraciones` agrupados por `grupo`.
- Subir el logo y el escudo (son del tipo `archivo`).
- Alta y edición de usuarios: `GuardarUsuarioRequest` ya está escrito y
  comentado, pero **no tiene ruta ni controlador todavía**.

---

## Problemas conocidos que siguen abiertos

### 1. ~~El carnet no se imprime en PDF~~ — RESUELTO

Se hizo: `GET /panel/carnets/{carnet}/imprimir` dibuja el plástico en CR80
(243 × 153 pt), una sola carilla, **calcando la cédula de papel**. La vista
previa del panel —`components/panel/tramites/vista-previa-carnet.tsx`, el
recuadro «ASÍ VA A SALIR EL CARNET» del paso 3— muestra ese mismo molde, así que
lo que el operador ve mientras carga el trámite es lo que sale impreso. Ver
[modulos/CARNETS.md](modulos/CARNETS.md).

Tres cosas que este punto daba por sentadas y no eran así:

- **`simplesoftwareio/simple-qrcode` no sirve en este servidor.** Su salida PNG
  exige la extensión `imagick`, que no está instalada (`php -m` lista `gd`). El
  QR lo arma `App\Support\CodigoQr`, con la matriz de BaconQrCode —la librería
  que ese paquete trae adentro— pintada con `gd`. El paquete queda instalado y
  sin usar.
- **La dirección del QR ya no la arma `CarnetController`**, sino
  `Carnet::urlVerificacion()`: la necesitan la ficha del panel y la impresión, y
  escrita dos veces un día dirían cosas distintas.
- **Imprimir no marca impreso.** `tramites.fecha_generacion` la sigue escribiendo
  `PATCH /tramites/{tramite}/generar`, que es otro botón. Abrir la vista previa
  no es haber sacado el plástico, y si esta ruta marcara alcanzaría con que el
  navegador precargara el enlace.

**EL CARNET NO LLEVA QR: se sacó a pedido.** Y conviene tenerlo presente,
porque es lo que dejó a la credencial sin poder verificarse desde la calle: la
pantalla pública y la firma de validación siguen existiendo, pero quien tiene el
plástico en la mano ya no tiene forma de llegar a ellas. Es la misma limitación
que tenía la cédula de papel.

`App\Support\CodigoQr` queda **escrito y sin usar**, igual que quedó
`CorrelativoService` en su momento: el día que el QR vuelva, la parte difícil ya
está resuelta. Lo que hay que saber es que `simplesoftwareio/simple-qrcode` —el
paquete instalado justamente para esto— NO sirve en este servidor: su salida PNG
exige `imagick`, que no está.

### 2. Nadie marca los carnets como vencidos

`EstadoCarnet::Vencido` nunca se escribe solo. Falta el comando programado que
recorra los carnets de gestiones cerradas y actualice la columna.

**Mientras tanto el sistema no miente**, porque `Carnet::estaVigente()` compara
además contra `fecha_vencimiento`. Lo que sí queda mal es el filtro por estado
del listado, que muestra como «vigentes» carnets del año pasado hasta que el
comando exista.

### 3. ~~`StorageController` devuelve URL completa cuando el disco es s3~~ — RESUELTO

Se hizo lo que este mismo punto proponía: **`StorageController` devuelve siempre
la ruta, y la URL se arma al leer** en `Archivos::url()`, con el disco que el
sistema tenga configurado en ese momento.

Se descubrió porque con `FILESYSTEM_DISK=s3` los adjuntos abrían en
`http://jichi.test/storage/...`: `Archivos::url()` estaba clavado en
`disk('public')` e ignoraba el disco activo.

Con el cambio se arreglan tres cosas de una:

- **Se pueden borrar.** Con la ruta se borra del disco activo; desde una URL no
  había forma de volver a la clave del objeto.
- **El dominio deja de estar congelado.** Cambiar de bucket, endpoint o poner un
  CDN es cambiar el `.env`; antes había que reescribir filas.
- **Una sola forma en la columna.** Ya no conviven rutas y direcciones.

`Archivos::url()` conserva la rama que devuelve tal cual lo que empieza con
`http`, por las filas viejas. Se puede sacar el día que no queden.

También se retiró `jichi.archivos.prefijo_s3`: el disco ya lleva
`'root' => env('AWS_ROOT')` y Flysystem lo antepone solo. Tenerlo en los dos
lados duplicaba la carpeta —`dev/dev/tramites/...`—.

### 3b. El enlace `public/storage` apuntaba a OTRO PROYECTO

`public/storage` era un enlace a `surubiNet/storage/app/public`, no a `jichi`.
Un `storage:link` mal hecho o heredado de otro proyecto. Se rehízo.

Con el disco en s3 casi no se notaba; el día que se pase a disco local, habría
servido los archivos del proyecto equivocado.

### 3c. `APP_URL` no coincide con cómo se accede — ABIERTO

`.env` dice `APP_URL=http://jichi.test`, pero el sistema se usa en
`http://127.0.0.1:8000`, y `jichi.test` no resuelve.

Con el disco en s3 ya no afecta a los adjuntos, pero `APP_URL` la usan las rutas
absolutas, Ziggy y cualquier enlace que se mande por correo. **Hay que ponerlo en
la dirección real de cada entorno** —o crear el host `jichi.test` en Laragon—.

No se cambió desde el código: es configuración del entorno de cada máquina.

### 4. Solo hay un rol

`RolSistema` tiene un único `case`: `administrador`, con todos los permisos. Es
una decisión, no un olvido —ver el comentario del enum—, pero significa que hoy
**cualquier usuario del sistema puede aprobar sus propios trámites**.

Los permisos ya están repartidos por bloque dentro del enum (`$lectura`,
`$operacion`, `$supervision`, `$administracion`), así que agregar el rol de
ventanilla es escribir una línea:

```php
self::Operador => [...$lectura, ...$operacion],
```

Y las rutas ya lo respetan, porque cada una declara su `permiso:`.

### 5. `GuardarUsuarioRequestTest` se eliminó

Probaba escenarios de cuatro roles —degradar al último administrador, por
ejemplo— que hoy no pueden ocurrir. Se borró al dejar un solo rol. Cuando el
módulo de Usuarios se construya, hay que volver a escribirlo como pruebas HTTP
contra sus rutas.

### 6. ~~`DemoSeeder` no pasa por el servicio~~ — RESUELTO

Ya no escribe carnets, trámites ni pagos: siembra **solo el padrón** —treinta
beneficiarios— y el circuito se carga desde la pantalla. El motivo por el que la
copia era mala se confirmó en el cambio a «un carnet por rubro»: ese guion
escrito a mano habría habido que reescribirlo entero, y ninguna prueba habría
avisado si quedaba mal.

Lo que sí hace falta sembrado es el padrón: tipear treinta personas para probar
el buscador o la paginación no prueba nada y cuesta una tarde.

### 7. No hay edición ni anulación de pagos

Una boleta cargada con el monto equivocado no se puede corregir desde la
pantalla. La tabla no tiene `estado` ni `deleted_at` a propósito —un pago
«anulado» que sigue en la lista invita a sumarlo por error— pero falta la
pantalla de edición, que sí correspondería: el trait `Auditable` ya guardaría el
valor anterior.

### 8. ~~Un rubro suspendido no se puede volver a tramitar~~ — RESUELTO

El comportamiento sigue siendo el mismo —un rubro suspendido bloquea, porque la
habilitación existe y volver a tramitarla sería cobrar dos veces— pero ahora el
mensaje lo explica y dice qué hacer: `SolicitudInvalidaException::rubroSuspendido()`.

Además el formulario ya no lo ofrece: el selector de rubros deshabilita los que
el carnet tiene, y la tarjeta de situación los muestra con su estado.

### 9. ~~`CorrelativoService` quedó sin usar~~ — RESUELTO

Se lo había conservado con el argumento de que «el día que haga falta un número
correlativo —de recibo, de resolución— ya está escrito y probado contra
concurrencia». Ese día llegó: el **módulo de Recibos** lo usa para numerar el
talonario, serie `RECIBO`, reiniciada cada gestión.

Se le extrajo `siguienteNumero()`, que devuelve el entero crudo — el recibo
necesita el número pelado (`0016`) y guardarlo como entero para poder ordenarlo,
cosa que con el código formateado no se podía. El bloqueo sigue viviendo en un
solo lugar.

### 11. El recibo no se puede anular

En el talonario de papel se anulaba escribiendo «ANULADO» sobre las tres copias
y archivándolas. La versión digital no tiene el equivalente: una vez emitido, el
recibo queda.

No es urgente —el número nunca se reusa y el rastro está completo— pero el día
que se cobre mal y haya que dejar constancia, hace falta. Está sin definir con la
unidad qué debería pasar con la plata en ese caso, y por eso no se construyó
adivinando.

### 12. No hay libro de recibos

**OJO: este pendiente cambió de forma.** La tabla `recibos` se retiró, así que
ya no hay nada que listar directamente; un libro de recibos hoy se arma
recorriendo los trámites con `fecha_revision`. El índice que estaba preparado
para el listado, pero no existe la pantalla. Contabilidad lo va a pedir junto con
Reportes: es el equivalente a revisar el talonario para cuadrar contra caja.

### 13. Las boletas de pago no se borran con el trámite — BUG

`SolicitudCarnetService::rutasDeAdjuntos()` hace
`$tramite->pagos->pluck('comprobante')`, pero la columna de `pagos` se llama
**`urlFile`**; `comprobante` no existe como atributo —el accesor es
`comprobante_url`— así que devuelve `null` por cada pago y `descartar()` los
filtra en silencio.

**Efecto:** al borrar un expediente, sus dos adjuntos propios sí se borran, pero
**cada boleta escaneada queda huérfana en el disco para siempre**. Nada se rompe
y nadie se entera.

Se arregla cambiando `pluck('comprobante')` por `pluck('urlFile')`. Las pruebas
de borrado verifican filas, no disco, por eso no lo detectaron.

### 14. Dos N+1 silenciosos — BUG

Los dos calculan en PHP lo que el comentario dice que se calcula en SQL:

- **`DashboardController::resumenDelDia()`**, en `listos_para_aprobar`: hace
  `Tramite::abiertos()->get()` sin `withSum` y después llama a `estaPagado()` por
  fila, o sea una consulta agregada por trámite abierto. Es la pantalla de
  entrada del sistema. Se arregla agregando `->withSum('pagos', 'monto')` antes
  del `get()` — el propio archivo ya lo hace bien en `ultimosTramites()`.

- **`Beneficiario::deudaTotal()`**: su docblock dice «la resta se hace en SQL y no
  trayendo las filas a PHP», y el código hace exactamente lo contrario. Lo llama
  `BeneficiarioController::show()`.

### 15. `CarnetImpresionController::filas()` es código muerto que además no compila

Nadie la llama —el PDF sale de `datos()`— y adentro invoca `$this->celda()`, un
método **que no existe en la clase**. Es el sobrante de la versión 2 del diseño,
la que tenía siete renglones con GESTIÓN y VENCE compartiendo uno. Si alguien la
llamara, error fatal; y como nada la llama, nada lo avisa.

Lo que la vuelve una trampa y no solo basura es su docblock: encabeza «LOS SIETE
RENGLONES DE LA TARJETA» y explica el reparto de GESTIÓN + VENCE. Hoy la tarjeta
tiene **seis** renglones y el segundo par es GESTIÓN, sin VENCE. Quien entre a
tocar los renglones de la tarjeta encuentra primero ese comentario, que describe
una tarjeta que no existe.

Se arregla borrando el método y su docblock. Se dejó para no mezclarlo con el
cambio de diseño del 15/09/2026.

### 16. ~~El tablero se corría de costado en el celular~~ — RESUELTO

La tabla de últimos trámites mide 675 px —seis columnas— y vivía dentro de un
elemento de grilla. Un elemento de grilla arranca con `min-width: auto`, que le
prohíbe encogerse por debajo de su contenido, así que la tarjeta se estiraba a
675 px aunque la pantalla midiera 375. El `overflow-x-auto` que la tabla ya tenía
no servía de nada: el que quedaba desplazable era **el documento entero**, y en
un celular había que correr de costado la pantalla completa —menú, encabezado y
todo— para leer una columna.

Se arregló con `min-w-0` en la tarjeta (`tabla-ultimos-tramites.tsx`). Medido en
390, 768 y 1440 px: el documento ya no desborda en ninguno, y la tabla se
desplaza sola dentro de su tarjeta.

No se notaba porque el tablero se prueba en el escritorio, donde sobra ancho.
Los otros cinco listados se revisaron y **no** tienen el problema: son los únicos
que no están dentro de una grilla. La trampa quedó anotada en CLAUDE.md.

### 17. El cambio a «un carnet por rubro» dejó dos cosas por decidir

El modelo nuevo funciona y está verificado, pero abrió dos preguntas que no
corresponde resolver sin la unidad:

**a) Un carnet ANULADO bloquea el rubro por el resto del año.** El índice único
`(beneficiario, rubro, gestión)` no distingue estados, así que anular el carnet
de Pescador de alguien le impide sacar otro de Pescador hasta enero. Antes
pasaba lo mismo pero con TODO el carnet, así que no es una regresión — y es
coherente con que anular sea una sanción. Pero si la unidad quiere permitir
reemitir tras una anulación, hace falta decidir cómo: un índice parcial
`WHERE estado != 'anulado'` lo permitiría, al precio de que dos carnets del mismo
rubro convivan en la misma gestión.

**b) El trámite de ACTUALIZACIÓN no tiene tarifa propia.** Copia
`rubros.costo`, igual que una emisión inicial, así que corregir un cupo cuesta lo
mismo que emitir el carnet. Puede ser lo querido o no; hoy nadie lo definió.

### 10. NO HAY PRUEBAS AUTOMÁTICAS — de nada

El **14 de septiembre de 2026** se vació `tests/Feature/` por pedido del
responsable del proyecto. Eran **143 pruebas** y cubrían el backend entero.
Frontend nunca hubo.

**Qué dejó de estar cubierto**, que es lo que importa de este punto:

| Regla | Qué pasa si se rompe y nadie avisa |
| --- | --- |
| Un carnet por persona y gestión | Se emiten dos documentos a la misma persona |
| No aprobar sin cobrar | Un rubro queda habilitado sin que entre la plata |
| El recibo conserva su número al reimprimir | Contabilidad recibe dos comprobantes por un pago |
| El recibo queda congelado | Una reimpresión dice algo distinto al papel entregado |
| Todo archivo pasa por `StorageController` | Alguien sube sin el tope de 3 MB ni el nombre aleatorio |
| Los saltos de estado válidos | Un expediente resuelto vuelve atrás |

El más difícil de reemplazar es el último de la tabla: `SubidaArchivosTest`
**leía el código fuente** y fallaba si aparecía un `->store()` nuevo. Esa clase
de regla no se detecta mirando la pantalla, porque no es un error de hoy sino un
atajo de mañana.

**Mientras tanto, cada cambio se verifica abriendo la pantalla y probando el caso
a mano.** `npx tsc --noEmit` y `pint` siguen revisando tipos y formato; de la
lógica de negocio no dicen nada.

**El andamiaje quedó en su lugar** —`phpunit.xml`, `tests/TestCase.php` y las
dependencias de PHPUnit—, así que volver a escribir una prueba es crear un solo
archivo, sin instalar nada.

**Por dónde volver a empezar, si algún día se retoma.** En este orden, que es el
de mayor daño posible:

1. `SolicitudCarnetTest` — la Regla A es la que sostiene todo el dominio.
2. `PagoTramiteTest` — es dinero.
3. `SubidaArchivosTest` — es la única forma de proteger el embudo de archivos.
4. `ReciboTramiteTest` — el recibo es un papel numerado que se entrega.

Y del frontend, lo más barato: `resources/js/lib/utils.ts` —`fecha()`,
`edadEnAnios()`, `bs()`— son funciones puras y se probarían en veinte líneas con
vitest. `fecha()` ya costó un error real: mostraba todas las fechas un día antes
por la zona horaria, y se descubrió mirando la pantalla.

---

## Orden sugerido

1. **Los dos bugs (13 y 14)** — son de media hora entre los dos y uno pierde
   archivos en silencio.
2. El comando de vencimiento de carnets (problema 2) — es de una tarde y arregla
   un dato que ya se muestra mal.
3. ~~El PDF del carnet (problema 1)~~ — hecho el 15/09/2026.
4. Reportes — la unidad de recaudación los pide todos los meses. Va junto con el
   libro de recibos (problema 12).
5. Usuarios y roles (problema 4) — antes de poner el sistema en manos de varias
   personas.
6. Configuración.
