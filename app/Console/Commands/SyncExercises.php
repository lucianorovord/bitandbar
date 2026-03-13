<?php

namespace App\Console\Commands;

use App\Models\Exercise;
use App\Services\ApiNinjaExerciseService;
use App\Services\TextTranslationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Throwable;

class SyncExercises extends Command
{
    protected $signature = 'exercises:sync';

    protected $description = 'Sincroniza ejercicios desde API Ninjas a la base de datos local';

    public function __construct(
        private ApiNinjaExerciseService $exerciseService,
        private TextTranslationService $translator
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        // Evita depender de CACHE_STORE=database durante la sincronizacion.
        Config::set('cache.default', 'array');

        if (!$this->canUseDatabase()) {
            return self::FAILURE;
        }

        $muscles = [
            'abdominals',
            'abductors',
            'adductors',
            'biceps',
            'calves',
            'chest',
            'forearms',
            'glutes',
            'hamstrings',
            'lats',
            'lower_back',
            'middle_back',
            'neck',
            'quadriceps',
            'traps',
            'triceps',
            'shoulders',
        ];

        $synced = 0;
        $failedMuscles = [];

        foreach ($muscles as $muscle) {
            $this->info("Sincronizando grupo muscular: {$muscle}");
            try {
                $items = $this->exerciseService->search(['muscle' => $muscle]);

                $this->withProgressBar($items, function (array $item) use (&$synced): void {
                    $texts = array_values(array_filter([
                        $item['name'] ?? null,
                        $item['type'] ?? null,
                        $item['muscle'] ?? null,
                        $item['difficulty'] ?? null,
                        $item['equipment'] ?? null,
                        $item['instructions'] ?? null,
                        $item['safety_info'] ?? null,
                    ], fn ($value) => is_string($value) && trim($value) !== ''));

                    $translated = $this->translator->translateMany($texts, 'en', 'es');
                    $name = (string) ($item['name'] ?? 'Sin nombre');
                    $muscle = (string) ($item['muscle'] ?? '');
                    $type = (string) ($item['type'] ?? '');
                    $apiKey = sha1(
                        strtolower(trim($name)).'|'.
                        strtolower(trim($muscle)).'|'.
                        strtolower(trim($type))
                    );

                    Exercise::updateOrCreate(
                        ['api_key' => $apiKey],
                        [
                            'name' => $name,
                            'name_es' => $translated[$name] ?? $name,
                            'type' => $item['type'] ?? null,
                            'type_es' => isset($item['type']) ? ($translated[$item['type']] ?? $item['type']) : null,
                            'muscle' => $item['muscle'] ?? null,
                            'muscle_es' => isset($item['muscle']) ? ($translated[$item['muscle']] ?? $item['muscle']) : null,
                            'difficulty' => $item['difficulty'] ?? null,
                            'difficulty_es' => isset($item['difficulty']) ? ($translated[$item['difficulty']] ?? $item['difficulty']) : null,
                            'equipment' => $item['equipment'] ?? null,
                            'equipment_es' => isset($item['equipment']) ? ($translated[$item['equipment']] ?? $item['equipment']) : null,
                            'instructions' => $item['instructions'] ?? null,
                            'instructions_es' => isset($item['instructions']) ? ($translated[$item['instructions']] ?? $item['instructions']) : null,
                            'safety_info' => $item['safety_info'] ?? null,
                            'safety_info_es' => isset($item['safety_info']) ? ($translated[$item['safety_info']] ?? $item['safety_info']) : null,
                        ]
                    );

                    $synced++;
                });

                $this->newLine(2);
            } catch (Throwable $exception) {
                $failedMuscles[$muscle] = $exception->getMessage();
                $this->newLine();
                $this->warn("No se pudo sincronizar {$muscle}: {$exception->getMessage()}");

                if (str_contains($exception->getMessage(), 'currently down for free users')) {
                    $this->warn('API Ninjas esta bloqueando este endpoint para cuentas gratuitas. El comando no puede continuar con datos reales desde esa API.');
                }

                $this->newLine();
            }
        }

        $this->info("Total de ejercicios sincronizados: {$synced}");

        if (!empty($failedMuscles)) {
            $this->warn('Grupos musculares con error: '.implode(', ', array_keys($failedMuscles)));
        }

        return $synced > 0 ? self::SUCCESS : self::FAILURE;
    }

    private function canUseDatabase(): bool
    {
        try {
            DB::connection()->getPdo();
            return true;
        } catch (Throwable $exception) {
            $this->error('No se puede conectar a la base de datos con el PHP actual.');
            $this->warn('Causa detectada: '.$exception->getMessage());
            $this->newLine();
            $this->line('Este proyecto esta configurado para MySQL y normalmente se ejecuta con Sail/Docker.');
            $this->line('Opciones correctas:');
            $this->line('1. Ejecutar el comando con `./vendor/bin/sail artisan exercises:sync`');
            $this->line('2. O instalar/habilitar `pdo_mysql` en tu PHP local si quieres usar `php artisan` directamente');
            return false;
        }
    }
}
