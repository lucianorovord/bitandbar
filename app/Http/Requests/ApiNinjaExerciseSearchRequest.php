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
            'name' => ['nullable', 'string', 'min:2', 'max:80'],
            'muscle' => ['nullable', 'string', 'min:2', 'max:80'],
            'type' => ['nullable', 'string', 'min:2', 'max:80'],
            'difficulty' => ['nullable', 'string', 'min:2', 'max:80'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.min' => 'El filtro nombre debe tener al menos 2 caracteres.',
            'muscle.min' => 'El filtro musculo debe tener al menos 2 caracteres.',
            'type.min' => 'El filtro tipo debe tener al menos 2 caracteres.',
            'difficulty.min' => 'El filtro dificultad debe tener al menos 2 caracteres.',
        ];
    }
}
