@extends('plantilla')
@section('title','Registrar entrenamiento')
@section('contenido')
    @vite('resources/sass/entrenamientos/entrenamiento.scss')
    @vite('resources/js/entrenamiento/registrar.js')
    @vite('resources/js/workout-session.js')
    @if(session('workout_success'))
        <section class="top-success-alert" role="status" aria-live="polite">
            {{ session('workout_success') }}
        </section>
    @endif
    <header class="ws-session-hero">
        <div class="ws-session-hero__left">
            <span class="ws-session-hero__kicker">SESIÓN ACTIVA</span>
            <h2 class="ws-session-hero__title">Registrar entrenamiento</h2>
        </div>
    </header>

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
        <form method="GET" action="{{ url('/entrenamiento/sesion') }}" class="search-form">
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
            <form method="GET" action="{{ url('/entrenamiento/sesion') }}" class="search-form">
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
        <p id="workout-session-empty-copy">Aun no has anadido ejercicios al registro.</p>
        <div
            id="workout-session-panel"
            class="workout-session-panel"
            data-save-set-url="{{ url('/entrenamiento/sesion/serie') }}"
            data-finish-url="{{ url('/entrenamiento/sesion/finalizar') }}"
            data-exercise-search-url="{{ url('/entrenamiento/ejercicios') }}"
            data-exercise-detail-base-url="{{ url('/entrenamiento/ejercicios') }}"
            data-csrf="{{ csrf_token() }}"
            data-registered-at="{{ $today_date ?? now()->toDateString() }}"
            data-previous-map='@json($exercise_previous_map ?? [])'
        >
            <section class="ws-shell" id="ws-shell" hidden>
                <header class="ws-header">
                    <div class="ws-header__left">
                        <p class="ws-header__kicker">Sesion activa</p>
                        <input id="ws-training-name" type="text" value="Entrenamiento activo" maxlength="120" aria-label="Nombre del entrenamiento">
                    </div>
                    <div class="ws-header__right">
                        <label for="ws-training-type" class="ws-visually-hidden">Tipo de entrenamiento</label>
                        <select id="ws-training-type" aria-label="Tipo de entrenamiento">
                            <option value="fuerza">Fuerza</option>
                            <option value="cardio">Cardio</option>
                            <option value="movilidad">Movilidad</option>
                            <option value="hiit">HIIT</option>
                        </select>
                        <div class="ws-session-clock" id="ws-session-clock" aria-live="polite">00:00</div>
                        <button type="button" class="ws-btn ws-btn--cancel" id="ws-cancel-btn" aria-label="Cancelar sesión sin guardar">
                            Cancelar
                        </button>
                        <button type="button" class="ws-btn ws-btn--primary" id="ws-finish-btn">Finalizar</button>
                    </div>
                </header>

                <div class="ws-tabs-row">
                    <div class="ws-tabs" id="ws-tabs" role="tablist" aria-label="Ejercicios en sesion"></div>
                    <button type="button" class="ws-tab ws-tab--add" id="ws-add-exercise-tab" aria-label="Anadir otro ejercicio">+</button>
                </div>

                <div class="ws-exercise-card" id="ws-exercise-card" hidden>
                    <div class="ws-exercise-card__header">
                        <h4 class="ws-exercise-title" id="ws-exercise-title"></h4>
                        <button type="button" class="ws-btn ws-btn--secondary" id="ws-exercise-info-btn">Info</button>
                    </div>
                    <div class="ws-table-wrap">
                        <table class="ws-table" aria-label="Series del ejercicio activo">
                            <thead>
                                <tr>
                                    <th class="ws-col-set">SERIE</th>
                                    <th class="ws-col-reps">REPS</th>
                                    <th class="ws-col-kg">KG</th>
                                    <th class="ws-col-check">✓</th>
                                </tr>
                            </thead>
                            <tbody id="ws-sets-body"></tbody>
                        </table>
                    </div>
                    <button type="button" class="ws-btn ws-btn--secondary" id="ws-add-set-btn">+ Anadir serie</button>
                </div>

                <p class="ws-helper" id="ws-helper">Usa "Buscar ejercicios" para anadir tu primer ejercicio.</p>
            </section>

            <section class="ws-rest-overlay" id="ws-rest-overlay" hidden aria-live="polite" aria-label="Temporizador de descanso">
                <div class="ws-rest-card" id="ws-rest-card">
                    <svg class="ws-rest-svg" viewBox="0 0 120 120" aria-hidden="true">
                        <circle class="ws-rest-track" cx="60" cy="60" r="52"></circle>
                        <circle class="ws-rest-progress" id="ws-rest-progress" cx="60" cy="60" r="52"></circle>
                    </svg>
                    <div class="ws-rest-time" id="ws-rest-time">01:30</div>
                    <div class="ws-rest-controls">
                        <button type="button" class="ws-btn ws-btn--secondary" id="ws-rest-minus">-15s</button>
                        <button type="button" class="ws-btn ws-btn--secondary" id="ws-rest-plus">+15s</button>
                    </div>
                    <button type="button" class="ws-skip" id="ws-rest-skip">Saltar</button>
                </div>
            </section>

            <section class="ws-picker-overlay" id="ws-picker-overlay" hidden aria-label="Seleccion de ejercicios">
                <div class="ws-picker-card">
                    <header class="ws-picker-header">
                        <h4>Seleccionar ejercicio</h4>
                        <button type="button" class="ws-btn ws-btn--secondary" id="ws-picker-close">Cerrar</button>
                    </header>
                    <div class="ws-picker-search-row">
                        <input type="search" id="ws-picker-search" placeholder="Buscar por nombre (ingles o espanol)">
                    </div>
                    <div class="ws-picker-results" id="ws-picker-results"></div>
                </div>
            </section>

            <section class="ws-info-overlay" id="ws-info-overlay" hidden aria-label="Informacion del ejercicio">
                <div class="ws-info-card">
                    <header class="ws-info-header">
                        <div>
                            <p class="ws-info-kicker">Ejercicio</p>
                            <h4 id="ws-info-title">Informacion</h4>
                        </div>
                        <button type="button" class="ws-btn ws-btn--secondary" id="ws-info-close">Cerrar</button>
                    </header>
                    <div class="ws-info-body">
                        <section class="ws-info-block">
                            <h5>Como hacerlo</h5>
                            <p id="ws-info-instructions">No hay instrucciones disponibles.</p>
                        </section>
                        <section class="ws-info-block">
                            <h5>Seguridad</h5>
                            <p id="ws-info-safety">No hay medidas de seguridad disponibles.</p>
                        </section>
                    </div>
                </div>
            </section>
        </div>
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
                            @php
                                $exercisePayload = [
                                    'key' => sha1(strtolower(trim((string) ($exercise['name'] ?? ''))).'|'.strtolower(trim((string) ($exercise['muscle'] ?? ''))).'|'.strtolower(trim((string) ($exercise['type'] ?? '')))),
                                    'name' => $exercise['name'] ?? 'Ejercicio',
                                    'type' => $exercise['type'] ?? null,
                                    'muscle' => $exercise['muscle'] ?? null,
                                    'difficulty' => $exercise['difficulty'] ?? null,
                                    'equipment' => $exercise['equipment'] ?? null,
                                    'instructions' => $exercise['instructions'] ?? null,
                                    'safety_info' => $exercise['safety_info'] ?? null,
                                ];
                            @endphp
                            <button
                                type="button"
                                class="mini-btn js-workout-add"
                                data-workout-exercise='@json($exercisePayload)'
                            >
                                Anadir a sesion
                            </button>
                            <button
                                type="button"
                                class="mini-btn js-workout-info"
                                data-workout-info='@json($exercisePayload)'
                            >
                                Info
                            </button>
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
                        <svg class="meal-history-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
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
