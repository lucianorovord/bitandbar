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
            'muscle' => $this->displayMuscle(),
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
            'muscle' => $this->displayMuscle(),
            'difficulty' => $this->difficulty_es ?? $this->difficulty,
            'equipment' => $this->equipment_es ?? $this->equipment,
            'instructions' => $this->instructions_es ?? $this->instructions,
            'safety_info' => $this->safety_info_es ?? $this->safety_info,
        ];
    }

    private function displayMuscle(): ?string
    {
        $value = $this->muscle_es ?? $this->muscle;
        if ($value === null) {
            return null;
        }

        $normalized = $this->normalizeLabel($value);

        return match ($normalized) {
            'abductors',
            'abductores',
            'secuestradores' => 'abductores',
            'calves',
            'gemelos',
            'terneros',
            'pantorrillas' => 'gemelos',
            'traps',
            'trapecio',
            'trapecios',
            'trampas' => 'trapecio',
            'lower_back',
            'espalda baja',
            'espalda_inferior',
            'esp. baja' => 'espalda baja',
            'shoulders',
            'hombros',
            'espalda',
            'deltoides' => 'hombros',
            default => $value,
        };
    }

    private function normalizeLabel(string $value): string
    {
        $value = strtolower(trim($value));
        $replacements = [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ü' => 'u',
        ];

        return strtr($value, $replacements);
    }
}
