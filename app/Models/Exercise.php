<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Exercise extends Model
{
    protected $fillable = [
        'api_key',
        'name',
        'name_es',
        'type',
        'type_es',
        'muscle',
        'muscle_es',
        'difficulty',
        'difficulty_es',
        'equipment',
        'equipment_es',
        'instructions',
        'instructions_es',
        'safety_info',
        'safety_info_es',
    ];

    public function toPickerArray(): array
    {
        return [
            'key' => $this->api_key,
            'name' => $this->name_es ?? $this->name,
            'type' => $this->type_es ?? $this->type,
            'muscle' => $this->muscle_es ?? $this->muscle,
            'difficulty' => $this->difficulty_es ?? $this->difficulty,
            'equipment' => $this->equipment_es ?? $this->equipment,
            'instructions' => null,
            'safety_info' => null,
        ];
    }

    public function toFullArray(): array
    {
        return [
            'key' => $this->api_key,
            'name' => $this->name_es ?? $this->name,
            'type' => $this->type_es ?? $this->type,
            'muscle' => $this->muscle_es ?? $this->muscle,
            'difficulty' => $this->difficulty_es ?? $this->difficulty,
            'equipment' => $this->equipment_es ?? $this->equipment,
            'instructions' => $this->instructions_es ?? $this->instructions,
            'safety_info' => $this->safety_info_es ?? $this->safety_info,
        ];
    }
}
