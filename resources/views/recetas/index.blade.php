@extends('plantilla')
@section('title','Recetas')
@section('contenido')
    @vite('resources/sass/inicio.scss')
    <section class="hero">
        <p class="hero__kicker">Recetas</p>
        <h2 class="hero__title">Buscar recetas por ingredientes</h2>
        <p class="hero__text">
            Escribe ingredientes separados por coma para consultar Spoonacular.
        </p>
    </section>

    <section class="search-panel">
        <form id="recipe-search-form" class="search-form">
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

    <script>
        const form = document.getElementById('recipe-search-form');
        const input = document.getElementById('ingredients');
        const errorNode = document.getElementById('recipe-error');
        const resultsNode = document.getElementById('recipe-results');

        const renderRecipeCard = (recipe) => {
            const missed = (recipe.missedIngredients || []).map(i => i.name).join(', ') || '-';
            const used = (recipe.usedIngredients || []).map(i => i.name).join(', ') || '-';
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
                        <p class="exercise-card__label">Used Ingredients</p>
                        <p class="exercise-card__text">${used}</p>
                    </div>
                    <div class="exercise-card__section">
                        <p class="exercise-card__label">Missed Ingredients</p>
                        <p class="exercise-card__text">${missed}</p>
                    </div>
                    <div class="exercise-card__section">
                        <p class="exercise-card__label">Unused Ingredients</p>
                        <p class="exercise-card__text">${unused}</p>
                    </div>
                    <div class="exercise-card__section">
                        <p class="exercise-card__label">Image Type</p>
                        <p class="exercise-card__text">${recipe.imageType || '-'}</p>
                    </div>
                </article>
            `;
        };

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            errorNode.style.display = 'none';
            resultsNode.innerHTML = '';

            const ingredients = input.value
                .split(',')
                .map(v => v.trim())
                .filter(Boolean);

            if (!ingredients.length) {
                errorNode.textContent = 'Debes introducir al menos un ingrediente.';
                errorNode.style.display = 'block';
                return;
            }

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
                    throw new Error('error');
                }

                const recipes = await response.json();
                if (!Array.isArray(recipes) || recipes.length === 0) {
                    resultsNode.innerHTML = '<p class="hero__text">No se encontraron recetas.</p>';
                    return;
                }

                resultsNode.innerHTML = recipes.map(renderRecipeCard).join('');
            } catch (_e) {
                errorNode.textContent = 'No se pudo consultar Spoonacular en este momento.';
                errorNode.style.display = 'block';
            }
        });
    </script>
@endsection
