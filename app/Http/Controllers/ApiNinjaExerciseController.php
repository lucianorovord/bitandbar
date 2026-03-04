<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApiNinjaExerciseSearchRequest;
use App\Models\Ejercicio;
use App\Models\EjercicioItem;
use App\Services\ApiNinjaExerciseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class ApiNinjaExerciseController extends Controller
{
    public function __construct(private ApiNinjaExerciseService $exerciseService)
    {
    }

    public function register(ApiNinjaExerciseSearchRequest $request): View
    {
        $filters = [
            'muscle' => trim((string) $request->validated('muscle', '')),
            'difficulty' => trim((string) $request->validated('difficulty', '')),
        ];

        $apiFilters = array_filter($filters, fn ($value) => $value !== '');

        $allExercises = [];
        $error = null;
        $perPage = 3;
        $page = max(1, (int) $request->query('page', 1));
        $total = 0;

        if (!empty($apiFilters['muscle'])) {
            try {
                $result = $this->exerciseService->searchPaged($apiFilters, $page, $perPage);
                $allExercises = $result['items'];
                $total = (int) ($result['total'] ?? 0);
            } catch (Throwable $exception) {
                report($exception);
                $error = $this->friendlyApiError($exception->getMessage());
            }
        }

        $exercises = new LengthAwarePaginator(
            $allExercises,
            $total,
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        return view('entrenamiento.registrar', [
            'filters' => $filters,
            'has_filters' => !empty($apiFilters['muscle']),
            'exercises' => $exercises,
            'error' => $error,
            'workout_cart' => $this->cartItems(),
            'workout_totals' => $this->cartTotals(),
            'workout_history' => $this->workoutHistory(),
        ]);
    }

    public function addToCart(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:120'],
            'muscle' => ['nullable', 'string', 'max:120'],
            'difficulty' => ['nullable', 'string', 'max:120'],
            'equipment' => ['nullable', 'string', 'max:255'],
            'instructions' => ['nullable', 'string'],
            'safety_info' => ['nullable', 'string'],
            'sets' => ['nullable', 'integer', 'min:1', 'max:20'],
            'reps' => ['nullable', 'integer', 'min:1', 'max:100'],
            'minutes' => ['nullable', 'integer', 'min:0', 'max:300'],
            'set_weights' => ['nullable', 'string', 'max:255'],
            'muscle_filter' => ['nullable', 'string', 'max:80'],
            'difficulty_filter' => ['nullable', 'string', 'max:80'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $cart = $this->cartItems();
        $itemKey = $this->itemKey($data);
        $defaults = [
            'sets' => (int) ($data['sets'] ?? 3),
            'reps' => (int) ($data['reps'] ?? 12),
            'minutes' => (int) ($data['minutes'] ?? 0),
            'set_weights' => $this->normalizeSetWeights($data['set_weights'] ?? null, (int) ($data['sets'] ?? 3)),
        ];

        if (isset($cart[$itemKey])) {
            $mergedSets = min(20, ((int) $cart[$itemKey]['sets']) + $defaults['sets']);
            $existingSetWeights = $this->normalizeStoredSetWeights(
                $cart[$itemKey]['set_weights'] ?? null,
                (int) ($cart[$itemKey]['sets'] ?? 0)
            );
            $combinedSetWeights = array_slice(array_merge($existingSetWeights, $defaults['set_weights']), 0, $mergedSets);
            $cart[$itemKey]['sets'] = $mergedSets;
            $cart[$itemKey]['set_weights'] = $this->padSetWeights($combinedSetWeights, $mergedSets);
        } else {
            $cart[$itemKey] = [
                'name' => $data['name'],
                'type' => $data['type'] ?? null,
                'muscle' => $data['muscle'] ?? null,
                'difficulty' => $data['difficulty'] ?? null,
                'equipment' => $data['equipment'] ?? null,
                'instructions' => $data['instructions'] ?? null,
                'safety_info' => $data['safety_info'] ?? null,
                'sets' => $defaults['sets'],
                'reps' => $defaults['reps'],
                'minutes' => $defaults['minutes'],
                'set_weights' => $defaults['set_weights'],
            ];
        }

        session(['workout_cart' => $cart]);

        return redirect()->to($this->trainingRegisterUrl($data))
            ->with('workout_success', 'Ejercicio anadido al registro.');
    }

    public function updateCart(Request $request, string $itemKey): RedirectResponse
    {
        $data = $request->validate([
            'sets' => ['required', 'integer', 'min:1', 'max:20'],
            'reps' => ['required', 'integer', 'min:1', 'max:100'],
            'minutes' => ['nullable', 'integer', 'min:0', 'max:300'],
            'set_weights' => ['nullable', 'string', 'max:255'],
            'muscle_filter' => ['nullable', 'string', 'max:80'],
            'difficulty_filter' => ['nullable', 'string', 'max:80'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $cart = $this->cartItems();
        if (isset($cart[$itemKey])) {
            $cart[$itemKey]['sets'] = (int) $data['sets'];
            $cart[$itemKey]['reps'] = (int) $data['reps'];
            $cart[$itemKey]['minutes'] = (int) ($data['minutes'] ?? 0);
            $cart[$itemKey]['set_weights'] = $this->normalizeSetWeights(
                $data['set_weights'] ?? null,
                (int) $data['sets']
            );
            session(['workout_cart' => $cart]);
        }

        return redirect()->to($this->trainingRegisterUrl($data))
            ->with('workout_success', 'Ejercicio actualizado.');
    }

    public function removeFromCart(Request $request, string $itemKey): RedirectResponse
    {
        $data = $request->validate([
            'muscle_filter' => ['nullable', 'string', 'max:80'],
            'difficulty_filter' => ['nullable', 'string', 'max:80'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $cart = $this->cartItems();
        unset($cart[$itemKey]);
        session(['workout_cart' => $cart]);

        return redirect()->to($this->trainingRegisterUrl($data))
            ->with('workout_success', 'Ejercicio eliminado del registro.');
    }

    public function clearCart(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'muscle_filter' => ['nullable', 'string', 'max:80'],
            'difficulty_filter' => ['nullable', 'string', 'max:80'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        session()->forget('workout_cart');

        return redirect()->to($this->trainingRegisterUrl($data))
            ->with('workout_success', 'Registro de ejercicios vaciado.');
    }

    public function storeWorkout(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'training_type' => ['required', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:600'],
            'muscle_filter' => ['nullable', 'string', 'max:80'],
            'difficulty_filter' => ['nullable', 'string', 'max:80'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $cart = $this->cartItems();
        if (empty($cart)) {
            return redirect()->to($this->trainingRegisterUrl($data))
                ->with('workout_error', 'No puedes registrar un entrenamiento vacio.');
        }

        $userId = (int) Auth::id();
        $trainingType = $this->normalizeTrainingType((string) $data['training_type']);
        $newNotes = trim((string) ($data['notes'] ?? ''));

        $workout = Ejercicio::with('items')
            ->where('user_id', $userId)
            ->where('tipo_entrene', $trainingType)
            ->first();

        if ($workout) {
            $existingItems = array_map(
                fn (EjercicioItem $item): array => $this->workoutItemFromModel($item),
                $workout->items->all()
            );
            $mergedItems = $this->mergeWorkoutItems($existingItems, array_values($cart));

            $workout->notes = $this->mergeNotes($workout->notes, $newNotes);
            $workout->registered_at = now();
            $workout->save();

            $workout->items()->delete();
            $this->persistWorkoutItems($workout, $mergedItems);
        } else {
            $workout = Ejercicio::create([
                'user_id' => $userId,
                'tipo_entrene' => $trainingType,
                'notes' => $newNotes !== '' ? $newNotes : null,
                'registered_at' => now(),
            ]);

            $this->persistWorkoutItems($workout, array_values($cart));
        }

        session()->forget('workout_cart');

        return redirect()->to($this->trainingRegisterUrl($data))
            ->with('workout_success', 'Entrenamiento registrado correctamente.');
    }

    public function updateWorkoutRecord(Request $request, string $workoutIndex): RedirectResponse
    {
        $data = $request->validate([
            'training_type' => ['required', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:600'],
            'muscle_filter' => ['nullable', 'string', 'max:80'],
            'difficulty_filter' => ['nullable', 'string', 'max:80'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $workout = Ejercicio::where('user_id', Auth::id())->find((int) $workoutIndex);
        if (!$workout) {
            return redirect()->to($this->trainingRegisterUrl($data))
                ->with('workout_error', 'No se encontro el registro de entrenamiento para editar.');
        }

        $workout->tipo_entrene = $this->normalizeTrainingType((string) $data['training_type']);
        $workout->notes = $data['notes'] ?? null;
        $workout->save();

        return redirect()->to($this->trainingRegisterUrl($data))
            ->with('workout_success', 'Registro de entrenamiento actualizado.');
    }

    public function deleteWorkoutRecord(Request $request, string $workoutIndex): RedirectResponse
    {
        $data = $request->validate([
            'muscle_filter' => ['nullable', 'string', 'max:80'],
            'difficulty_filter' => ['nullable', 'string', 'max:80'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $workout = Ejercicio::where('user_id', Auth::id())->find((int) $workoutIndex);
        if (!$workout) {
            return redirect()->to($this->trainingRegisterUrl($data))
                ->with('workout_error', 'No se encontro el registro de entrenamiento para eliminar.');
        }

        $workout->delete();

        return redirect()->to($this->trainingRegisterUrl($data))
            ->with('workout_success', 'Registro de entrenamiento eliminado.');
    }

    private function friendlyApiError(string $technicalMessage): string
    {
        if (str_contains($technicalMessage, '(429)')) {
            $nextReset = now()->addDay()->startOfDay()->format('d/m/Y H:i');
            return "Se alcanzo el limite de llamadas diarias de la API. Podras volver a consultar a partir de {$nextReset}.";
        }

        if (str_contains($technicalMessage, '(401)') || str_contains($technicalMessage, '(403)')) {
            return 'La API rechazo la autenticacion. Revisa API_NINJA_KEY.';
        }

        return 'No se pudo consultar la API de ejercicios en este momento.';
    }

    private function workoutHistory(): array
    {
        $workouts = Ejercicio::with('items')
            ->where('user_id', Auth::id())
            ->orderBy('registered_at')
            ->get();

        $history = [];
        foreach ($workouts as $workout) {
            $items = array_map(fn (EjercicioItem $item): array => $this->workoutItemFromModel($item), $workout->items->all());

            $history[$workout->id] = [
                'registered_at' => optional($workout->registered_at ?? $workout->created_at)?->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i'),
                'training_type' => $workout->tipo_entrene,
                'notes' => $workout->notes,
                'items' => $items,
                'totals' => $this->cartTotals($items),
            ];
        }

        return $history;
    }

    private function workoutItemFromModel(EjercicioItem $item): array
    {
        return [
            'name' => $item->nombre,
            'type' => $item->tipo,
            'muscle' => $item->musculo,
            'difficulty' => $item->dificultad,
            'equipment' => $item->equipamiento,
            'instructions' => $item->instrucciones,
            'safety_info' => $item->safety_info,
            'sets' => (int) $item->sets,
            'reps' => (int) $item->reps,
            'minutes' => (int) $item->minutes,
            'set_weights' => $this->normalizeStoredSetWeights($item->set_weights, (int) $item->sets),
        ];
    }

    private function persistWorkoutItems(Ejercicio $workout, array $items): void
    {
        $payload = array_map(function (array $item): array {
            return [
                'nombre' => (string) ($item['name'] ?? 'Ejercicio'),
                'tipo' => $item['type'] ?? null,
                'musculo' => $item['muscle'] ?? null,
                'dificultad' => $item['difficulty'] ?? null,
                'equipamiento' => $item['equipment'] ?? null,
                'instrucciones' => $item['instructions'] ?? null,
                'safety_info' => $item['safety_info'] ?? null,
                'sets' => (int) ($item['sets'] ?? 0),
                'reps' => (int) ($item['reps'] ?? 0),
                'minutes' => (int) ($item['minutes'] ?? 0),
                'set_weights' => $this->normalizeStoredSetWeights(
                    $item['set_weights'] ?? null,
                    (int) ($item['sets'] ?? 0)
                ),
            ];
        }, $items);

        $workout->items()->createMany($payload);
    }

    private function mergeNotes(?string $existingNotes, string $newNotes): ?string
    {
        $existingNotes = trim((string) $existingNotes);
        if ($newNotes === '') {
            return $existingNotes !== '' ? $existingNotes : null;
        }

        if ($existingNotes === '') {
            return $newNotes;
        }

        return $existingNotes."\n".$newNotes;
    }

    private function cartItems(): array
    {
        $cart = session('workout_cart', []);

        return is_array($cart) ? $cart : [];
    }

    private function cartTotals(?array $cart = null): array
    {
        $cart = $cart ?? $this->cartItems();
        $totals = [
            'exercises' => 0,
            'sets' => 0,
            'reps' => 0,
            'minutes' => 0,
            'volume' => 0,
        ];

        foreach ($cart as $item) {
            $sets = (int) ($item['sets'] ?? 0);
            $reps = (int) ($item['reps'] ?? 0);
            $minutes = (int) ($item['minutes'] ?? 0);
            $setWeights = $this->normalizeStoredSetWeights($item['set_weights'] ?? null, $sets);

            $totals['exercises']++;
            $totals['sets'] += $sets;
            $totals['reps'] += $reps;
            $totals['minutes'] += $minutes;
            $totals['volume'] += $this->workoutVolumeForItem($sets, $reps, $setWeights);
        }

        return $totals;
    }

    private function itemKey(array $data): string
    {
        return sha1(
            strtolower(trim((string) ($data['name'] ?? ''))).'|'.
            strtolower(trim((string) ($data['muscle'] ?? ''))).'|'.
            strtolower(trim((string) ($data['type'] ?? '')))
        );
    }

    private function trainingRegisterUrl(array $data): string
    {
        $params = [];
        foreach ([
            'muscle_filter' => 'muscle',
            'difficulty_filter' => 'difficulty',
            'page' => 'page',
        ] as $source => $target) {
            $value = $data[$source] ?? null;
            if ($value !== null && $value !== '') {
                $params[$target] = $value;
            }
        }

        $base = url('/entrenamiento/registrar');

        return !empty($params) ? $base.'?'.http_build_query($params) : $base;
    }

    private function normalizeTrainingType(string $trainingType): string
    {
        $raw = strtolower(trim($trainingType));

        return match ($raw) {
            'fuerza' => 'fuerza',
            'cardio' => 'cardio',
            'movilidad' => 'movilidad',
            'hiit' => 'hiit',
            default => $raw !== '' ? $raw : 'fuerza',
        };
    }

    private function mergeWorkoutItems(array $baseItems, array $newItems): array
    {
        $merged = [];

        foreach (array_merge($baseItems, $newItems) as $item) {
            if (!is_array($item)) {
                continue;
            }

            $key = $this->itemKey($item);
            if (!isset($merged[$key])) {
                $merged[$key] = $item;
                $merged[$key]['sets'] = (int) ($item['sets'] ?? 0);
                $merged[$key]['reps'] = (int) ($item['reps'] ?? 0);
                $merged[$key]['minutes'] = (int) ($item['minutes'] ?? 0);
                $merged[$key]['set_weights'] = $this->normalizeStoredSetWeights(
                    $item['set_weights'] ?? null,
                    (int) ($item['sets'] ?? 0)
                );
                continue;
            }

            $existingSets = (int) ($merged[$key]['sets'] ?? 0);
            $existingSetWeights = $this->normalizeStoredSetWeights(
                $merged[$key]['set_weights'] ?? null,
                $existingSets
            );
            $incomingSetWeights = $this->normalizeStoredSetWeights(
                $item['set_weights'] ?? null,
                (int) ($item['sets'] ?? 0)
            );

            $merged[$key]['sets'] += (int) ($item['sets'] ?? 0);
            $merged[$key]['reps'] += (int) ($item['reps'] ?? 0);
            $merged[$key]['minutes'] += (int) ($item['minutes'] ?? 0);
            $merged[$key]['set_weights'] = $this->padSetWeights(
                array_merge($existingSetWeights, $incomingSetWeights),
                (int) ($merged[$key]['sets'] ?? 0)
            );
        }

        return array_values($merged);
    }

    private function normalizeSetWeights(null|string $rawWeights, int $sets): array
    {
        if ($sets <= 0) {
            return [];
        }

        if ($rawWeights === null || trim($rawWeights) === '') {
            return array_fill(0, $sets, 0.0);
        }

        $tokens = preg_split('/\s*,\s*/', trim($rawWeights)) ?: [];
        $weights = [];

        foreach ($tokens as $token) {
            if ($token === '') {
                continue;
            }

            if (!is_numeric($token)) {
                throw ValidationException::withMessages([
                    'set_weights' => 'Los pesos por serie deben ser numeros separados por comas (ej: 10,15,20).',
                ]);
            }

            $value = round((float) $token, 2);
            if ($value < 0 || $value > 1000) {
                throw ValidationException::withMessages([
                    'set_weights' => 'Cada peso por serie debe estar entre 0 y 1000 kg.',
                ]);
            }

            $weights[] = $value;
        }

        if (empty($weights)) {
            return array_fill(0, $sets, 0.0);
        }

        if (count($weights) === 1 && $sets > 1) {
            return array_fill(0, $sets, $weights[0]);
        }

        if (count($weights) !== $sets) {
            throw ValidationException::withMessages([
                'set_weights' => "Debes indicar exactamente {$sets} pesos (uno por serie) o un solo peso para todas las series.",
            ]);
        }

        return $weights;
    }

    private function normalizeStoredSetWeights(mixed $rawWeights, int $sets): array
    {
        if ($sets <= 0) {
            return [];
        }

        if (!is_array($rawWeights)) {
            return array_fill(0, $sets, 0.0);
        }

        $weights = array_values(array_map(
            fn ($value): float => max(0, round((float) $value, 2)),
            $rawWeights
        ));

        return $this->padSetWeights($weights, $sets);
    }

    private function padSetWeights(array $weights, int $sets): array
    {
        if ($sets <= 0) {
            return [];
        }

        $weights = array_values(array_map(fn ($value): float => round((float) $value, 2), $weights));
        if (empty($weights)) {
            return array_fill(0, $sets, 0.0);
        }

        $weights = array_slice($weights, 0, $sets);
        $filler = $weights[array_key_last($weights)] ?? 0.0;

        while (count($weights) < $sets) {
            $weights[] = $filler;
        }

        return $weights;
    }

    private function workoutVolumeForItem(int $sets, int $reps, array $setWeights): float
    {
        if ($sets <= 0 || $reps <= 0) {
            return 0.0;
        }

        $effectiveWeights = $this->normalizeStoredSetWeights($setWeights, $sets);
        $sumWeights = array_sum($effectiveWeights);

        if ($sumWeights > 0) {
            return round($sumWeights * $reps, 2);
        }

        return (float) ($sets * $reps);
    }
}
