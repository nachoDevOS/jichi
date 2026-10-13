<?php

namespace App\Http\Requests\Panel;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Reglas para definir las tarifas de un arancel: cada fila con su etiqueta (sin
 * repetir) y su tarifa de SIREB. Si la tarifa es del servicio lo decide el servicio.
 */
class DefinirTarifasRequest extends FormRequest
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
        return [
            'tarifas' => ['present', 'array'],
            'tarifas.*.id' => ['nullable', 'integer'],
            // La clave no la escribe nadie: viaja de vuelta en las filas fijas del carnet.
            'tarifas.*.clave' => ['nullable', 'string', 'max:30'],
            'tarifas.*.etiqueta' => ['required', 'string', 'max:120', 'distinct:ignore_case'],
            'tarifas.*.sireb_tarifa_id' => ['nullable', 'uuid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tarifas.*.etiqueta.required' => 'Escriba la etiqueta.',
            'tarifas.*.etiqueta.max' => 'La etiqueta no puede pasar de 120 caracteres.',
            'tarifas.*.etiqueta.distinct' => 'Esa etiqueta está repetida.',
            'tarifas.*.sireb_tarifa_id.uuid' => 'Esa tarifa no es de SIREB.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Sin espacios de más y los ids en minúscula, como los manda SIREB.
        $this->merge([
            'tarifas' => collect($this->input('tarifas', []))->map(fn ($f): array => [
                'id' => isset($f['id']) && $f['id'] !== '' ? (int) $f['id'] : null,
                'clave' => ($f['clave'] ?? '') === '' ? null : (string) $f['clave'],
                'etiqueta' => trim((string) ($f['etiqueta'] ?? '')),
                'sireb_tarifa_id' => ($f['sireb_tarifa_id'] ?? '') === '' ? null : mb_strtolower(trim((string) $f['sireb_tarifa_id'])),
            ])->values()->all(),
        ]);
    }
}
