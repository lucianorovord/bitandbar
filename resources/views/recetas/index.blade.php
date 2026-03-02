@extends('plantilla')
@section('title','Recetas')
@section('contenido')
    @vite('resources/sass/comidas/comidas.scss')
    <section class="hero">
        <p class="hero__kicker">Recetas</p>
        <h2 class="hero__title">Buscar recetas por ingredientes</h2>
        <p class="hero__text">
            Escribe ingredientes separados por coma para consultar Spoonacular.
        </p>
    </section>

    <section class="search-panel">
        <form id="recipe-search-form" class="search-form" data-search-url="{{ url('/recipes/search-by-ingredients') }}" data-csrf-token="{{ csrf_token() }}">
            <label for="ingredients">Ingredientes</label>
            <div class="search-form__row">
                <input id="ingredients" type="text" placeholder="Ejemplo: apples, flour, sugar">
                <button type="submit" class="hero__cta">Buscar recetas</button>
            </div>
        </form>
        <p id="recipe-error" class="form-error" style="display:none;"></p>
    </section>

    <section class="results-panel">
        <h3>Resultados</h3>
        <div id="recipe-results" class="exercise-grid"></div>
    </section>

    @vite('resources/js/recetas/index.js')

@endsection
