@extends('plantilla')
@section('title','Recetas')
@section('contenido')
    @vite('resources/sass/comidas/comidas.scss')

    <section class="hero">
        <p class="hero__kicker">Recetas</p>
        <h2 class="hero__title">Buscar recetas</h2>
        <p class="hero__text">
            Busca por ingredientes, objetivos nutricionales o tipo de dieta.
        </p>
    </section>

    <div class="recipe-search-tabs" id="recipe-search-tabs">
        <button type="button" class="recipe-tab is-active" data-tab="ingredients">Por ingredientes</button>
        <button type="button" class="recipe-tab" data-tab="nutrients">Por nutrientes</button>
        <button type="button" class="recipe-tab" data-tab="diet">Dieta y filtros</button>
        <button type="button" class="recipe-tab" data-tab="favorites">Mis favoritos</button>
    </div>

    <section class="search-panel recipe-tab-panel is-active" id="tab-ingredients">
        <form id="recipe-search-form" class="search-form"
              data-search-url="{{ url('/recipes/search-by-ingredients') }}"
              data-csrf-token="{{ csrf_token() }}">
            <label for="ingredients">Ingredientes</label>
            <div class="search-form__row">
                <input id="ingredients" type="text" placeholder="Ejemplo: chicken, rice, tomato">
                <button type="submit" class="hero__cta">Buscar</button>
            </div>
        </form>
        <p id="recipe-error" class="form-error" hidden></p>
    </section>

    <section class="search-panel recipe-tab-panel" id="tab-nutrients" hidden>
        <form id="nutrients-search-form" class="search-form"
              data-search-url="{{ url('/recipes/search-by-nutrients') }}">
            <label>Filtra por objetivos nutricionales</label>
            <div class="recipe-nutrients-grid">
                <div class="recipe-nutrient-field">
                    <label for="maxCalories">Máx. calorías</label>
                    <input id="maxCalories" type="number" name="maxCalories" placeholder="Ej: 500" min="0">
                </div>
                <div class="recipe-nutrient-field">
                    <label for="minProtein">Mín. proteína (g)</label>
                    <input id="minProtein" type="number" name="minProtein" placeholder="Ej: 30" min="0">
                </div>
                <div class="recipe-nutrient-field">
                    <label for="minCarbs">Mín. carbohidratos (g)</label>
                    <input id="minCarbs" type="number" name="minCarbs" placeholder="Ej: 20" min="0">
                </div>
                <div class="recipe-nutrient-field">
                    <label for="maxFat">Máx. grasa (g)</label>
                    <input id="maxFat" type="number" name="maxFat" placeholder="Ej: 20" min="0">
                </div>
            </div>
            <div class="search-form__row search-form__row--spaced">
                <button type="submit" class="hero__cta">Buscar recetas</button>
            </div>
        </form>
        <p id="nutrients-error" class="form-error" hidden></p>
    </section>

    <section class="search-panel recipe-tab-panel" id="tab-diet" hidden data-search-url="{{ url('/recipes/search-complex') }}">
        <form id="diet-search-form" class="search-form">
            <label>Busca recetas que se adapten a tu estilo de vida</label>

            <div class="recipe-diet-section">
                <p class="recipe-filter-label">Tipo de dieta</p>
                <div class="recipe-chips-group" id="diet-chips">
                    @foreach([
                      ['value'=>'gluten free',    'label'=>'Sin gluten',    'icon'=>'bi-slash-circle'],
                      ['value'=>'vegan',          'label'=>'Vegano',        'icon'=>'bi-tree'],
                      ['value'=>'vegetarian',     'label'=>'Vegetariano',   'icon'=>'bi-flower1'],
                      ['value'=>'ketogenic',      'label'=>'Keto',          'icon'=>'bi-lightning'],
                      ['value'=>'paleo',          'label'=>'Paleo',         'icon'=>'bi-virus'],
                      ['value'=>'pescetarian',    'label'=>'Pescetariano',  'icon'=>'bi-water'],
                      ['value'=>'whole30',        'label'=>'Whole30',       'icon'=>'bi-circle'],
                      ['value'=>'low fodmap',     'label'=>'Low FODMAP',    'icon'=>'bi-heart-pulse'],
                    ] as $diet)
                        <button type="button"
                                class="recipe-chip"
                                data-filter-type="diet"
                                data-value="{{ $diet['value'] }}">
                            <i class="bi {{ $diet['icon'] }}"></i>
                            {{ $diet['label'] }}
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="recipe-diet-section">
                <p class="recipe-filter-label">Intolerancias (puedes elegir varias)</p>
                <div class="recipe-chips-group" id="intolerance-chips">
                    @foreach([
                      ['value'=>'gluten',    'label'=>'Gluten'],
                      ['value'=>'dairy',     'label'=>'Lácteos'],
                      ['value'=>'egg',       'label'=>'Huevo'],
                      ['value'=>'peanut',    'label'=>'Cacahuete'],
                      ['value'=>'soy',       'label'=>'Soja'],
                      ['value'=>'wheat',     'label'=>'Trigo'],
                      ['value'=>'tree nut',  'label'=>'Frutos secos'],
                      ['value'=>'seafood',   'label'=>'Marisco'],
                      ['value'=>'shellfish', 'label'=>'Crustáceos'],
                      ['value'=>'sulfite',   'label'=>'Sulfitos'],
                    ] as $intol)
                        <button type="button"
                                class="recipe-chip recipe-chip--intolerance"
                                data-filter-type="intolerance"
                                data-value="{{ $intol['value'] }}">
                            {{ $intol['label'] }}
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="recipe-diet-section">
                <p class="recipe-filter-label">Tipo de plato</p>
                <div class="recipe-chips-group" id="type-chips">
                    @foreach([
                      ['value'=>'main course',  'label'=>'Plato principal'],
                      ['value'=>'breakfast',    'label'=>'Desayuno'],
                      ['value'=>'dessert',      'label'=>'Postre'],
                      ['value'=>'salad',        'label'=>'Ensalada'],
                      ['value'=>'soup',         'label'=>'Sopa'],
                      ['value'=>'snack',        'label'=>'Snack'],
                      ['value'=>'side dish',    'label'=>'Acompañamiento'],
                      ['value'=>'bread',        'label'=>'Pan'],
                      ['value'=>'beverage',     'label'=>'Bebida'],
                    ] as $mtype)
                        <button type="button"
                                class="recipe-chip recipe-chip--type"
                                data-filter-type="mealtype"
                                data-value="{{ $mtype['value'] }}">
                            {{ $mtype['label'] }}
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="recipe-nutrients-grid" style="margin-top:0.8rem;">
                <div class="recipe-nutrient-field">
                    <label for="diet-query">Nombre (opcional)</label>
                    <input id="diet-query" type="text" placeholder="Ej: pasta, chicken...">
                </div>
                <div class="recipe-nutrient-field">
                    <label for="maxReadyTime">Tiempo máx. (min)</label>
                    <input id="maxReadyTime" type="number" placeholder="Ej: 30" min="5" max="300">
                </div>
            </div>

            <div class="recipe-active-filters" id="recipe-active-filters" hidden>
                <span class="recipe-filter-label">Filtros activos:</span>
                <div id="recipe-active-chips"></div>
                <button type="button" class="mini-btn" id="recipe-clear-filters">Limpiar todo</button>
            </div>

            <div class="search-form__row search-form__row--spaced">
                <button type="submit" class="hero__cta">Buscar recetas</button>
            </div>
        </form>
        <p id="diet-error" class="form-error" hidden></p>
    </section>

    <section class="search-panel recipe-tab-panel" id="tab-favorites" hidden data-favorites-url="{{ url('/recipes/favorites') }}">
        <p class="hero__text">Tus recetas guardadas aparecerán aquí.</p>
        <p id="favorites-error" class="form-error" hidden></p>
    </section>

    <section class="results-panel" id="recipe-results-panel">
        <h3 id="recipe-results-title">Resultados</h3>
        <div id="recipe-results" class="exercise-grid"></div>
    </section>

    <div class="recipe-modal-overlay" id="recipe-modal-overlay" hidden>
        <div class="recipe-modal" id="recipe-modal">
            <button type="button" class="recipe-modal__close" id="recipe-modal-close">
                <i class="bi bi-x-lg"></i>
            </button>
            <div class="recipe-modal__body" id="recipe-modal-body">
                <p class="hero__text">Cargando...</p>
            </div>
        </div>
    </div>

    @vite('resources/js/recetas/index.js')
@endsection
