<?php

namespace App\Models;

use App\Enums\RolSistema;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['beneficiario_id', 'name', 'ci', 'mamore_id', 'email', 'cargo', 'telefono', 'password', 'activo', 'debe_cambiar_password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /*
     * NO LLEVA HasFactory, y su UserFactory se borró.
     */
    use Auditable, HasRoles, Notifiable, SoftDeletes;

    /** Ver PermisoFaena::$attributes: los defaults de la base no llegan al create(). */
    protected $attributes = [
        'activo' => true,
        'debe_cambiar_password' => false,
    ];

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
            'debe_cambiar_password' => 'boolean',
        ];
    }

    public function accesos(): HasMany
    {
        return $this->hasMany(Acceso::class)->latest();
    }

    /** Solo en las cuentas del portal. */
    public function beneficiario(): BelongsTo
    {
        return $this->belongsTo(Beneficiario::class);
    }

    /** Con beneficiario = cuenta del portal: solo /mi-cuenta, nunca el panel. */
    public function esBeneficiario(): bool
    {
        return $this->beneficiario_id !== null;
    }

    public function esAdministrador(): bool
    {
        return $this->hasRole(RolSistema::Administrador->value);
    }

    /** El personal del SEDAG: toda lista de usuarios del panel parte de acá. */
    public function scopeFuncionarios(Builder $query): Builder
    {
        return $query->whereNull($this->qualifyColumn('beneficiario_id'));
    }
}
