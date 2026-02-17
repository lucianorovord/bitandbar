<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class SpoonacularController extends Controller
{
    public function searchByIngredients(Request $request)
    {
        $ingredients = $request->input('ingredients', []);

        if (!is_array($ingredients)) {
            return response()->json(['error' => 'ingredients must be an array'], 422);
        }

        $ingredientsCsv = implode(',', array_filter(array_map('trim', $ingredients), fn ($value) => $value !== ''));

        try {
            $baseUrl = rtrim((string) env('SPOONACULAR_BASE_URL'), '/');
            $endpoint = str_ends_with(strtolower($baseUrl), '/recipes/findbyingredients')
                ? $baseUrl
                : $baseUrl.'/recipes/findByIngredients';

            $response = Http::get($endpoint, [
                'ingredients' => $ingredientsCsv,
                'number' => 5,
                'apiKey' => env('SPOONACULAR_KEY'),
            ]);

            if ($response->failed()) {
                return response()->json(['error' => 'Spoonacular request failed'], 500);
            }

            return response($response->body(), 200)->header('Content-Type', 'application/json');
        } catch (\Throwable) {
            return response()->json(['error' => 'Spoonacular request failed'], 500);
        }
    }

    public function nutritionDetails(int $recipeId)
    {
        try {
            $root = $this->spoonacularRootUrl();
            $response = Http::get($root.'/recipes/'.$recipeId.'/information', [
                'includeNutrition' => 'true',
                'apiKey' => env('SPOONACULAR_KEY'),
            ]);

            if ($response->failed()) {
                return response()->json(['error' => 'Spoonacular request failed'], 500);
            }

            $payload = $response->json();
            if (!is_array($payload)) {
                return response()->json(['error' => 'Invalid Spoonacular response'], 500);
            }

            $nutrition = is_array($payload['nutrition'] ?? null) ? $payload['nutrition'] : [];
            $totalNutrients = is_array($nutrition['nutrients'] ?? null) ? $nutrition['nutrients'] : [];
            $ingredients = is_array($nutrition['ingredients'] ?? null) ? $nutrition['ingredients'] : [];

            $result = [
                'id' => $payload['id'] ?? $recipeId,
                'title' => $payload['title'] ?? 'Recipe',
                'dish_totals' => [
                    'calories' => $this->extractTotalNutrient($totalNutrients, 'calories'),
                    'protein' => $this->extractTotalNutrient($totalNutrients, 'protein'),
                    'carbs' => $this->extractTotalNutrient($totalNutrients, 'carbohydrates'),
                    'fat' => $this->extractTotalNutrient($totalNutrients, 'fat'),
                ],
                'ingredients' => array_map(function (array $ingredient) {
                    $nutrients = is_array($ingredient['nutrients'] ?? null) ? $ingredient['nutrients'] : [];
                    return [
                        'name' => $ingredient['name'] ?? 'Ingredient',
                        'amount' => $ingredient['amount'] ?? null,
                        'unit' => $ingredient['unit'] ?? null,
                        'calories' => $this->extractIngredientNutrient($nutrients, 'calories'),
                        'protein' => $this->extractIngredientNutrient($nutrients, 'protein'),
                        'carbs' => $this->extractIngredientNutrient($nutrients, 'carbohydrates'),
                        'fat' => $this->extractIngredientNutrient($nutrients, 'fat'),
                    ];
                }, $ingredients),
            ];

            return response()->json($result);
        } catch (\Throwable) {
            return response()->json(['error' => 'Spoonacular request failed'], 500);
        }
    }

    private function spoonacularRootUrl(): string
    {
        $baseUrl = rtrim((string) env('SPOONACULAR_BASE_URL'), '/');
        if ($baseUrl === '') {
            return 'https://api.spoonacular.com';
        }

        $parts = parse_url($baseUrl);
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? '';

        return $host !== '' ? $scheme.'://'.$host : 'https://api.spoonacular.com';
    }

    private function extractTotalNutrient(array $nutrients, string $needle): ?float
    {
        foreach ($nutrients as $nutrient) {
            $name = strtolower((string) ($nutrient['name'] ?? ''));
            if (str_contains($name, $needle)) {
                $amount = $nutrient['amount'] ?? null;
                return is_numeric($amount) ? (float) $amount : null;
            }
        }

        return null;
    }

    private function extractIngredientNutrient(array $nutrients, string $needle): ?float
    {
        foreach ($nutrients as $nutrient) {
            $name = strtolower((string) ($nutrient['name'] ?? ''));
            if (str_contains($name, $needle)) {
                $amount = $nutrient['amount'] ?? null;
                return is_numeric($amount) ? (float) $amount : null;
            }
        }

        return null;
    }
}
