const STORAGE_KEY = 'workout_templates';

const panel = document.getElementById('training-template-panel');
if (!panel) {
    // noop
}

const searchUrl = panel?.dataset.exerciseSearchUrl || '';
const form = document.getElementById('template-create-form');
const tabsNode = document.getElementById('tpl-exercise-tabs');
const addExerciseBtn = document.getElementById('tpl-add-exercise-btn');
const listNode = document.getElementById('training-template-list');

const pickerOverlay = document.getElementById('tpl-picker-overlay');
const pickerCloseBtn = document.getElementById('tpl-picker-close');
const pickerSearchInput = document.getElementById('tpl-picker-search');
const pickerResultsNode = document.getElementById('tpl-picker-results');

let selectedExercises = [];
let searchTimer = null;

const readTemplates = () => {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        const parsed = raw ? JSON.parse(raw) : [];
        return Array.isArray(parsed) ? parsed : [];
    } catch (_) {
        return [];
    }
};

const writeTemplates = (templates) => {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(templates));
};

const renderSelectedTabs = () => {
    if (!tabsNode) return;

    tabsNode.innerHTML = selectedExercises.length
        ? selectedExercises
            .map((exercise) => `
                <button type="button" class="ws-tab" data-template-remove="${exercise.key}">
                    ${exercise.name} ×
                </button>
            `)
            .join('')
        : '<span class="ws-helper">No has anadido ejercicios.</span>';
};

const renderTemplates = () => {
    const templates = readTemplates();

    if (!templates.length) {
        listNode.innerHTML = '<p class="ws-helper">Aun no hay plantillas guardadas.</p>';
        return;
    }

    listNode.innerHTML = templates
        .map((template) => `
            <article class="template-item">
                <header>
                    <h4>${template.name}</h4>
                    <button type="button" class="mini-btn mini-btn--danger" data-template-delete="${template.id}">Eliminar</button>
                </header>
                <p>${template.exercises.length} ejercicios</p>
                <ul>
                    ${template.exercises.map((exercise) => `<li>${exercise.name}</li>`).join('')}
                </ul>
            </article>
        `)
        .join('');
};

const openPicker = async () => {
    pickerOverlay.hidden = false;
    pickerSearchInput.value = '';
    pickerSearchInput.focus();
    await fetchExercises('');
};

const closePicker = () => {
    pickerOverlay.hidden = true;
};

const fetchExercises = async (query) => {
    if (!searchUrl) {
        pickerResultsNode.innerHTML = '<p class="ws-helper">No se pudo cargar la lista de ejercicios.</p>';
        return;
    }

    pickerResultsNode.innerHTML = '<p class="ws-helper">Cargando ejercicios...</p>';

    const params = new URLSearchParams();
    params.set('per_page', '20');
    if (query) params.set('q', query);

    try {
        const response = await fetch(`${searchUrl}?${params.toString()}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        const payload = await response.json();
        const items = Array.isArray(payload?.items) ? payload.items : [];

        if (!items.length) {
            pickerResultsNode.innerHTML = '<p class="ws-helper">No se encontraron ejercicios.</p>';
            return;
        }

        pickerResultsNode.innerHTML = items
            .map((item) => {
                const tags = [item.type, item.muscle, item.difficulty].filter(Boolean);
                return `
                    <article class="ws-picker-item">
                        <div>
                            <h5>${item.name || 'Ejercicio'}</h5>
                            <div class="ws-picker-tags">${tags.map((tag) => `<span>${tag}</span>`).join('')}</div>
                        </div>
                        <button type="button" class="ws-btn ws-btn--secondary" data-template-add='${JSON.stringify(item).replace(/'/g, '&apos;')}'>Anadir</button>
                    </article>
                `;
            })
            .join('');
    } catch (_) {
        pickerResultsNode.innerHTML = '<p class="ws-helper">No se pudo cargar la lista de ejercicios.</p>';
    }
};

form?.addEventListener('submit', (event) => {
    event.preventDefault();

    const nameInput = document.getElementById('template-name');
    const name = nameInput.value.trim();

    if (!name || !selectedExercises.length) {
        return;
    }

    const templates = readTemplates();
    templates.unshift({
        id: `tpl-${Date.now()}`,
        name,
        exercises: selectedExercises,
        created_at: new Date().toISOString(),
    });

    writeTemplates(templates);
    form.reset();
    selectedExercises = [];
    renderSelectedTabs();
    renderTemplates();
});

addExerciseBtn?.addEventListener('click', () => {
    openPicker();
});

pickerCloseBtn?.addEventListener('click', closePicker);
pickerOverlay?.addEventListener('click', (event) => {
    if (event.target === pickerOverlay) {
        closePicker();
    }
});

pickerSearchInput?.addEventListener('input', () => {
    window.clearTimeout(searchTimer);
    searchTimer = window.setTimeout(() => {
        fetchExercises(pickerSearchInput.value.trim());
    }, 220);
});

document.addEventListener('click', (event) => {
    const deleteButton = event.target.closest('[data-template-delete]');
    if (deleteButton) {
        const id = deleteButton.getAttribute('data-template-delete');
        const templates = readTemplates().filter((template) => template.id !== id);
        writeTemplates(templates);
        renderTemplates();
        return;
    }

    const removeButton = event.target.closest('[data-template-remove]');
    if (removeButton) {
        const key = removeButton.getAttribute('data-template-remove');
        selectedExercises = selectedExercises.filter((exercise) => exercise.key !== key);
        renderSelectedTabs();
        return;
    }

    const addButton = event.target.closest('[data-template-add]');
    if (addButton) {
        const payload = addButton.getAttribute('data-template-add') || '{}';
        let exercise = null;
        try {
            exercise = JSON.parse(payload);
        } catch (_) {
            exercise = null;
        }

        if (!exercise) return;

        if (!selectedExercises.some((item) => item.key === exercise.key)) {
            selectedExercises.push(exercise);
            renderSelectedTabs();
        }
        closePicker();
    }
});

renderSelectedTabs();
renderTemplates();
