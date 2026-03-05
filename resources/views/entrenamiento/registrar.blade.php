@extends('plantilla')
@section('title','Registrar entrenamiento')
@section('contenido')
    @vite('resources/sass/entrenamientos/entrenamiento.scss')
    @vite('resources/js/entrenamiento/registrar.js')
    @if(session('workout_success'))
        <section class="top-success-alert" role="status" aria-live="polite">
            {{ session('workout_success') }}
        </section>
    @endif
    <section class="hero">
        <p class="hero__kicker">Deporte</p>
        <h2 class="hero__title">Registrar entrenamiento</h2>

    </section>

    @php
        $muscleCards = [
            ['value' => 'abdominals', 'label' => 'Abdominales', 'img' => asset('images/muscles/abs.png')],
            ['value' => 'abductors', 'label' => 'Abductores', 'img' => asset('images/muscles/abductores.png')],
            ['value' => 'adductors', 'label' => 'Aductores', 'img' => asset('images/muscles/aductores.png')],
            ['value' => 'biceps', 'label' => 'Biceps', 'img' => asset('images/muscles/biceps.png')],
            ['value' => 'calves', 'label' => 'Gemelos', 'img' => asset('images/muscles/gemelos.png')],
            ['value' => 'chest', 'label' => 'Pecho', 'img' => asset('images/muscles/chest.png')],
            ['value' => 'forearms', 'label' => 'Antebrazos', 'img' => asset('images/muscles/forearms.png')],
            ['value' => 'glutes', 'label' => 'Gluteos', 'img' => asset('images/muscles/gluts.png')],
            ['value' => 'hamstrings', 'label' => 'Isquiotibiales', 'img' => asset('images/muscles/hamstrings.png')],
            ['value' => 'lats', 'label' => 'Dorsales', 'img' => asset('images/muscles/lats.png')],
            ['value' => 'lower_back', 'label' => 'Espalda baja', 'img' => asset('images/muscles/lower_back.png')],
            ['value' => 'middle_back', 'label' => 'Espalda media', 'img' => asset('images/muscles/middle_back.png')],
            ['value' => 'neck', 'label' => 'Cuello', 'img' => asset('images/muscles/cuello.png')],
            ['value' => 'quadriceps', 'label' => 'Cuadriceps', 'img' => asset('images/muscles/quads.png')],
            ['value' => 'traps', 'label' => 'Trapecio', 'img' => asset('images/muscles/traps.png')],
            ['value' => 'triceps', 'label' => 'Triceps', 'img' => asset('images/muscles/triceps.png')],
            ['value' => 'shoulders', 'label' => 'Hombros', 'img' => asset('images/muscles/shoulders.png')],

        ];
    @endphp

    <section class="search-panel">
        <form method="GET" action="{{ url('/entrenamiento/registrar') }}" class="search-form">
            <label>Selecciona grupo muscular</label>
            <div class="muscle-grid">
                @foreach($muscleCards as $card)
                    <label class="muscle-card {{ ($filters['muscle'] ?? '') === $card['value'] ? 'muscle-card--active' : '' }}">
                        <input type="radio" name="muscle" value="{{ $card['value'] }}" @checked(($filters['muscle'] ?? '') === $card['value'])>
                        <img src="{{ $card['img'] }}" alt="{{ $card['label'] }}">
                        <span>{{ $card['label'] }}</span>
                    </label>
                @endforeach
            </div>

            <div class="search-form__row search-form__row--spaced">
                <button type="submit" class="hero__cta">Buscar ejercicios</button>
            </div>
        </form>

        @if(!empty($filters['muscle']))
            <form method="GET" action="{{ url('/entrenamiento/registrar') }}" class="search-form">
                <label for="difficulty">Filtra por dificultad (opcional)</label>
                <div class="search-form__row">
                    <input type="hidden" name="muscle" value="{{ $filters['muscle'] }}">
                    <select id="difficulty" name="difficulty">
                        <option value="">Todas las dificultades</option>
                        <option value="beginner" @selected(($filters['difficulty'] ?? '') === 'beginner')>Principiante</option>
                        <option value="intermediate" @selected(($filters['difficulty'] ?? '') === 'intermediate')>Intermedio</option>
                        <option value="expert" @selected(($filters['difficulty'] ?? '') === 'expert')>Avanzado</option>
                    </select>
                    <button type="submit" class="mini-btn">Aplicar</button>
                </div>
            </form>
        @endif

        @if(!empty($error))
            <p class="form-error">{{ $error }}</p>
        @endif
        @if($errors->has('set_weights'))
            <p class="form-error">{{ $errors->first('set_weights') }}</p>
        @endif
        @if(session('workout_error'))
            <p class="form-error">{{ session('workout_error') }}</p>
        @endif
        @error('registered_at')
            <p class="form-error">{{ $message }}</p>
        @enderror
    </section>

    <section class="results-panel">
        <h3>Registro de entrenamiento</h3>
        @if(!empty($workout_cart))
            <div class="results-table-wrap">
                <table class="results-table">
                    <thead>
                        <tr>
                            <th>Ejercicio</th>
                            <th>Series</th>
                            <th>Reps</th>
                            <th>Pesos por serie (kg)</th>
                            <th>Minutos</th>
                            <th>Volumen</th>
                            <th>Accion</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($workout_cart as $itemKey => $item)
                            @php
                                $setWeights = array_values(array_map(
                                    fn ($value) => round((float) $value, 2),
                                    is_array($item['set_weights'] ?? null) ? $item['set_weights'] : []
                                ));
                                $setWeightLabel = !empty($setWeights)
                                    ? implode(', ', array_map(fn ($value) => rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.'), $setWeights))
                                    : '-';
                                $sumWeights = array_sum($setWeights);
                                $rowVolume = $sumWeights > 0
                                    ? $sumWeights * (int) ($item['reps'] ?? 0)
                                    : (int) ($item['sets'] ?? 0) * (int) ($item['reps'] ?? 0);
                            @endphp
                            <tr>
                                <td>{{ $item['name'] }}</td>
                                <td>{{ $item['sets'] ?? 0 }}</td>
                                <td>{{ $item['reps'] ?? 0 }}</td>
                                <td>{{ $setWeightLabel }}</td>
                                <td>{{ $item['minutes'] ?? 0 }}</td>
                                <td>{{ $rowVolume }}</td>
                                <td>
                                    <form method="POST" action="{{ url('/entrenamiento/carrito/actualizar/'.$itemKey) }}" class="inline-form">
                                        @csrf
                                        <input type="hidden" name="muscle_filter" value="{{ $filters['muscle'] ?? '' }}">
                                        <input type="hidden" name="difficulty_filter" value="{{ $filters['difficulty'] ?? '' }}">
                                        <input type="hidden" name="page" value="{{ $exercises->currentPage() }}">
                                        <input type="number" name="sets" min="1" max="20" value="{{ $item['sets'] ?? 3 }}">
                                        <input type="number" name="reps" min="1" max="100" value="{{ $item['reps'] ?? 12 }}">
                                        <input type="text" name="set_weights" value="{{ $setWeightLabel !== '-' ? $setWeightLabel : '' }}" placeholder="10,15,20">
                                        <input type="number" name="minutes" min="0" max="300" value="{{ $item['minutes'] ?? 0 }}">
                                        <button type="submit" class="mini-btn">Actualizar</button>
                                    </form>
                                    <form method="POST" action="{{ url('/entrenamiento/carrito/eliminar/'.$itemKey) }}">
                                        @csrf
                                        <input type="hidden" name="muscle_filter" value="{{ $filters['muscle'] ?? '' }}">
                                        <input type="hidden" name="difficulty_filter" value="{{ $filters['difficulty'] ?? '' }}">
                                        <input type="hidden" name="page" value="{{ $exercises->currentPage() }}">
                                        <button type="submit" class="mini-btn mini-btn--danger" aria-label="Eliminar fila" title="Eliminar fila">X</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="cart-summary">
                <p><strong>Ejercicios:</strong> {{ $workout_totals['exercises'] ?? 0 }}</p>
                <p><strong>Series:</strong> {{ $workout_totals['sets'] ?? 0 }}</p>
                <p><strong>Reps:</strong> {{ $workout_totals['reps'] ?? 0 }}</p>
                <p><strong>Volumen:</strong> {{ $workout_totals['volume'] ?? 0 }}</p>
                <p><strong>Minutos:</strong> {{ $workout_totals['minutes'] ?? 0 }}</p>
            </div>

            <form method="POST" action="{{ url('/entrenamiento/registrar/guardar') }}" class="search-form">
                @csrf
                <div class="search-form__row">
                    <select name="training_type" required>
                        <option value="">Tipo de entrenamiento</option>
                        <option value="fuerza">Fuerza</option>
                        <option value="cardio">Cardio</option>
                        <option value="movilidad">Movilidad</option>
                        <option value="hiit">HIIT</option>
                    </select>
                    <input
                        type="date"
                        name="registered_at"
                        value="{{ old('registered_at', $today_date ?? now()->toDateString()) }}"
                        required
                    >
                    <input type="text" name="notes" placeholder="Notas (ej: foco en tecnica)">
                    <button type="submit" class="hero__cta">Registrar entrenamiento</button>
                </div>
                <input type="hidden" name="muscle_filter" value="{{ $filters['muscle'] ?? '' }}">
                <input type="hidden" name="difficulty_filter" value="{{ $filters['difficulty'] ?? '' }}">
                <input type="hidden" name="page" value="{{ $exercises->currentPage() }}">
            </form>

            <form method="POST" action="{{ url('/entrenamiento/carrito/limpiar') }}">
                @csrf
                <input type="hidden" name="muscle_filter" value="{{ $filters['muscle'] ?? '' }}">
                <input type="hidden" name="difficulty_filter" value="{{ $filters['difficulty'] ?? '' }}">
                <input type="hidden" name="page" value="{{ $exercises->currentPage() }}">
                <button type="submit" class="mini-btn mini-btn--danger">Vaciar registro</button>
            </form>
        @else
            <p>Aun no has anadido ejercicios al registro.</p>
        @endif
    </section>

    @if($exercises->count() > 0)
        <section class="results-panel" id="exercise-results">
            <h3>Ejercicios del grupo muscular seleccionado</h3>
            <div class="exercise-grid">
                @foreach($exercises as $exercise)
                    <article class="exercise-card">
                        <h2 class="exercise-card__title">{{ $exercise['name'] }}</h2>
                        <div class="exercise-card__meta">
                            <span class="exercise-pill exercise-pill--type"><strong>Tipo:</strong> {{ $exercise['type'] ?? '-' }}</span>
                            <span class="exercise-pill"><strong>Musculo:</strong> {{ $exercise['muscle'] ?? '-' }}</span>
                            <span class="exercise-pill"><strong>Dificultad:</strong> {{ $exercise['difficulty'] ?? '-' }}</span>
                            <span class="exercise-pill"><strong>Equipo:</strong> {{ $exercise['equipment'] ?? '-' }}</span>
                        </div>
                        <div class="exercise-card__section">
                            <p class="exercise-card__label">Instrucciones</p>
                            <p class="exercise-card__text">{{ $exercise['instructions'] ?? '-' }}</p>
                        </div>
                        <div class="exercise-card__section">
                            <p class="exercise-card__label">Seguridad</p>
                            <p class="exercise-card__text">{{ $exercise['safety_info'] ?? '-' }}</p>
                        </div>
                        <div class="exercise-card__section">
                            <form method="POST" action="{{ url('/entrenamiento/carrito/agregar') }}" class="search-form">
                                @csrf
                                <input type="hidden" name="muscle_filter" value="{{ $filters['muscle'] ?? '' }}">
                                <input type="hidden" name="difficulty_filter" value="{{ $filters['difficulty'] ?? '' }}">
                                <input type="hidden" name="page" value="{{ $exercises->currentPage() }}">
                                <input type="hidden" name="name" value="{{ $exercise['name'] }}">
                                <input type="hidden" name="type" value="{{ $exercise['type'] ?? '' }}">
                                <input type="hidden" name="muscle" value="{{ $exercise['muscle'] ?? '' }}">
                                <input type="hidden" name="difficulty" value="{{ $exercise['difficulty'] ?? '' }}">
                                <input type="hidden" name="equipment" value="{{ $exercise['equipment'] ?? '' }}">
                                <input type="hidden" name="instructions" value="{{ $exercise['instructions'] ?? '' }}">
                                <input type="hidden" name="safety_info" value="{{ $exercise['safety_info'] ?? '' }}">
                                <div class="exercise-input-row">
                                    <div class="exercise-input-group">
                                        <h6>Series</h6>
                                        <input type="number" name="sets" min="1" max="20" value="3">
                                    </div>
                                    <div class="exercise-input-group">
                                        <h6>Repeticiones</h6>
                                        <input type="number" name="reps" min="1" max="100" value="12">
                                    </div>
                                    <div class="exercise-input-group">
                                        <h6>Peso por serie (kg)</h6>
                                        <input type="text" name="set_weights" placeholder="10,15,20">
                                    </div>
                                    <div class="exercise-input-group">
                                        <h6>Minutos</h6>
                                        <input type="number" name="minutes" min="0" max="300" value="0">
                                    </div>
                                    <button type="submit" class="mini-btn">+</button>
                                </div>
                            </form>
                        </div>
                    </article>
                @endforeach
            </div>
            @if($exercises->hasPages())
                <div class="pagination-wrap">
                    @if($exercises->onFirstPage())
                        <span class="pagination-link pagination-link--disabled">Anterior</span>
                    @else
                        <a class="pagination-link" href="{{ $exercises->previousPageUrl() }}">Anterior</a>
                    @endif

                    @for($page = 1; $page <= $exercises->lastPage(); $page++)
                        <a
                            class="pagination-link {{ $page === $exercises->currentPage() ? 'pagination-link--active' : '' }}"
                            href="{{ $exercises->url($page) }}"
                        >
                            {{ $page }}
                        </a>
                    @endfor

                    @if($exercises->hasMorePages())
                        <a class="pagination-link" href="{{ $exercises->nextPageUrl() }}">Siguiente</a>
                    @else
                        <span class="pagination-link pagination-link--disabled">Siguiente</span>
                    @endif
                </div>
            @endif
        </section>
    @elseif(!empty($has_filters))
        <section class="results-panel">
            <p>No se encontraron ejercicios para ese grupo muscular con la dificultad indicada.</p>
        </section>
    @endif

    @if(!empty($workout_history))
        <section class="results-panel">
            <h3>Entrenamientos registrados (sesion actual)</h3>
            @foreach(array_reverse($workout_history, true) as $workoutIndex => $workout)
                <details class="meal-history-item">
                    <summary class="meal-history-summary">
                        <span><strong>{{ ucfirst($workout['training_type'] ?? '') }}</strong> - {{ $workout['registered_at'] ?? '' }}</span>
                        <span>Volumen {{ $workout['totals']['volume'] ?? 0 }}</span>
                    </summary>
                    <div class="meal-history-content">
                        <p>
                            Ejercicios: {{ $workout['totals']['exercises'] ?? 0 }} |
                            Series: {{ $workout['totals']['sets'] ?? 0 }} |
                            Reps: {{ $workout['totals']['reps'] ?? 0 }} |
                            Volumen: {{ $workout['totals']['volume'] ?? 0 }}
                        </p>
                        @if(!empty($workout['notes']))
                            <p>Nota: {{ $workout['notes'] }}</p>
                        @endif
                        <form method="POST" action="{{ url('/entrenamiento/registro/editar/'.$workoutIndex) }}" class="search-form">
                            @csrf
                            <div class="search-form__row">
                                <select name="training_type" required>
                                    <option value="fuerza" @selected(($workout['training_type'] ?? '') === 'fuerza')>Fuerza</option>
                                    <option value="cardio" @selected(($workout['training_type'] ?? '') === 'cardio')>Cardio</option>
                                    <option value="movilidad" @selected(($workout['training_type'] ?? '') === 'movilidad')>Movilidad</option>
                                    <option value="hiit" @selected(($workout['training_type'] ?? '') === 'hiit')>HIIT</option>
                                </select>
                                <input type="text" name="notes" value="{{ $workout['notes'] ?? '' }}" placeholder="Editar nota">
                                <button type="submit" class="mini-btn">Guardar cambios</button>
                            </div>
                            <input type="hidden" name="muscle_filter" value="{{ $filters['muscle'] ?? '' }}">
                            <input type="hidden" name="difficulty_filter" value="{{ $filters['difficulty'] ?? '' }}">
                            <input type="hidden" name="page" value="{{ $exercises->currentPage() }}">
                        </form>

                        <form method="POST" action="{{ url('/entrenamiento/registro/eliminar/'.$workoutIndex) }}">
                            @csrf
                            <input type="hidden" name="muscle_filter" value="{{ $filters['muscle'] ?? '' }}">
                            <input type="hidden" name="difficulty_filter" value="{{ $filters['difficulty'] ?? '' }}">
                            <input type="hidden" name="page" value="{{ $exercises->currentPage() }}">
                            <button type="submit" class="mini-btn mini-btn--danger">Eliminar registro</button>
                        </form>
                        <div class="results-table-wrap">
                            <table class="results-table">
                                <thead>
                                    <tr>
                                        <th>Ejercicio</th>
                                        <th>Series</th>
                                        <th>Reps</th>
                                        <th>Pesos por serie (kg)</th>
                                        <th>Volumen</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach(($workout['items'] ?? []) as $item)
                                        @php
                                            $historySetWeights = array_values(array_map(
                                                fn ($value) => round((float) $value, 2),
                                                is_array($item['set_weights'] ?? null) ? $item['set_weights'] : []
                                            ));
                                            $historyWeightLabel = !empty($historySetWeights)
                                                ? implode(', ', array_map(fn ($value) => rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.'), $historySetWeights))
                                                : '-';
                                            $historySumWeights = array_sum($historySetWeights);
                                            $historyVolume = $historySumWeights > 0
                                                ? $historySumWeights * (int) ($item['reps'] ?? 0)
                                                : (int) ($item['sets'] ?? 0) * (int) ($item['reps'] ?? 0);
                                        @endphp
                                        <tr>
                                            <td>{{ $item['name'] ?? 'Ejercicio' }}</td>
                                            <td>{{ $item['sets'] ?? 0 }}</td>
                                            <td>{{ $item['reps'] ?? 0 }}</td>
                                            <td>{{ $historyWeightLabel }}</td>
                                            <td>{{ $historyVolume }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </details>
            @endforeach
        </section>
    @endif
@endsection
