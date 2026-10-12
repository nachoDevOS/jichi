<?php

namespace App\Http\Requests\Panel;

use App\Enums\ModoVigencia;
use App\Enums\TramiteConVigencia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Reglas para guardar la vigencia de los tres trámites: un modo y, si es por
 * días, cuántos (de 1 a 3650).
 */
class GuardarVigenciasRequest extends FormRequest
{
    /** El permiso ya lo revisa el middleware de la ruta. */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $reglas = [];

        foreach (TramiteConVigencia::cases() as $t) {
            $reglas[$t->value] = ['required', 'array'];
            $reglas["{$t->value}.modo"] = ['required', Rule::enum(ModoVigencia::class)];
            $reglas["{$t->value}.dias"] = [
                "required_if:{$t->value}.modo,".ModoVigencia::Dias->value,
                'nullable', 'integer', 'min:1', 'max:3650',
            ];
        }

        return $reglas;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $mensajes = [];

        foreach (TramiteConVigencia::cases() as $t) {
            $mensajes["{$t->value}.modo.required"] = 'Elija hasta cuándo vale.';
            $mensajes["{$t->value}.modo.enum"] = 'Elija una opción de la lista.';
            $mensajes["{$t->value}.dias.required_if"] = 'Indique cuántos días vale.';
            $mensajes["{$t->value}.dias.integer"] = 'Los días van sin decimales.';
            $mensajes["{$t->value}.dias.min"] = 'Tiene que valer al menos un día.';
            $mensajes["{$t->value}.dias.max"] = 'Como máximo 3650 días (diez años).';
        }

        return $mensajes;
    }
}
