# Jichi — Instalación en desarrollo

Sistema de recaudación, certificación y credenciales del Gobierno Autónomo
Departamental del Beni.

## Stack

| Capa | Tecnología |
| --- | --- |
| Backend | Laravel 13.17 · PHP 8.3 |
| Frontend | React 19 · Inertia 2 · TypeScript · Tailwind 4 |
| Base de datos | PostgreSQL 18 |
| Roles y permisos | spatie/laravel-permission |
| PDF | barryvdh/laravel-dompdf |
| Excel | maatwebsite/excel |
| QR | simplesoftwareio/simple-qrcode |
| Rutas en JS | tightenco/ziggy |
| Gráficos | recharts |
| Toasts | sonner |

## 1. PHP: driver de PostgreSQL

Ya está habilitado en `C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.ini`
(`extension=pdo_pgsql` y `extension=pgsql`). El respaldo del archivo original
quedó como `php.ini.bak-jichi`.

Verificar:

```sh
php -r "echo implode(', ', PDO::getAvailableDrivers());"
# mysql, pgsql, sqlite, sqlsrv
```

## 2. Base de datos

### Estado actual: PostgreSQL 18

El entorno corre sobre la base `jichi` en el PostgreSQL local, ya migrada
y sembrada. Configuración en `.env`:

```ini
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=jichi
DB_USERNAME=postgres
DB_PASSWORD=<contraseña local>
```

Si hay que recrear la base desde cero:

```sh
"C:\Program Files\PostgreSQL\18\bin\createdb.exe" -U postgres -h 127.0.0.1 jichi
php artisan migrate:fresh --seed
```

### Volver a SQLite

Comentar el bloque `pgsql` en `.env` y dejar `DB_CONNECTION=sqlite`, luego
`php artisan migrate:fresh --seed`. No hay que tocar código: lo específico de
cada motor está aislado en `app/Support/Sql.php` (operador `ILIKE` vs `LIKE`,
agrupación por mes).

## 3. Migrar y sembrar

```sh
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan storage:link
```

El seeder crea el rol `administrador` con todos sus permisos, la configuración
institucional, los dos rubros del catálogo —Pescador y Comercializador— y la
cuenta `admin@admin.com`.

Fuera de producción carga además datos de prueba, a propósito POCOS: 16
beneficiarios (4 de ellos sin carnet), 12 carnets y 15 trámites. No se siembran
al azar sino siguiendo un guion fijo, de modo que estén representadas todas las
situaciones que el sistema tiene que poder mostrar —aprobado, pendiente con pago
parcial, rechazado, rubro suspendido, carnet anulado, carnet de la gestión
anterior— una vez cada una. Ver `database/seeders/DemoSeeder.php`.

### Usuarios sembrados

Contraseña común: **`password`**. Es el valor por defecto de
`jichi.password_semilla`, y **no está en `.env`**: si hace falta otra, se agrega
ahí la línea `JICHI_SEED_PASSWORD` y la toma. **Cambiar antes de cualquier
despliegue.**

| Rol | Correo |
| --- | --- |
| Administrador | admin@admin.com |

Por ahora hay una sola cuenta y un solo rol. Supervisor, operador de ventanilla y
solo-lectura se agregarán cuando la unidad defina quién firma qué; los permisos
que van a usar ya están repartidos por bloque en `app/Enums/RolSistema.php`.

## 4. Levantar

```sh
composer run dev     # servidor + vite + cola + logs
# o por separado:
php artisan serve
npm run dev
```

## Estructura del proyecto

El mapa completo de carpetas —qué hay en cada una y dónde va un archivo nuevo—
está en [ESTRUCTURA.md](ESTRUCTURA.md).

Para entender cómo Laravel se comunica con React en este sistema, empezar por
[GUIA-INERTIA.md](GUIA-INERTIA.md).

## Qué falta

Los módulos Trámites, Documentos, Reportes y Configuración todavía no
están construidos: aparecen en gris en el menú lateral. El detalle de qué
implica cada uno está en [PENDIENTES.md](PENDIENTES.md).

El módulo **Beneficiarios** ya está completo y sirve de plantilla comentada para
los otros cuatro.
