<?php

namespace App\Models;

use App\Enums\RolSistema;
use App\Traits\Auditable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'ci', 'email', 'cargo', 'telefono', 'password', 'activo'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasFactory, HasRoles, Notifiable, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'ultimo_acceso_at' => 'datetime',
            'password' => 'hashed',
            'activo' => 'boolean',
        ];
    }

    public function tramitesRecepcionados(): HasMany
    {
        return $this->hasMany(Tramite::class, 'user_id');
    }

    public function pagosRegistrados(): HasMany
    {
        return $this->hasMany(Pago::class, 'user_id');
    }

    public function accesos(): HasMany
    {
        return $this->hasMany(Acceso::class)->latest();
    }

    public function esAdministrador(): bool
    {
        return $this->hasRole(RolSistema::Administrador->value);
    }

    public function puedeAprobar(): bool
    {
        return $this->hasAnyRole([
            RolSistema::Administrador->value,
            RolSistema::Supervisor->value,
        ]);
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }
}
