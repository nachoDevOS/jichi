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

El seeder crea roles, permisos, configuración institucional, las 6 áreas con
sus tipos de trámite y tarifas, y 4 usuarios (uno por rol). Fuera de
producción también carga datos de prueba: 48 solicitantes, 60 trámites en
todos los estados, pagos y documentos emitidos.

### Usuarios sembrados

Contraseña común: la de `JICHI_SEED_PASSWORD` en `.env`
(`password` por defecto). **Cambiar antes de cualquier despliegue.**

| Rol | Correo |
| --- | --- |
| Administrador | admin@admin.com |
| Supervisor | supervisor@beni.gob.bo |
| Operador de Ventanilla | ventanilla1@beni.gob.bo |
| Solo Lectura | consulta@beni.gob.bo |

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

El módulo **Solicitantes** ya está completo y sirve de plantilla comentada para
los otros cuatro.
