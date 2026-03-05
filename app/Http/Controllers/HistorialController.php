<?php

namespace App\Http\Controllers;

use App\Models\Comida;
use App\Models\Ejercicio;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class HistorialController extends Controller
{
    public function index(Request $request): View
    {
        return view('historial', [
            'week_days' => ['Lun', 'Mar', 'Mie', 'Jue', 'Vie', 'Sab', 'Dom'],
            'calendar_payload' => $this->buildCalendarPayload(
                $this->resolveMonth(trim((string) $request->query('month', '')))
            ),
        ]);
    }

    public function calendarData(Request $request): JsonResponse
    {
        $month = $this->resolveMonth(trim((string) $request->query('month', '')));

        return response()->json($this->buildCalendarPayload($month));
    }

    private function resolveMonth(string $month): Carbon
    {
        if (preg_match('/^\\d{4}-\\d{2}$/', $month) === 1) {
            try {
                return Carbon::createFromFormat('Y-m', $month)->startOfMonth();
            } catch (\Throwable) {
                // fallback below
            }
        }

        return now()->startOfMonth();
    }

    private function buildCalendarPayload(Carbon $monthDate): array
    {
        $monthStart = $monthDate->copy()->startOfMonth();
        $monthEnd = $monthDate->copy()->endOfMonth();

        $mealRecords = Comida::query()
            ->with('items')
            ->where('user_id', Auth::id())
            ->where(function ($query) use ($monthStart, $monthEnd): void {
                $query
                    ->whereBetween('registered_at', [$monthStart, $monthEnd])
                    ->orWhere(function ($subQuery) use ($monthStart, $monthEnd): void {
                        $subQuery
                            ->whereNull('registered_at')
                            ->whereBetween('created_at', [$monthStart, $monthEnd]);
                    });
            })
            ->get();

        $workoutRecords = Ejercicio::query()
            ->with('items')
            ->where('user_id', Auth::id())
            ->where(function ($query) use ($monthStart, $monthEnd): void {
                $query
                    ->whereBetween('registered_at', [$monthStart, $monthEnd])
                    ->orWhere(function ($subQuery) use ($monthStart, $monthEnd): void {
                        $subQuery
                            ->whereNull('registered_at')
                            ->whereBetween('created_at', [$monthStart, $monthEnd]);
                    });
            })
            ->get();

        $mealStatusByDate = [];
        $workoutStatusByDate = [];
        $dayDetails = [];

        foreach ($mealRecords as $record) {
            $date = $this->resolveRecordDate($record->registered_at, $record->created_at);
            if (!$date) {
                continue;
            }

            $dateKey = $date->toDateString();
            $mealType = strtolower(trim((string) ($record->tipo_comida ?? '')));

            if (!isset($mealStatusByDate[$dateKey])) {
                $mealStatusByDate[$dateKey] = [];
            }

            if ($mealType !== '') {
                $mealStatusByDate[$dateKey][$mealType] = true;
            }

            if (!isset($dayDetails[$dateKey])) {
                $dayDetails[$dateKey] = ['meals' => [], 'workouts' => []];
            }

            $mealItems = $record->items->map(function ($item): array {
                return [
                    'name' => $item->nombre,
                    'quantity' => (float) ($item->cantidad ?? 1),
                    'calories' => is_numeric($item->calorias) ? (float) $item->calorias : null,
                    'protein' => is_numeric($item->proteinas) ? (float) $item->proteinas : null,
                    'carbs' => is_numeric($item->carbs) ? (float) $item->carbs : null,
                    'fat' => is_numeric($item->fat) ? (float) $item->fat : null,
                ];
            })->all();

            $dayDetails[$dateKey]['meals'][] = [
                'meal_type' => (string) ($record->tipo_comida ?? 'Comida'),
                'registered_at' => $date->format('d/m/Y H:i'),
                'notes' => (string) ($record->notes ?? ''),
                'totals' => $this->mealTotals($mealItems),
                'items' => $mealItems,
            ];
        }

        foreach ($workoutRecords as $record) {
            $date = $this->resolveRecordDate($record->registered_at, $record->created_at);
            if (!$date) {
                continue;
            }

            $dateKey = $date->toDateString();
            $workoutStatusByDate[$dateKey] = true;

            if (!isset($dayDetails[$dateKey])) {
                $dayDetails[$dateKey] = ['meals' => [], 'workouts' => []];
            }

            $workoutItems = $record->items->map(function ($item): array {
                $setWeights = is_array($item->set_weights ?? null)
                    ? array_values(array_map(fn ($value) => (float) $value, $item->set_weights))
                    : [];

                return [
                    'name' => $item->nombre,
                    'sets' => (int) ($item->sets ?? 0),
                    'reps' => (int) ($item->reps ?? 0),
                    'minutes' => (int) ($item->minutes ?? 0),
                    'set_weights' => $setWeights,
                ];
            })->all();

            $dayDetails[$dateKey]['workouts'][] = [
                'training_type' => (string) ($record->tipo_entrene ?? 'Entrenamiento'),
                'registered_at' => $date->format('d/m/Y H:i'),
                'notes' => (string) ($record->notes ?? ''),
                'totals' => $this->workoutTotals($workoutItems),
                'items' => $workoutItems,
            ];
        }

        $calendarStart = $monthStart->copy()->startOfWeek(Carbon::MONDAY);
        $calendarEnd = $monthEnd->copy()->endOfWeek(Carbon::SUNDAY);

        $weeks = [];
        $cursor = $calendarStart->copy();

        while ($cursor->lte($calendarEnd)) {
            $week = [];

            for ($day = 0; $day < 7; $day++) {
                $key = $cursor->toDateString();
                $mealTypesCount = isset($mealStatusByDate[$key]) ? count($mealStatusByDate[$key]) : 0;

                $status = 'none';
                if ($mealTypesCount >= 2) {
                    $status = 'green';
                } elseif ($mealTypesCount === 1) {
                    $status = 'yellow';
                }

                $hasWorkout = isset($workoutStatusByDate[$key]);

                $week[] = [
                    'date' => $cursor->toDateString(),
                    'day' => (int) $cursor->format('j'),
                    'is_current_month' => $cursor->month === $monthDate->month,
                    'is_today' => $cursor->isToday(),
                    'status' => $status,
                    'has_workout' => $hasWorkout,
                    'meal_types_count' => $mealTypesCount,
                    'has_any_record' => $mealTypesCount > 0 || $hasWorkout,
                ];

                $cursor->addDay();
            }

            $weeks[] = $week;
        }

        return [
            'month_label' => ucfirst($monthDate->translatedFormat('F Y')),
            'month_value' => $monthDate->format('Y-m'),
            'prev_month' => $monthDate->copy()->subMonth()->format('Y-m'),
            'next_month' => $monthDate->copy()->addMonth()->format('Y-m'),
            'weeks' => $weeks,
            'day_details' => $dayDetails,
        ];
    }

    private function resolveRecordDate(mixed $registeredAt, mixed $createdAt): ?Carbon
    {
        if ($registeredAt instanceof Carbon) {
            return $registeredAt->copy();
        }

        if ($createdAt instanceof Carbon) {
            return $createdAt->copy();
        }

        return null;
    }

    private function mealTotals(array $items): array
    {
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

        return array_map(fn ($value): float => round($value, 2), $totals);
    }

    private function workoutTotals(array $items): array
    {
        $totals = [
            'exercises' => 0,
            'sets' => 0,
            'reps' => 0,
            'minutes' => 0,
            'volume' => 0.0,
        ];

        foreach ($items as $item) {
            $sets = (int) ($item['sets'] ?? 0);
            $reps = (int) ($item['reps'] ?? 0);
            $minutes = (int) ($item['minutes'] ?? 0);
            $setWeights = is_array($item['set_weights'] ?? null) ? $item['set_weights'] : [];
            $weightsSum = array_sum(array_map(fn ($value): float => (float) $value, $setWeights));

            $totals['exercises']++;
            $totals['sets'] += $sets;
            $totals['reps'] += $reps;
            $totals['minutes'] += $minutes;
            $totals['volume'] += $weightsSum > 0 ? ($weightsSum * $reps) : ($sets * $reps);
        }

        $totals['volume'] = round($totals['volume'], 2);

        return $totals;
    }
}
