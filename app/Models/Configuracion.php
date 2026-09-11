<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

#[Fillable(['clave', 'valor', 'tipo', 'grupo', 'etiqueta', 'descripcion', 'publico'])]
class Configuracion extends Model
{
    protected $table = 'configuraciones';

    protected function casts(): array
    {
        return [
            'publico' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('configuraciones'));
        static::deleted(fn () => Cache::forget('configuraciones'));
    }

    /**
     * Todas las configuraciones, cacheadas y ya convertidas a su tipo PHP.
     *
     * @return array<string, mixed>
     */
    public static function todas(): array
    {
        return Cache::rememberForever('configuraciones', fn (): array => static::query()
            ->get()
            ->mapWithKeys(fn (self $c): array => [$c->clave => $c->valorTipado()])
            ->all());
    }

    public static function obtener(string $clave, mixed $porDefecto = null): mixed
    {
        return static::todas()[$clave] ?? $porDefecto;
    }

    public static function guardar(string $clave, mixed $valor): void
    {
        static::query()->where('clave', $clave)->update([
            'valor' => is_array($valor) ? json_encode($valor) : (string) $valor,
        ]);

        Cache::forget('configuraciones');
    }

    public function valorTipado(): mixed
    {
        return match ($this->tipo) {
            'number' => is_numeric($this->valor) ? $this->valor + 0 : null,
            'boolean' => filter_var($this->valor, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode((string) $this->valor, true),
            default => $this->valor,
        };
    }
}
