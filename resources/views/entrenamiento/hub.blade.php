@extends('plantilla')
@section('title','Entrenamiento')
@section('contenido')
    @vite('resources/sass/entrenamientos/entrenamiento.scss')
    @vite('resources/js/entrenamiento/hub.js')

    <section class="ws-session-hero">
        <div class="ws-session-hero__left">
            <span class="ws-session-hero__emoji" aria-hidden="true">🏋️</span>
            <h2 class="ws-session-hero__title">Prepara tu sesión</h2>
            <p class="ws-session-hero__subtitle">Elige si quieres empezar un entrenamiento vacío o usar una plantilla guardada.</p>
        </div>
    </section>

    <section class="results-panel training-hub-panel" id="training-hub-panel" data-session-url="{{ url('/entrenamiento/sesion') }}" data-template-url="{{ url('/entrenamiento/plantillas') }}">
        <h3>Ultimos 3 registros</h3>
        <div class="training-hub-slider" id="training-hub-slider">
            @forelse($latest_workouts as $workout)
                <article class="training-hub-slide">
                    <h4>{{ ucfirst($workout['training_type'] ?? 'Entrenamiento') }}</h4>
                    <p>{{ $workout['registered_at'] ?? '' }}</p>
                    <p>Volumen: <strong>{{ $workout['totals']['volume'] ?? 0 }}</strong></p>
                    <p>Series: <strong>{{ $workout['totals']['sets'] ?? 0 }}</strong></p>
                </article>
            @empty
                <article class="training-hub-slide">
                    <h4>Sin registros recientes</h4>
                    <p>Empieza tu primera sesion para ver historial aqui.</p>
                </article>
            @endforelse
        </div>

        <div class="training-hub-actions">
            <button type="button" class="ws-btn ws-btn--primary" id="hub-start-training">Empezar Entrenamiento</button>
            <a href="{{ url('/entrenamiento/plantillas') }}" class="ws-btn ws-btn--secondary">Plantilla entrenamiento</a>
        </div>
    </section>

    <section class="training-hub-choice" id="training-hub-choice" hidden>
        <div class="training-hub-choice__card">
            <h4>Como quieres empezar?</h4>
            <label class="training-choice-option">
                <input type="radio" name="start_mode" value="empty" checked>
                <span>Entrenamiento vacio</span>
            </label>
            <label class="training-choice-option">
                <input type="radio" name="start_mode" value="template">
                <span>Usar plantilla</span>
            </label>

            <div id="training-template-select-wrap" hidden>
                <label for="training-template-select">Plantilla</label>
                <select id="training-template-select"></select>
            </div>

            <div class="training-hub-choice__actions">
                <button type="button" class="ws-btn ws-btn--secondary" id="hub-choice-cancel">Cancelar</button>
                <button type="button" class="ws-btn ws-btn--primary" id="hub-choice-confirm">Continuar</button>
            </div>
        </div>
    </section>
@endsection
