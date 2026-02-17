<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FoodDataService
{
    public function __construct(private TextTranslationService $translator)
    {
    }

    public function searchFoods(string $query, int $pageSize = 12): array
    {
        $apiKey = (string) config('fooddata.api_key');

        if ($apiKey === '') {
            throw new RuntimeException('FOODDATA_API_KEY no esta configurada.');
        }

        /** @var Response $response */
        $response = Http::baseUrl((string) config('fooddata.base_url'))
            ->timeout((int) config('fooddata.timeout', 10))
            ->connectTimeout((int) config('fooddata.connect_timeout', 5))
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

        $textsToTranslate = [];
        foreach ($normalizedFoods as $food) {
            $textsToTranslate[] = $food['name'] ?? null;
            $textsToTranslate[] = $food['brand'] ?? null;
        }

        $translatedMap = $this->translator->translateMany($textsToTranslate, 'en', 'es');

        return array_map(function (array $food) use ($translatedMap): array {
            $name = $food['name'] ?? '';
            $brand = $food['brand'] ?? '';

            $food['name'] = $translatedMap[$name] ?? $name;
            if (is_string($brand) && $brand !== '') {
                $food['brand'] = $translatedMap[$brand] ?? $brand;
            }

            return $food;
        }, $normalizedFoods);
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
