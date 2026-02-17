<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ApiNinjaExerciseService
{
    public function __construct(private TextTranslationService $translator)
    {
    }

    public function search(array $filters): array
    {
        $result = $this->searchPaged($filters, 1, 500);

        return $result['items'];
    }

    public function searchPaged(array $filters, int $page = 1, int $perPage = 6): array
    {
        $query = [];
        foreach (['name', 'muscle', 'type', 'difficulty'] as $allowedFilter) {
            $value = $filters[$allowedFilter] ?? null;
            if (is_string($value) && trim($value) !== '') {
                $query[$allowedFilter] = trim($value);
            }
        }

        ksort($query);
        $cacheKey = 'api_ninja.exercises.'.sha1(json_encode($query));
        $payload = Cache::remember($cacheKey, now()->addSeconds((int) config('api_ninja.cache_ttl_seconds', 43200)), function () use ($query) {
            return $this->sendRequest('exercises', $query);
        });

        if (!array_is_list($payload)) {
            return ['items' => [], 'total' => 0];
        }

        $normalized = array_map(fn (array $item): array => $this->normalizeExercise($item), $payload);
        $total = count($normalized);
        $offset = max(0, ($page - 1) * $perPage);
        $pageItems = array_slice($normalized, $offset, $perPage);

        $translatedItems = $this->translateExercises($pageItems);

        return [
            'items' => $translatedItems,
            'total' => $total,
        ];
    }

    private function translateExercises(array $exercises): array
    {
        $textsToTranslate = [];
        foreach ($exercises as $exercise) {
            $textsToTranslate[] = $exercise['name'] ?? null;
            $textsToTranslate[] = $exercise['type'] ?? null;
            $textsToTranslate[] = $exercise['muscle'] ?? null;
            $textsToTranslate[] = $exercise['equipment'] ?? null;
            $textsToTranslate[] = $exercise['difficulty'] ?? null;
            $textsToTranslate[] = $exercise['instructions'] ?? null;
            $textsToTranslate[] = $exercise['safety_info'] ?? null;
        }

        $translatedMap = $this->translator->translateMany($textsToTranslate, 'en', 'es');

        return array_map(function (array $exercise) use ($translatedMap): array {
            foreach (['name', 'type', 'muscle', 'equipment', 'difficulty', 'instructions', 'safety_info'] as $field) {
                $value = $exercise[$field] ?? null;
                if (is_string($value) && $value !== '') {
                    $exercise[$field] = $translatedMap[$value] ?? $value;
                }
            }

            return $exercise;
        }, $exercises);
    }

    private function sendRequest(string $endpointKey, array $query): array
    {
        $apiKey = (string) config('api_ninja.api_key');
        if ($apiKey === '') {
            throw new RuntimeException('API_NINJA_KEY no esta configurada.');
        }

        $url = (string) config("api_ninja.endpoints.{$endpointKey}");
        if ($url === '') {
            throw new RuntimeException("Endpoint API Ninjas no configurado: {$endpointKey}.");
        }

        /** @var Response $response */
        $response = Http::timeout((int) config('api_ninja.timeout', 15))
            ->connectTimeout((int) config('api_ninja.connect_timeout', 7))
            ->withHeaders([
                'X-Api-Key' => $apiKey,
                'Accept' => 'application/json',
            ])
            ->get($url, $query);

        if ($response->failed()) {
            $status = $response->status();
            $body = trim((string) $response->body());
            $bodyPreview = $body !== '' ? mb_substr($body, 0, 240) : 'sin detalle';
            throw new RuntimeException("No se pudo obtener informacion desde API Ninjas ({$status}): {$bodyPreview}");
        }

        $payload = $response->json();
        if (!is_array($payload)) {
            throw new RuntimeException('Respuesta invalida de API Ninjas.');
        }

        return $payload;
    }

    private function normalizeExercise(array $item): array
    {
        $equipments = $item['equipments'] ?? $item['equipment'] ?? null;
        $equipmentText = '-';

        if (is_array($equipments)) {
            $equipmentText = implode(', ', array_filter($equipments, fn ($value) => is_string($value) && trim($value) !== ''));
            $equipmentText = $equipmentText !== '' ? $equipmentText : '-';
        } elseif (is_string($equipments) && trim($equipments) !== '') {
            $equipmentText = trim($equipments);
        }

        return [
            'name' => $item['name'] ?? 'Sin nombre',
            'type' => $item['type'] ?? null,
            'muscle' => $item['muscle'] ?? null,
            'equipment' => $equipmentText,
            'difficulty' => $item['difficulty'] ?? null,
            'instructions' => $item['instructions'] ?? null,
            'safety_info' => $item['safety_info'] ?? null,
        ];
    }
}
