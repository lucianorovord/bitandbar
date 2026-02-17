<?php

namespace App\Http\Controllers;

use App\Http\Requests\FoodDataSearchRequest;
use App\Services\FoodDataService;
use App\Services\TextTranslationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class FoodDataController extends Controller
{
    public function __construct(
        private FoodDataService $foodDataService,
        private TextTranslationService $translator
    )
    {
    }

    public function register(FoodDataSearchRequest $request): View
    {
        $query = trim((string) $request->validated('q', ''));
        $foods = [];
        $error = null;

        if ($query !== '') {
            try {
                $queryInEnglish = $this->translator->translate($query, 'es', 'en') ?? $query;
                $foods = $this->foodDataService->searchFoods($queryInEnglish);
            } catch (Throwable $exception) {
                report($exception);
                $error = 'No se pudo consultar la API de alimentos en este momento.';
            }
        }

        return view('comida.registrar', [
            'foods' => $foods,
            'query' => $query,
            'error' => $error,
            'cart' => $this->cartItems(),
            'cart_totals' => $this->cartTotals(),
            'meal_history' => session('meal_history', []),
        ]);
    }

    public function addToCart(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'fdc_id' => ['nullable', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
            'calories' => ['nullable', 'numeric', 'min:0'],
            'protein' => ['nullable', 'numeric', 'min:0'],
            'carbs' => ['nullable', 'numeric', 'min:0'],
            'fat' => ['nullable', 'numeric', 'min:0'],
            'quantity' => ['nullable', 'numeric', 'min:0.1', 'max:20'],
            'q' => ['nullable', 'string', 'max:80'],
        ]);

        $quantity = (float) ($data['quantity'] ?? 1);
        $cart = $this->cartItems();
        $itemKey = $this->resolveItemKey($data);

        if (isset($cart[$itemKey])) {
            $cart[$itemKey]['quantity'] = round(((float) $cart[$itemKey]['quantity']) + $quantity, 2);
        } else {
            $cart[$itemKey] = [
                'fdc_id' => $data['fdc_id'] ?? null,
                'name' => $data['name'],
                'brand' => $data['brand'] ?? null,
                'calories' => $this->toNullableFloat($data['calories'] ?? null),
                'protein' => $this->toNullableFloat($data['protein'] ?? null),
                'carbs' => $this->toNullableFloat($data['carbs'] ?? null),
                'fat' => $this->toNullableFloat($data['fat'] ?? null),
                'quantity' => $quantity,
            ];
        }

        session(['meal_cart' => $cart]);

        return redirect()->to($this->foodRegisterUrl($data['q'] ?? null))
            ->with('food_success', 'Alimento anadido al carrito.');
    }

    public function updateCart(Request $request, string $itemKey): RedirectResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'numeric', 'min:0.1', 'max:20'],
            'q' => ['nullable', 'string', 'max:80'],
        ]);

        $cart = $this->cartItems();
        if (isset($cart[$itemKey])) {
            $cart[$itemKey]['quantity'] = round((float) $data['quantity'], 2);
            session(['meal_cart' => $cart]);
        }

        return redirect()->to($this->foodRegisterUrl($data['q'] ?? null))
            ->with('food_success', 'Cantidad actualizada.');
    }

    public function removeFromCart(Request $request, string $itemKey): RedirectResponse
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:80'],
        ]);

        $cart = $this->cartItems();
        unset($cart[$itemKey]);
        session(['meal_cart' => $cart]);

        return redirect()->to($this->foodRegisterUrl($request->input('q')))
            ->with('food_success', 'Alimento eliminado del carrito.');
    }

    public function clearCart(Request $request): RedirectResponse
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:80'],
        ]);

        session()->forget('meal_cart');

        return redirect()->to($this->foodRegisterUrl($request->input('q')))
            ->with('food_success', 'Carrito vaciado.');
    }

    public function importRecipeToCart(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'recipe_title' => ['required', 'string', 'max:255'],
            'ingredients' => ['required', 'array', 'min:1'],
            'ingredients.*.name' => ['required', 'string', 'max:255'],
            'ingredients.*.amount' => ['nullable', 'numeric', 'min:0'],
            'ingredients.*.unit' => ['nullable', 'string', 'max:30'],
            'ingredients.*.calories' => ['nullable', 'numeric', 'min:0'],
            'ingredients.*.protein' => ['nullable', 'numeric', 'min:0'],
            'ingredients.*.carbs' => ['nullable', 'numeric', 'min:0'],
            'ingredients.*.fat' => ['nullable', 'numeric', 'min:0'],
            'q' => ['nullable', 'string', 'max:80'],
        ]);

        $cart = $this->cartItems();
        foreach ($data['ingredients'] as $ingredient) {
            $labelParts = [$ingredient['name']];
            if (is_numeric($ingredient['amount'] ?? null)) {
                $labelParts[] = '('.$ingredient['amount'].' '.($ingredient['unit'] ?? '').')';
            }
            $displayName = trim(implode(' ', $labelParts));

            $itemData = [
                'fdc_id' => null,
                'name' => $displayName,
                'brand' => 'Receta: '.$data['recipe_title'],
                'calories' => $this->toNullableFloat($ingredient['calories'] ?? null),
                'protein' => $this->toNullableFloat($ingredient['protein'] ?? null),
                'carbs' => $this->toNullableFloat($ingredient['carbs'] ?? null),
                'fat' => $this->toNullableFloat($ingredient['fat'] ?? null),
            ];

            $itemKey = $this->resolveItemKey($itemData);
            if (isset($cart[$itemKey])) {
                $cart[$itemKey]['quantity'] = round(((float) $cart[$itemKey]['quantity']) + 1, 2);
            } else {
                $cart[$itemKey] = $itemData + ['quantity' => 1.0];
            }
        }

        session(['meal_cart' => $cart]);

        return redirect()->to($this->foodRegisterUrl($data['q'] ?? null))
            ->with('food_success', 'Ingredientes de la receta anadidos al carrito.');
    }

    public function storeMeal(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'meal_type' => ['required', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:600'],
            'q' => ['nullable', 'string', 'max:80'],
        ]);

        $cart = $this->cartItems();
        if (empty($cart)) {
            return redirect()->to($this->foodRegisterUrl($data['q'] ?? null))
                ->with('food_error', 'No puedes registrar una comida vacia.');
        }

        $history = session('meal_history', []);
        $mealType = $this->normalizeMealType((string) $data['meal_type']);
        $newNotes = trim((string) ($data['notes'] ?? ''));

        $existingIndex = null;
        foreach ($history as $index => $meal) {
            $existingType = $this->normalizeMealType((string) ($meal['meal_type'] ?? ''));
            if ($existingType === $mealType) {
                $existingIndex = $index;
                break;
            }
        }

        if ($existingIndex !== null) {
            $existingItems = is_array($history[$existingIndex]['items'] ?? null)
                ? $history[$existingIndex]['items']
                : [];
            $mergedItems = $this->mergeMealItems($existingItems, array_values($cart));

            $existingNotes = trim((string) ($history[$existingIndex]['notes'] ?? ''));
            $mergedNotes = $existingNotes;
            if ($newNotes !== '') {
                $mergedNotes = $existingNotes === ''
                    ? $newNotes
                    : ($existingNotes."\n".$newNotes);
            }

            $history[$existingIndex]['items'] = $mergedItems;
            $history[$existingIndex]['totals'] = $this->cartTotals($mergedItems);
            $history[$existingIndex]['notes'] = $mergedNotes !== '' ? $mergedNotes : null;
            $history[$existingIndex]['meal_type'] = $mealType;
        } else {
            $history[] = [
                'registered_at' => now()->format('d/m/Y H:i'),
                'meal_type' => $mealType,
                'notes' => $newNotes !== '' ? $newNotes : null,
                'items' => array_values($cart),
                'totals' => $this->cartTotals($cart),
            ];
        }

        session([
            'meal_history' => $history,
            'meal_cart' => [],
        ]);

        return redirect()->to($this->foodRegisterUrl($data['q'] ?? null))
            ->with('food_success', 'Comida registrada correctamente.');
    }

    public function updateMealRecord(Request $request, string $mealIndex): RedirectResponse
    {
        $data = $request->validate([
            'meal_type' => ['required', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:600'],
            'q' => ['nullable', 'string', 'max:80'],
        ]);

        $history = session('meal_history', []);
        $index = (int) $mealIndex;

        if (!isset($history[$index]) || !is_array($history[$index])) {
            return redirect()->to($this->foodRegisterUrl($data['q'] ?? null))
                ->with('food_error', 'No se encontro el registro de comida para editar.');
        }

        $history[$index]['meal_type'] = $this->normalizeMealType((string) $data['meal_type']);
        $history[$index]['notes'] = $data['notes'] ?? null;
        session(['meal_history' => $history]);

        return redirect()->to($this->foodRegisterUrl($data['q'] ?? null))
            ->with('food_success', 'Registro de comida actualizado.');
    }

    public function deleteMealRecord(Request $request, string $mealIndex): RedirectResponse
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:80'],
        ]);

        $history = session('meal_history', []);
        $index = (int) $mealIndex;

        if (!isset($history[$index])) {
            return redirect()->to($this->foodRegisterUrl($request->input('q')))
                ->with('food_error', 'No se encontro el registro de comida para eliminar.');
        }

        unset($history[$index]);
        session(['meal_history' => array_values($history)]);

        return redirect()->to($this->foodRegisterUrl($request->input('q')))
            ->with('food_success', 'Registro de comida eliminado.');
    }

    private function foodRegisterUrl(?string $query): string
    {
        $base = url('/comida/registrar');
        $query = trim((string) $query);

        return $query !== '' ? $base.'?q='.urlencode($query) : $base;
    }

    private function cartItems(): array
    {
        $cart = session('meal_cart', []);

        return is_array($cart) ? $cart : [];
    }

    private function cartTotals(?array $cart = null): array
    {
        $cart = $cart ?? $this->cartItems();
        $totals = ['calories' => 0.0, 'protein' => 0.0, 'carbs' => 0.0, 'fat' => 0.0];

        foreach ($cart as $item) {
            $quantity = (float) ($item['quantity'] ?? 1);
            foreach (['calories', 'protein', 'carbs', 'fat'] as $nutrient) {
                $value = $this->toNullableFloat($item[$nutrient] ?? null);
                if ($value !== null) {
                    $totals[$nutrient] += ($value * $quantity);
                }
            }
        }

        return array_map(fn ($value) => round($value, 2), $totals);
    }

    private function resolveItemKey(array $data): string
    {
        $fdcId = trim((string) ($data['fdc_id'] ?? ''));
        if ($fdcId !== '') {
            return 'fdc_'.$fdcId;
        }

        return 'custom_'.sha1(strtolower(($data['name'] ?? '').'|'.($data['brand'] ?? '')));
    }

    private function toNullableFloat(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function normalizeMealType(string $mealType): string
    {
        $raw = strtolower(trim($mealType));

        return match ($raw) {
            'desayuno' => 'desayuno',
            'almuerzo', 'comida' => 'comida',
            'cena' => 'cena',
            'snack', 'snacks' => 'snacks',
            default => $raw !== '' ? $raw : 'comida',
        };
    }

    private function mergeMealItems(array $baseItems, array $newItems): array
    {
        $merged = [];

        foreach (array_merge($baseItems, $newItems) as $item) {
            if (!is_array($item)) {
                continue;
            }

            $key = $this->resolveItemKey([
                'fdc_id' => $item['fdc_id'] ?? null,
                'name' => $item['name'] ?? '',
                'brand' => $item['brand'] ?? null,
            ]);

            if (!isset($merged[$key])) {
                $merged[$key] = $item;
                $merged[$key]['quantity'] = (float) ($item['quantity'] ?? 1);
                continue;
            }

            $merged[$key]['quantity'] = round(
                ((float) ($merged[$key]['quantity'] ?? 0)) + ((float) ($item['quantity'] ?? 1)),
                2
            );
        }

        return array_values($merged);
    }
}
