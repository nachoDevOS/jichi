<?php

namespace App\Http\Requests\Panel;

use App\Enums\RolSistema;
use App\Models\Rol;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Reglas para crear y editar un rol desde Seguridad › Roles.
 */
class GuardarRolRequest extends FormRequest
{
    /** El permiso ya lo revisa el middleware de la ruta. */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['nombre' => trim((string) $this->input('nombre'))]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rol = $this->route('rol');

        return [
            'nombre' => [
                'required', 'string', 'max:60',
                // Sin distinguir mayúsculas: «Operador» y «operador» se verían iguales.
                function (string $campo, string $valor, Closure $falla) use ($rol): void {
                    $buscado = mb_strtolower($valor);

                    foreach (RolSistema::cases() as $r) {
                        if (in_array($buscado, [$r->value, mb_strtolower($r->etiqueta())], true)) {
                            $falla('Ese nombre lo usa un rol del sistema.');

                            return;
                        }
                    }

                    $repetido = Rol::where('guard_name', 'web')
                        ->when($rol instanceof Rol, fn ($q) => $q->whereKeyNot($rol->id))
                        ->pluck('name')
                        ->contains(fn (string $n) => mb_strtolower($n) === $buscado);

                    if ($repetido) {
                        $falla('Ya existe un rol con ese nombre.');
                    }
                },
            ],
            'permisos' => ['required', 'array', 'min:1'],
            'permisos.*' => ['string', 'distinct', Rule::in(RolSistema::todosLosPermisos())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'permisos.required' => 'Marque al menos un permiso.',
            'permisos.min' => 'Marque al menos un permiso.',
            'permisos.*.in' => 'Uno de los permisos marcados no existe en el sistema.',
        ];
    }
}
