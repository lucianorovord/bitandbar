<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FoodDataSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'min:2', 'max:80'],
        ];
    }

    public function messages(): array
    {
        return [
            'q.min' => 'El termino de busqueda debe tener al menos 2 caracteres.',
            'q.max' => 'El termino de busqueda no debe superar 80 caracteres.',
        ];
    }
}
