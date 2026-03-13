<?php

namespace Database\Seeders;

use App\Models\Exercise;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class ExercisesTableSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('data/exercises.json');

        if (!File::exists($path)) {
            if ($this->command) {
                $this->command->warn("No existe el archivo de dataset: {$path}");
            }
            return;
        }

        $raw = File::get($path);
        $decoded = json_decode($raw, true);

        if (!is_array($decoded) || !array_is_list($decoded)) {
            if ($this->command) {
                $this->command->warn('El archivo database/data/exercises.json no contiene un array JSON valido.');
            }
            return;
        }

        $rows = [];

        foreach ($decoded as $item) {
            if (!is_array($item)) {
                continue;
            }

            $name = trim((string) ($item['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $muscle = isset($item['muscle']) ? trim((string) $item['muscle']) : null;
            $type = isset($item['type']) ? trim((string) $item['type']) : null;

            $apiKey = trim((string) ($item['api_key'] ?? ''));
            if ($apiKey === '') {
                $apiKey = sha1(
                    strtolower($name).'|'.
                    strtolower((string) $muscle).'|'.
                    strtolower((string) $type)
                );
            }

            $rows[] = [
                'api_key' => $apiKey,
                'name' => $name,
                'name_es' => $this->nullableString($item['name_es'] ?? null),
                'type' => $this->nullableString($type),
                'type_es' => $this->nullableString($item['type_es'] ?? null),
                'muscle' => $this->nullableString($muscle),
                'muscle_es' => $this->nullableString($item['muscle_es'] ?? null),
                'difficulty' => $this->nullableString($item['difficulty'] ?? null),
                'difficulty_es' => $this->nullableString($item['difficulty_es'] ?? null),
                'equipment' => $this->nullableString($item['equipment'] ?? null),
                'equipment_es' => $this->nullableString($item['equipment_es'] ?? null),
                'instructions' => $this->nullableString($item['instructions'] ?? null),
                'instructions_es' => $this->nullableString($item['instructions_es'] ?? null),
                'safety_info' => $this->nullableString($item['safety_info'] ?? null),
                'safety_info_es' => $this->nullableString($item['safety_info_es'] ?? null),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (empty($rows)) {
            if ($this->command) {
                $this->command->warn('El dataset de ejercicios esta vacio. No se insertaron registros.');
            }
            return;
        }

        Exercise::upsert(
            $rows,
            ['api_key'],
            [
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
                'updated_at',
            ]
        );

        if ($this->command) {
            $this->command->info('Ejercicios importados desde database/data/exercises.json: '.count($rows));
        }
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }
}
