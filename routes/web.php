<?php

use App\Http\Controllers\ApiNinjaExerciseController;
use App\Http\Controllers\FoodDataController;
use App\Http\Controllers\HistorialController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SpoonacularController;
use App\Models\Comida;
use App\Models\Ejercicio;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/recetas', function () {
        return view('recetas.index');
    });

    Route::get('/comida/registrar', [FoodDataController::class, 'register'])->middleware('throttle:food-search');
    Route::post('/comida/carrito/agregar', [FoodDataController::class, 'addToCart']);
    Route::post('/comida/carrito/actualizar/{itemKey}', [FoodDataController::class, 'updateCart']);
    Route::post('/comida/carrito/eliminar/{itemKey}', [FoodDataController::class, 'removeFromCart']);
    Route::post('/comida/carrito/limpiar', [FoodDataController::class, 'clearCart']);
    Route::post('/comida/carrito/importar-receta', [FoodDataController::class, 'importRecipeToCart']);
    Route::post('/comida/registrar/guardar', [FoodDataController::class, 'storeMeal']);
    Route::post('/comida/registro/editar/{mealIndex}', [FoodDataController::class, 'updateMealRecord']);
    Route::post('/comida/registro/eliminar/{mealIndex}', [FoodDataController::class, 'deleteMealRecord']);

    Route::get('/entrenamiento/registrar', [ApiNinjaExerciseController::class, 'hub']);
    Route::get('/entrenamiento/sesion', [ApiNinjaExerciseController::class, 'register']);
    Route::get('/entrenamiento/plantillas', [ApiNinjaExerciseController::class, 'templates']);
    Route::get('/entrenamiento/ejercicios', [ApiNinjaExerciseController::class, 'exerciseLookup']);
    Route::get('/entrenamiento/ejercicios/{apiKey}', [ApiNinjaExerciseController::class, 'exerciseDetail']);
    Route::post('/entrenamiento/carrito/agregar', [ApiNinjaExerciseController::class, 'addToCart']);
    Route::post('/entrenamiento/carrito/actualizar/{itemKey}', [ApiNinjaExerciseController::class, 'updateCart']);
    Route::post('/entrenamiento/carrito/eliminar/{itemKey}', [ApiNinjaExerciseController::class, 'removeFromCart']);
    Route::post('/entrenamiento/carrito/limpiar', [ApiNinjaExerciseController::class, 'clearCart']);
    Route::post('/entrenamiento/registrar/guardar', [ApiNinjaExerciseController::class, 'storeWorkout']);
    Route::post('/entrenamiento/sesion/serie', [ApiNinjaExerciseController::class, 'saveActiveSet']);
    Route::post('/entrenamiento/sesion/cancelar', [ApiNinjaExerciseController::class, 'cancelActiveSession']);
    Route::post('/entrenamiento/sesion/finalizar', [ApiNinjaExerciseController::class, 'finishActiveSession']);
    Route::post('/entrenamiento/registro/editar/{workoutIndex}', [ApiNinjaExerciseController::class, 'updateWorkoutRecord']);
    Route::post('/entrenamiento/registro/eliminar/{workoutIndex}', [ApiNinjaExerciseController::class, 'deleteWorkoutRecord']);

    Route::post('/recipes/search-by-ingredients', [SpoonacularController::class, 'searchByIngredients'])->middleware('throttle:recipes-search');
    Route::get('/recipes/search-by-nutrients', [SpoonacularController::class, 'searchByNutrients'])->middleware('throttle:recipes-search');
    Route::get('/recipes/search-complex', [SpoonacularController::class, 'searchComplex'])->middleware('throttle:recipes-search');
    Route::get('/recipes/favorites', [SpoonacularController::class, 'getFavorites']);
    Route::get('/recipes/{recipeId}/information', [SpoonacularController::class, 'recipeInformation'])->middleware('throttle:recipes-search');
    Route::get('/recipes/{recipeId}/similar', [SpoonacularController::class, 'similarRecipes'])->middleware('throttle:recipes-search');
    Route::post('/recipes/{recipeId}/favorite', [SpoonacularController::class, 'toggleFavorite']);
    Route::get('/historial', [HistorialController::class, 'index']);
    Route::get('/historial/calendar-data', [HistorialController::class, 'calendarData']);
    Route::get('/recipes/{recipeId}/nutrition-details', [SpoonacularController::class, 'nutritionDetails'])->middleware('throttle:recipes-search');
});

Route::get('/', function () {
    $kcalByType = [
        'desayuno' => 0.0,
        'comida' => 0.0,
        'cena' => 0.0,
        'snacks' => 0.0,
    ];

    $workoutByType = [
        'fuerza' => 0,
        'cardio' => 0,
        'movilidad' => 0,
        'hiit' => 0,
    ];

    $mealHistory = [];
    $workoutHistory = [];

    $mealTotals = static function (array $items): array {
        $totals = ['calories' => 0.0, 'protein' => 0.0, 'carbs' => 0.0, 'fat' => 0.0];

        foreach ($items as $item) {
            $quantity = is_numeric($item['quantity'] ?? null) ? (float) $item['quantity'] : 1.0;
            foreach (['calories', 'protein', 'carbs', 'fat'] as $nutrient) {
                $value = is_numeric($item[$nutrient] ?? null) ? (float) $item[$nutrient] : null;
                if ($value !== null) {
                    $totals[$nutrient] += ($value * $quantity);
                }
            }
        }

        return array_map(fn ($value) => round($value, 2), $totals);
    };

    $workoutTotals = static function (array $items): array {
        $totals = [
            'exercises' => 0,
            'sets' => 0,
            'reps' => 0,
            'minutes' => 0,
            'volume' => 0,
        ];

        foreach ($items as $item) {
            $sets = (int) ($item['sets'] ?? 0);
            $reps = (int) ($item['reps'] ?? 0);
            $minutes = (int) ($item['minutes'] ?? 0);

            $totals['exercises']++;
            $totals['sets'] += $sets;
            $totals['reps'] += $reps;
            $totals['minutes'] += $minutes;
            $totals['volume'] += ($sets * $reps);
        }

        return $totals;
    };

    if (Auth::check()) {
        $userId = (int) Auth::id();
        $todayDate = now()->toDateString();

        $meals = Comida::with('items')
            ->where('user_id', $userId)
            ->where(function ($query) use ($todayDate): void {
                $query
                    ->whereDate('registered_at', $todayDate)
                    ->orWhere(function ($subQuery) use ($todayDate): void {
                        $subQuery
                            ->whereNull('registered_at')
                            ->whereDate('created_at', $todayDate);
                    });
            })
            ->orderBy('registered_at')
            ->get();

        foreach ($meals as $meal) {
            $items = [];
            foreach ($meal->items as $item) {
                $items[] = [
                    'name' => $item->nombre,
                    'quantity' => (float) ($item->cantidad ?? 1),
                    'calories' => $item->calorias,
                    'protein' => $item->proteinas,
                    'carbs' => $item->carbs,
                    'fat' => $item->fat,
                ];
            }

            $totals = $mealTotals($items);
            $type = strtolower(trim((string) $meal->tipo_comida));
            if (array_key_exists($type, $kcalByType)) {
                $kcalByType[$type] += (float) ($totals['calories'] ?? 0);
            }

            $mealHistory[] = [
                'registered_at' => optional($meal->registered_at ?? $meal->created_at)?->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i'),
                'meal_type' => $meal->tipo_comida,
                'notes' => $meal->notes,
                'items' => $items,
                'totals' => $totals,
            ];
        }

        $workouts = Ejercicio::with('items')
            ->where('user_id', $userId)
            ->where(function ($query) use ($todayDate): void {
                $query
                    ->whereDate('registered_at', $todayDate)
                    ->orWhere(function ($subQuery) use ($todayDate): void {
                        $subQuery
                            ->whereNull('registered_at')
                            ->whereDate('created_at', $todayDate);
                    });
            })
            ->orderBy('registered_at')
            ->get();

        foreach ($workouts as $workout) {
            $items = [];
            foreach ($workout->items as $item) {
                $items[] = [
                    'name' => $item->nombre,
                    'sets' => (int) ($item->sets ?? 0),
                    'reps' => (int) ($item->reps ?? 0),
                    'minutes' => (int) ($item->minutes ?? 0),
                ];
            }

            $totals = $workoutTotals($items);
            $type = strtolower(trim((string) $workout->tipo_entrene));
            if (array_key_exists($type, $workoutByType)) {
                $workoutByType[$type] += (int) ($totals['volume'] ?? 0);
            }

            $workoutHistory[] = [
                'registered_at' => optional($workout->registered_at ?? $workout->created_at)?->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i'),
                'training_type' => $workout->tipo_entrene,
                'notes' => $workout->notes,
                'items' => $items,
                'totals' => $totals,
            ];
        }
    }

    $kcalByType = array_map(fn ($value) => round($value, 2), $kcalByType);
    $kcalTotal = round(array_sum($kcalByType), 2);
    $workoutTotalVolume = array_sum($workoutByType);

    return view('inicio', [
        'kcal_total' => $kcalTotal,
        'kcal_by_type' => [
            ['label' => 'Desayuno', 'kcal' => $kcalByType['desayuno']],
            ['label' => 'Comida', 'kcal' => $kcalByType['comida']],
            ['label' => 'Cena', 'kcal' => $kcalByType['cena']],
            ['label' => 'Snacks', 'kcal' => $kcalByType['snacks']],
        ],
        'meal_history' => array_reverse($mealHistory),
        'workout_total_volume' => $workoutTotalVolume,
        'workout_by_type' => [
            ['label' => 'Fuerza', 'volume' => $workoutByType['fuerza']],
            ['label' => 'Cardio', 'volume' => $workoutByType['cardio']],
            ['label' => 'Movilidad', 'volume' => $workoutByType['movilidad']],
            ['label' => 'HIIT', 'volume' => $workoutByType['hiit']],
        ],
        'workout_history' => array_reverse($workoutHistory),
    ]);
})->name('home');

require __DIR__.'/auth.php';
