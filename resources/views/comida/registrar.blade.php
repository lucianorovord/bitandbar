@extends('plantilla')
@section('title','Registrar comida')
@section('contenido')
    @vite('resources/sass/comidas/comidas.scss')
    <section class="hero">
        <p class="hero__kicker">Nutricion</p>
        <h2 class="hero__title">Registrar comida</h2>
        <p class="hero__text">
            Busca alimentos con FoodData Central y revisa sus nutrientes base.
        </p>
    </section>

    <section class="search-panel">
        <form method="GET" action="{{ url('/comida/registrar') }}" class="search-form">
            <label for="q">Buscar alimento</label>
            <div class="search-form__row">
                <input
                    id="q"
                    name="q"
                    type="text"
                    value="{{ old('q', $query ?? '') }}"
                    placeholder="Ejemplo: chicken breast"
                >
                <button type="submit" class="hero__cta">Buscar</button>
            </div>
            @error('q')
                <p class="form-error">{{ $message }}</p>
            @enderror
            @if(!empty($error))
                <p class="form-error">{{ $error }}</p>
            @endif
        </form>
        @if(session('food_success'))
            <p class="form-success">{{ session('food_success') }}</p>
        @endif
        @if(session('food_error'))
            <p class="form-error">{{ session('food_error') }}</p>
        @endif
    </section>

    <section class="results-panel">
        <h3>Registro de comida</h3>
        @if(!empty($cart))
            <div class="results-table-wrap">
                <table class="results-table">
                    <thead>
                        <tr>
                            <th>Alimento</th>
                            <th>Cantidad</th>
                            <th>Kcal</th>
                            <th>Proteina</th>
                            <th>Carbohidratos</th>
                            <th>Grasa</th>
                            <th>Accion</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($cart as $itemKey => $item)
                            <tr>
                                <td>
                                    {{ $item['name'] }}
                                    @if(!empty($item['brand']))
                                        <br><small>{{ $item['brand'] }}</small>
                                    @endif
                                </td>
                                <td>
                                    <form method="POST" action="{{ url('/comida/carrito/actualizar/'.$itemKey) }}" class="inline-form">
                                        @csrf
                                        <input type="hidden" name="q" value="{{ $query }}">
                                        <input type="number" step="0.1" min="0.1" max="20" name="quantity" value="{{ $item['quantity'] }}">
                                        <button type="submit" class="mini-btn">Actualizar</button>
                                    </form>
                                </td>
                                <td>{{ $item['calories'] ?? '-' }}</td>
                                <td>{{ $item['protein'] ?? '-' }}</td>
                                <td>{{ $item['carbs'] ?? '-' }}</td>
                                <td>{{ $item['fat'] ?? '-' }}</td>
                                <td>
                                    <form method="POST" action="{{ url('/comida/carrito/eliminar/'.$itemKey) }}">
                                        @csrf
                                        <input type="hidden" name="q" value="{{ $query }}">
                                        <button type="submit" class="mini-btn mini-btn--danger">Quitar</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="cart-summary">
                <p><strong>Total kcal:</strong> {{ $cart_totals['calories'] }}</p>
                <p><strong>Total proteina:</strong> {{ $cart_totals['protein'] }}</p>
                <p><strong>Total carbohidratos:</strong> {{ $cart_totals['carbs'] }}</p>
                <p><strong>Total grasa:</strong> {{ $cart_totals['fat'] }}</p>
            </div>

            <form method="POST" action="{{ url('/comida/registrar/guardar') }}" class="search-form">
                @csrf
                <input type="hidden" name="q" value="{{ $query }}">
                <div class="search-form__row">
                    <select name="meal_type" required>
                        <option value="">Tipo de comida</option>
                        <option value="desayuno">Desayuno</option>
                        <option value="comida">Comida</option>
                        <option value="cena">Cena</option>
                        <option value="snacks">Snacks</option>
                    </select>
                    <input type="text" name="notes" placeholder="Notas opcionales (ej: post-entreno)">
                    <button type="submit" class="hero__cta">Registrar comida</button>
                </div>
            </form>

            <form method="POST" action="{{ url('/comida/carrito/limpiar') }}">
                @csrf
                <input type="hidden" name="q" value="{{ $query }}">
                <button type="submit" class="mini-btn mini-btn--danger">Vaciar carrito</button>
            </form>
        @else
            <p>Aun no has anadido alimentos al carrito.</p>
        @endif
    </section>

    @if(!empty($foods))
        <section class="results-panel">
            <h3>Resultados de busqueda</h3>
            <div class="results-table-wrap">
                <table class="results-table">
                    <thead>
                        <tr>
                            <th>Alimento</th>
                            <th>Marca</th>
                            <th>Kcal</th>
                            <th>Proteina</th>
                            <th>Carbohidratos</th>
                            <th>Grasa</th>
                            <th>Cantidad x porcion</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($foods as $food)
                            <tr>
                                <td>{{ $food['name'] }}</td>
                                <td>{{ $food['brand'] ?? '-' }}</td>
                                <td>{{ $food['calories'] ?? '-' }}</td>
                                <td>{{ $food['protein'] ?? '-' }}</td>
                                <td>{{ $food['carbs'] ?? '-' }}</td>
                                <td>{{ $food['fat'] ?? '-' }}</td>
                                <td>
                                    <form method="POST" action="{{ url('/comida/carrito/agregar') }}" class="inline-form">
                                        @csrf
                                        <input type="hidden" name="q" value="{{ $query }}">
                                        <input type="hidden" name="fdc_id" value="{{ $food['fdc_id'] ?? '' }}">
                                        <input type="hidden" name="name" value="{{ $food['name'] }}">
                                        <input type="hidden" name="brand" value="{{ $food['brand'] ?? '' }}">
                                        <input type="hidden" name="calories" value="{{ $food['calories'] ?? '' }}">
                                        <input type="hidden" name="protein" value="{{ $food['protein'] ?? '' }}">
                                        <input type="hidden" name="carbs" value="{{ $food['carbs'] ?? '' }}">
                                        <input type="hidden" name="fat" value="{{ $food['fat'] ?? '' }}">
                                        <input type="number" name="quantity" min="0.1" max="20" step="0.1" value="1">
                                        <button type="submit" class="mini-btn"> <strong> + </strong></button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @elseif(!empty($query))
        <section class="results-panel">
            <p>No se encontraron resultados para la busqueda actual.</p>
        </section>
    @endif


    @if(!empty($query))
        <section
            class="results-panel"
            id="recipe-panel"
            data-search-url="{{ url('/recipes/search-by-ingredients') }}"
            data-nutrition-base-url="{{ url('/recipes') }}"
            data-import-url="{{ url('/comida/carrito/importar-receta') }}"
            data-csrf-token="{{ csrf_token() }}"
            data-query="{{ $query }}"
            data-cart-ingredients='@json(array_values(array_unique(array_map(fn ($item) => $item['name'] ?? '', $cart ?? []))))'
        >
            <h3>Recetas sugeridas con ese ingrediente</h3>
            <div class="search-form">
                <p id="recipe-search-help" class="hero__text">
                    Resultados del ingrediente buscado en formato carrusel.
                </p>
                <p id="recipe-search-error" class="form-error" style="display:none;"></p>
            </div>
            <div id="recipe-suggestions" class="recipe-slider"></div>
        </section>
    @endif

    @if(!empty($meal_history))
        <section class="results-panel">
            <h3>Comidas registradas (sesion actual)</h3>
            @foreach(array_reverse($meal_history, true) as $mealIndex => $meal)
                <details class="meal-history-item">
                    <summary class="meal-history-summary">
                        <span><strong>{{ ucfirst($meal['meal_type']) }}</strong> - {{ $meal['registered_at'] }}</span>
                        <span>{{ $meal['totals']['calories'] }} kcal</span>
                    </summary>

                    <div class="meal-history-content">
                        <p>
                            Totales:
                            {{ $meal['totals']['calories'] }} kcal ->
                            P {{ $meal['totals']['protein'] }} | 
                            C {{ $meal['totals']['carbs'] }} | 
                            G {{ $meal['totals']['fat'] }}
                        </p>

                        @if(!empty($meal['notes']))
                            <p>Nota: {{ $meal['notes'] }}</p>
                        @endif

                        <form method="POST" action="{{ url('/comida/registro/editar/'.$mealIndex) }}" class="search-form">
                            @csrf
                            <input type="hidden" name="q" value="{{ $query }}">
                            <div class="search-form__row">
                                <select name="meal_type" required>
                                    <option value="desayuno" @selected(($meal['meal_type'] ?? '') === 'desayuno')>Desayuno</option>
                                    <option value="comida" @selected(in_array(($meal['meal_type'] ?? ''), ['comida', 'almuerzo'], true))>Comida</option>
                                    <option value="cena" @selected(($meal['meal_type'] ?? '') === 'cena')>Cena</option>
                                    <option value="snacks" @selected(in_array(($meal['meal_type'] ?? ''), ['snack', 'snacks'], true))>Snacks</option>
                                </select>
                                <input type="text" name="notes" value="{{ $meal['notes'] ?? '' }}" placeholder="Editar nota">
                                <button type="submit" class="mini-btn">Guardar cambios</button>
                            </div>
                        </form>

                        <form method="POST" action="{{ url('/comida/registro/eliminar/'.$mealIndex) }}">
                            @csrf
                            <input type="hidden" name="q" value="{{ $query }}">
                            <button type="submit" class="mini-btn mini-btn--danger">Eliminar registro</button>
                        </form>

                        <div class="results-table-wrap">
                            <table class="results-table">
                                <thead>
                                    <tr>
                                        <th>Alimento</th>
                                        <th>Cantidad</th>
                                        <th>Kcal</th>
                                        <th>Proteina</th>
                                        <th>Carbohidratos</th>
                                        <th>Grasa</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($meal['items'] as $item)
                                        @php
                                            $q = (float) ($item['quantity'] ?? 1);
                                            $kcal = is_numeric($item['calories'] ?? null) ? round(((float) $item['calories']) * $q, 2) : '-';
                                            $protein = is_numeric($item['protein'] ?? null) ? round(((float) $item['protein']) * $q, 2) : '-';
                                            $carbs = is_numeric($item['carbs'] ?? null) ? round(((float) $item['carbs']) * $q, 2) : '-';
                                            $fat = is_numeric($item['fat'] ?? null) ? round(((float) $item['fat']) * $q, 2) : '-';
                                        @endphp
                                        <tr>
                                            <td>{{ $item['name'] }}</td>
                                            <td>{{ $item['quantity'] }}</td>
                                            <td>{{ $kcal }}</td>
                                            <td>{{ $protein }}</td>
                                            <td>{{ $carbs }}</td>
                                            <td>{{ $fat }}</td>
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

    @vite('resources/js/comida/registrar.js')

@endsection
