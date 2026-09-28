# Jichi en Docker

Imagen para correr el sistema en un contenedor —hoy en local con Docker
Desktop, mañana en Coolify—. Para levantarlo paso a paso: [docker.md](../docker.md), en la raíz. Tres archivos en la raíz:

| Archivo | Qué hace |
| --- | --- |
| `Dockerfile` | Arma la imagen: dependencias de PHP, assets de Vite y la imagen final |
| `.dockerignore` | Lo que NO entra en la imagen: `.env`, `vendor`, `node_modules`, `storage`, `public/hot` |
| `docker-compose.yml` | Uso local: la app + PostgreSQL 18, con volúmenes para la base y los adjuntos |

## Uso local

```sh
# Docker Desktop en Linux usa su propio contexto; el socket del sistema da «permission denied»
export DOCKER_CONTEXT=desktop-linux

docker compose up -d --build        # arma y levanta; migra solo al arrancar
docker compose exec app php artisan db:seed --force                           # roles, configuración, admin
docker compose exec app php artisan db:seed --class=CatalogoSeeder --force    # catálogos de plantilla
```

Queda en **http://localhost:8090**, con `admin@admin.com` y la contraseña de
`JICHI_SEED_PASSWORD` (por defecto `password`). PostgreSQL sale en el puerto
**5433** del equipo, usuario, clave y base `jichi`.

| Variable (en el `.env` del proyecto) | Para |
| --- | --- |
| `APP_KEY` | Obligatoria: compose la toma del `.env` y la pasa al contenedor |
| `DOCKER_APP_PORT` | Otro puerto si 8090 está ocupado (8080 ya lo estaba) |
| `DOCKER_APP_DEBUG` | `false` para ver las páginas de error como en producción |
| `JICHI_SEED_PASSWORD` | La clave del administrador sembrado |

`docker compose down` apaga; `down -v` borra además la base y los adjuntos.

## Decisiones

- **Imagen `serversideup/php:8.3-fpm-nginx`.** Trae nginx, php-fpm, `pdo_pgsql`,
  `zip` y opcache. Con `AUTORUN_ENABLED` corre al arrancar `storage:link`,
  `migrate --force` y `optimize`. Escucha en el **8080** del contenedor, sin root.
- **Se suman `gd`, `intl` y `bcmath`.** gd sale con freetype, jpeg y webp: sin
  freetype revienta el texto girado de la guía (`TextoVertical`), y sin webp se
  rechazan las fotos webp. Imagick no hace falta (ver CLAUDE.md).
- **El `.env` no entra en la imagen.** Toda la configuración viaja como variable
  de entorno; `config:cache` corre al arrancar, con esas variables.
- **`APP_ENV=production` también en local.** Con otro valor el seeder vacía el
  dominio y siembra el padrón de prueba con Faker, que no está instalado
  (`--no-dev`). Por eso los catálogos se siembran aparte.
- **Adjuntos en disco local, en el volumen `adjuntos`** (`storage/app`). Para
  usar S3/Spaces se pasan `FILESYSTEM_DISK=s3` y las `AWS_*`.
- **Límites de subida:** `upload_max_filesize=4M`, `post_max_size=16M`. El
  carnet sube cédula, aval y foto en un solo POST.
- **`storage/fonts` se crea en la imagen:** ahí DomPDF escribe su caché de fuentes.

## Trampas

- **El build baja las fuentes de Bunny** (plugin `fonts` de `vite.config.ts`).
  Un corte de DNS durante el build da `getaddrinfo` en `npm run build`; se
  vuelve a correr.
- **`public/hot` en la imagen rompería todo:** la página pediría los assets al
  servidor de Vite. Está en `.dockerignore`.

## Para Coolify (pendiente)

- `trustProxies(at: '*')` en `bootstrap/app.php`: detrás de Traefik, sin eso
  Laravel arma URLs `http://` y el navegador bloquea los assets.
- `APP_URL` con el dominio real en `https`: el QR impreso sale de ahí.
- PostgreSQL como recurso de Coolify, con respaldos programados.
- Persistir `storage/app` o usar S3/MinIO.
- Congelar las migraciones antes de cargar datos reales (regla 12).
