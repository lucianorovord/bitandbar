<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
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

    public function recipeInformation(int $recipeId): JsonResponse
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
            $ingredients = is_array($payload['extendedIngredients'] ?? null) ? $payload['extendedIngredients'] : [];

            return response()->json([
                'id' => $payload['id'] ?? $recipeId,
                'title' => $payload['title'] ?? 'Recipe',
                'image' => $payload['image'] ?? null,
                'imageType' => $payload['imageType'] ?? null,
                'readyInMinutes' => $payload['readyInMinutes'] ?? null,
                'servings' => $payload['servings'] ?? null,
                'summary' => trim(strip_tags((string) ($payload['summary'] ?? ''))),
                'cuisines' => array_values(array_filter($payload['cuisines'] ?? [], 'is_string')),
                'dishTypes' => array_values(array_filter($payload['dishTypes'] ?? [], 'is_string')),
                'diets' => array_values(array_filter($payload['diets'] ?? [], 'is_string')),
                'instructions' => $this->resolveInstructions($payload),
                'extendedIngredients' => array_map(static function (array $ingredient): array {
                    return [
                        'id' => $ingredient['id'] ?? null,
                        'name' => $ingredient['name'] ?? ($ingredient['originalName'] ?? 'Ingredient'),
                        'amount' => is_numeric($ingredient['amount'] ?? null) ? (float) $ingredient['amount'] : null,
                        'unit' => $ingredient['unit'] ?? null,
                        'image' => $ingredient['image'] ?? null,
                    ];
                }, $ingredients),
                'nutrition' => [
                    'calories' => $this->extractTotalNutrient($totalNutrients, 'calories'),
                    'protein' => $this->extractTotalNutrient($totalNutrients, 'protein'),
                    'carbs' => $this->extractTotalNutrient($totalNutrients, 'carbohydrates'),
                    'fat' => $this->extractTotalNutrient($totalNutrients, 'fat'),
                ],
            ]);
        } catch (\Throwable) {
            return response()->json(['error' => 'Spoonacular request failed'], 500);
        }
    }

    public function searchByNutrients(Request $request): JsonResponse
    {
        $data = $request->validate([
            'minProtein' => ['nullable', 'numeric', 'min:0', 'max:200'],
            'maxCalories' => ['nullable', 'numeric', 'min:0', 'max:5000'],
            'minCarbs' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'maxFat' => ['nullable', 'numeric', 'min:0', 'max:300'],
            'number' => ['nullable', 'integer', 'min:1', 'max:12'],
            'offset' => ['nullable', 'integer', 'min:0'],
        ]);

        try {
            $params = [
                'apiKey' => env('SPOONACULAR_KEY'),
                'addRecipeNutrition' => 'true',
                'number' => (int) ($data['number'] ?? 6),
                'offset' => (int) ($data['offset'] ?? 0),
            ];

            foreach (['minProtein', 'maxCalories', 'minCarbs', 'maxFat'] as $key) {
                if (array_key_exists($key, $data) && $data[$key] !== null && $data[$key] !== '') {
                    $params[$key] = $data[$key];
                }
            }

            $response = Http::get($this->spoonacularRootUrl().'/recipes/findByNutrients', $params);
            if ($response->failed()) {
                return response()->json(['error' => 'Spoonacular request failed'], 500);
            }

            $payload = $response->json();
            if (!is_array($payload)) {
                return response()->json(['error' => 'Invalid Spoonacular response'], 500);
            }

            $recipes = array_map(function (array $item): array {
                $nutrients = is_array($item['nutrition']['nutrients'] ?? null)
                    ? $item['nutrition']['nutrients']
                    : (is_array($item['nutrients'] ?? null) ? $item['nutrients'] : []);

                return [
                    'id' => $item['id'] ?? null,
                    'title' => $item['title'] ?? 'Recipe',
                    'image' => $item['image'] ?? null,
                    'calories' => $this->numericFieldOrNutrient($item, 'calories', $nutrients),
                    'protein' => $this->numericFieldOrNutrient($item, 'protein', $nutrients),
                    'carbs' => $this->numericFieldOrNutrient($item, 'carbohydrates', $nutrients, ['carbs']),
                    'fat' => $this->numericFieldOrNutrient($item, 'fat', $nutrients),
                ];
            }, $payload);

            return response()->json($recipes);
        } catch (\Throwable) {
            return response()->json(['error' => 'Spoonacular request failed'], 500);
        }
    }

    public function searchComplex(Request $request): JsonResponse
    {
        $data = $request->validate([
            'query' => ['nullable', 'string', 'max:120'],
            'diet' => ['nullable', 'string', 'max:60', 'in:gluten free,ketogenic,vegetarian,lacto-vegetarian,ovo-vegetarian,vegan,pescetarian,paleo,primal,low fodmap,whole30'],
            'intolerances' => ['nullable', 'string', 'max:200'],
            'type' => ['nullable', 'string', 'max:60', 'in:main course,side dish,dessert,appetizer,salad,bread,breakfast,soup,beverage,sauce,marinade,fingerfood,snack,drink'],
            'maxReadyTime' => ['nullable', 'integer', 'min:5', 'max:300'],
            'number' => ['nullable', 'integer', 'min:1', 'max:12'],
            'offset' => ['nullable', 'integer', 'min:0'],
        ]);

        $allowedIntolerances = ['dairy', 'egg', 'gluten', 'grain', 'peanut', 'seafood', 'sesame', 'shellfish', 'soy', 'sulfite', 'tree nut', 'wheat'];
        $intolerances = [];
        if (!empty($data['intolerances'])) {
            $intolerances = array_values(array_filter(array_map('trim', explode(',', (string) $data['intolerances']))));
            foreach ($intolerances as $value) {
                if (!in_array($value, $allowedIntolerances, true)) {
                    return response()->json([
                        'message' => 'The given data was invalid.',
                        'errors' => ['intolerances' => ['Una o mas intolerancias no son validas.']],
                    ], 422);
                }
            }
        }

        try {
            $params = [
                'apiKey' => env('SPOONACULAR_KEY'),
                'addRecipeNutrition' => 'true',
                'addRecipeInformation' => 'false',
                'number' => (int) ($data['number'] ?? 6),
                'offset' => (int) ($data['offset'] ?? 0),
            ];

            foreach (['query', 'diet', 'intolerances', 'type', 'maxReadyTime'] as $key) {
                if (array_key_exists($key, $data) && $data[$key] !== null && $data[$key] !== '') {
                    $params[$key] = $key === 'intolerances' ? implode(',', $intolerances) : $data[$key];
                }
            }

            $response = Http::get($this->spoonacularRootUrl().'/recipes/complexSearch', $params);
            if ($response->failed()) {
                return response()->json(['error' => 'Spoonacular request failed'], 500);
            }

            $payload = $response->json();
            if (!is_array($payload)) {
                return response()->json(['error' => 'Invalid Spoonacular response'], 500);
            }

            $results = is_array($payload['results'] ?? null) ? $payload['results'] : [];

            return response()->json(array_map(function (array $item): array {
                $nutrients = is_array($item['nutrition']['nutrients'] ?? null) ? $item['nutrition']['nutrients'] : [];

                return [
                    'id' => $item['id'] ?? null,
                    'title' => $item['title'] ?? 'Recipe',
                    'image' => $item['image'] ?? null,
                    'readyInMinutes' => $item['readyInMinutes'] ?? null,
                    'calories' => $this->extractComplexNutrient($nutrients, 'Calories'),
                    'protein' => $this->extractComplexNutrient($nutrients, 'Protein'),
                    'carbs' => $this->extractComplexNutrient($nutrients, 'Carbohydrates'),
                    'fat' => $this->extractComplexNutrient($nutrients, 'Fat'),
                ];
            }, $results));
        } catch (\Throwable) {
            return response()->json(['error' => 'Spoonacular request failed'], 500);
        }
    }

    public function similarRecipes(int $recipeId): JsonResponse
    {
        try {
            $response = Http::get($this->spoonacularRootUrl().'/recipes/'.$recipeId.'/similar', [
                'number' => 4,
                'apiKey' => env('SPOONACULAR_KEY'),
            ]);

            if ($response->failed()) {
                return response()->json(['error' => 'Spoonacular request failed'], 500);
            }

            $payload = $response->json();
            if (!is_array($payload)) {
                return response()->json(['error' => 'Invalid Spoonacular response'], 500);
            }

            return response()->json(array_map(static function (array $item): array {
                return [
                    'id' => $item['id'] ?? null,
                    'title' => $item['title'] ?? 'Recipe',
                    'readyInMinutes' => $item['readyInMinutes'] ?? null,
                    'servings' => $item['servings'] ?? null,
                ];
            }, $payload));
        } catch (\Throwable) {
            return response()->json(['error' => 'Spoonacular request failed'], 500);
        }
    }

    public function toggleFavorite(Request $request, int $recipeId): JsonResponse
    {
        $favorites = session('recipe_favorites', []);

        if (array_key_exists($recipeId, $favorites)) {
            unset($favorites[$recipeId]);
            session(['recipe_favorites' => $favorites]);

            return response()->json([
                'ok' => true,
                'action' => 'removed',
                'count' => count($favorites),
            ]);
        }

        $data = $request->validate([
            'title' => ['required', 'string'],
            'image' => ['nullable', 'string'],
        ]);

        $favorites[$recipeId] = [
            'id' => $recipeId,
            'title' => $data['title'],
            'image' => $data['image'] ?? null,
            'saved_at' => now()->toDateString(),
        ];

        session(['recipe_favorites' => $favorites]);

        return response()->json([
            'ok' => true,
            'action' => 'added',
            'count' => count($favorites),
        ]);
    }

    public function getFavorites(Request $request): JsonResponse
    {
        unset($request);

        return response()->json(array_values(session('recipe_favorites', [])));
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

    private function resolveInstructions(array $payload): ?string
    {
        $instructions = trim(strip_tags((string) ($payload['instructions'] ?? '')));
        if ($instructions !== '') {
            return $instructions;
        }

        $analyzed = is_array($payload['analyzedInstructions'] ?? null) ? $payload['analyzedInstructions'] : [];
        $steps = is_array($analyzed[0]['steps'] ?? null) ? $analyzed[0]['steps'] : [];
        $fragments = [];
        foreach ($steps as $step) {
            $text = trim((string) ($step['step'] ?? ''));
            if ($text !== '') {
                $fragments[] = $text;
            }
        }

        return empty($fragments) ? null : implode(' ', $fragments);
    }

    private function numericFieldOrNutrient(array $item, string $needle, array $nutrients, array $fieldAliases = []): ?float
    {
        $aliases = array_merge([$needle], $fieldAliases);

        foreach ($aliases as $alias) {
            $value = $item[$alias] ?? null;
            if (is_numeric($value)) {
                return (float) $value;
            }

            if (is_string($value) && preg_match('/-?\d+(?:\.\d+)?/', $value, $matches) === 1) {
                return (float) $matches[0];
            }
        }

        foreach ($aliases as $alias) {
            $value = $this->extractTotalNutrient($nutrients, strtolower($alias));
            if ($value !== null) {
                return $value;
            }
        }

        return null;
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

    private function extractComplexNutrient(array $nutrients, string $needle): ?float
    {
        foreach ($nutrients as $nutrient) {
            $name = trim((string) ($nutrient['name'] ?? ''));
            if ($name === $needle) {
                $amount = $nutrient['amount'] ?? null;
                return is_numeric($amount) ? (float) $amount : null;
            }
        }

        return null;
    }
}
