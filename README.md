# Jichi

Sistema de recaudación, certificación y credenciales del **Gobierno Autónomo
Departamental del Beni**, Bolivia.

Gestiona el circuito completo de ventanilla: se recepciona un trámite, se cobra
la tasa, se aprueba, se emite el documento en PDF con un código QR, y
cualquier ciudadano puede verificar su autenticidad escaneando ese QR desde la
calle, sin iniciar sesión.

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
| Administrador | Control total: configuración, usuarios, tarifas. |
| Supervisor | Aprueba y rechaza trámites, supervisa la recaudación, exporta reportes. |
| Operador de Ventanilla | Recepciona trámites, cobra, entrega documentos. |
| Solo Lectura | Consulta e informes, sin modificar nada. |

---

## Estado del proyecto

**Funcionando:**

- Inicio de sesión con bitácora de accesos (incluidos los intentos fallidos)
- Panel principal con recaudación, gráficos y alertas de vencimiento
- Módulo de Solicitantes completo (alta, edición, búsqueda, baja, foto)
- Verificación pública de documentos por código QR

**Pendiente:** Trámites, Documentos, Reportes y Configuración. Aparecen en
gris en el menú lateral. La lista de qué falta en cada uno está en
[docs/PENDIENTES.md](docs/PENDIENTES.md).

El módulo **Solicitantes es la plantilla**: está comentado paso a paso para
copiar su patrón en los otros cuatro.

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
