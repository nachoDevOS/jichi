<?php

namespace App\Models;

use App\Enums\RolSistema;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'ci', 'email', 'cargo', 'telefono', 'password', 'activo'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /*
     * NO LLEVA HasFactory, y su UserFactory se borró.
     *
     * Las cuentas del sistema no se generan al azar: las crea el administrador
     * desde el panel, y la única que se siembra —admin@admin.com— la escribe
     * UsuarioSeeder con `updateOrCreate`. El único que usaba `User::factory()`
     * era el juego de pruebas, que se eliminó el 14/09/2026.
     *
     * `Beneficiario` sí conserva la suya: DemoSeeder la usa para poblar el
     * padrón de prueba.
     */
    use Auditable, HasRoles, Notifiable, SoftDeletes;

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

    /**
     * Los depósitos que esta persona cargó en ventanilla.
     *
     * OJO: apuntaba a `user_id`, UNA COLUMNA QUE NUNCA EXISTIÓ en `pagos`. La
     * relación estaba rota desde el primer día y no se notaba porque nadie la
     * llamaba —Eloquent no valida el nombre de la columna hasta que se ejecuta
     * la consulta—. Se arregló al agregar `registrado_por`.
     */
    public function pagosRegistrados(): HasMany
    {
        return $this->hasMany(Pago::class, 'registrado_por');
    }

    /**
     * Los depósitos que esta persona controló contra el extracto.
     *
     * Es a propósito que un mismo usuario no pueda aparecer en las dos listas
     * para el mismo pago: quien carga no valida. Ver Pago::puedeValidarlo().
     */
    public function pagosValidados(): HasMany
    {
        return $this->hasMany(Pago::class, 'validado_por');
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
