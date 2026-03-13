import { RestTimer } from './rest-timer';

const STORAGE_KEY = 'workout_session_active';
const TEMPLATE_STORAGE_KEY = 'workout_templates';
const ACTIVE_SESSION_LEFT_KEY = 'workout_session_left_tab';

class WorkoutSession {
    constructor(panel) {
        this.panel = panel;
        this.saveSetUrl = panel.dataset.saveSetUrl || '';
        this.finishUrl = panel.dataset.finishUrl || '';
        this.exerciseSearchUrl = panel.dataset.exerciseSearchUrl || '';
        this.exerciseDetailBaseUrl = panel.dataset.exerciseDetailBaseUrl || '';
        this.csrf = panel.dataset.csrf || '';
        this.registeredAt = panel.dataset.registeredAt || '';

        this.previousMap = this.safeParse(panel.dataset.previousMap, {});
        this.emptyCopy = document.getElementById('workout-session-empty-copy');
        this.shell = document.getElementById('ws-shell');

        this.nameInput = document.getElementById('ws-training-name');
        this.typeInput = document.getElementById('ws-training-type');
        this.clockNode = document.getElementById('ws-session-clock');
        this.cancelBtn = document.getElementById('ws-cancel-btn');
        this.finishBtn = document.getElementById('ws-finish-btn');
        this.tabsNode = document.getElementById('ws-tabs');
        this.addExerciseTabBtn = document.getElementById('ws-add-exercise-tab');
        this.helperNode = document.getElementById('ws-helper');

        this.card = document.getElementById('ws-exercise-card');
        this.titleNode = document.getElementById('ws-exercise-title');
        this.exerciseInfoBtn = document.getElementById('ws-exercise-info-btn');
        this.setsBody = document.getElementById('ws-sets-body');
        this.addSetBtn = document.getElementById('ws-add-set-btn');

        this.pickerOverlay = document.getElementById('ws-picker-overlay');
        this.pickerCloseBtn = document.getElementById('ws-picker-close');
        this.pickerSearchInput = document.getElementById('ws-picker-search');
        this.pickerResultsNode = document.getElementById('ws-picker-results');
        this.infoOverlay = document.getElementById('ws-info-overlay');
        this.infoCloseBtn = document.getElementById('ws-info-close');
        this.infoTitleNode = document.getElementById('ws-info-title');
        this.infoInstructionsNode = document.getElementById('ws-info-instructions');
        this.infoSafetyNode = document.getElementById('ws-info-safety');

        this.state = null;
        this.clockTimer = null;
        this.searchTimer = null;
        this.restTimer = new RestTimer(document.getElementById('ws-rest-overlay'));

        this.bindEvents();
        this.handleStartup();
    }

    safeParse(value, fallback) {
        try {
            return JSON.parse(value);
        } catch (_) {
            return fallback;
        }
    }

    bindEvents() {
        document.addEventListener('click', (event) => {
            const addButton = event.target.closest('.js-workout-add');
            if (addButton) {
                const raw = addButton.getAttribute('data-workout-exercise') || '{}';
                const exercise = this.safeParse(raw, null);
                if (exercise) {
                    this.addExercise(exercise);
                }
                return;
            }

            const infoButton = event.target.closest('.js-workout-info');
            if (infoButton) {
                const raw = infoButton.getAttribute('data-workout-info') || '{}';
                const exercise = this.safeParse(raw, null);
                if (exercise) {
                    this.openInfo(exercise);
                }
                return;
            }

            const tab = event.target.closest('.ws-tab[data-key]');
            if (tab) {
                this.setActiveExercise(tab.dataset.key || '');
                return;
            }

            const completeBtn = event.target.closest('.ws-complete-btn[data-index]');
            if (completeBtn) {
                this.toggleSetCompletion(Number(completeBtn.dataset.index));
                return;
            }

            const stepper = event.target.closest('.ws-stepper[data-index][data-field][data-step]');
            if (stepper) {
                this.adjustSetField(
                    Number(stepper.dataset.index),
                    stepper.dataset.field || '',
                    Number(stepper.dataset.step)
                );
                return;
            }

            const pickerAdd = event.target.closest('.ws-picker-add[data-payload]');
            if (pickerAdd) {
                const exercise = this.safeParse(pickerAdd.dataset.payload || '{}', null);
                if (exercise) {
                    this.addExercise(exercise);
                    this.closePicker();
                }
                return;
            }
        });

        this.setsBody?.addEventListener('input', (event) => {
            const input = event.target.closest('.ws-input[data-index][data-field]');
            if (!input) {
                return;
            }

            this.updateSetField(Number(input.dataset.index), input.dataset.field || '', input.value);
        });

        this.nameInput?.addEventListener('input', () => {
            if (!this.state) return;
            this.state.training_name = this.nameInput.value.slice(0, 120);
            this.persistLocal();
        });

        this.typeInput?.addEventListener('change', () => {
            if (!this.state) return;
            this.state.training_type = this.typeInput.value;
            this.persistLocal();
        });

        this.addSetBtn?.addEventListener('click', () => this.addSet());

        this.addExerciseTabBtn?.addEventListener('click', () => this.openPicker());
        this.exerciseInfoBtn?.addEventListener('click', () => {
            const exercise = this.getActiveExercise();
            if (exercise) {
                this.openInfo(exercise);
            }
        });
        this.pickerCloseBtn?.addEventListener('click', () => this.closePicker());
        this.pickerOverlay?.addEventListener('click', (event) => {
            if (event.target === this.pickerOverlay) {
                this.closePicker();
            }
        });
        this.infoCloseBtn?.addEventListener('click', () => this.closeInfo());
        this.infoOverlay?.addEventListener('click', (event) => {
            if (event.target === this.infoOverlay) {
                this.closeInfo();
            }
        });

        this.pickerSearchInput?.addEventListener('input', () => {
            window.clearTimeout(this.searchTimer);
            this.searchTimer = window.setTimeout(() => {
                this.fetchExercises(this.pickerSearchInput.value.trim());
            }, 220);
        });

        this.finishBtn?.addEventListener('click', () => this.finishSession());
        this.cancelBtn?.addEventListener('click', () => this.cancelSession());

        window.addEventListener('beforeunload', () => {
            if (this.state && Array.isArray(this.state.exercises) && this.state.exercises.length > 0) {
                localStorage.setItem(ACTIVE_SESSION_LEFT_KEY, '1');
            }
        });

        document.addEventListener('visibilitychange', () => {
            if (this.state && Array.isArray(this.state.exercises) && this.state.exercises.length > 0) {
                localStorage.setItem(ACTIVE_SESSION_LEFT_KEY, '1');
            }
        });
    }

    handleStartup() {
        const params = new URLSearchParams(window.location.search);

        if (params.get('resume') === '1') {
            const state = this.readLocal();
            if (state && Array.isArray(state.exercises)) {
                this.startWithState(state);
            }
            this.cleanUrl();
            return;
        }

        const templateId = params.get('template_id');

        if (templateId) {
            const template = this.readTemplate(templateId);
            if (template) {
                const state = this.createState();
                state.training_name = template.name || 'Entrenamiento activo';
                state.exercises = (template.exercises || []).map((exercise) => ({
                    ...exercise,
                    previous: '-',
                    sets: [this.defaultSet('-')],
                }));
                state.active_key = state.exercises[0]?.key || null;
                this.startWithState(state);
                this.persistLocal();
                this.cleanUrl();
                return;
            }
        }

        if (params.get('start') === 'empty') {
            this.startWithState(this.createState());
            this.persistLocal();
            this.cleanUrl();
            return;
        }

        const stored = this.readLocal();
        if (stored && Array.isArray(stored.exercises) && stored.exercises.length > 0) {
            this.startWithState(stored);
            window.localStorage.removeItem(ACTIVE_SESSION_LEFT_KEY);
        }
    }

    readLocal() {
        try {
            const raw = window.localStorage.getItem(STORAGE_KEY);
            if (!raw) return null;
            return JSON.parse(raw);
        } catch (_) {
            return null;
        }
    }

    readTemplate(templateId) {
        try {
            const raw = window.localStorage.getItem(TEMPLATE_STORAGE_KEY);
            const templates = raw ? JSON.parse(raw) : [];
            if (!Array.isArray(templates)) {
                return null;
            }

            return templates.find((template) => String(template.id) === String(templateId)) || null;
        } catch (_) {
            return null;
        }
    }

    cleanUrl() {
        const url = new URL(window.location.href);
        url.searchParams.delete('template_id');
        url.searchParams.delete('start');
        url.searchParams.delete('resume');
        window.history.replaceState({}, '', url.toString());
    }

    persistLocal() {
        if (!this.state) return;
        window.localStorage.setItem(STORAGE_KEY, JSON.stringify(this.state));
    }

    createState() {
        return {
            id: `ws-${Date.now()}`,
            training_name: 'Entrenamiento activo',
            training_type: 'fuerza',
            started_at: new Date().toISOString(),
            active_key: null,
            exercises: [],
        };
    }

    startWithState(state) {
        this.state = state;
        if (!this.state.active_key && this.state.exercises[0]) {
            this.state.active_key = this.state.exercises[0].key;
        }

        this.shell.hidden = false;
        this.emptyCopy.hidden = true;

        this.nameInput.value = this.state.training_name || 'Entrenamiento activo';
        this.typeInput.value = this.state.training_type || 'fuerza';

        this.startSessionClock();
        this.render();
    }

    startSessionClock() {
        if (this.clockTimer !== null) {
            window.clearInterval(this.clockTimer);
        }

        const updateClock = () => {
            if (!this.state) return;
            const started = new Date(this.state.started_at).getTime();
            const elapsed = Math.max(0, Date.now() - started);
            const totalSeconds = Math.floor(elapsed / 1000);
            const minutes = Math.floor(totalSeconds / 60);
            const seconds = totalSeconds % 60;
            const hours = Math.floor(minutes / 60);
            const mm = String(minutes % 60).padStart(2, '0');
            const ss = String(seconds).padStart(2, '0');
            this.clockNode.textContent = hours > 0 ? `${String(hours).padStart(2, '0')}:${mm}:${ss}` : `${mm}:${ss}`;
        };

        updateClock();
        this.clockTimer = window.setInterval(updateClock, 1000);
    }

    addExercise(exercise) {
        if (!this.state) {
            this.startWithState(this.createState());
        }

        const existing = this.state.exercises.find((item) => item.key === exercise.key);
        if (!existing) {
            const previousKey = String(exercise.name || '').trim().toLowerCase();
            this.state.exercises.push({
                ...exercise,
                previous: this.previousMap[previousKey] || '-',
                sets: [this.defaultSet(this.previousMap[previousKey] || '-')],
            });
        }

        this.setActiveExercise(exercise.key);
        this.persistLocal();
    }

    defaultSet(previousValue) {
        return {
            kg: 0,
            reps: 12,
            completed: false,
            previous: previousValue || '-',
        };
    }

    getActiveExercise() {
        if (!this.state || !this.state.active_key) return null;
        return this.state.exercises.find((item) => item.key === this.state.active_key) || null;
    }

    setActiveExercise(key) {
        if (!this.state) return;
        this.state.active_key = key;
        this.persistLocal();
        this.render();
    }

    addSet() {
        const exercise = this.getActiveExercise();
        if (!exercise) return;

        const seed = exercise.sets[exercise.sets.length - 1] || this.defaultSet(exercise.previous);
        exercise.sets.push({
            ...seed,
            completed: false,
        });
        this.persistLocal();
        this.render();
    }

    updateSetField(index, field, value) {
        const exercise = this.getActiveExercise();
        if (!exercise || !exercise.sets[index] || exercise.sets[index].completed) return;

        if (field === 'kg') {
            exercise.sets[index].kg = Math.max(0, Number(value || 0));
        }

        if (field === 'reps') {
            exercise.sets[index].reps = Math.max(1, Number(value || 1));
        }

        this.persistLocal();
    }

    adjustSetField(index, field, step) {
        const exercise = this.getActiveExercise();
        if (!exercise || !exercise.sets[index] || exercise.sets[index].completed) return;

        if (field === 'kg') {
            exercise.sets[index].kg = Math.max(0, Number(exercise.sets[index].kg || 0) + step);
        }

        if (field === 'reps') {
            exercise.sets[index].reps = Math.max(1, Number(exercise.sets[index].reps || 1) + step);
        }

        this.persistLocal();
        this.render();
    }

    async toggleSetCompletion(index) {
        const exercise = this.getActiveExercise();
        if (!exercise || !exercise.sets[index]) return;

        const set = exercise.sets[index];
        if (set.completed) {
            set.completed = false;
            this.persistLocal();
            this.render();
            return;
        }

        set.completed = true;
        this.persistLocal();
        this.render();

        try {
            await fetch(this.saveSetUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.csrf,
                    Accept: 'application/json',
                },
                body: JSON.stringify({
                    training_name: this.state.training_name,
                    training_type: this.state.training_type,
                    started_at: this.state.started_at,
                    exercise: {
                        key: exercise.key,
                        name: exercise.name,
                        type: exercise.type || null,
                        muscle: exercise.muscle || null,
                        difficulty: exercise.difficulty || null,
                        equipment: exercise.equipment || null,
                        instructions: exercise.instructions || null,
                        safety_info: exercise.safety_info || null,
                    },
                    set: {
                        index,
                        kg: Number(set.kg || 0),
                        reps: Number(set.reps || 1),
                        rpe: 7,
                        previous: set.previous || '-',
                    },
                }),
            });
        } catch (_) {
            // No bloqueamos el flujo local si falla la red.
        }

        this.restTimer.open(90);
    }

    async finishSession() {
        if (!this.state) return;

        this.finishBtn.disabled = true;
        try {
            const response = await fetch(this.finishUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.csrf,
                    Accept: 'application/json',
                },
                body: JSON.stringify({
                    training_name: this.state.training_name,
                    training_type: this.state.training_type,
                    notes: '',
                    registered_at: this.registeredAt,
                    session_state: this.state,
                }),
            });

            if (!response.ok) {
                this.finishBtn.disabled = false;
                return;
            }

            window.localStorage.removeItem(STORAGE_KEY);
            window.localStorage.removeItem(ACTIVE_SESSION_LEFT_KEY);
            window.location.reload();
        } catch (_) {
            this.finishBtn.disabled = false;
        }
    }

    cancelSession() {
        const confirmed = window.confirm(
            '¿Cancelar el entrenamiento? Se perderán todos los datos de esta sesión.'
        );
        if (!confirmed) return;

        window.localStorage.removeItem('workout_session_active');
        window.localStorage.removeItem('workout_session_left_tab');

        fetch(this.finishUrl.replace('/finalizar', '/cancelar'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': this.csrf,
                Accept: 'application/json',
            },
            body: JSON.stringify({ cancel: true }),
        }).catch(() => {}).finally(() => {
            this.state = null;
            if (this.clockTimer !== null) {
                window.clearInterval(this.clockTimer);
                this.clockTimer = null;
            }
            this.shell.hidden = true;
            this.emptyCopy.hidden = false;
            window.location.href = '/entrenamiento/registrar';
        });
    }

    async openPicker() {
        if (!this.pickerOverlay) return;
        this.pickerOverlay.hidden = false;
        if (this.pickerSearchInput) {
            this.pickerSearchInput.value = '';
            this.pickerSearchInput.focus();
        }
        await this.fetchExercises('');
    }

    closePicker() {
        if (!this.pickerOverlay) return;
        this.pickerOverlay.hidden = true;
    }

    async openInfo(exercise) {
        if (!this.infoOverlay || !exercise) return;

        let resolvedExercise = exercise;
        const instructions = String(exercise.instructions || '').trim();
        const safetyInfo = String(exercise.safety_info || '').trim();

        if (instructions === '' && safetyInfo === '' && exercise.key && this.exerciseDetailBaseUrl) {
            try {
                const response = await fetch(`${this.exerciseDetailBaseUrl}/${encodeURIComponent(exercise.key)}`, {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (response.ok) {
                    resolvedExercise = await response.json();

                    if (this.state && this.state.active_key === exercise.key) {
                        const activeExercise = this.getActiveExercise();
                        if (activeExercise) {
                            activeExercise.instructions = resolvedExercise.instructions || null;
                            activeExercise.safety_info = resolvedExercise.safety_info || null;
                            this.persistLocal();
                        }
                    }
                }
            } catch (_) {
                // Si falla el detalle, mostramos lo disponible.
            }
        }

        const finalInstructions = String(resolvedExercise.instructions || '').trim();
        const finalSafety = String(resolvedExercise.safety_info || '').trim();

        if (this.infoTitleNode) {
            this.infoTitleNode.textContent = resolvedExercise.name || 'Informacion';
        }

        if (this.infoInstructionsNode) {
            this.infoInstructionsNode.textContent = finalInstructions !== '' ? finalInstructions : 'No hay instrucciones disponibles.';
        }

        if (this.infoSafetyNode) {
            this.infoSafetyNode.textContent = finalSafety !== '' ? finalSafety : 'No hay medidas de seguridad disponibles.';
        }

        this.infoOverlay.hidden = false;
    }

    closeInfo() {
        if (!this.infoOverlay) return;
        this.infoOverlay.hidden = true;
    }

    async fetchExercises(query) {
        if (!this.exerciseSearchUrl || !this.pickerResultsNode) {
            return;
        }

        this.pickerResultsNode.innerHTML = '<p class="ws-helper">Cargando ejercicios...</p>';

        const params = new URLSearchParams();
        params.set('per_page', '20');
        if (query) params.set('q', query);

        try {
            const response = await fetch(`${this.exerciseSearchUrl}?${params.toString()}`, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            const payload = await response.json();
            const items = Array.isArray(payload?.items) ? payload.items : [];
            if (!items.length) {
                this.pickerResultsNode.innerHTML = '<p class="ws-helper">No se encontraron ejercicios.</p>';
                return;
            }

            this.pickerResultsNode.innerHTML = items
                .map((item) => {
                    const tags = [item.type, item.muscle, item.difficulty].filter(Boolean);
                    return `
                        <article class="ws-picker-item">
                            <div>
                                <h5>${item.name || 'Ejercicio'}</h5>
                                <div class="ws-picker-tags">${tags.map((tag) => `<span>${tag}</span>`).join('')}</div>
                            </div>
                            <button type="button" class="ws-btn ws-btn--secondary ws-picker-add" data-payload='${JSON.stringify(item).replace(/'/g, '&apos;')}'>Anadir</button>
                        </article>
                    `;
                })
                .join('');
        } catch (_) {
            this.pickerResultsNode.innerHTML = '<p class="ws-helper">No se pudo cargar la lista de ejercicios.</p>';
        }
    }

    renderTabs() {
        const activeKey = this.state?.active_key;
        this.tabsNode.innerHTML = (this.state?.exercises || [])
            .map((exercise) => {
                const isActive = exercise.key === activeKey;
                return `
                    <button
                        type="button"
                        role="tab"
                        class="ws-tab ${isActive ? 'is-active' : ''}"
                        data-key="${exercise.key}"
                        aria-selected="${isActive ? 'true' : 'false'}"
                    >
                        ${exercise.name}
                    </button>
                `;
            })
            .join('');
    }

    renderRows(exercise) {
        this.setsBody.innerHTML = exercise.sets
            .map((set, index) => {
                const completed = set.completed ? 'is-completed' : '';

                return `
                    <tr class="${completed}">
                        <td class="ws-col-set">S${index + 1}</td>
                        <td class="ws-col-reps">
                            <div class="ws-control">
                                <button type="button" class="ws-stepper" data-index="${index}" data-field="reps" data-step="-1" ${set.completed ? 'disabled' : ''}>-</button>
                                <input type="number" min="1" step="1" class="ws-input" data-index="${index}" data-field="reps" value="${Number(set.reps || 1)}" ${set.completed ? 'disabled' : ''}>
                                <button type="button" class="ws-stepper" data-index="${index}" data-field="reps" data-step="1" ${set.completed ? 'disabled' : ''}>+</button>
                            </div>
                        </td>
                        <td class="ws-col-kg">
                            <div class="ws-control">
                                <button type="button" class="ws-stepper" data-index="${index}" data-field="kg" data-step="-1" ${set.completed ? 'disabled' : ''}>-</button>
                                <input type="number" min="0" step="1" class="ws-input" data-index="${index}" data-field="kg" value="${Number(set.kg || 0)}" ${set.completed ? 'disabled' : ''}>
                                <button type="button" class="ws-stepper" data-index="${index}" data-field="kg" data-step="1" ${set.completed ? 'disabled' : ''}>+</button>
                            </div>
                        </td>
                        <td class="ws-col-check">
                            <button type="button" class="ws-complete-btn" data-index="${index}">
                                <span class="ws-check-icon">✓</span>
                            </button>
                        </td>
                    </tr>
                `;
            })
            .join('');
    }

    render() {
        if (!this.state) {
            return;
        }

        this.renderTabs();
        const exercise = this.getActiveExercise();

        if (!exercise) {
            this.card.hidden = true;
            this.helperNode.hidden = false;
            return;
        }

        this.helperNode.hidden = true;
        this.card.hidden = false;
        this.titleNode.textContent = exercise.name || 'Ejercicio';
        this.renderRows(exercise);
    }
}

const panel = document.getElementById('workout-session-panel');
if (panel) {
    new WorkoutSession(panel);
}

export { WorkoutSession };
