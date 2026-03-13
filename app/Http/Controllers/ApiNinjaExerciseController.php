<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApiNinjaExerciseSearchRequest;
use App\Models\Exercise;
use App\Models\Ejercicio;
use App\Models\EjercicioItem;
use App\Services\ApiNinjaExerciseService;
use App\Services\TextTranslationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class ApiNinjaExerciseController extends Controller
{
    public function __construct(
        private ApiNinjaExerciseService $exerciseService,
        private TextTranslationService $translator
    )
    {
    }

    public function hub(): View
    {
        $latest = Ejercicio::with('items')
            ->where('user_id', Auth::id())
            ->orderByDesc('registered_at')
            ->orderByDesc('created_at')
            ->limit(3)
            ->get()
            ->map(function (Ejercicio $workout): array {
                $items = array_map(
                    fn (EjercicioItem $item): array => $this->workoutItemFromModel($item),
                    $workout->items->all()
                );

                return [
                    'training_type' => $workout->tipo_entrene,
                    'registered_at' => optional($workout->registered_at ?? $workout->created_at)?->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i'),
                    'totals' => $this->cartTotals($items),
                ];
            })
            ->values()
            ->all();

        return view('entrenamiento.hub', [
            'latest_workouts' => $latest,
        ]);
    }

    public function templates(): View
    {
        return view('entrenamiento.templates');
    }

    public function exerciseLookup(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:30'],
        ]);

        $query = trim((string) ($data['q'] ?? ''));
        $page = max(1, (int) ($data['page'] ?? 1));
        $perPage = max(1, min(30, (int) ($data['per_page'] ?? 20)));

        if (Exercise::query()->count() === 0) {
            try {
                if ($query === '') {
                    $result = $this->exerciseService->searchPaged([], $page, $perPage);
                    return response()->json([
                        'items' => array_map(fn (array $item): array => $this->compactExerciseForPicker($item), $result['items'] ?? []),
                        'total' => (int) ($result['total'] ?? 0),
                        'page' => $page,
                        'needs_sync' => true,
                    ]);
                }

                $queries = [$query];
                $translated = trim((string) ($this->translator->translate($query, 'es', 'en') ?? ''));
                if ($translated !== '' && !in_array(mb_strtolower($translated), array_map(fn ($value) => mb_strtolower($value), $queries), true)) {
                    $queries[] = $translated;
                }

                $merged = [];
                foreach ($queries as $nameQuery) {
                    $result = $this->exerciseService->searchPaged(['name' => $nameQuery], 1, $perPage);
                    foreach (($result['items'] ?? []) as $item) {
                        $key = $this->itemKey($item);
                        $merged[$key] = $item;
                    }
                }

                return response()->json([
                    'items' => array_map(
                        fn (array $item): array => $this->compactExerciseForPicker($item),
                        array_values($merged)
                    ),
                    'total' => count($merged),
                    'page' => 1,
                    'needs_sync' => true,
                ]);
            } catch (Throwable $exception) {
                report($exception);
                return response()->json([
                    'items' => [],
                    'total' => 0,
                    'page' => $page,
                    'error' => $this->friendlyApiError($exception->getMessage()),
                    'needs_sync' => true,
                ], 500);
            }
        }

        $paginator = Exercise::query()
            ->when($query !== '', function ($builder) use ($query): void {
                $builder->where(function ($inner) use ($query): void {
                    $inner
                        ->where('name_es', 'like', "%{$query}%")
                        ->orWhere('name', 'like', "%{$query}%")
                        ->orWhere('muscle_es', 'like', "%{$query}%")
                        ->orWhere('muscle', 'like', "%{$query}%");
                });
            })
            ->paginate(perPage: $perPage, page: $page);

        return response()->json([
            'items' => $paginator->getCollection()->map(fn (Exercise $exercise): array => $exercise->toPickerArray())->all(),
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
        ]);
    }

    public function exerciseDetail(string $apiKey): JsonResponse
    {
        $exercise = Exercise::query()->where('api_key', $apiKey)->first();

        if (!$exercise) {
            return response()->json([
                'message' => 'Ejercicio no encontrado.',
            ], 404);
        }

        return response()->json($exercise->toFullArray());
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
            $exerciseQuery = Exercise::query()
                ->where(function ($builder) use ($filters): void {
                    $builder
                        ->where('muscle', $filters['muscle'])
                        ->orWhere('muscle_es', $filters['muscle']);
                });

            if (!empty($apiFilters['difficulty'])) {
                $exerciseQuery->where(function ($builder) use ($filters): void {
                    $builder
                        ->where('difficulty', $filters['difficulty'])
                        ->orWhere('difficulty_es', $filters['difficulty']);
                });
            }

            $paginator = $exerciseQuery->paginate(perPage: $perPage, page: $page);
            $allExercises = $paginator->getCollection()->map(fn (Exercise $exercise): array => $exercise->toFullArray())->all();
            $total = $paginator->total();
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

        $workoutHistory = $this->workoutHistory();

        return view('entrenamiento.registrar', [
            'filters' => $filters,
            'has_filters' => !empty($apiFilters['muscle']),
            'exercises' => $exercises,
            'error' => $error,
            'workout_cart' => $this->cartItems(),
            'workout_totals' => $this->cartTotals(),
            'workout_history' => $workoutHistory,
            'exercise_previous_map' => $this->exercisePreviousMap($workoutHistory),
            'today_date' => now()->toDateString(),
        ]);
    }

    public function cancelActiveSession(Request $request): JsonResponse
    {
        $this->clearActiveWorkoutSession();

        try {
            return response()->json([
                'ok' => true,
            ]);
        } catch (Throwable) {
            return response()->json([
                'ok' => true,
            ]);
        }
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

    public function saveActiveSet(Request $request): JsonResponse
    {
        $data = $request->validate([
            'training_name' => ['nullable', 'string', 'max:120'],
            'training_type' => ['nullable', 'string', 'max:60'],
            'started_at' => ['nullable', 'date'],
            'exercise' => ['required', 'array'],
            'exercise.key' => ['required', 'string', 'max:80'],
            'exercise.name' => ['required', 'string', 'max:255'],
            'exercise.type' => ['nullable', 'string', 'max:120'],
            'exercise.muscle' => ['nullable', 'string', 'max:120'],
            'exercise.difficulty' => ['nullable', 'string', 'max:120'],
            'exercise.equipment' => ['nullable', 'string', 'max:255'],
            'exercise.instructions' => ['nullable', 'string'],
            'exercise.safety_info' => ['nullable', 'string'],
            'set' => ['required', 'array'],
            'set.index' => ['required', 'integer', 'min:0', 'max:99'],
            'set.kg' => ['required', 'numeric', 'min:0', 'max:1000'],
            'set.reps' => ['required', 'integer', 'min:1', 'max:200'],
            'set.rpe' => ['nullable', 'integer', 'min:1', 'max:10'],
            'set.previous' => ['nullable', 'string', 'max:80'],
        ]);

        $state = $this->activeWorkoutSession();
        if (empty($state)) {
            $state = [
                'id' => (string) Str::uuid(),
                'training_name' => 'Entrenamiento activo',
                'training_type' => 'fuerza',
                'started_at' => now()->toIso8601String(),
                'exercises' => [],
            ];
        }

        $state['training_name'] = trim((string) ($data['training_name'] ?? $state['training_name'] ?? 'Entrenamiento activo'));
        $state['training_type'] = $this->normalizeTrainingType((string) ($data['training_type'] ?? $state['training_type'] ?? 'fuerza'));
        if (!empty($data['started_at'])) {
            $state['started_at'] = Carbon::parse((string) $data['started_at'])->toIso8601String();
        }

        $exercise = $data['exercise'];
        $set = $data['set'];
        $index = (int) $set['index'];
        $exerciseIndex = $this->activeExerciseIndex($state, (string) $exercise['key']);

        if ($exerciseIndex === null) {
            $state['exercises'][] = [
                'key' => (string) $exercise['key'],
                'name' => (string) $exercise['name'],
                'type' => $exercise['type'] ?? null,
                'muscle' => $exercise['muscle'] ?? null,
                'difficulty' => $exercise['difficulty'] ?? null,
                'equipment' => $exercise['equipment'] ?? null,
                'instructions' => $exercise['instructions'] ?? null,
                'safety_info' => $exercise['safety_info'] ?? null,
                'sets' => [],
            ];
            $exerciseIndex = array_key_last($state['exercises']);
        }

        $sets = is_array($state['exercises'][$exerciseIndex]['sets'] ?? null) ? $state['exercises'][$exerciseIndex]['sets'] : [];
        while (count($sets) <= $index) {
            $sets[] = [
                'kg' => 0,
                'reps' => 12,
                'rpe' => 7,
                'completed' => false,
                'previous' => null,
                'completed_at' => null,
            ];
        }

        $sets[$index] = [
            'kg' => round((float) $set['kg'], 2),
            'reps' => (int) $set['reps'],
            'rpe' => (int) ($set['rpe'] ?? 7),
            'completed' => true,
            'previous' => $set['previous'] ?? null,
            'completed_at' => now()->toIso8601String(),
        ];

        $state['exercises'][$exerciseIndex]['sets'] = array_values($sets);
        $state['last_saved_at'] = now()->toIso8601String();

        $this->setActiveWorkoutSession($state);

        return response()->json([
            'ok' => true,
            'saved_at' => $state['last_saved_at'],
        ]);
    }

    public function finishActiveSession(Request $request): JsonResponse
    {
        $data = $request->validate([
            'training_name' => ['nullable', 'string', 'max:120'],
            'training_type' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:600'],
            'registered_at' => ['nullable', 'date_format:Y-m-d'],
            'session_state' => ['nullable', 'array'],
        ]);

        $state = $this->activeWorkoutSession();
        if (is_array($data['session_state'] ?? null)) {
            $state = $this->normalizeActiveSessionState($data['session_state']);
        }

        $items = $this->completedItemsFromActiveSession($state);
        if (empty($items)) {
            return response()->json([
                'ok' => false,
                'message' => 'No hay series completadas para guardar.',
            ], 422);
        }

        $registeredAt = !empty($data['registered_at'])
            ? Carbon::createFromFormat('Y-m-d', (string) $data['registered_at'])->setTimeFrom(now())
            : now();

        $trainingType = $this->normalizeTrainingType((string) ($data['training_type'] ?? $state['training_type'] ?? 'fuerza'));
        $trainingName = trim((string) ($data['training_name'] ?? $state['training_name'] ?? ''));
        $notes = trim((string) ($data['notes'] ?? ''));
        $finalNotes = trim(
            ($trainingName !== '' ? 'Sesion: '.$trainingName : '').
            ($notes !== '' ? (($trainingName !== '' ? "\n" : '').$notes) : '')
        );

        $this->upsertWorkout(
            (int) Auth::id(),
            $trainingType,
            $finalNotes,
            $registeredAt,
            $items
        );

        $this->clearActiveWorkoutSession();

        return response()->json([
            'ok' => true,
            'message' => 'Entrenamiento registrado correctamente.',
        ]);
    }

    public function storeWorkout(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'training_type' => ['required', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:600'],
            'registered_at' => ['required', 'date_format:Y-m-d'],
            'muscle_filter' => ['nullable', 'string', 'max:80'],
            'difficulty_filter' => ['nullable', 'string', 'max:80'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $cart = $this->cartItems();
        if (empty($cart)) {
            return redirect()->to($this->trainingRegisterUrl($data))
                ->with('workout_error', 'No puedes registrar un entrenamiento vacio.');
        }

        $registeredAt = Carbon::createFromFormat('Y-m-d', (string) $data['registered_at'])
            ->setTimeFrom(now());
        $this->upsertWorkout(
            (int) Auth::id(),
            $this->normalizeTrainingType((string) $data['training_type']),
            trim((string) ($data['notes'] ?? '')),
            $registeredAt,
            array_values($cart)
        );

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

    private function upsertWorkout(
        int $userId,
        string $trainingType,
        string $newNotes,
        Carbon $registeredAt,
        array $items
    ): void {
        $workout = Ejercicio::with('items')
            ->where('user_id', $userId)
            ->where('tipo_entrene', $trainingType)
            ->where(function ($query) use ($registeredAt): void {
                $query
                    ->whereDate('registered_at', $registeredAt->toDateString())
                    ->orWhere(function ($subQuery) use ($registeredAt): void {
                        $subQuery
                            ->whereNull('registered_at')
                            ->whereDate('created_at', $registeredAt->toDateString());
                    });
            })
            ->first();

        if ($workout) {
            $existingItems = array_map(
                fn (EjercicioItem $item): array => $this->workoutItemFromModel($item),
                $workout->items->all()
            );
            $mergedItems = $this->mergeWorkoutItems($existingItems, $items);

            $workout->notes = $this->mergeNotes($workout->notes, $newNotes);
            $workout->registered_at = $registeredAt;
            $workout->save();

            $workout->items()->delete();
            $this->persistWorkoutItems($workout, $mergedItems);
            return;
        }

        $workout = Ejercicio::create([
            'user_id' => $userId,
            'tipo_entrene' => $trainingType,
            'notes' => $newNotes !== '' ? $newNotes : null,
            'registered_at' => $registeredAt,
        ]);

        $this->persistWorkoutItems($workout, $items);
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

        $base = url('/entrenamiento/sesion');

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

    private function exercisePreviousMap(array $history): array
    {
        $map = [];

        foreach (array_reverse($history, true) as $workout) {
            foreach (($workout['items'] ?? []) as $item) {
                $name = strtolower(trim((string) ($item['name'] ?? '')));
                if ($name === '' || isset($map[$name])) {
                    continue;
                }

                $reps = max(1, (int) ($item['reps'] ?? 0));
                $weights = $this->normalizeStoredSetWeights(
                    $item['set_weights'] ?? null,
                    (int) ($item['sets'] ?? 0)
                );
                $firstNonZeroWeight = 0.0;
                foreach ($weights as $weight) {
                    if ((float) $weight > 0) {
                        $firstNonZeroWeight = (float) $weight;
                        break;
                    }
                }

                $weightLabel = rtrim(rtrim(number_format($firstNonZeroWeight, 2, '.', ''), '0'), '.');
                if ($weightLabel === '') {
                    $weightLabel = '0';
                }

                $map[$name] = $weightLabel.'kg × '.$reps;
            }
        }

        return $map;
    }

    private function activeWorkoutSession(): array
    {
        $state = session('workout_session_active', []);
        return is_array($state) ? $state : [];
    }

    private function setActiveWorkoutSession(array $state): void
    {
        session(['workout_session_active' => $state]);
    }

    private function clearActiveWorkoutSession(): void
    {
        session()->forget('workout_session_active');
    }

    private function activeExerciseIndex(array $state, string $exerciseKey): ?int
    {
        foreach (($state['exercises'] ?? []) as $index => $exercise) {
            if ((string) ($exercise['key'] ?? '') === $exerciseKey) {
                return (int) $index;
            }
        }

        return null;
    }

    private function normalizeActiveSessionState(array $state): array
    {
        $normalized = [
            'id' => (string) ($state['id'] ?? Str::uuid()),
            'training_name' => trim((string) ($state['training_name'] ?? 'Entrenamiento activo')),
            'training_type' => $this->normalizeTrainingType((string) ($state['training_type'] ?? 'fuerza')),
            'started_at' => (string) ($state['started_at'] ?? now()->toIso8601String()),
            'exercises' => [],
        ];

        foreach (($state['exercises'] ?? []) as $exercise) {
            if (!is_array($exercise)) {
                continue;
            }

            $exerciseKey = trim((string) ($exercise['key'] ?? ''));
            $exerciseName = trim((string) ($exercise['name'] ?? ''));
            if ($exerciseKey === '' || $exerciseName === '') {
                continue;
            }

            $sets = [];
            foreach (($exercise['sets'] ?? []) as $set) {
                if (!is_array($set)) {
                    continue;
                }

                $sets[] = [
                    'kg' => max(0, round((float) ($set['kg'] ?? 0), 2)),
                    'reps' => max(1, (int) ($set['reps'] ?? 1)),
                    'rpe' => min(10, max(1, (int) ($set['rpe'] ?? 7))),
                    'completed' => (bool) ($set['completed'] ?? false),
                    'previous' => isset($set['previous']) ? trim((string) $set['previous']) : null,
                    'completed_at' => isset($set['completed_at']) ? (string) $set['completed_at'] : null,
                ];
            }

            $normalized['exercises'][] = [
                'key' => $exerciseKey,
                'name' => $exerciseName,
                'type' => $exercise['type'] ?? null,
                'muscle' => $exercise['muscle'] ?? null,
                'difficulty' => $exercise['difficulty'] ?? null,
                'equipment' => $exercise['equipment'] ?? null,
                'instructions' => $exercise['instructions'] ?? null,
                'safety_info' => $exercise['safety_info'] ?? null,
                'sets' => $sets,
            ];
        }

        return $normalized;
    }

    private function completedItemsFromActiveSession(array $state): array
    {
        $normalized = $this->normalizeActiveSessionState($state);
        $items = [];

        foreach (($normalized['exercises'] ?? []) as $exercise) {
            $completedSets = array_values(array_filter(
                $exercise['sets'] ?? [],
                fn (array $set): bool => (bool) ($set['completed'] ?? false)
            ));

            if (empty($completedSets)) {
                continue;
            }

            $setCount = count($completedSets);
            $avgReps = (int) round(array_sum(array_map(
                fn (array $set): int => (int) ($set['reps'] ?? 0),
                $completedSets
            )) / max(1, $setCount));

            $items[] = [
                'name' => $exercise['name'],
                'type' => $exercise['type'] ?? null,
                'muscle' => $exercise['muscle'] ?? null,
                'difficulty' => $exercise['difficulty'] ?? null,
                'equipment' => $exercise['equipment'] ?? null,
                'instructions' => $exercise['instructions'] ?? null,
                'safety_info' => $exercise['safety_info'] ?? null,
                'sets' => $setCount,
                'reps' => max(1, $avgReps),
                'minutes' => 0,
                'set_weights' => array_map(
                    fn (array $set): float => max(0.0, round((float) ($set['kg'] ?? 0), 2)),
                    $completedSets
                ),
            ];
        }

        return $items;
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

    private function compactExerciseForPicker(array $item): array
    {
        return [
            'key' => $this->itemKey($item),
            'name' => (string) ($item['name'] ?? 'Ejercicio'),
            'type' => $item['type'] ?? null,
            'muscle' => $item['muscle'] ?? null,
            'difficulty' => $item['difficulty'] ?? null,
            'equipment' => $item['equipment'] ?? null,
            'instructions' => null,
            'safety_info' => null,
        ];
    }
}
