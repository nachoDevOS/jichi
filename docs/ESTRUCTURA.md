# Estructura del proyecto

Mapa de dónde está cada cosa y, sobre todo, **dónde va un archivo nuevo**. Qué
hace cada archivo existente está en [MAPA-ARCHIVOS.md](MAPA-ARCHIVOS.md).

> Reescrito el 03/10/2026 contra el disco: el árbol anterior era del modelo de
> `rubros` y `tramites`.

---

## La regla que ordena todo

El sistema tiene **tres mitades** que no se mezclan, y la división se repite
igual en el backend y en el frontend:

| | Panel (funcionarios) | Público (sin sesión) | Portal (beneficiario) |
| --- | --- | --- | --- |
| Rutas | `routes/panel.php`, `/panel` | `routes/publico.php` | `routes/portal.php`, `/mi-cuenta` |
| Controladores | `Http/Controllers/Panel/` | `Http/Controllers/Publico/` | `Http/Controllers/Portal/` |
| Pantallas | `pages/panel/` | `pages/publico/` | `pages/portal/` |
| Componentes | `components/panel/` | `components/publico/` | `components/portal/` |
| Layout | `layout-panel.tsx` | `layout-publico.tsx` / `layout-institucional.tsx` | `layout-portal.tsx` |

Si sabés de qué mitad es lo que estás escribiendo, ya sabés dónde ponerlo.

---

## Backend (`app/`)

```
app/
├── Enums/                  Los valores fijos del negocio y sus TRANSICIONES
│   ├── EstadoAprovechamiento.php  pendiente · aprobado · agotado · revocado · no_pagado
│   ├── EstadoCarnet.php           pendiente · aprobado · revocado · no_pagado
│   ├── EstadoFaena.php            pendiente · aprobado · completado · revocado · no_pagado
│   ├── EstadoGuia.php             pendiente · aprobado · anulada · no_pagado
│   ├── EstadoLiquidacionSireb.php por_enviar · registrada · anulada
│   ├── TipoActor.php              pescador · comercializador (qué emite cada carnet)
│   ├── ModalidadAprovechamiento.php, ConceptoArancel.php, ConceptoRecibo.php,
│   │   CondicionProducto.php, MedioTransporte.php, TipoTransporte.php, EstadoAsociacion.php
│   └── RolSistema.php             los roles y TODOS sus permisos
│
├── Http/
│   ├── Controllers/
│   │   ├── Auth/                  iniciar y cerrar sesión (correo)
│   │   ├── Panel/                 ← ADMINISTRACIÓN (sesión + permiso:)
│   │   │   ├── BeneficiarioController.php     ← la plantilla a copiar
│   │   │   ├── AprovechamientoController.php  Carnet, Faena, GuiaController.php
│   │   │   ├── *ImpresionController.php, AutorizacionPescaController.php  los PDF
│   │   │   ├── ReciboController.php, DashboardController.php, CuentaPortalController.php
│   │   │   └── Asociacion, CategoriaAprovechamiento, TipoCarnet,
│   │   │       ProductoHidrobiologico, ArancelSirebController.php   catálogos
│   │   ├── Publico/               ← SIN SESIÓN: portada y verificación
│   │   ├── Portal/                ← BENEFICIARIO: solo lectura
│   │   └── StorageController.php  el ÚNICO que escribe archivos
│   ├── Requests/{Auth,Panel,Portal}/   las reglas de cada formulario
│   └── Middleware/                HandleInertiaRequests, SoloFuncionario, SoloBeneficiario
│
├── Models/                 Una clase por tabla
├── Exceptions/             Una regla de negocio dijo que no: CupoInvalido,
│                           CarnetInvalido, PermisoOperativo, CuentaPortal
├── Services/               ← ACÁ VIVEN LAS REGLAS, no en los controladores
│   ├── OtorgarCupoService.php, EmitirCarnetService.php,
│   │   EmitirFaenaService.php, EmitirGuiaService.php     crear, corregir, eliminar
│   ├── Revisar{Cupo,Carnet,Faena,Guia}Service.php        aprobar (y revocar)
│   ├── LiquidarSirebService.php    la liquidación en SIREB
│   ├── ConfirmarPagoService.php    pregunta a SIREB: aprueba o «No pagado»
│   ├── CargarPagoService.php       carga el pago en SIREB
│   ├── CorrelativoService.php, CodigoService.php, CuentaPortalService.php
├── Sireb/                  El cliente de Recaudaciones (SirebService, PrecioSireb, VistaSireb)
├── AuthIbare/              El login con Ibare, módulo cerrado
├── Support/                Piezas sin estado: Sql, Archivos, CodigoQr, ResumenPortal…
├── Traits/                 Auditable, Codificable, LiquidableSireb, HistorialSireb
└── Console/Commands/       jichi:verificar-pagos, jichi:sireb
```

### Dónde va un archivo nuevo del backend

| Estoy escribiendo... | Va en |
| --- | --- |
| Una pantalla del panel | `app/Http/Controllers/Panel/` |
| Algo que ve el ciudadano sin sesión | `app/Http/Controllers/Publico/` |
| Algo del portal del beneficiario | `app/Http/Controllers/Portal/` |
| Las reglas de un formulario | `app/Http/Requests/{Panel,Portal}/` |
| Una lista de valores fijos o un estado | `app/Enums/` (columna `string`) |
| Una regla de negocio | `app/Services/` — el formulario y un comando la comparten |
| Una llamada a SIREB | `app/Sireb/SirebService.php` |
| Un comando de consola | `app/Console/Commands/` (se programa en `routes/console.php`) |

---

## Rutas (`routes/`)

```
routes/
├── web.php        Solo incluye a publico, auth, panel y portal. Laravel carga ESTE.
├── publico.php    /  y  /verificar/{codigo?}                       sin sesión
├── auth.php       /login  /logout
├── panel.php      /panel/...           middleware funcionario + permiso: en cada ruta
├── portal.php     /mi-cuenta/...       middleware beneficiario, ninguna ruta recibe un id
└── console.php    el scheduler: jichi:verificar-pagos cada 10 minutos
```

Las rutas de Ibare las carga su provider (`app/AuthIbare/rutas.php`).

Cada ruta del panel declara el permiso que exige:

```php
Route::get('/beneficiarios', [BeneficiarioController::class, 'index'])
    ->middleware('permiso:beneficiarios.ver')
    ->name('beneficiarios.index');
```

**El orden importa.** `/beneficiarios/crear` tiene que ir antes de
`/beneficiarios/{beneficiario}`, o Laravel tomaría la palabra «crear» como id.

---

## Frontend (`resources/js/`)

```
resources/js/
├── app.tsx                 Punto de entrada. Arranca Inertia y React.
│
├── pages/                  UNA PANTALLA = UN ARCHIVO
│   ├── auth/login.tsx
│   ├── panel/                  ← ADMINISTRACIÓN
│   │   ├── dashboard.tsx
│   │   ├── beneficiarios/          index · crear · editar · ver
│   │   ├── aprovechamientos/       index · crear · editar · ver
│   │   ├── carnets/  faenas/  guias/   index · crear · editar · ver
│   │   ├── recibos/                index · ver
│   │   └── catalogos/              asociaciones, escala, tipos-carnet, productos,
│   │                               aranceles (cada uno con -formulario e -historial)
│   ├── publico/                ← SIN SESIÓN: inicio (portada), verificar
│   └── portal/                 ← BENEFICIARIO: ingresar, inicio, en-curso,
│                                 papeles, pagos, perfil, clave
│
├── layouts/                layout-panel, layout-publico (el acta), layout-institucional
│                           (la portada), layout-portal
│
├── components/
│   ├── ui/                     genéricas: button, card, input, label, select, textarea,
│   │                           badge, campo, paginacion, estado-vacio, confirmar-accion,
│   │                           confirmar-con-motivo, selector-archivo
│   ├── comunes/                panel Y público: logo-jichi, retrato, texto-copiable,
│   │                           toggle-apariencia
│   ├── panel/
│   │   ├── layout/                 barra-lateral, barra-superior, navegacion.ts (el menú)
│   │   ├── pagos/                  tarjeta-recaudaciones: el cobro de las cuatro fichas
│   │   ├── beneficiarios/  aprovechamientos/  guias/  catalogos/  dashboard/  comunes/
│   ├── portal/                 fila-papel, fila-tramite, visor-vista-previa, piezas
│   └── publico/                hoja-oficial, ficha-documento, buscador-codigo,
│                               splash-verificacion, institucional/ (la portada)
│
├── hooks/                  use-apariencia, use-archivos, use-flash, use-permisos
├── lib/                    utils (bs, fecha, fechaInput, fechaHora, cn), graficos,
│                           rueda-numerica
└── types/                  index.d.ts (estados y lo compartido) + uno por módulo:
                            aprovechamientos, beneficiarios, carnets, faenas, guias,
                            catalogos, recibos, dashboard, portal, publico
```

### Dónde va un archivo nuevo del frontend

| Estoy escribiendo... | Va en |
| --- | --- |
| Una pantalla nueva del panel | `pages/panel/<modulo>/` |
| Una pantalla pública | `pages/publico/` |
| Una pantalla del portal | `pages/portal/` |
| Un botón, input o tarjeta genérico | `components/ui/` |
| Una pieza de un módulo | `components/panel/<modulo>/` |
| Algo que usan dos mitades | `components/comunes/` |
| Los tipos de un módulo | `types/<modulo>.ts` |

---

## Tres convenciones de nombres

**1. Los archivos van en español, en minúsculas y con guiones.**

```
formulario-beneficiario.tsx      ✓
FormularioBeneficiario.tsx       ✗
```

**2. Los componentes de `ui/` conservan su nombre en inglés.**

`Button`, `Card`, `Input`, `Label`, `Badge`, `Select`, `Textarea` son el
vocabulario estándar de React. Todo lo demás —lo propio de este sistema— va en
español: `Campo`, `Paginacion`, `EstadoVacio`, `ConfirmarAccion`,
`TextoCopiable`, `TarjetaRecaudaciones`.

**3. Las columnas de `beneficiarios` van en camelCase desde `primerNombre`.**

`primerNombre`, `segundoNombre`, `apellidoPaterno`, `apellidoMaterno`,
`apellidoCasado`, `fechaNacimiento`… Es la única tabla así; `ci` y `complemento`
van en minúscula. **En PostgreSQL esas columnas necesitan comillas dobles en
toda consulta escrita a mano:**

```sql
SELECT primerNombre FROM beneficiarios;     -- ERROR: column "primernombre" does not exist
SELECT "primerNombre" FROM beneficiarios;   -- así sí
```

Eloquent entrecomilla solo. El que lo paga es quien escribe un `whereRaw` o abre
pgAdmin; ver `Beneficiario::SQL_NOMBRE`.

---

## Base de datos (`database/`)

> **Una tabla nueva del dominio toca SIETE lugares:**
>
> | Dónde | Qué |
> | --- | --- |
> | `database/migrations/` | La tabla, con su orden fijo y comentarios cortos (regla 12 de CLAUDE.md) |
> | `app/Enums/` | Sus estados y dominios cerrados, en columnas `string` |
> | `app/Models/` | El modelo, `SoftDeletes`, sus relaciones y las preguntas que sabe contestar |
> | `app/Services/` | Las reglas de negocio — **nunca en el controlador** |
> | `app/Http/Controllers/Panel/` + `Requests/` | La pantalla y su validación |
> | `resources/js/pages/panel/` + `types/` | El frontend |
> | `docs/MER.md` + `docs/ARQUITECTURA.md` + `docs/MAPA-ARCHIVOS.md` | La documentación |

```
database/
├── migrations/     2026_09_01_*: soporte (usuarios, correlativos, configuración,
│                   auditoría, accesos, permisos) · 2026_09_18_*: el núcleo, una por tabla
├── seeders/
│   ├── DatabaseSeeder.php      el orden: RolPermiso → Configuracion → Usuario → …
│   ├── RolPermisoSeeder.php    el rol administrador con TODOS los permisos
│   ├── ConfiguracionSeeder.php datos de la institución (updateOrCreate: pisa)
│   ├── UsuarioSeeder.php       la cuenta admin@admin.com
│   ├── CatalogoSeeder.php      asociaciones, escala, tipos de carnet, productos y
│   │                           aranceles, con sus tarifas de SIREB (firstOrCreate)
│   └── BeneficiarioSeeder.php  datos de prueba: NO corre en producción
└── factories/BeneficiarioFactory.php
```

Mientras el núcleo se esté armando, **una columna nueva va DENTRO de la
migración de su tabla** y se rearma la base (`migrate:fresh --seed`), probándolo
antes en una SQLite descartable. Ver la regla 12 de CLAUDE.md.

---

## Configuración (`config/`)

- **`config/jichi.php`** — los ajustes del sistema: SIREB, Ibare, el modo
  estricto de la autorización, la semilla de departamentos. Todo valor que venga
  del `.env` pasa por acá.

  > **Regla que no se rompe nunca:** `env()` solo dentro de `config/`. En el
  > resto del código, `config('jichi.lo_que_sea')`. Con `config:cache` activo,
  > `env()` devuelve `null` fuera de `config/` y el error es silencioso.

- **`config/permission.php`** y **`config/dompdf.php`** — publicados por sus
  paquetes, sin tocar.

---

## Verificación

No hay carpeta `tests/`: las pruebas automáticas se retiraron el 27/09/2026 a
pedido del responsable (commit `7dc0ac6`).

```sh
npx tsc --noEmit        # tipos
./vendor/bin/pint       # formato
npm run build           # que compile
```

> ⚠️ Los tres revisan **tipos y formato**, no las reglas de negocio. Todo cambio
> se verifica **abriendo la pantalla y probando el caso a mano**, incluidos los
> bordes. `tsc` tampoco revisa los nombres de ruta: al quitar una, cruzar contra
> `php artisan route:list --json`.

---

## Documentación (`docs/`)

```
docs/
├── REGLAS-NEGOCIO.md   LA ESPECIFICACIÓN: cuando el código no coincide, manda ella
├── ARQUITECTURA.md     ← EMPEZAR ACÁ. Reemplaza a leer el código
├── MAPA-ARCHIVOS.md    qué hace cada archivo y qué tiene de no obvio
├── MER.md              las tablas, columna por columna, y el porqué
├── ESTRUCTURA.md       este archivo: dónde va un archivo NUEVO
├── GUIA-INERTIA.md     cómo se conectan Laravel y React, paso a paso
├── INSTALACION.md, DOCKER-TECNICO.md   levantar el proyecto
├── PENDIENTES.md       qué falta y qué problemas siguen abiertos
├── NOTAS-CODIGO.md     el porqué largo de una decisión puntual, por archivo
├── modulos/            SIREB, PAGOS, RECIBOS, CARNETS, PERMISOS-OPERATIVOS, PORTAL, IBARE
├── diagramas/          los flujos del pescador y del comercializador (HTML)
└── sesiones/           bitácora: _plantilla.md y MM-AAAA/AAAA-MM-DD.md
```

**Estos archivos existen para no tener que leer el sistema entero.** Si al
terminar un trabajo sabés algo que no está ahí, agregalo.

**Toda sesión de trabajo se registra** en `sesiones/MM-AAAA/AAAA-MM-DD.md`,
copiando `_plantilla.md`: el problema, la tabla de archivos modificados y la
solución con su porqué. El último bloque es el **informe para presentación**, en
lenguaje simple, porque se copia a Word y lo lee gente que no programa.
