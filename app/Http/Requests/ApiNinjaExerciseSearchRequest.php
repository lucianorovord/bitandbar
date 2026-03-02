<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApiNinjaExerciseSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'muscle' => ['nullable', 'string', 'min:2', 'max:80'],
            'difficulty' => ['nullable', 'string', 'min:2', 'max:80'],
        ];
    }

    public function messages(): array
    {
        return [
            'muscle.min' => 'Selecciona un grupo muscular valido.',
            'difficulty.min' => 'El filtro dificultad debe tener al menos 2 caracteres.',
        ];
    }
}
