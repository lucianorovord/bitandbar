@extends('plantilla')
@section('title','Inicio')
@section('contenido')
    @vite('resources/sass/inicio.scss')
    @vite('resources/js/inicio/inicio.js')
    <section class="hero">
        <p class="hero__kicker">Nutricion y entrenamiento personalizado</p>
        <h2 class="hero__title">Bit & Bar: mejora tu salud con datos y constancia</h2>
        <p class="hero__text">
            Gestiona tus habitos diarios de alimentacion y ejercicio desde una sola plataforma.
            Registra tus avances y mantente enfocado en tus objetivos de bienestar.
        </p>
    </section>

    <section class="home-highlight home-highlight--banner" aria-label="Resumen visual de la plataforma">
        <div class="home-highlight__overlay">
            <h3>Tu plan saludable en un solo panel</h3>
            <img src="{{asset('images/inicio/gym_motivation')}}" alt="">
        </div>
    </section>

    <section class="home-feature home-feature--food" aria-label="Registro de comidas">
        <div class="home-feature__media">
            <div class="home-feature__media-placeholder">
                <img src="{{asset('images/inicio/comidas.jpeg')}}" alt="comidas orientales">
            </div>
        </div>
        <div class="home-feature__content">
            <h3>Registro de comidas</h3>
            <p>Busca alimentos, anadelos al carrito nutricional y guarda cada comida con su reparto de calorias y macros.</p>
            @auth
                <section class="kcal-box" aria-label="Resumen calorico diario">
                    <h3 class="kcal-box__title">Resumen de comidas registradas</h3>
                    <p class="kcal-box__total">Kcal totales: <strong>{{ $kcal_total ?? 0 }}</strong></p>
                    <div class="kcal-box__list">
                        @foreach(($kcal_by_type ?? []) as $item)
                            <div class="kcal-box__item">
                                <span>{{ $item['label'] }}</span>
                                <strong>{{ $item['kcal'] }} kcal</strong>
                            </div>
                        @endforeach
                    </div>

                    @if(!empty($meal_history))
                        <div class="kcal-box__history">
                            @foreach($meal_history as $meal)
                                <details class="kcal-meal-item">
                                    <summary class="kcal-meal-summary">
                                        <span><strong>{{ ucfirst($meal['meal_type'] ?? '') }}</strong> - {{ $meal['registered_at'] ?? '' }}</span>
                                        <span>{{ $meal['totals']['calories'] ?? 0 }} kcal</span>
                                    </summary>
                                    <div class="kcal-meal-content">
                                        @if(!empty($meal['notes']))
                                            <p><strong>Nota:</strong> {{ $meal['notes'] }}</p>
                                        @endif
                                        <ul>
                                            @foreach(($meal['items'] ?? []) as $item)
                                                <li>{{ $item['name'] ?? 'Alimento' }} (x{{ $item['quantity'] ?? 1 }})</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                </details>
                            @endforeach
                        </div>
                    @endif
                </section>
            @endauth
        </div>
    </section>

    <section class="home-feature home-feature--training" aria-label="Registro de entrenamientos">
        <div class="home-feature__content">
            <h3>Registro de entrenamientos</h3>
            <p>Selecciona grupo muscular, revisa ejercicios por dificultad y registra volumen total para seguir tu progreso semanal.</p>
            @auth
                <section class="kcal-box" aria-label="Resumen de entrenamientos">
                    <h3 class="kcal-box__title">Resumen de entrenamientos</h3>
                    
                    <p class="kcal-box__total">Volumen total: <strong>{{ $workout_total_volume ?? 0 }}</strong></p>
                    <div class="kcal-box__list">
                        @foreach(($workout_by_type ?? []) as $item)
                            <div class="kcal-box__item">
                                <span>{{ $item['label'] }}</span>
                                <strong>{{ $item['volume'] }}</strong>
                            </div>
                        @endforeach
                    </div>

                    @if(!empty($workout_history))
                        <div class="kcal-box__history">
                            @foreach($workout_history as $workout)
                                <details class="kcal-meal-item">
                                    <summary class="kcal-meal-summary">
                                        <span><strong>{{ ucfirst($workout['training_type'] ?? '') }}</strong> - {{ $workout['registered_at'] ?? '' }}</span>
                                        <span>Vol {{ $workout['totals']['volume'] ?? 0 }}</span>
                                    </summary>
                                    <div class="kcal-meal-content">
                                        @if(!empty($workout['notes']))
                                            <p><strong>Nota:</strong> {{ $workout['notes'] }}</p>
                                        @endif
                                        <ul>
                                            @foreach(($workout['items'] ?? []) as $item)
                                                @php
                                                    $homeSetWeights = array_values(array_map(
                                                        fn ($value) => round((float) $value, 2),
                                                        is_array($item['set_weights'] ?? null) ? $item['set_weights'] : []
                                                    ));
                                                    $homeSumWeights = array_sum($homeSetWeights);
                                                    $homeVolume = $homeSumWeights > 0
                                                        ? $homeSumWeights * (int) ($item['reps'] ?? 0)
                                                        : (int) ($item['sets'] ?? 0) * (int) ($item['reps'] ?? 0);
                                                @endphp
                                                <li>
                                                    {{ $item['name'] ?? 'Ejercicio' }} -
                                                    S {{ $item['sets'] ?? 0 }},
                                                    R {{ $item['reps'] ?? 0 }},
                                                    V {{ $homeVolume }}
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                </details>
                            @endforeach
                        </div>
                    @endif
                </section>
            @endauth
        </div>
        <div class="home-feature__media2">
            <div class="home-feature__media-placeholder"><img src="{{asset('images/inicio/jpeg')}}" alt="gym image"> </div>
        </div>
    </section>
@guest
    <section class="home-actions" aria-label="Acciones principales">
        <aside class="action-card">
            <h3 class="action-card__title">Registra tus entrenamientos y comidas</h3>
            <p class="action-card__text">
                Lleva un mejor control de tus ejercicios y calorías para una mejor vida saludable. <br>
                si no sabes como nosotros te podemos ayudar.
            </p>
            <br>
            <a class="site-nav__link" href="{{ route('login') }}">Iniciar sesion</a>
        </aside>
    </section>
@endguest
    <footer class="home-footer">
        <p>Bit & Bar | Nutricion y deporte para un estilo de vida saludable.</p>
    </footer>
@endsection
