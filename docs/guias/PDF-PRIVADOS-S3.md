# PDF privados en S3 con enlaces firmados — guía para llevar a otro sistema

> Escrito el 23/10/2026 a partir de lo que se hizo en Jichi. Sirve para cualquier
> sistema **Laravel** que guarde documentos con datos personales en S3 o en
> DigitalOcean Spaces. Los nombres de clases son de ejemplo: cambialos por los tuyos.

---

## 1. La idea en una línea

El PDF se guarda **privado** en S3. Nadie puede abrirlo con la dirección sola. El
sistema revisa sesión, permiso y estado, y recién ahí entrega un **enlace firmado**
que vence solo.

```
https://bucket.sfo3.digitaloceanspaces.com/carpeta/archivo.pdf
  → 403 Acceso denegado            (privado: la dirección sola no sirve)

https://bucket.sfo3.digitaloceanspaces.com/carpeta/archivo.pdf?X-Amz-Date=…&X-Amz-Expires=1800&X-Amz-Signature=…
  → 200, abre el PDF               (firmado: vale 30 minutos)
```

## 2. Cómo funciona la firma

- **No se guarda en ningún lado**, ni en S3 ni en la base. Se calcula en el momento.
- La calcula el SDK de Amazon que trae Laravel (`aws/aws-sdk-php`):
  `HMAC-SHA256(clave secreta, ruta + fecha + vencimiento + host)`.
- La clave secreta (`AWS_SECRET_ACCESS_KEY`) está en el `.env` y **nunca viaja**:
  en la URL va solo el resultado de la cuenta.
- S3 tiene la misma clave, repite la cuenta y compara. Si coincide y no pasó la
  hora, entrega el archivo.
- Firmar **no consulta a S3**: es una cuenta local de milisegundos. Se puede firmar
  una URL por fila de un listado sin hacerlo más lento.

**Qué pasa si alguien modifica la URL** (medido contra Spaces):

| Cambio | Respuesta |
| --- | --- |
| Ninguno | 200 |
| `X-Amz-Expires` (estirar o acortar) | 403 |
| `X-Amz-Date` | 403 |
| Una letra de la firma | 403 |
| Otro nombre de archivo con la misma firma | 403 |
| Sacar la firma | 403 |

> En el primer intento de prueba, una URL con `X-Amz-Expires` alterado respondió
> 200. No se pudo reproducir en las repeticiones (todas 403). La sospecha es un caché
> del borde de Spaces que sirvió la respuesta de la URL recién pedida. Quien lo
> aprovecharía ya tenía un enlace válido del mismo archivo.

## 3. Quién ve el PDF

| Quién | Por dónde |
| --- | --- |
| Funcionario | Botón «Imprimir» del panel: sesión + permiso + estado del documento |
| Dueño del documento | Su portal, solo sus papeles, como descarga |
| Público | **No ve el PDF**: verifica con el QR o el código en una página pública con datos recortados |
| Alguien con un enlace copiado | Hasta que vence (30 min); después, 403 |

---

## 4. Implementación paso a paso

### 4.1 La tabla: una fila por PDF emitido

```php
Schema::create('documentos_emitidos', function (Blueprint $table) {
    $table->id();
    $table->morphs('emitible');                 // el documento dueño (carnet, recibo, …)
    $table->string('disco', 20);                // s3 o local
    $table->string('ruta');                     // dentro del disco; NUNCA la URL
    $table->char('sha256', 64);                 // huella: detecta si el archivo cambió
    $table->unsignedInteger('bytes');
    $table->string('motivo')->nullable();       // por qué se regeneró, si pasó
    $table->timestamp('generado_en');
    $table->timestamps();
    $table->softDeletes();
});

// Un PDF vigente por documento: el anterior queda dado de baja como historial.
DB::statement('CREATE UNIQUE INDEX documentos_emitidos_uno_por_papel
    ON documentos_emitidos (emitible_type, emitible_id) WHERE deleted_at IS NULL');
```

Se guarda la **ruta**, no la URL: la URL firmada se arma cada vez y vence.

### 4.2 Subir el archivo como PRIVADO

```php
public function pdf(string $contenido, string $carpeta): array
{
    $disco = config('filesystems.default') === 's3' ? 's3' : 'local';
    // Nombre aleatorio: nadie adivina la dirección de otro documento.
    $ruta = $carpeta.'/'.date('FY').'/'.Str::random(20).time().'.pdf';

    Storage::disk($disco)->put($ruta, $contenido, 'private');   // ← la clave de todo

    return ['disco' => $disco, 'ruta' => $ruta];
}
```

Sin disco s3, va al disco `local` (`storage/app/private`), **nunca** al `public`.

### 4.3 El enlace firmado

```php
public static function enlace(?Model $documento, int $minutos = 30): ?array
{
    $emitido = $documento?->relationLoaded('documentoEmitido')
        ? $documento->getRelation('documentoEmitido')       // en listados: sin N+1
        : $documento?->documentoEmitido()->first();

    if ($emitido?->disco !== 's3') {
        return null;
    }

    $vence = now()->addMinutes($minutos);

    return [
        'url' => Storage::disk('s3')->temporaryUrl($emitido->ruta, $vence, [
            // Que el navegador lo MUESTRE como PDF y con un nombre legible.
            'ResponseContentType' => 'application/pdf',
            'ResponseContentDisposition' => 'inline; filename="documento-000123.pdf"',
        ]),
        'vence' => $vence->toIso8601String(),
    ];
}
```

Para una descarga, `attachment` en vez de `inline`.

### 4.4 La ruta del sistema: el respaldo que controla

Los botones abren el enlace firmado directo, pero la ruta del sistema existe igual.
Cubre tres casos: el PDF todavía no se generó, el enlace venció porque la pantalla
quedó abierta, y alguien escribe la dirección a mano.

```php
// routes: el permiso va EN LA RUTA, no solo escondiendo el botón.
Route::get('/carnets/{carnet}/imprimir', [CarnetImpresionController::class, 'imprimir'])
    ->middleware('permiso:carnets.imprimir')->name('carnets.imprimir');

public function imprimir(Carnet $carnet, bool $descarga = false)
{
    if (! $carnet->puedeImprimirse()) {          // revocado / sin aprobar: no sale
        return back()->with('error', 'Este carnet no se puede imprimir.');
    }

    return app(DocumentoEmitidoService::class)->respuesta($carnet, $descarga);
}

// En el servicio:
public function respuesta(Model $documento, bool $descarga = false)
{
    $emitido = $this->vigenteConArchivo($documento);   // si no hay, lo genera y guarda

    if ($emitido->disco === 's3') {
        // Firma corta: se usa en el acto.
        return redirect()->away(Storage::disk('s3')->temporaryUrl($emitido->ruta, now()->addMinutes(5), [
            'ResponseContentType' => 'application/pdf',
            'ResponseContentDisposition' => ($descarga ? 'attachment' : 'inline').'; filename="…"',
        ]));
    }

    return response(Storage::disk('local')->get($emitido->ruta), 200, [
        'Content-Type' => 'application/pdf',
        'Content-Disposition' => ($descarga ? 'attachment' : 'inline').'; filename="…"',
    ]);
}
```

### 4.5 La pantalla: mandar el enlace y abrirlo directo

El controlador manda el enlace por fila:

```php
->with(['documentoEmitido'])                      // UNA consulta para todo el listado
->through(fn ($c) => [
    'id' => $c->id,
    'pdf_enlace' => DocumentoEmitidoService::enlace($c),
]);
```

En React, el `<a>` conserva el `href` del sistema (el respaldo) y abre S3 si el
enlace sigue vigente:

```tsx
export type EnlaceDirecto = { url: string; vence: string };

// Un minuto de margen: que no venza mientras el visor lo abre.
function enlaceVigente(d?: EnlaceDirecto | null): boolean {
    return !!d && Date.parse(d.vence) - Date.now() > 60_000;
}

export function abrirDirecto(d?: EnlaceDirecto | null) {
    return (e: React.MouseEvent<HTMLAnchorElement>) => {
        if (enlaceVigente(d)) {
            e.preventDefault();
            window.open(d!.url, '_blank', 'noreferrer');
        }
        // si no: sigue el href → la ruta del sistema lo genera o firma uno nuevo
    };
}

<a href={route('carnets.imprimir', c.id)} target="_blank" rel="noreferrer"
   onClick={abrirDirecto(c.pdf_enlace)}>Imprimir</a>
```

| Caso | Qué abre el botón |
| --- | --- |
| PDF guardado y enlace vigente | S3 directo |
| Sin PDF todavía | La ruta del sistema: lo genera, lo guarda y redirige |
| Enlace vencido (pantalla abierta más de media hora) | La ruta del sistema firma uno nuevo **del mismo archivo** |

### 4.6 Generarlo una sola vez: al aprobar

```php
use function Illuminate\Support\defer;

DB::transaction(fn () => $servicio->aprobar($documento));
// Fuera de la transacción y después de responder: subir a S3 no demora al usuario.
defer(fn () => $documentos->emitirAlAprobar($documento->refresh()));
```

Una transacción **no deshace** lo escrito en S3. Por eso el archivo se sube **antes**
de abrir la transacción que escribe la fila, y si la fila falla, se borra el archivo.

---

## 5. Comprobarlo (no darlo por bueno leyendo)

```sh
# 1. La dirección pelada tiene que dar 403
curl -s -o /dev/null -w '%{http_code}\n' "https://bucket…/carpeta/archivo.pdf"

# 2. El enlace firmado, 200; y alterado, 403
URL=$(php artisan tinker --execute="echo App\Services\DocumentoEmitidoService::enlace(App\Models\Carnet::find(1))['url'];" < /dev/null | tail -1)
curl -s -o /dev/null -w '%{http_code}\n' "$URL"
curl -s -o /dev/null -w '%{http_code}\n' "$(echo "$URL" | sed 's/X-Amz-Expires=1800/X-Amz-Expires=999999/')"

# 3. Que el botón abra S3 de entrada: en el navegador, la primera URL de la pestaña
#    nueva tiene que ser la de digitaloceanspaces.com, no la del sistema.
```

En las pruebas automáticas, `Storage::fake('s3')`. Ojo: en Windows el disco falso
contesta siempre `public` a `getVisibility()`. Ahí se comprueba que el archivo **no
esté en el disco público**, en vez de preguntar la visibilidad.

---

## 6. Lo que hay que cuidar

- ⚠️ **`AWS_SECRET_ACCESS_KEY` es la llave de todo.** Con ella se firma cualquier
  archivo, y además se sube y se borra en el bucket. Solo en el `.env` del servidor:
  nunca en git, en el código ni en el frontend. Si se filtra, se genera una nueva
  en el panel de DigitalOcean (Spaces Keys) y la vieja deja de servir.
- **`env()` solo dentro de `config/`.** Con `config:cache`, `env()` devuelve null
  fuera de `config/` y el sistema cree que no hay s3, sin avisar.
- **El bucket tiene que aceptar ACL** (`private` / `public-read` por archivo). En
  Spaces viene así. En AWS S3 nuevo, las ACL vienen apagadas.
- **El vencimiento máximo** de una firma es de 7 días (604.800 s). Más, y S3 la rechaza.
- **El reloj del servidor** tiene que estar en hora (NTP). La firma lleva la fecha:
  un servidor atrasado firma enlaces que S3 da por vencidos o por futuros.
- **Revocar no borra el enlace ya entregado.** Un enlace firmado vale hasta que
  vence aunque el documento se revoque después. Por eso la vida del enlace es corta
  (30 min en pantalla, 5 min en la redirección).
- **Imprimir lo guardado, no rearmarlo:** el PDF queda igual a como se emitió. Una
  vista previa con marca «NO VÁLIDO» sí se arma al vuelo, y no se guarda.

## 7. Público vs. privado: por qué privado

| | Público | Privado + firma |
| --- | --- | --- |
| Dirección | Fija, sirve para siempre | Vence (30 min) |
| Enlace filtrado (WhatsApp, correo) | Abierto para siempre | Muere solo |
| Documento revocado | El enlace viejo sigue mostrando el PDF | Deja de entregarse al vencer |
| Cortar el acceso | Hay que borrar el archivo | No hace falta: vence |
| Para el usuario del sistema | Igual | Igual: el botón siempre abre |

Para fotos sueltas, público puede alcanzar. Para un documento con nombre, C.I.,
foto y dirección juntos, privado.
