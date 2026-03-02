<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FoodDataService
{
    public function __construct(private TextTranslationService $translator)
    {
    }

    public function searchFoods(string $query, int $pageSize = 8): array
    {
        $apiKey = (string) config('fooddata.api_key');

        if ($apiKey === '') {
            throw new RuntimeException('FOODDATA_API_KEY no esta configurada.');
        }

        $normalizedQuery = trim(mb_strtolower($query));
        $cacheKey = 'fooddata.search.'.sha1($normalizedQuery.'|'.$pageSize);
        $ttlSeconds = (int) config('fooddata.cache_ttl_seconds', 900);

        return Cache::remember($cacheKey, now()->addSeconds($ttlSeconds), function () use ($apiKey, $query, $pageSize): array {
            /** @var Response $response */
            $response = Http::baseUrl((string) config('fooddata.base_url'))
                ->timeout((int) config('fooddata.timeout', 8))
                ->connectTimeout((int) config('fooddata.connect_timeout', 4))
                ->get('/foods/search', [
                    'api_key' => $apiKey,
                    'query' => $query,
                    'pageSize' => $pageSize,
                ]);

            if ($response->failed()) {
                throw new RuntimeException('No se pudo obtener informacion desde FoodData Central.');
            }

            $payload = $response->json();
            $foods = data_get($payload, 'foods', []);

            $normalizedFoods = array_map(function (array $food): array {
                return [
                    'fdc_id' => $food['fdcId'] ?? null,
                    'name' => $food['description'] ?? 'Sin nombre',
                    'brand' => $food['brandOwner'] ?? ($food['brandName'] ?? null),
                    'calories' => $this->extractNutrient($food['foodNutrients'] ?? [], '208'),
                    'protein' => $this->extractNutrient($food['foodNutrients'] ?? [], '203'),
                    'carbs' => $this->extractNutrient($food['foodNutrients'] ?? [], '205'),
                    'fat' => $this->extractNutrient($food['foodNutrients'] ?? [], '204'),
                ];
            }, $foods);

            // Traducimos solo los primeros resultados para acelerar la respuesta.
            $translateLimit = (int) config('fooddata.translate_top_n', 5);
            $headFoods = array_slice($normalizedFoods, 0, max(0, $translateLimit));
            $tailFoods = array_slice($normalizedFoods, max(0, $translateLimit));

            $textsToTranslate = [];
            foreach ($headFoods as $food) {
                $textsToTranslate[] = $food['name'] ?? null;
                $textsToTranslate[] = $food['brand'] ?? null;
            }

            $translatedMap = $this->translator->translateMany($textsToTranslate, 'en', 'es');

            $translatedHead = array_map(function (array $food) use ($translatedMap): array {
                $name = $food['name'] ?? '';
                $brand = $food['brand'] ?? '';

                $food['name'] = $translatedMap[$name] ?? $name;
                if (is_string($brand) && $brand !== '') {
                    $food['brand'] = $translatedMap[$brand] ?? $brand;
                }

                return $food;
            }, $headFoods);

            return array_merge($translatedHead, $tailFoods);
        });
    }

    private function extractNutrient(array $nutrients, string $nutrientNumber): ?float
    {
        foreach ($nutrients as $nutrient) {
            if (($nutrient['nutrientNumber'] ?? null) === $nutrientNumber) {
                $value = $nutrient['value'] ?? null;

                return is_numeric($value) ? (float) $value : null;
            }
        }

        return null;
    }
}
