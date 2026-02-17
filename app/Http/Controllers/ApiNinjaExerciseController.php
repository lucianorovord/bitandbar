<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApiNinjaExerciseSearchRequest;
use App\Services\ApiNinjaExerciseService;
use App\Services\TextTranslationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
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

    public function register(ApiNinjaExerciseSearchRequest $request): View
    {
        $filters = [
            'name' => trim((string) $request->validated('name', '')),
            'muscle' => trim((string) $request->validated('muscle', '')),
            'type' => trim((string) $request->validated('type', '')),
            'difficulty' => trim((string) $request->validated('difficulty', '')),
        ];
        $activeFilters = array_filter($filters, fn ($value) => $value !== '');
        $apiFilters = $this->translateFiltersToEnglish($activeFilters);
        $allExercises = [];
        $error = null;

        if (!empty($apiFilters)) {
            try {
                $perPage = 6;
                $page = max(1, (int) $request->query('page', 1));
                $result = $this->exerciseService->searchPaged($apiFilters, $page, $perPage);
                $allExercises = $result['items'];
                $total = (int) ($result['total'] ?? 0);
            } catch (Throwable $exception) {
                report($exception);
                $error = $this->friendlyApiError($exception->getMessage());
                $allExercises = [];
                $total = 0;
            }
        } else {
            $perPage = 6;
            $page = 1;
            $total = 0;
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
            'has_filters' => !empty($apiFilters),
            'exercises' => $exercises,
            'error' => $error,
            'workout_cart' => $this->cartItems(),
            'workout_totals' => $this->cartTotals(),
            'workout_history' => session('workout_history', []),
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
            'name_filter' => ['nullable', 'string', 'max:80'],
            'muscle_filter' => ['nullable', 'string', 'max:80'],
            'type_filter' => ['nullable', 'string', 'max:80'],
            'difficulty_filter' => ['nullable', 'string', 'max:80'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $cart = $this->cartItems();
        $itemKey = $this->itemKey($data);
        $defaults = [
            'sets' => (int) ($data['sets'] ?? 3),
            'reps' => (int) ($data['reps'] ?? 12),
            'minutes' => (int) ($data['minutes'] ?? 0),
        ];

        if (isset($cart[$itemKey])) {
            $cart[$itemKey]['sets'] = min(20, ((int) $cart[$itemKey]['sets']) + $defaults['sets']);
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
            'name_filter' => ['nullable', 'string', 'max:80'],
            'muscle_filter' => ['nullable', 'string', 'max:80'],
            'type_filter' => ['nullable', 'string', 'max:80'],
            'difficulty_filter' => ['nullable', 'string', 'max:80'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $cart = $this->cartItems();
        if (isset($cart[$itemKey])) {
            $cart[$itemKey]['sets'] = (int) $data['sets'];
            $cart[$itemKey]['reps'] = (int) $data['reps'];
            $cart[$itemKey]['minutes'] = (int) ($data['minutes'] ?? 0);
            session(['workout_cart' => $cart]);
        }

        return redirect()->to($this->trainingRegisterUrl($data))
            ->with('workout_success', 'Ejercicio actualizado.');
    }

    public function removeFromCart(Request $request, string $itemKey): RedirectResponse
    {
        $data = $request->validate([
            'name_filter' => ['nullable', 'string', 'max:80'],
            'muscle_filter' => ['nullable', 'string', 'max:80'],
            'type_filter' => ['nullable', 'string', 'max:80'],
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
            'name_filter' => ['nullable', 'string', 'max:80'],
            'muscle_filter' => ['nullable', 'string', 'max:80'],
            'type_filter' => ['nullable', 'string', 'max:80'],
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
            'name_filter' => ['nullable', 'string', 'max:80'],
            'muscle_filter' => ['nullable', 'string', 'max:80'],
            'type_filter' => ['nullable', 'string', 'max:80'],
            'difficulty_filter' => ['nullable', 'string', 'max:80'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $cart = $this->cartItems();
        if (empty($cart)) {
            return redirect()->to($this->trainingRegisterUrl($data))
                ->with('workout_error', 'No puedes registrar un entrenamiento vacio.');
        }

        $history = session('workout_history', []);
        $trainingType = $this->normalizeTrainingType((string) $data['training_type']);
        $newNotes = trim((string) ($data['notes'] ?? ''));

        $existingIndex = null;
        foreach ($history as $index => $workout) {
            $existingType = $this->normalizeTrainingType((string) ($workout['training_type'] ?? ''));
            if ($existingType === $trainingType) {
                $existingIndex = $index;
                break;
            }
        }

        if ($existingIndex !== null) {
            $existingItems = is_array($history[$existingIndex]['items'] ?? null)
                ? $history[$existingIndex]['items']
                : [];
            $mergedItems = $this->mergeWorkoutItems($existingItems, array_values($cart));

            $existingNotes = trim((string) ($history[$existingIndex]['notes'] ?? ''));
            $mergedNotes = $existingNotes;
            if ($newNotes !== '') {
                $mergedNotes = $existingNotes === ''
                    ? $newNotes
                    : ($existingNotes."\n".$newNotes);
            }

            $history[$existingIndex]['training_type'] = $trainingType;
            $history[$existingIndex]['items'] = $mergedItems;
            $history[$existingIndex]['totals'] = $this->cartTotals($mergedItems);
            $history[$existingIndex]['notes'] = $mergedNotes !== '' ? $mergedNotes : null;
        } else {
            $history[] = [
                'registered_at' => now()->format('d/m/Y H:i'),
                'training_type' => $trainingType,
                'notes' => $newNotes !== '' ? $newNotes : null,
                'items' => array_values($cart),
                'totals' => $this->cartTotals($cart),
            ];
        }

        session([
            'workout_history' => $history,
            'workout_cart' => [],
        ]);

        return redirect()->to($this->trainingRegisterUrl($data))
            ->with('workout_success', 'Entrenamiento registrado correctamente.');
    }

    public function updateWorkoutRecord(Request $request, string $workoutIndex): RedirectResponse
    {
        $data = $request->validate([
            'training_type' => ['required', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:600'],
            'name_filter' => ['nullable', 'string', 'max:80'],
            'muscle_filter' => ['nullable', 'string', 'max:80'],
            'type_filter' => ['nullable', 'string', 'max:80'],
            'difficulty_filter' => ['nullable', 'string', 'max:80'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $history = session('workout_history', []);
        $index = (int) $workoutIndex;
        if (!isset($history[$index]) || !is_array($history[$index])) {
            return redirect()->to($this->trainingRegisterUrl($data))
                ->with('workout_error', 'No se encontro el registro de entrenamiento para editar.');
        }

        $history[$index]['training_type'] = $this->normalizeTrainingType((string) $data['training_type']);
        $history[$index]['notes'] = $data['notes'] ?? null;
        session(['workout_history' => $history]);

        return redirect()->to($this->trainingRegisterUrl($data))
            ->with('workout_success', 'Registro de entrenamiento actualizado.');
    }

    public function deleteWorkoutRecord(Request $request, string $workoutIndex): RedirectResponse
    {
        $data = $request->validate([
            'name_filter' => ['nullable', 'string', 'max:80'],
            'muscle_filter' => ['nullable', 'string', 'max:80'],
            'type_filter' => ['nullable', 'string', 'max:80'],
            'difficulty_filter' => ['nullable', 'string', 'max:80'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $history = session('workout_history', []);
        $index = (int) $workoutIndex;
        if (!isset($history[$index])) {
            return redirect()->to($this->trainingRegisterUrl($data))
                ->with('workout_error', 'No se encontro el registro de entrenamiento para eliminar.');
        }

        unset($history[$index]);
        session(['workout_history' => array_values($history)]);

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

            $totals['exercises']++;
            $totals['sets'] += $sets;
            $totals['reps'] += $reps;
            $totals['minutes'] += $minutes;
            $totals['volume'] += ($sets * $reps);
        }

        return $totals;
    }

    private function itemKey(array $data): string
    {
        return sha1(strtolower(trim((string) ($data['name'] ?? ''))).'|'.strtolower(trim((string) ($data['muscle'] ?? ''))).'|'.strtolower(trim((string) ($data['type'] ?? ''))));
    }

    private function trainingRegisterUrl(array $data): string
    {
        $params = [];
        foreach ([
            'name_filter' => 'name',
            'muscle_filter' => 'muscle',
            'type_filter' => 'type',
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

    private function translateFiltersToEnglish(array $filters): array
    {
        $translated = [];

        foreach ($filters as $key => $value) {
            if (!is_string($value) || trim($value) === '') {
                continue;
            }

            $translated[$key] = $this->translator->translate($value, 'es', 'en') ?? $value;
        }

        return $translated;
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
                continue;
            }

            $merged[$key]['sets'] += (int) ($item['sets'] ?? 0);
            $merged[$key]['reps'] += (int) ($item['reps'] ?? 0);
            $merged[$key]['minutes'] += (int) ($item['minutes'] ?? 0);
        }

        return array_values($merged);
    }
}
