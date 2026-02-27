<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ApiNinjaExerciseController;
use App\Http\Controllers\FoodDataController;
use App\Http\Controllers\SpoonacularController;

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

Route::get('/', function () {
    $history = session('meal_history', []);
    $workoutHistory = session('workout_history', []);
    $kcalByType = [
        'desayuno' => 0.0,
        'comida' => 0.0,
        'cena' => 0.0,
        'snacks' => 0.0,
    ];

    foreach ($history as $meal) {
        $rawType = strtolower(trim((string) ($meal['meal_type'] ?? '')));
        $normalizedType = match ($rawType) {
            'desayuno' => 'desayuno',
            'almuerzo', 'comida' => 'comida',
            'cena' => 'cena',
            'snack', 'snacks' => 'snacks',
            default => null,
        };

        if ($normalizedType === null) {
            continue;
        }

        $kcal = is_numeric($meal['totals']['calories'] ?? null)
            ? (float) $meal['totals']['calories']
            : 0.0;

        $kcalByType[$normalizedType] += $kcal;
    }

    $kcalByType = array_map(fn ($value) => round($value, 2), $kcalByType);
    $kcalTotal = round(array_sum($kcalByType), 2);

    $workoutByType = [
        'fuerza' => 0,
        'cardio' => 0,
        'movilidad' => 0,
        'hiit' => 0,
    ];

    foreach ($workoutHistory as $workout) {
        $type = strtolower(trim((string) ($workout['training_type'] ?? '')));
        if (!array_key_exists($type, $workoutByType)) {
            continue;
        }
        $volume = is_numeric($workout['totals']['volume'] ?? null)
            ? (int) $workout['totals']['volume']
            : 0;
        $workoutByType[$type] += $volume;
    }

    $workoutTotalVolume = array_sum($workoutByType);

    return view('inicio', [
        'kcal_total' => $kcalTotal,
        'kcal_by_type' => [
            ['label' => 'Desayuno', 'kcal' => $kcalByType['desayuno']],
            ['label' => 'Comida', 'kcal' => $kcalByType['comida']],
            ['label' => 'Cena', 'kcal' => $kcalByType['cena']],
            ['label' => 'Snacks', 'kcal' => $kcalByType['snacks']],
        ],
        'meal_history' => array_reverse($history),
        'workout_total_volume' => $workoutTotalVolume,
        'workout_by_type' => [
            ['label' => 'Fuerza', 'volume' => $workoutByType['fuerza']],
            ['label' => 'Cardio', 'volume' => $workoutByType['cardio']],
            ['label' => 'Movilidad', 'volume' => $workoutByType['movilidad']],
            ['label' => 'HIIT', 'volume' => $workoutByType['hiit']],
        ],
        'workout_history' => array_reverse($workoutHistory),
    ]);
});

Route::get('/login', function () {
    return view('login');
});
Route::get('/recetas', function () {
    return view('recetas.index');
});

Route::get('/comida/registrar', [FoodDataController::class, 'register']);
Route::post('/comida/carrito/agregar', [FoodDataController::class, 'addToCart']);
Route::post('/comida/carrito/actualizar/{itemKey}', [FoodDataController::class, 'updateCart']);
Route::post('/comida/carrito/eliminar/{itemKey}', [FoodDataController::class, 'removeFromCart']);
Route::post('/comida/carrito/limpiar', [FoodDataController::class, 'clearCart']);
Route::post('/comida/carrito/importar-receta', [FoodDataController::class, 'importRecipeToCart']);
Route::post('/comida/registrar/guardar', [FoodDataController::class, 'storeMeal']);
Route::post('/comida/registro/editar/{mealIndex}', [FoodDataController::class, 'updateMealRecord']);
Route::post('/comida/registro/eliminar/{mealIndex}', [FoodDataController::class, 'deleteMealRecord']);

Route::get('/entrenamiento/registrar', [ApiNinjaExerciseController::class, 'register']);
Route::post('/entrenamiento/carrito/agregar', [ApiNinjaExerciseController::class, 'addToCart']);
Route::post('/entrenamiento/carrito/actualizar/{itemKey}', [ApiNinjaExerciseController::class, 'updateCart']);
Route::post('/entrenamiento/carrito/eliminar/{itemKey}', [ApiNinjaExerciseController::class, 'removeFromCart']);
Route::post('/entrenamiento/carrito/limpiar', [ApiNinjaExerciseController::class, 'clearCart']);
Route::post('/entrenamiento/registrar/guardar', [ApiNinjaExerciseController::class, 'storeWorkout']);
Route::post('/entrenamiento/registro/editar/{workoutIndex}', [ApiNinjaExerciseController::class, 'updateWorkoutRecord']);
Route::post('/entrenamiento/registro/eliminar/{workoutIndex}', [ApiNinjaExerciseController::class, 'deleteWorkoutRecord']);
Route::post('/recipes/search-by-ingredients', [SpoonacularController::class, 'searchByIngredients']);
Route::get('/recipes/{recipeId}/nutrition-details', [SpoonacularController::class, 'nutritionDetails']);

