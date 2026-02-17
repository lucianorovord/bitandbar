@extends('plantilla')
@section('title','Registrar comida')
@section('contenido')
    @vite('resources/sass/inicio.scss')
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
        <h3>Sugerencia de recetas por ingredientes</h3>
        <div class="search-form">
            <label for="recipe-ingredients">Ingredientes (separados por coma)</label>
            <div class="search-form__row">
                <input
                    id="recipe-ingredients"
                    type="text"
                    placeholder="Ejemplo: tortilla, chicken, cheese, onion"
                >
                <button type="button" id="use-cart-ingredients" class="mini-btn">Usar carrito</button>
            </div>
            <p id="recipe-search-help" class="hero__text">Empieza a escribir y se sugeriran recetas automaticamente.</p>
            <p id="recipe-search-error" class="form-error" style="display:none;"></p>
        </div>
        <div id="recipe-suggestions" class="exercise-grid"></div>
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
                            <th>Accion</th>
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

    <script>
        const recipeInput = document.getElementById('recipe-ingredients');
        const recipeError = document.getElementById('recipe-search-error');
        const recipeHelp = document.getElementById('recipe-search-help');
        const recipeResults = document.getElementById('recipe-suggestions');
        const useCartIngredientsBtn = document.getElementById('use-cart-ingredients');
        const cartIngredientNames = @json(array_values(array_unique(array_map(fn ($item) => $item['name'] ?? '', $cart ?? []))));
        const recipeDetailsCache = {};

        let recipeSearchTimer = null;

        const getIngredientArray = (raw) => raw
            .split(',')
            .map(v => v.trim())
            .filter(Boolean);

        const renderRecipeCards = (recipes) => {
            if (!Array.isArray(recipes) || recipes.length === 0) {
                recipeResults.innerHTML = '<p class="hero__text">No se encontraron recetas para esos ingredientes.</p>';
                return;
            }

            recipeResults.innerHTML = recipes.map((recipe) => {
                const used = (recipe.usedIngredients || []).map(i => i.name).join(', ') || '-';
                const missed = (recipe.missedIngredients || []).map(i => i.name).join(', ') || '-';
                const unused = (recipe.unusedIngredients || []).map(i => i.name).join(', ') || '-';

                return `
                    <article class="exercise-card">
                        <h2 class="exercise-card__title">${recipe.title || 'Sin titulo'}</h2>
                        <div class="exercise-card__meta">
                            <span class="exercise-pill"><strong>ID:</strong> ${recipe.id ?? '-'}</span>
                            <span class="exercise-pill"><strong>Likes:</strong> ${recipe.likes ?? '-'}</span>
                            <span class="exercise-pill"><strong>Used:</strong> ${recipe.usedIngredientCount ?? 0}</span>
                            <span class="exercise-pill"><strong>Missed:</strong> ${recipe.missedIngredientCount ?? 0}</span>
                        </div>
                        ${recipe.image ? `<img src="${recipe.image}" alt="${recipe.title || 'recipe'}" style="width:100%;margin-top:.7rem;border-radius:10px;">` : ''}
                        <div class="exercise-card__section">
                            <p class="exercise-card__label">Ingredientes usados</p>
                            <p class="exercise-card__text">${used}</p>
                        </div>
                        <div class="exercise-card__section">
                            <p class="exercise-card__label">Ingredientes faltantes</p>
                            <p class="exercise-card__text">${missed}</p>
                        </div>
                        <div class="exercise-card__section">
                            <p class="exercise-card__label">Ingredientes sin usar</p>
                            <p class="exercise-card__text">${unused}</p>
                        </div>
                        <div class="exercise-card__section">
                            <button type="button" class="mini-btn js-load-nutrition" data-recipe-id="${recipe.id}" data-recipe-title="${(recipe.title || '').replace(/"/g, '&quot;')}">
                                Ver nutricion e importar
                            </button>
                        </div>
                        <div class="exercise-card__section recipe-nutrition-panel" id="recipe-nutrition-${recipe.id}" style="display:none;"></div>
                    </article>
                `;
            }).join('');

            bindNutritionButtons();
        };

        const renderNutritionPanel = (recipeId, data) => {
            const target = document.getElementById(`recipe-nutrition-${recipeId}`);
            if (!target) return;
            recipeDetailsCache[recipeId] = data;

            const totals = data.dish_totals || {};
            const ingredients = Array.isArray(data.ingredients) ? data.ingredients : [];
            const ingredientsRows = ingredients.map((ing) => `
                <tr>
                    <td>${ing.name || '-'}</td>
                    <td>${ing.amount ?? '-'} ${ing.unit ?? ''}</td>
                    <td>${ing.calories ?? '-'}</td>
                    <td>${ing.protein ?? '-'}</td>
                    <td>${ing.carbs ?? '-'}</td>
                    <td>${ing.fat ?? '-'}</td>
                </tr>
            `).join('');

            target.innerHTML = `
                <p class="exercise-card__label">Nutricion total del plato</p>
                <p class="exercise-card__text">
                    Kcal: ${totals.calories ?? '-'} | P: ${totals.protein ?? '-'} | C: ${totals.carbs ?? '-'} | G: ${totals.fat ?? '-'}
                </p>
                <div class="results-table-wrap">
                    <table class="results-table">
                        <thead>
                            <tr>
                                <th>Ingrediente</th>
                                <th>Cantidad</th>
                                <th>Kcal</th>
                                <th>Proteina</th>
                                <th>Carbs</th>
                                <th>Grasa</th>
                            </tr>
                        </thead>
                        <tbody>${ingredientsRows}</tbody>
                    </table>
                </div>
                <button type="button" class="mini-btn js-import-recipe" data-recipe-id="${recipeId}">Importar ingredientes al carrito</button>
            `;
            target.style.display = 'block';

            const importBtn = target.querySelector('.js-import-recipe');
            if (importBtn) {
                importBtn.addEventListener('click', async () => {
                    const payload = recipeDetailsCache[importBtn.dataset.recipeId] || {};
                    const response = await fetch('{{ url('/comida/carrito/importar-receta') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        },
                        body: JSON.stringify({
                            recipe_title: payload.title || 'Receta',
                            ingredients: payload.ingredients || [],
                            q: '{{ $query }}',
                        }),
                    });

                    if (response.ok) {
                        window.location.reload();
                    } else {
                        recipeError.textContent = 'No se pudo importar la receta al carrito.';
                        recipeError.style.display = 'block';
                    }
                });
            }
        };

        const bindNutritionButtons = () => {
            document.querySelectorAll('.js-load-nutrition').forEach((button) => {
                button.addEventListener('click', async () => {
                    const recipeId = button.dataset.recipeId;
                    recipeError.style.display = 'none';
                    try {
                        const response = await fetch(`{{ url('/recipes') }}/${recipeId}/nutrition-details`);
                        if (!response.ok) throw new Error('failed');
                        const data = await response.json();
                        renderNutritionPanel(recipeId, data);
                    } catch (_e) {
                        recipeError.textContent = 'No se pudo cargar la nutricion de la receta.';
                        recipeError.style.display = 'block';
                    }
                });
            });
        };

        const fetchRecipeSuggestions = async () => {
            const ingredients = getIngredientArray(recipeInput.value);

            if (ingredients.length < 2) {
                recipeResults.innerHTML = '';
                recipeError.style.display = 'none';
                recipeHelp.textContent = 'Anade al menos 2 ingredientes para recibir sugerencias.';
                return;
            }

            recipeHelp.textContent = 'Consultando recetas...';
            recipeError.style.display = 'none';

            try {
                const response = await fetch('{{ url('/recipes/search-by-ingredients') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    },
                    body: JSON.stringify({ ingredients }),
                });

                if (!response.ok) {
                    throw new Error('request_failed');
                }

                const data = await response.json();
                renderRecipeCards(data);
                recipeHelp.textContent = `Sugerencias generadas para: ${ingredients.join(', ')}`;
            } catch (_error) {
                recipeError.textContent = 'No se pudieron cargar sugerencias de recetas en este momento.';
                recipeError.style.display = 'block';
                recipeHelp.textContent = 'Vuelve a intentarlo en unos segundos.';
            }
        };

        recipeInput.addEventListener('input', () => {
            clearTimeout(recipeSearchTimer);
            recipeSearchTimer = setTimeout(fetchRecipeSuggestions, 600);
        });

        useCartIngredientsBtn.addEventListener('click', () => {
            const names = cartIngredientNames.filter(Boolean);
            if (names.length === 0) {
                recipeError.textContent = 'El carrito esta vacio, no hay ingredientes para sugerir recetas.';
                recipeError.style.display = 'block';
                return;
            }

            recipeInput.value = names.join(', ');
            fetchRecipeSuggestions();
        });
    </script>
@endsection
