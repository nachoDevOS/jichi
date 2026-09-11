<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'user_id',
    'evento',
    'auditable_type',
    'auditable_id',
    'valores_anteriores',
    'valores_nuevos',
    'descripcion',
    'ip',
    'user_agent',
    'url',
])]
class Auditoria extends Model
{
    protected $table = 'auditorias';

    protected function casts(): array
    {
        return [
            'valores_anteriores' => 'array',
            'valores_nuevos' => 'array',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeDe(Builder $query, Model $modelo): Builder
    {
        return $query->where('auditable_type', $modelo::class)
            ->where('auditable_id', $modelo->getKey());
    }
}
