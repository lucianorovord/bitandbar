@extends('plantilla')
@section('title','Registrar entrenamiento')
@section('contenido')
    @vite('resources/sass/inicio.scss')
    <section class="hero">
        <p class="hero__kicker">Deporte</p>
        <h2 class="hero__title">Registrar entrenamiento</h2>
        <p class="hero__text">
            Consulta ejercicios con API Ninjas por musculo, tipo, dificultad o nombre.
        </p>
    </section>

    <section class="search-panel">
        <div class="quick-filters">
            <a class="mini-btn" href="{{ url('/entrenamiento/registrar?muscle=chest') }}">Pecho</a>
            <a class="mini-btn" href="{{ url('/entrenamiento/registrar?muscle=middle_back') }}">Espalda Baja</a>
            <a class="mini-btn" href="{{ url('/entrenamiento/registrar?muscle=biceps') }}">Biceps</a>
            <a class="mini-btn" href="{{ url('/entrenamiento/registrar?muscle=quadriceps') }}">Pierna</a>
            <a class="mini-btn" href="{{ url('/entrenamiento/registrar?difficulty=beginner') }}">Principiante</a>
        </div>
        <form method="GET" action="{{ url('/entrenamiento/registrar') }}" class="search-form">
            <label for="muscle">Filtros de busqueda (puedes combinar varios)</label>
            <div class="search-form__row">
                <select id="name" name="name">
                    <option value="">Nombre (opcional)</option>
                    <option value="bench press" @selected(($filters['name'] ?? '') === 'bench press')>Bench press</option>
                    <option value="push up" @selected(($filters['name'] ?? '') === 'push up')>Push up</option>
                    <option value="squat" @selected(($filters['name'] ?? '') === 'squat')>Squat</option>
                    <option value="deadlift" @selected(($filters['name'] ?? '') === 'deadlift')>Deadlift</option>
                    <option value="pull up" @selected(($filters['name'] ?? '') === 'pull up')>Pull up</option>
                    <option value="bicep curl" @selected(($filters['name'] ?? '') === 'bicep curl')>Bicep curl</option>
                </select>
                <select id="muscle" name="muscle">
                    <option value="">Musculo (opcional)</option>
                    <option value="biceps" @selected(($filters['muscle'] ?? '') === 'biceps')>Biceps</option>
                    <option value="triceps" @selected(($filters['muscle'] ?? '') === 'triceps')>Triceps</option>
                    <option value="chest" @selected(($filters['muscle'] ?? '') === 'chest')>Pecho</option>
                    <option value="back" @selected(($filters['muscle'] ?? '') === 'back')>Espalda</option>
                    <option value="shoulders" @selected(($filters['muscle'] ?? '') === 'shoulders')>Hombros</option>
                    <option value="quadriceps" @selected(($filters['muscle'] ?? '') === 'quadriceps')>Cuadriceps</option>
                    <option value="hamstrings" @selected(($filters['muscle'] ?? '') === 'hamstrings')>Isquiotibiales</option>
                    <option value="abdominals" @selected(($filters['muscle'] ?? '') === 'abdominals')>Abdominales</option>
                </select>
            </div>
            <div class="search-form__row search-form__row--spaced">
                <select id="type" name="type">
                    <option value="">Tipo (opcional)</option>
                    <option value="strength" @selected(($filters['type'] ?? '') === 'strength')>Strength</option>
                    <option value="stretching" @selected(($filters['type'] ?? '') === 'stretching')>Stretching</option>
                    <option value="plyometrics" @selected(($filters['type'] ?? '') === 'plyometrics')>Plyometrics</option>
                    <option value="cardio" @selected(($filters['type'] ?? '') === 'cardio')>Cardio</option>
                    <option value="powerlifting" @selected(($filters['type'] ?? '') === 'powerlifting')>Powerlifting</option>
                    <option value="olympic_weightlifting" @selected(($filters['type'] ?? '') === 'olympic_weightlifting')>Olympic weightlifting</option>
                </select>
                <select id="difficulty" name="difficulty">
                    <option value="">Dificultad (opcional)</option>
                    <option value="beginner" @selected(($filters['difficulty'] ?? '') === 'beginner')>beginner</option>
                    <option value="intermediate" @selected(($filters['difficulty'] ?? '') === 'intermediate')>intermediate</option>
                    <option value="expert" @selected(($filters['difficulty'] ?? '') === 'expert')>expert</option>
                </select>
                <button type="submit" class="hero__cta">Buscar</button>
            </div>
            @error('name')
                <p class="form-error">{{ $message }}</p>
            @enderror
            @error('muscle')
                <p class="form-error">{{ $message }}</p>
            @enderror
            @error('type')
                <p class="form-error">{{ $message }}</p>
            @enderror
            @error('difficulty')
                <p class="form-error">{{ $message }}</p>
            @enderror
            @if(!empty($error))
                <p class="form-error">{{ $error }}</p>
            @endif
            @if(session('workout_success'))
                <p class="form-success">{{ session('workout_success') }}</p>
            @endif
            @if(session('workout_error'))
                <p class="form-error">{{ session('workout_error') }}</p>
            @endif
        </form>
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
                            <th>Minutos</th>
                            <th>Volumen</th>
                            <th>Accion</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($workout_cart as $itemKey => $item)
                            <tr>
                                <td>{{ $item['name'] }}</td>
                                <td>{{ $item['sets'] ?? 0 }}</td>
                                <td>{{ $item['reps'] ?? 0 }}</td>
                                <td>{{ $item['minutes'] ?? 0 }}</td>
                                <td>{{ (int) ($item['sets'] ?? 0) * (int) ($item['reps'] ?? 0) }}</td>
                                <td>
                                    <form method="POST" action="{{ url('/entrenamiento/carrito/actualizar/'.$itemKey) }}" class="inline-form">
                                        @csrf
                                        <input type="hidden" name="name_filter" value="{{ $filters['name'] ?? '' }}">
                                        <input type="hidden" name="muscle_filter" value="{{ $filters['muscle'] ?? '' }}">
                                        <input type="hidden" name="type_filter" value="{{ $filters['type'] ?? '' }}">
                                        <input type="hidden" name="difficulty_filter" value="{{ $filters['difficulty'] ?? '' }}">
                                        <input type="hidden" name="page" value="{{ $exercises->currentPage() }}">
                                        <input type="number" name="sets" min="1" max="20" value="{{ $item['sets'] ?? 3 }}">
                                        <input type="number" name="reps" min="1" max="100" value="{{ $item['reps'] ?? 12 }}">
                                        <input type="number" name="minutes" min="0" max="300" value="{{ $item['minutes'] ?? 0 }}">
                                        <button type="submit" class="mini-btn">Actualizar</button>
                                    </form>
                                    <form method="POST" action="{{ url('/entrenamiento/carrito/eliminar/'.$itemKey) }}">
                                        @csrf
                                        <input type="hidden" name="name_filter" value="{{ $filters['name'] ?? '' }}">
                                        <input type="hidden" name="muscle_filter" value="{{ $filters['muscle'] ?? '' }}">
                                        <input type="hidden" name="type_filter" value="{{ $filters['type'] ?? '' }}">
                                        <input type="hidden" name="difficulty_filter" value="{{ $filters['difficulty'] ?? '' }}">
                                        <input type="hidden" name="page" value="{{ $exercises->currentPage() }}">
                                        <button type="submit" class="mini-btn mini-btn--danger">Quitar</button>
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
                    <input type="text" name="notes" placeholder="Notas (ej: foco en tecnica)">
                    <button type="submit" class="hero__cta">Registrar entrenamiento</button>
                </div>
                <input type="hidden" name="name_filter" value="{{ $filters['name'] ?? '' }}">
                <input type="hidden" name="muscle_filter" value="{{ $filters['muscle'] ?? '' }}">
                <input type="hidden" name="type_filter" value="{{ $filters['type'] ?? '' }}">
                <input type="hidden" name="difficulty_filter" value="{{ $filters['difficulty'] ?? '' }}">
                <input type="hidden" name="page" value="{{ $exercises->currentPage() }}">
            </form>

            <form method="POST" action="{{ url('/entrenamiento/carrito/limpiar') }}">
                @csrf
                <input type="hidden" name="name_filter" value="{{ $filters['name'] ?? '' }}">
                <input type="hidden" name="muscle_filter" value="{{ $filters['muscle'] ?? '' }}">
                <input type="hidden" name="type_filter" value="{{ $filters['type'] ?? '' }}">
                <input type="hidden" name="difficulty_filter" value="{{ $filters['difficulty'] ?? '' }}">
                <input type="hidden" name="page" value="{{ $exercises->currentPage() }}">
                <button type="submit" class="mini-btn mini-btn--danger">Vaciar registro</button>
            </form>
        @else
            <p>Aun no has anadido ejercicios al registro.</p>
        @endif
    </section>

    @if($exercises->count() > 0)
        <section class="results-panel">
            <h3>Resultados de ejercicios</h3>
            <div class="exercise-grid">
                @foreach($exercises as $exercise)
                    <article class="exercise-card">
                        <h2 class="exercise-card__title">{{ $exercise['name'] }}</h2>
                        <div class="exercise-card__meta">
                            <span class="exercise-pill"><strong>Tipo:</strong> {{ $exercise['type'] ?? '-' }}</span>
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
                                <input type="hidden" name="name_filter" value="{{ $filters['name'] ?? '' }}">
                                <input type="hidden" name="muscle_filter" value="{{ $filters['muscle'] ?? '' }}">
                                <input type="hidden" name="type_filter" value="{{ $filters['type'] ?? '' }}">
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
            <p>No se encontraron ejercicios para ese filtro.</p>
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
                            <input type="hidden" name="name_filter" value="{{ $filters['name'] ?? '' }}">
                            <input type="hidden" name="muscle_filter" value="{{ $filters['muscle'] ?? '' }}">
                            <input type="hidden" name="type_filter" value="{{ $filters['type'] ?? '' }}">
                            <input type="hidden" name="difficulty_filter" value="{{ $filters['difficulty'] ?? '' }}">
                            <input type="hidden" name="page" value="{{ $exercises->currentPage() }}">
                        </form>

                        <form method="POST" action="{{ url('/entrenamiento/registro/eliminar/'.$workoutIndex) }}">
                            @csrf
                            <input type="hidden" name="name_filter" value="{{ $filters['name'] ?? '' }}">
                            <input type="hidden" name="muscle_filter" value="{{ $filters['muscle'] ?? '' }}">
                            <input type="hidden" name="type_filter" value="{{ $filters['type'] ?? '' }}">
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
                                        <th>Volumen</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach(($workout['items'] ?? []) as $item)
                                        <tr>
                                            <td>{{ $item['name'] ?? 'Ejercicio' }}</td>
                                            <td>{{ $item['sets'] ?? 0 }}</td>
                                            <td>{{ $item['reps'] ?? 0 }}</td>
                                            <td>{{ (int) ($item['sets'] ?? 0) * (int) ($item['reps'] ?? 0) }}</td>
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
