const RESULTS_NODE = document.getElementById('recipe-results');
const RESULTS_TITLE = document.getElementById('recipe-results-title');
const MODAL_OVERLAY = document.getElementById('recipe-modal-overlay');
const MODAL_BODY = document.getElementById('recipe-modal-body');
const MODAL_CLOSE = document.getElementById('recipe-modal-close');
const FAV_PANEL = document.getElementById('tab-favorites');
const INFO_BASE = '/recipes';
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || document.getElementById('recipe-search-form')?.dataset.csrfToken || '';

const activeFilters = { diet: null, intolerances: new Set(), mealtype: null };

document.querySelectorAll('.recipe-tab').forEach((tab) => {
  tab.addEventListener('click', () => {
    document.querySelectorAll('.recipe-tab').forEach((t) => t.classList.remove('is-active'));
    document.querySelectorAll('.recipe-tab-panel').forEach((p) => {
      p.hidden = true;
    });
    tab.classList.add('is-active');
    const panel = document.getElementById(`tab-${tab.dataset.tab}`);
    if (panel) panel.hidden = false;
    if (tab.dataset.tab === 'favorites') loadFavorites();
  });
});

document.querySelectorAll('.recipe-chip').forEach((chip) => {
  chip.addEventListener('click', () => {
    const type = chip.dataset.filterType;
    const value = chip.dataset.value;

    if (type === 'intolerance') {
      if (activeFilters.intolerances.has(value)) {
        activeFilters.intolerances.delete(value);
        chip.classList.remove('is-active');
      } else {
        activeFilters.intolerances.add(value);
        chip.classList.add('is-active');
      }
    } else {
      const groupKey = type === 'diet' ? 'diet' : 'mealtype';
      const siblings = document.querySelectorAll(`.recipe-chip[data-filter-type="${type}"]`);
      if (activeFilters[groupKey] === value) {
        activeFilters[groupKey] = null;
        chip.classList.remove('is-active');
      } else {
        siblings.forEach((s) => s.classList.remove('is-active'));
        activeFilters[groupKey] = value;
        chip.classList.add('is-active');
      }
    }

    updateActiveFilterBar();
  });
});

function updateActiveFilterBar() {
  const bar = document.getElementById('recipe-active-filters');
  const chips = document.getElementById('recipe-active-chips');
  if (!bar || !chips) return;

  const all = [];
  if (activeFilters.diet) all.push({ label: activeFilters.diet, type: 'diet' });
  if (activeFilters.mealtype) all.push({ label: activeFilters.mealtype, type: 'mealtype' });
  activeFilters.intolerances.forEach((value) => all.push({ label: value, type: 'intolerance' }));

  if (!all.length) {
    bar.hidden = true;
    chips.innerHTML = '';
    return;
  }

  bar.hidden = false;
  chips.innerHTML = all.map((filter) => `
    <span class="recipe-active-chip">
      ${escHtml(filter.label)}
      <button type="button"
              data-clear-type="${filter.type}"
              data-clear-value="${escHtml(filter.label)}"
              aria-label="Quitar filtro">×</button>
    </span>
  `).join('');

  chips.querySelectorAll('button[data-clear-type]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const type = btn.dataset.clearType;
      const value = btn.dataset.clearValue;

      if (type === 'intolerance') {
        activeFilters.intolerances.delete(value);
        document.querySelectorAll(`.recipe-chip[data-filter-type="intolerance"][data-value="${cssEscape(value)}"]`).forEach((chip) => chip.classList.remove('is-active'));
      } else if (type === 'diet') {
        activeFilters.diet = null;
        document.querySelectorAll('.recipe-chip[data-filter-type="diet"]').forEach((chip) => chip.classList.remove('is-active'));
      } else if (type === 'mealtype') {
        activeFilters.mealtype = null;
        document.querySelectorAll('.recipe-chip[data-filter-type="mealtype"]').forEach((chip) => chip.classList.remove('is-active'));
      }

      updateActiveFilterBar();
    });
  });
}

document.getElementById('recipe-clear-filters')?.addEventListener('click', () => {
  activeFilters.diet = null;
  activeFilters.mealtype = null;
  activeFilters.intolerances.clear();
  document.querySelectorAll('.recipe-chip').forEach((chip) => chip.classList.remove('is-active'));
  updateActiveFilterBar();
});

document.getElementById('diet-search-form')?.addEventListener('submit', async (event) => {
  event.preventDefault();
  const errorNode = document.getElementById('diet-error');
  if (errorNode) errorNode.hidden = true;

  const searchUrl = document.getElementById('tab-diet')?.dataset.searchUrl;
  if (!searchUrl || !RESULTS_NODE || !RESULTS_TITLE) return;

  RESULTS_NODE.innerHTML = '<p class="hero__text">Buscando...</p>';
  RESULTS_TITLE.textContent = 'Recetas filtradas';

  const params = new URLSearchParams();
  const query = document.getElementById('diet-query')?.value.trim();
  const maxTime = document.getElementById('maxReadyTime')?.value.trim();

  if (query) params.set('query', query);
  if (activeFilters.diet) params.set('diet', activeFilters.diet);
  if (activeFilters.mealtype) params.set('type', activeFilters.mealtype);
  if (activeFilters.intolerances.size) params.set('intolerances', [...activeFilters.intolerances].join(','));
  if (maxTime) params.set('maxReadyTime', maxTime);
  params.set('number', '6');

  if (!activeFilters.diet && !activeFilters.mealtype && !activeFilters.intolerances.size && !query) {
    if (errorNode) {
      errorNode.textContent = 'Selecciona al menos un filtro o escribe un nombre.';
      errorNode.hidden = false;
    }
    RESULTS_NODE.innerHTML = '';
    return;
  }

  try {
    const response = await fetch(`${searchUrl}?${params.toString()}`, {
      headers: { Accept: 'application/json' },
    });
    if (!response.ok) throw new Error('failed');
    const recipes = await response.json();
    if (!Array.isArray(recipes) || !recipes.length) {
      RESULTS_NODE.innerHTML = '<p class="hero__text">No se encontraron recetas con esos filtros.</p>';
      return;
    }
    RESULTS_NODE.innerHTML = recipes.map(renderRecipeCard).join('');
    bindCardEvents();
  } catch (_) {
    if (errorNode) {
      errorNode.textContent = 'No se pudo buscar en este momento.';
      errorNode.hidden = false;
    }
  }
});

document.getElementById('recipe-search-form')?.addEventListener('submit', async (event) => {
  event.preventDefault();
  const errorNode = document.getElementById('recipe-error');
  if (errorNode) errorNode.hidden = true;
  if (!RESULTS_NODE || !RESULTS_TITLE) return;

  RESULTS_NODE.innerHTML = '<p class="hero__text">Buscando...</p>';
  RESULTS_TITLE.textContent = 'Resultados por ingredientes';

  const form = event.target;
  const input = document.getElementById('ingredients');
  const ingredients = (input?.value || '').split(',').map((value) => value.trim()).filter(Boolean);

  if (!ingredients.length) {
    if (errorNode) {
      errorNode.textContent = 'Introduce al menos un ingrediente.';
      errorNode.hidden = false;
    }
    return;
  }

  try {
    const response = await fetch(form.dataset.searchUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': form.dataset.csrfToken,
      },
      body: JSON.stringify({ ingredients }),
    });
    if (!response.ok) throw new Error('failed');
    const recipes = await response.json();
    if (!Array.isArray(recipes) || !recipes.length) {
      RESULTS_NODE.innerHTML = '<p class="hero__text">No se encontraron recetas.</p>';
      return;
    }
    RESULTS_NODE.innerHTML = recipes.map(renderRecipeCard).join('');
    bindCardEvents();
  } catch (_) {
    if (errorNode) {
      errorNode.textContent = 'No se pudo consultar Spoonacular.';
      errorNode.hidden = false;
    }
  }
});

document.getElementById('nutrients-search-form')?.addEventListener('submit', async (event) => {
  event.preventDefault();
  const errorNode = document.getElementById('nutrients-error');
  if (errorNode) errorNode.hidden = true;
  if (!RESULTS_NODE || !RESULTS_TITLE) return;

  RESULTS_NODE.innerHTML = '<p class="hero__text">Buscando...</p>';
  RESULTS_TITLE.textContent = 'Recetas por objetivos nutricionales';

  const form = event.target;
  const params = new URLSearchParams();
  ['maxCalories', 'minProtein', 'minCarbs', 'maxFat'].forEach((field) => {
    const value = document.getElementById(field)?.value?.trim();
    if (value) params.set(field, value);
  });
  params.set('number', '6');

  try {
    const response = await fetch(`${form.dataset.searchUrl}?${params.toString()}`, {
      headers: { Accept: 'application/json' },
    });
    if (!response.ok) throw new Error('failed');
    const recipes = await response.json();
    if (!Array.isArray(recipes) || !recipes.length) {
      RESULTS_NODE.innerHTML = '<p class="hero__text">No se encontraron recetas con esos parámetros.</p>';
      return;
    }
    RESULTS_NODE.innerHTML = recipes.map(renderRecipeCard).join('');
    bindCardEvents();
  } catch (_) {
    if (errorNode) {
      errorNode.textContent = 'Error al buscar por nutrientes.';
      errorNode.hidden = false;
    }
  }
});

async function loadFavorites() {
  const url = FAV_PANEL?.dataset.favoritesUrl;
  if (!url || !RESULTS_NODE || !RESULTS_TITLE) return;

  try {
    const response = await fetch(url, { headers: { Accept: 'application/json' } });
    const data = await response.json();
    RESULTS_TITLE.textContent = 'Mis recetas favoritas';
    if (!Array.isArray(data) || !data.length) {
      RESULTS_NODE.innerHTML = '<p class="hero__text">No tienes recetas guardadas aún.</p>';
      return;
    }
    RESULTS_NODE.innerHTML = data.map((recipe) => renderRecipeCard({ ...recipe, is_favorite: true })).join('');
    bindCardEvents();
  } catch (_) {
    RESULTS_NODE.innerHTML = '<p class="form-error">No se pudieron cargar los favoritos.</p>';
  }
}

async function toggleFavorite(btn) {
  const recipeId = btn.dataset.recipeId;
  try {
    const response = await fetch(`${INFO_BASE}/${recipeId}/favorite`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken,
        Accept: 'application/json',
      },
      body: JSON.stringify({
        title: btn.dataset.recipeTitle,
        image: btn.dataset.recipeImage,
      }),
    });
    if (!response.ok) throw new Error('failed');
    const data = await response.json();
    const icon = btn.querySelector('i');

    if (data.action === 'added') {
      btn.classList.add('is-fav');
      if (icon) icon.classList.replace('bi-heart', 'bi-heart-fill');
    } else {
      btn.classList.remove('is-fav');
      if (icon) icon.classList.replace('bi-heart-fill', 'bi-heart');
      if (document.querySelector('.recipe-tab.is-active')?.dataset.tab === 'favorites') {
        loadFavorites();
      }
    }
  } catch (_) {
    // noop
  }
}

async function openRecipeModal(recipeId) {
  if (!MODAL_OVERLAY || !MODAL_BODY) return;
  MODAL_OVERLAY.hidden = false;
  MODAL_BODY.innerHTML = '<p class="hero__text">Cargando receta...</p>';

  try {
    const response = await fetch(`${INFO_BASE}/${recipeId}/information`, {
      headers: { Accept: 'application/json' },
    });
    if (!response.ok) throw new Error('failed');
    const recipe = await response.json();

    const diets = (recipe.diets || []).map((diet) => `<span class="exercise-pill recipe-diet-pill">${escHtml(diet)}</span>`).join('');
    const dishTypes = (recipe.dishTypes || []).map((type) => `<span class="exercise-pill">${escHtml(type)}</span>`).join('');
    const ingredients = (recipe.extendedIngredients || []).map((ingredient) =>
      `<li>${escHtml(String(ingredient.amount ?? '') + ' ' + (ingredient.unit ?? '') + ' ' + (ingredient.name ?? ''))}</li>`
    ).join('');

    MODAL_BODY.innerHTML = `
      <div class="recipe-modal__header">
        ${recipe.image ? `<img src="${recipe.image}" alt="${escHtml(recipe.title || '')}" class="recipe-modal__img">` : ''}
        <div class="recipe-modal__header-info">
          <h2 class="recipe-modal__title">${escHtml(recipe.title || 'Receta')}</h2>
          <div class="recipe-modal__meta">
            ${recipe.readyInMinutes != null ? `<span class="exercise-pill"><i class="bi bi-clock"></i> ${recipe.readyInMinutes} min</span>` : ''}
            ${recipe.servings != null ? `<span class="exercise-pill"><i class="bi bi-people"></i> ${recipe.servings} porciones</span>` : ''}
          </div>
          ${diets ? `<div class="recipe-modal__diets">${diets}</div>` : ''}
          ${dishTypes ? `<div class="recipe-modal__diets">${dishTypes}</div>` : ''}
        </div>
      </div>
      ${recipe.nutrition ? `
        <div class="recipe-modal__nutrition">
          <span><i class="bi bi-fire"></i> ${Math.round(recipe.nutrition.calories ?? 0)} kcal</span>
          <span><i class="bi bi-egg"></i> P: ${Math.round(recipe.nutrition.protein ?? 0)}g</span>
          <span><i class="bi bi-tsunami"></i> C: ${Math.round(recipe.nutrition.carbs ?? 0)}g</span>
          <span><i class="bi bi-droplet"></i> G: ${Math.round(recipe.nutrition.fat ?? 0)}g</span>
        </div>` : ''}
      ${recipe.summary ? `
        <div class="recipe-modal__section">
          <p class="exercise-card__label"><i class="bi bi-card-text"></i> Descripción</p>
          <p class="exercise-card__text recipe-modal__summary">${escHtml(recipe.summary)}</p>
        </div>` : ''}
      ${ingredients ? `
        <div class="recipe-modal__section">
          <p class="exercise-card__label"><i class="bi bi-basket"></i> Ingredientes</p>
          <ul class="recipe-modal__ingredients">${ingredients}</ul>
        </div>` : ''}
      ${recipe.instructions ? `
        <div class="recipe-modal__section">
          <p class="exercise-card__label"><i class="bi bi-list-ol"></i> Instrucciones</p>
          <p class="exercise-card__text">${escHtml(recipe.instructions)}</p>
        </div>` : ''}
      <div class="recipe-modal__footer">
        <div id="similar-recipes-${recipe.id}" class="recipe-modal__similar">
          <p class="exercise-card__label"><i class="bi bi-shuffle"></i> Recetas similares</p>
          <p class="hero__text">Cargando similares...</p>
        </div>
      </div>
    `;

    fetchSimilarRecipes(recipe.id);
  } catch (_) {
    MODAL_BODY.innerHTML = '<p class="form-error">No se pudo cargar la receta.</p>';
  }
}

async function fetchSimilarRecipes(recipeId) {
  const container = document.getElementById(`similar-recipes-${recipeId}`);
  if (!container) return;

  try {
    const response = await fetch(`${INFO_BASE}/${recipeId}/similar`, {
      headers: { Accept: 'application/json' },
    });
    if (!response.ok) throw new Error('failed');
    const items = await response.json();
    if (!Array.isArray(items) || !items.length) {
      const helper = container.querySelector('p.hero__text');
      if (helper) helper.textContent = 'No hay recetas similares.';
      return;
    }

    container.innerHTML = `
      <p class="exercise-card__label"><i class="bi bi-shuffle"></i> Recetas similares</p>
      <div class="recipe-similar-list">
        ${items.map((item) => `
          <button type="button" class="recipe-similar-item js-recipe-detail" data-recipe-id="${item.id}">
            <span>${escHtml(item.title || 'Receta')}</span>
            ${item.readyInMinutes ? `<small><i class="bi bi-clock"></i> ${item.readyInMinutes} min</small>` : ''}
          </button>`).join('')}
      </div>
    `;

    container.querySelectorAll('.js-recipe-detail').forEach((btn) => {
      btn.addEventListener('click', () => openRecipeModal(btn.dataset.recipeId));
    });
  } catch (_) {
    // noop
  }
}

MODAL_CLOSE?.addEventListener('click', () => {
  if (MODAL_OVERLAY) MODAL_OVERLAY.hidden = true;
});

MODAL_OVERLAY?.addEventListener('click', (event) => {
  if (event.target === MODAL_OVERLAY) MODAL_OVERLAY.hidden = true;
});

document.addEventListener('keydown', (event) => {
  if (event.key === 'Escape' && MODAL_OVERLAY && !MODAL_OVERLAY.hidden) {
    MODAL_OVERLAY.hidden = true;
  }
});

function renderRecipeCard(recipe) {
  const isFav = recipe.is_favorite ?? false;
  return `
    <article class="exercise-card recipe-card" data-id="${recipe.id}">
      ${recipe.image ? `<img class="recipe-card__img" src="${recipe.image}" alt="${escHtml(recipe.title || '')}">` : ''}
      <h2 class="exercise-card__title">${escHtml(recipe.title || 'Sin título')}</h2>
      <div class="exercise-card__meta">
        ${recipe.readyInMinutes != null ? `<span class="exercise-pill"><i class="bi bi-clock"></i> ${recipe.readyInMinutes} min</span>` : ''}
        ${recipe.calories != null ? `<span class="exercise-pill">${Math.round(recipe.calories)} kcal</span>` : ''}
        ${recipe.protein != null ? `<span class="exercise-pill">P: ${Math.round(recipe.protein)}g</span>` : ''}
        ${recipe.likes != null ? `<span class="exercise-pill"><i class="bi bi-heart"></i> ${recipe.likes}</span>` : ''}
      </div>
      <div class="recipe-card__actions">
        <button type="button" class="mini-btn js-recipe-detail" data-recipe-id="${recipe.id}">
          <i class="bi bi-eye"></i> Ver receta
        </button>
        <button type="button"
                class="mini-btn js-recipe-favorite ${isFav ? 'is-fav' : ''}"
                data-recipe-id="${recipe.id}"
                data-recipe-title="${escHtml(recipe.title || '')}"
                data-recipe-image="${recipe.image || ''}"
                aria-label="${isFav ? 'Quitar favorito' : 'Guardar favorito'}">
          <i class="bi ${isFav ? 'bi-heart-fill' : 'bi-heart'}"></i>
        </button>
      </div>
    </article>
  `;
}

function bindCardEvents() {
  document.querySelectorAll('.js-recipe-detail').forEach((btn) => {
    btn.addEventListener('click', () => openRecipeModal(btn.dataset.recipeId));
  });
  document.querySelectorAll('.js-recipe-favorite').forEach((btn) => {
    btn.addEventListener('click', () => toggleFavorite(btn));
  });
}

function escHtml(str) {
  return String(str ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

function cssEscape(value) {
  if (window.CSS && typeof window.CSS.escape === 'function') {
    return window.CSS.escape(value);
  }
  return String(value).replace(/"/g, '\\"');
}
