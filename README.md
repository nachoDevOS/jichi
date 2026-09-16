# Jichi

Sistema de gestión de **carnets, rubros y trámites** del **Gobierno Autónomo
Departamental del Beni**, Bolivia.

Gestiona el circuito completo de ventanilla: una persona pide que se le habilite
una actividad, presenta sus papeles y sus depósitos, un supervisor aprueba, y el
carnet queda habilitado para ese rubro. Cualquier inspector puede verificar su
autenticidad escaneando el QR impreso, desde la calle y sin iniciar sesión.

### La regla que ordena todo el sistema

> **Una persona tiene como máximo UN carnet por gestión (año).**

De ahí salen los dos únicos tipos de trámite, y el sistema los decide solo:

| Situación de la persona | Tipo de trámite | Qué pasa |
| --- | --- | --- |
| No tiene carnet de este año | **Emisión inicial** | Se crea el carnet y se habilita el rubro pedido |
| Ya tiene carnet de este año | **Adición de rubro** | Se reutiliza ese carnet y se le suma el rubro |

El operador de ventanilla nunca elige el tipo: lo determina el servidor mirando
la base, con la fila bloqueada, dentro de la misma transacción que registra el
trámite. Ver `app/Services/SolicitudCarnetService.php`.

---

## Stack

| Capa | Tecnología |
| --- | --- |
| Backend | Laravel 13 · PHP 8.3 |
| Frontend | React 19 · Inertia 2 · TypeScript · Tailwind 4 |
| Base de datos | PostgreSQL 18 (también corre en SQLite) |
| Roles y permisos | spatie/laravel-permission |
| PDF | barryvdh/laravel-dompdf |
| Excel | maatwebsite/excel |
| QR | simplesoftwareio/simple-qrcode |
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

## Las dos caras del sistema

Todo está separado en dos mitades que no se mezclan nunca:

| | Panel de administración | Vista pública |
| --- | --- | --- |
| **Quién entra** | Funcionarios de la Gobernación | Cualquier ciudadano |
| **Sesión** | Obligatoria | No pide login |
| **URLs** | `/panel/...` | `/verificar/...` |
| **Rutas** | `routes/panel.php` | `routes/publico.php` |
| **Controladores** | `app/Http/Controllers/Panel/` | `app/Http/Controllers/Publico/` |
| **Pantallas** | `resources/js/pages/panel/` | `resources/js/pages/publico/` |
| **Componentes** | `resources/js/components/panel/` | `resources/js/components/publico/` |
| **Marco visual** | `layouts/layout-panel.tsx` | `layouts/layout-publico.tsx` |

Esa división es deliberada: la pantalla pública se abre desde un teléfono en el
río, con mala señal, y no puede mostrar ni una pista de la estructura interna.
Con carpetas separadas nada del panel se filtra ahí por accidente.

---

## Roles

Los define el enum `app/Enums/RolSistema.php`, que es la única fuente de verdad
de qué puede hacer cada quién.

| Rol | Qué hace |
| --- | --- |
| Administrador | Control total: beneficiarios, trámites, pagos, carnets, rubros y configuración. |

**Por ahora hay un solo rol, y es a propósito.** Supervisor, operador de
ventanilla y solo-lectura se agregarán cuando la unidad defina quién firma qué;
inventar roles que nadie usa solo obliga a mantenerlos.

Lo que **sí** queda armado es la lista de permisos, y cada ruta exige el suyo
(`->middleware('permiso:tramites.aprobar')`). Esa parte no se saca aunque hoy el
único rol los tenga todos: el día que aparezca el segundo rol, se agrega un
`case` al enum y las rutas ya están protegidas.

---

## Estado del proyecto

**Funcionando:**

- Inicio de sesión con bitácora de accesos (incluidos los intentos fallidos)
- Panel principal con recaudación, carnets por rubro y aviso de cierre de gestión
- **Beneficiarios** — alta, edición, búsqueda, baja lógica, fotografía
- **Trámites** — el circuito completo: solicitud → aprobación → impresión → entrega
- **Pagos** — uno o varios depósitos por trámite, con su boleta escaneada
- **Carnets** — consulta, suspensión de un rubro suelto, anulación
- **Rubros** — catálogo de actividades con su tarifa vigente
- Verificación pública por código QR, protegida con firma de validación

**Pendiente:** Reportes y Configuración. Aparecen en gris en el menú lateral. La
lista de qué falta está en [docs/PENDIENTES.md](docs/PENDIENTES.md).

El módulo **Beneficiarios es la plantilla**: está comentado paso a paso para
copiar su patrón en los que falten.

---

## Documentación

| Archivo | Para qué |
| --- | --- |
| [docs/INSTALACION.md](docs/INSTALACION.md) | Levantar el entorno de desarrollo |
| [docs/ESTRUCTURA.md](docs/ESTRUCTURA.md) | Qué hay en cada carpeta y dónde va cada archivo nuevo |
| [docs/GUIA-INERTIA.md](docs/GUIA-INERTIA.md) | Cómo Laravel habla con React, explicado desde cero |
| [docs/PENDIENTES.md](docs/PENDIENTES.md) | Qué falta construir y en qué orden |

---

## Comandos útiles

```sh
php artisan test              # las pruebas automáticas
./vendor/bin/pint             # formatea el PHP
npx tsc --noEmit              # revisa los tipos de TypeScript
npm run build                 # compila el frontend para producción
php artisan migrate:fresh --seed   # rehace la base desde cero
```
