# Jichi

Sistema de **carnets, autorizaciones de pesca, permisos de faena y guías de
transporte** del **Gobierno Autónomo Departamental del Beni** (SEDAG), Bolivia.

Gestiona el circuito de ventanilla de la Unidad de Pesca. Cada documento se
registra en Jichi, **se paga en Recaudaciones (SIREB)** y, cuando SIREB valida el
pago, Jichi lo aprueba solo y emite el recibo. Cualquier inspector puede
verificar un documento escaneando su QR, desde la calle y sin iniciar sesión.

### El dominio, en corto

```
                    BENEFICIARIO (C.I. único)
        pescador ┌──────────┴──────────┐ comercializador
                 ▼                     ▼
 AUTORIZACIÓN DE PESCA PARA      CARNET (comercializador)
 APROVECHAMIENTO PESQUERO              │
 (un cupo en kg)                       ▼
          │                     GUÍA ÚNICA DE TRANSPORTE
          ▼                     (una por traslado, 5 días)
   CARNET (pescador)
          │
          ▼
   PERMISO DE FAENA (una por salida, 30 días; reserva y descuenta kilos)

 PENDIENTE ──(SIREB: pagada)──▶ APROBADO  + RECIBO
     └────(SIREB: vencida, sin pago)──▶ NO PAGADO
```

- **Una autorización vigente por persona y un carnet vigente por actividad.**
- **El pago se carga y se valida en SIREB.** Jichi puede cargarlo («Cargar
  pago»), nunca validarlo; pregunta con «Verificar pago» y cada 10 minutos.
- **Un trámite con un pago cargado en SIREB no se elimina.**

La especificación completa está en
[docs/REGLAS-NEGOCIO.md](docs/REGLAS-NEGOCIO.md) y la arquitectura en
[docs/ARQUITECTURA.md](docs/ARQUITECTURA.md).

---

## Stack

| Capa | Tecnología |
| --- | --- |
| Backend | Laravel 13 · PHP 8.3 |
| Frontend | React 19 · Inertia 2 · TypeScript · Tailwind 4 |
| Base de datos | PostgreSQL 18 (también corre en SQLite) |
| Roles y permisos | spatie/laravel-permission |
| PDF | barryvdh/laravel-dompdf |
| QR | bacon/bacon-qr-code (pintado con gd) |
| Rutas en JS | tightenco/ziggy |
| Gráficos | recharts |

**No hay API REST.** Inertia conecta Laravel con React directamente: el
controlador devuelve datos y React los recibe como props. Nunca se escribe
`fetch()` ni `axios`. El detalle está en [docs/GUIA-INERTIA.md](docs/GUIA-INERTIA.md).

---

## Arranque rápido

```sh
composer install
npm install

cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link

composer run dev      # servidor + vite + cola + logs, todo junto
```

Abrir <http://localhost:8000> y entrar con `admin@admin.com`. La contraseña es
la de `JICHI_SEED_PASSWORD` en el `.env` (por defecto `password`).

La guía completa de instalación, incluida la de PostgreSQL en Windows, está en
[docs/INSTALACION.md](docs/INSTALACION.md).

---

## Las tres caras del sistema

Todo está separado en tres mitades que no se mezclan nunca:

| | Panel | Público | Portal del beneficiario |
| --- | --- | --- | --- |
| **Quién entra** | Funcionarios de la Gobernación | Cualquier ciudadano | El beneficiario, con su C.I. |
| **URLs** | `/panel/...` | `/` y `/verificar/...` | `/mi-cuenta/...` |
| **Rutas** | `routes/panel.php` | `routes/publico.php` | `routes/portal.php` |
| **Controladores** | `Http/Controllers/Panel/` | `Http/Controllers/Publico/` | `Http/Controllers/Portal/` |
| **Pantallas** | `pages/panel/` | `pages/publico/` | `pages/portal/` |

La pantalla pública se abre desde un teléfono en el río, con mala señal, y no
puede mostrar ni una pista de la estructura interna. El portal es de solo
lectura: papeles, trámites en curso, recibos y datos.

---

## Roles

Los define el enum `app/Enums/RolSistema.php`, que es la única fuente de verdad
de qué puede hacer cada quién.

| Rol | Qué hace |
| --- | --- |
| Administrador | Control total: beneficiarios, autorizaciones, carnets, faenas, guías, recibos y catálogos. |

**Por ahora hay un solo rol, y es a propósito.** Supervisor, operador de
ventanilla y solo-lectura se agregarán cuando la unidad defina quién hace qué;
inventar roles que nadie usa solo obliga a mantenerlos.

Lo que **sí** queda armado es la lista de permisos, y cada ruta exige el suyo
(`->middleware('permiso:carnets.crear')`). Esa parte no se saca aunque hoy el
único rol los tenga todos: el día que aparezca el segundo rol, se agrega un
`case` al enum y las rutas ya están protegidas.

---

## Estado del proyecto

**Funcionando:**

- Inicio de sesión con bitácora de accesos, y login con Ibare (OAuth2)
- Panel principal: trabajo pendiente, vigentes, recaudación y avisos
- **Beneficiarios** — alta, edición, búsqueda, baja lógica, fotografía, ficha por actividad
- **Autorización de Pesca para Aprovechamiento Pesquero** — cupo en kg por la escala oficial, revocación
- **Carnets** — pescador y comercializador, revocación, reposición, impresión del plástico
- **Permisos de faena y guías de transporte** — reserva y descuento de kilos, impresión
- **Cobro en SIREB** — liquidación, «Cargar pago», «Verificar pago», «No pagado», recibo oficial
- **Catálogos** — asociaciones, escala, tipos de carnet, productos y aranceles, con tarifas de SIREB
- **Portal del beneficiario** (`/mi-cuenta`) y **verificación pública** por código QR

**Pendiente:** Reportes y Configuración. Aparecen en gris en el menú lateral. La
lista de qué falta está en [docs/PENDIENTES.md](docs/PENDIENTES.md).

El módulo **Beneficiarios es la plantilla**: está comentado paso a paso para
copiar su patrón en los que falten.

---

## Documentación

| Archivo | Para qué |
| --- | --- |
| [docs/REGLAS-NEGOCIO.md](docs/REGLAS-NEGOCIO.md) | La especificación: manda sobre el código |
| [docs/ARQUITECTURA.md](docs/ARQUITECTURA.md) | Cómo está construido. Empezar acá |
| [docs/INSTALACION.md](docs/INSTALACION.md) | Levantar el entorno de desarrollo |
| [docs/ESTRUCTURA.md](docs/ESTRUCTURA.md) | Qué hay en cada carpeta y dónde va cada archivo nuevo |
| [docs/GUIA-INERTIA.md](docs/GUIA-INERTIA.md) | Cómo Laravel habla con React, explicado desde cero |
| [docs/PENDIENTES.md](docs/PENDIENTES.md) | Qué falta construir y en qué orden |

---

## Comandos útiles

```sh
./vendor/bin/pint             # formatea el PHP
npx tsc --noEmit              # revisa los tipos de TypeScript
npm run build                 # compila el frontend para producción
php artisan migrate:fresh --seed   # rehace la base desde cero
```
