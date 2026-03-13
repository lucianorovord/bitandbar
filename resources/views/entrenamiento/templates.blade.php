@extends('plantilla')
@section('title','Plantillas de entrenamiento')
@section('contenido')
    @vite('resources/sass/entrenamientos/entrenamiento.scss')
    @vite('resources/js/entrenamiento/templates.js')

    <section class="ws-session-hero">
        <div class="ws-session-hero__left">
            <span class="ws-session-hero__kicker">PLANTILLAS</span>
            <h2 class="ws-session-hero__title">Mis plantillas</h2>
        </div>
    </section>

    <section class="results-panel training-template-panel" id="training-template-panel" data-exercise-search-url="{{ url('/entrenamiento/ejercicios') }}">
        <form id="template-create-form" class="search-form training-template-form" data-loader-ignore="1">
            <label for="template-name">Nombre de plantilla</label>
            <input id="template-name" type="text" maxlength="80" placeholder="Ej: Pierna pesada" required>

            <div class="ws-tabs-row">
                <div class="ws-tabs" id="tpl-exercise-tabs"></div>
                <button type="button" class="ws-tab ws-tab--add" id="tpl-add-exercise-btn" aria-label="Anadir ejercicio">+</button>
            </div>

            <div class="training-template-actions">
                <button type="submit" class="ws-btn ws-btn--primary">Guardar plantilla</button>
                <a href="{{ url('/entrenamiento/registrar') }}" class="ws-btn ws-btn--secondary">Volver</a>
            </div>
        </form>

        <div class="training-template-list" id="training-template-list"></div>

        <section class="ws-picker-overlay" id="tpl-picker-overlay" hidden aria-label="Seleccion de ejercicios para plantilla">
            <div class="ws-picker-card">
                <header class="ws-picker-header">
                    <h4>Seleccionar ejercicio</h4>
                    <button type="button" class="ws-btn ws-btn--secondary" id="tpl-picker-close">Cerrar</button>
                </header>
                <div class="ws-picker-search-row">
                    <input type="search" id="tpl-picker-search" placeholder="Buscar por nombre (ingles o espanol)">
                </div>
                <div class="ws-picker-results" id="tpl-picker-results"></div>
            </div>
        </section>
    </section>
@endsection
