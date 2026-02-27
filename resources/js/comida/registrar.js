const recipePanel = document.getElementById('recipe-panel');

if (recipePanel) {
    const recipeInput = document.getElementById('recipe-ingredients');
    const recipeError = document.getElementById('recipe-search-error');
    const recipeHelp = document.getElementById('recipe-search-help');
    const recipeResults = document.getElementById('recipe-suggestions');
    const useCartIngredientsBtn = document.getElementById('use-cart-ingredients');

    const searchUrl = recipePanel.dataset.searchUrl;
    const nutritionBaseUrl = recipePanel.dataset.nutritionBaseUrl;
    const importUrl = recipePanel.dataset.importUrl;
    const csrfToken = recipePanel.dataset.csrfToken;
    const query = recipePanel.dataset.query || '';

    let cartIngredientNames = [];
    try {
        cartIngredientNames = JSON.parse(recipePanel.dataset.cartIngredients || '[]');
    } catch (_e) {
        cartIngredientNames = [];
    }

    const recipeDetailsCache = {};
    let recipeSearchTimer = null;

    const getIngredientArray = (raw) => raw
        .split(',')
        .map((v) => v.trim())
        .filter(Boolean);

    const renderNutritionPanel = (recipeId, data) => {
        const target = document.getElementById(`recipe-nutrition-${recipeId}`);
        if (!target) {
            return;
        }

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
        if (!importBtn) {
            return;
        }

        importBtn.addEventListener('click', async () => {
            const payload = recipeDetailsCache[importBtn.dataset.recipeId] || {};
            const response = await fetch(importUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({
                    recipe_title: payload.title || 'Receta',
                    ingredients: payload.ingredients || [],
                    q: query,
                }),
            });

            if (response.ok) {
                window.location.reload();
                return;
            }

            recipeError.textContent = 'No se pudo importar la receta al carrito.';
            recipeError.style.display = 'block';
        });
    };

    const bindNutritionButtons = () => {
        document.querySelectorAll('.js-load-nutrition').forEach((button) => {
            button.addEventListener('click', async () => {
                const recipeId = button.dataset.recipeId;
                recipeError.style.display = 'none';

                try {
                    const response = await fetch(`${nutritionBaseUrl}/${recipeId}/nutrition-details`);
                    if (!response.ok) {
                        throw new Error('failed');
                    }
                    const data = await response.json();
                    renderNutritionPanel(recipeId, data);
                } catch (_e) {
                    recipeError.textContent = 'No se pudo cargar la nutricion de la receta.';
                    recipeError.style.display = 'block';
                }
            });
        });
    };

    const renderRecipeCards = (recipes) => {
        if (!Array.isArray(recipes) || recipes.length === 0) {
            recipeResults.innerHTML = '<p class="hero__text">No se encontraron recetas para esos ingredientes.</p>';
            return;
        }

        recipeResults.innerHTML = recipes.map((recipe) => {
            const used = (recipe.usedIngredients || []).map((i) => i.name).join(', ') || '-';
            const missed = (recipe.missedIngredients || []).map((i) => i.name).join(', ') || '-';
            const unused = (recipe.unusedIngredients || []).map((i) => i.name).join(', ') || '-';

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
                        <button type="button" class="mini-btn js-load-nutrition" data-recipe-id="${recipe.id}">
                            Ver nutricion e importar
                        </button>
                    </div>
                    <div class="exercise-card__section recipe-nutrition-panel" id="recipe-nutrition-${recipe.id}" style="display:none;"></div>
                </article>
            `;
        }).join('');

        bindNutritionButtons();
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
            const response = await fetch(searchUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
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
}
