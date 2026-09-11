<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'email', 'evento', 'ip', 'user_agent', 'session_id'])]
class Acceso extends Model
{
    protected $table = 'accesos';

    /** La tabla solo registra el instante del evento; no se actualiza nunca. */
    public const UPDATED_AT = null;

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
