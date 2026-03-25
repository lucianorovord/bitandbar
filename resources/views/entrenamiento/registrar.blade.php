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
            <span class="ws-session-hero__kicker">
                <i class="bi bi-activity"></i> SESION ACTIVA
            </span>
            <h2 class="ws-session-hero__title">Registrar entrenamiento</h2>
        </div>
    </header>

    <section class="results-panel">
        <p id="workout-session-empty-copy">Aun no has anadido ejercicios a la sesion.</p>
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
                        <button type="button" class="ws-btn ws-btn--cancel" id="ws-cancel-btn" aria-label="Cancelar sesion sin guardar">
                            <i class="bi bi-x-circle"></i> Cancelar
                        </button>
                        <button type="button" class="ws-btn ws-btn--primary" id="ws-finish-btn">Finalizar</button>
                    </div>
                </header>

                <div id="ws-mobile-cards-container" class="ws-mobile-cards-container"></div>

                <div class="ws-tabs-row">
                    <div class="ws-tabs" id="ws-tabs" role="tablist" aria-label="Ejercicios en sesion"></div>
                    <button type="button" class="ws-tab ws-tab--add" id="ws-add-exercise-tab" aria-label="Anadir otro ejercicio">+</button>
                </div>

                <div class="ws-exercise-card" id="ws-exercise-card" hidden>
                    <div class="ws-exercise-card__header">
                        <div class="ws-exercise-card__info">
                            <h4 class="ws-exercise-title" id="ws-exercise-title"></h4>
                            <span class="ws-exercise-muscle" id="ws-exercise-muscle"></span>
                        </div>
                        <div class="ws-exercise-menu">
                            <button type="button" class="ws-exercise-menu__btn" id="ws-exercise-menu-btn" aria-label="Opciones del ejercicio">
                                <i class="bi bi-three-dots-vertical"></i>
                            </button>
                            <div class="ws-exercise-dropdown" id="ws-exercise-dropdown" hidden>
                                <button type="button" class="ws-exercise-dropdown__item" id="ws-exercise-info-btn">
                                    <i class="bi bi-info-circle"></i> Info del ejercicio
                                </button>
                                <button type="button" class="ws-exercise-dropdown__item ws-exercise-dropdown__item--danger" id="ws-exercise-delete-btn">
                                    <i class="bi bi-trash3"></i> Eliminar ejercicio
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="ws-table-wrap">
                        <table class="ws-table" aria-label="Series del ejercicio activo">
                            <thead>
                                <tr>
                                    <th class="ws-col-set">SERIE</th>
                                    <th class="ws-col-prev">ANTERIOR</th>
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

                <p class="ws-helper" id="ws-helper">Usa el boton + para anadir tu primer ejercicio.</p>
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
                    <div class="ws-picker-handle"></div>
                    <header class="ws-picker-header">
                        <h4>Seleccionar ejercicio</h4>
                        <button type="button" class="ws-btn ws-btn--secondary" id="ws-picker-close">
                            <i class="bi bi-x-lg"></i> Cerrar
                        </button>
                    </header>

                    <div class="ws-picker-search-row">
                        <i class="bi bi-search ws-picker-search-icon"></i>
                        <input type="search" id="ws-picker-search" placeholder="Buscar por nombre (ingles o espanol)">
                    </div>

                    @php
                        $pickerMuscleCards = [
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
                            ['value' => 'lower_back', 'label' => 'Esp. baja', 'img' => asset('images/muscles/lower_back.png')],
                            ['value' => 'middle_back', 'label' => 'Esp. media', 'img' => asset('images/muscles/middle_back.png')],
                            ['value' => 'neck', 'label' => 'Cuello', 'img' => asset('images/muscles/cuello.png')],
                            ['value' => 'quadriceps', 'label' => 'Cuadriceps', 'img' => asset('images/muscles/quads.png')],
                            ['value' => 'traps', 'label' => 'Trapecio', 'img' => asset('images/muscles/traps.png')],
                            ['value' => 'triceps', 'label' => 'Triceps', 'img' => asset('images/muscles/triceps.png')],
                            ['value' => 'shoulders', 'label' => 'Hombros', 'img' => asset('images/muscles/shoulders.png')],
                        ];
                    @endphp

                    <div class="ws-picker-muscle-section">
                        <span class="ws-picker-muscle-label">
                            <i class="bi bi-funnel"></i> Grupo muscular
                        </span>
                        <div class="ws-picker-muscle-scroll-wrap">
                            <button type="button" class="ws-picker-arr" id="ws-picker-arr-left" aria-label="Desplazar izquierda">
                                <i class="bi bi-chevron-left"></i>
                            </button>
                            <div class="ws-picker-muscle-track" id="ws-picker-muscle-track">
                                @foreach($pickerMuscleCards as $pmc)
                                    <button type="button" class="ws-picker-mchip" data-muscle="{{ $pmc['value'] }}" data-label="{{ $pmc['label'] }}">
                                        <img src="{{ $pmc['img'] }}" alt="{{ $pmc['label'] }}">
                                        <span>{{ $pmc['label'] }}</span>
                                    </button>
                                @endforeach
                            </div>
                            <button type="button" class="ws-picker-arr" id="ws-picker-arr-right" aria-label="Desplazar derecha">
                                <i class="bi bi-chevron-right"></i>
                            </button>
                        </div>
                        <div class="ws-picker-active-filter" id="ws-picker-active-filter" hidden>
                            <span class="ws-picker-filter-label">Filtro:</span>
                            <span class="ws-picker-filter-badge">
                                <span id="ws-picker-filter-text"></span>
                                <button type="button" id="ws-picker-filter-clear" aria-label="Quitar filtro">×</button>
                            </span>
                        </div>
                    </div>

                    <div class="ws-picker-results" id="ws-picker-results"></div>
                </div>
            </section>

            <section class="ws-stats-overlay" id="ws-stats-overlay" hidden aria-label="Resumen de sesion">
                <div class="ws-stats-sheet">
                    <div class="ws-picker-handle"></div>
                    <header class="ws-picker-header">
                        <h4><i class="bi bi-heart-pulse"></i> Resumen de sesion</h4>
                        <button type="button" class="ws-btn ws-btn--secondary" id="ws-stats-close">
                            <i class="bi bi-x-lg"></i> Cerrar
                        </button>
                    </header>
                    <div class="ws-stats-body">
                        <div class="ws-stats-section">
                            <span class="ws-stats-label">
                                <i class="bi bi-person-arms-up"></i> Musculos trabajados
                            </span>
                            <div class="ws-stats-muscles" id="ws-stats-muscles">
                                <p class="ws-helper">Completa series para ver los musculos trabajados.</p>
                            </div>
                        </div>
                        <div class="ws-stats-section">
                            <span class="ws-stats-label">
                                <i class="bi bi-graph-up-arrow"></i> Estadisticas
                            </span>
                            <div class="ws-stats-grid">
                                <div class="ws-stats-card">
                                    <span class="ws-stats-card__num" id="ws-stats-sets">0</span>
                                    <span class="ws-stats-card__label">Series completadas</span>
                                </div>
                                <div class="ws-stats-card">
                                    <span class="ws-stats-card__num" id="ws-stats-volume">0 kg</span>
                                    <span class="ws-stats-card__label">Volumen total</span>
                                </div>
                                <div class="ws-stats-card">
                                    <span class="ws-stats-card__num" id="ws-stats-exercises">0</span>
                                    <span class="ws-stats-card__label">Ejercicios</span>
                                </div>
                                <div class="ws-stats-card">
                                    <span class="ws-stats-card__num" id="ws-stats-reps">0</span>
                                    <span class="ws-stats-card__label">Reps totales</span>
                                </div>
                            </div>
                        </div>
                        <div class="ws-stats-section" style="border-bottom:none;">
                            <span class="ws-stats-label">
                                <i class="bi bi-stars"></i> Recomendaciones
                            </span>
                            <div class="ws-stats-recos" id="ws-stats-recos">
                                <p class="ws-helper">Las recomendaciones aparecen al finalizar la sesion.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </section>

    <nav class="ws-bottom-nav" id="ws-bottom-nav" aria-label="Navegacion principal">
        <a href="{{ url('/') }}" class="ws-nav-item">
            <i class="bi bi-house"></i>
            <span>Inicio</span>
        </a>
        <a href="{{ url('/historial') }}" class="ws-nav-item">
            <i class="bi bi-calendar3"></i>
            <span>Historial</span>
        </a>
        <button type="button" class="ws-nav-center" id="ws-nav-stats-btn" aria-label="Ver resumen de sesion">
            <i class="bi bi-heart-pulse"></i>
        </button>
        <a href="{{ url('/entrenamiento/plantillas') }}" class="ws-nav-item">
            <i class="bi bi-journal-text"></i>
            <span>Rutinas</span>
        </a>
        <a href="{{ url('/profile') }}" class="ws-nav-item {{ request()->is('profile*') ? 'is-active' : '' }}">
            <i class="bi bi-person-circle"></i>
            <span>Perfil</span>
        </a>
    </nav>
@endsection
