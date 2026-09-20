<?php

namespace App\Http\Requests\Panel;

use App\Enums\RolSistema;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

/**
 * Reglas de validación para crear y editar un FUNCIONARIO del sistema.
 */
class GuardarUsuarioRequest extends FormRequest
{
    /**
     * El permiso real lo pone la ruta con el middleware
     * `permiso:usuarios.gestionar` (ver RolSistema::permisos(), donde ese
     * permiso es exclusivo del administrador). Acá no hace falta repetirlo.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // En edición Laravel inyecta el modelo por la ruta. El parámetro tiene
        // que llamarse {usuario} en routes/panel.php para que esto lo
        // encuentre; si se llamara {user}, esta línea devolvería null y el
        // formulario de edición se validaría como si fuera un alta.
        $usuario = $this->route('usuario');
        $idActual = $usuario instanceof User ? $usuario->id : null;
        $esAlta = $idActual === null;

        return [
            'name' => ['required', 'string', 'max:255'],

            /*
             * Cédula del funcionario.
             */
            'ci' => [
                'nullable', 'string', 'max:20',
                Rule::unique('users', 'ci')
                    ->whereNull('deleted_at')
                    ->ignore($idActual),
            ],

            /*
             * Correo institucional. ES EL USUARIO CON EL QUE SE INICIA SESIÓN
             * (ver LoginRequest), así que si esto se repite, dos personas
             * pelean por la misma cuenta.
             */
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($idActual),
                // Solo se exige el dominio de la Gobernación si está
                // configurado. Sin JICHI_DOMINIO_INSTITUCIONAL en el .env la
                // regla no se aplica, para no trabar los ambientes de prueba
                // donde las cuentas se crean con correos cualquiera.
                ...$this->reglaDominioInstitucional(),
            ],

            'cargo' => ['nullable', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:30'],

            /*
             * `activo` es la baja del día a día: el funcionario se va de
             * vacaciones o cambia de unidad y su cuenta deja de entrar, pero
             * su historial de trámites y pagos sigue intacto. El borrado
             * lógico (SoftDeletes) es otra cosa y no se toca desde acá.
             */
            'activo' => ['boolean'],

            /*
             * El rol decide TODO lo que la persona puede hacer: de él salen
             * los permisos que después revisa el middleware de cada ruta.
             */
            'rol' => ['required', Rule::enum(RolSistema::class)],

            /*
             * Contraseña.
             */
            'password' => [
                $esAlta ? 'required' : 'nullable',
                'confirmed',
                Password::min(8)->letters()->numbers(),
            ],
        ];
    }

    /**
     * Exige el dominio de la Gobernación, si está configurado.
     *
     * @return array<int, string>
     */
    private function reglaDominioInstitucional(): array
    {
        $dominio = config('jichi.dominio_institucional');

        // `ends_with` se aplica sobre el correo ya pasado a minúscula en
        // prepareForValidation(); si no, JUAN@GOB.BO no coincidiría con
        // @gob.bo y el mensaje de error sería incomprensible.
        return filled($dominio) ? ['ends_with:@'.ltrim((string) $dominio, '@')] : [];
    }

    /**
     * Comprobaciones que necesitan mirar la base de datos o saber QUIÉN está
     * guardando. No caben en una regla suelta porque dependen del contexto.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $usuario = $this->route('usuario');

                if (! $usuario instanceof User) {
                    return; // Es un alta: nada de esto aplica todavía.
                }

                /*
                 * NADIE SE CIERRA LA PUERTA A SÍ MISMO.
                 */
                if ($usuario->id === $this->user()?->id) {
                    if (! $this->boolean('activo')) {
                        $validator->errors()->add('activo', 'No puede desactivar su propia cuenta.');
                    }

                    if ($usuario->esAdministrador() && $this->input('rol') !== RolSistema::Administrador->value) {
                        $validator->errors()->add('rol', 'No puede quitarse a sí mismo el rol de administrador.');
                    }
                }

                /*
                 * Y EL SISTEMA NO SE QUEDA SIN ADMINISTRADOR.
                 */
                $pierdeElRol = $this->input('rol') !== RolSistema::Administrador->value
                    || ! $this->boolean('activo');

                if ($usuario->esAdministrador() && $pierdeElRol) {
                    $quedanOtros = User::query()
                        ->role(RolSistema::Administrador->value)
                        ->activos()
                        ->whereKeyNot($usuario->id)
                        ->exists();

                    if (! $quedanOtros) {
                        $validator->errors()->add(
                            'rol',
                            'Es el último administrador activo del sistema. Asigne el rol a otro funcionario antes de cambiar este.',
                        );
                    }
                }
            },
        ];
    }

    /**
     * Mensajes en español y en lenguaje de oficina, no de programador.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre completo del funcionario es obligatorio.',
            'ci.unique' => 'Ya existe un funcionario activo con esa cédula.',
            'email.required' => 'El correo es obligatorio: es el usuario con el que inicia sesión.',
            'email.email' => 'El correo no tiene un formato válido.',
            'email.unique' => 'Ese correo ya está asignado a otra cuenta.',
            'email.ends_with' => 'Debe usar el correo institucional (@'
                .ltrim((string) config('jichi.dominio_institucional'), '@').').',
            'rol.required' => 'Seleccione el rol que tendrá el funcionario.',
            'password.required' => 'Asigne una contraseña inicial para la cuenta.',
            'password.confirmed' => 'Las dos contraseñas no coinciden.',
        ];
    }

    /**
     * Normaliza ANTES de validar, por el mismo motivo que en beneficiarios:
     * quien carga escribe con espacios de más y mayúsculas sueltas.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),

            // El correo se guarda SIEMPRE en minúscula porque es la llave de
            // inicio de sesión: si entra como Juan@… y después la persona
            // escribe juan@…, la búsqueda no lo encuentra y parece que la
            // cuenta no existe.
            'email' => strtolower(trim((string) $this->input('email'))),

            'ci' => filled($this->input('ci')) ? trim((string) $this->input('ci')) : null,
            'cargo' => filled($this->input('cargo')) ? trim((string) $this->input('cargo')) : null,
            'telefono' => filled($this->input('telefono')) ? trim((string) $this->input('telefono')) : null,

            // Una casilla sin marcar no viaja en el formulario, así que
            // ausente tiene que significar «inactivo» y no «no se tocó».
            'activo' => $this->boolean('activo'),

            // Campo de contraseña en blanco = «no la cambies». Se pasa a null
            // para que `nullable` lo deje ir sin evaluar la fuerza.
            'password' => filled($this->input('password')) ? $this->input('password') : null,
        ]);
    }

    /**
     * Nombres legibles de los campos, para los mensajes automáticos.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nombre completo',
            'ci' => 'cédula de identidad',
            'email' => 'correo institucional',
            'telefono' => 'teléfono',
            'password' => 'contraseña',
        ];
    }
}
