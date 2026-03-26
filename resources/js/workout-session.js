import { RestTimer } from './rest-timer';

const STORAGE_KEY = 'workout_session_active';
const TEMPLATE_STORAGE_KEY = 'workout_templates';
const ACTIVE_SESSION_LEFT_KEY = 'workout_session_left_tab';
const ACTIVE_SESSION_STARTED_KEY = 'workout_session_started';

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
        this.tabsRow = this.tabsNode?.closest('.ws-tabs-row') || null;
        this.addExerciseTabBtn = document.getElementById('ws-add-exercise-tab');
        this.helperNode = document.getElementById('ws-helper');
        this.mobileCardsContainer = document.getElementById('ws-mobile-cards-container');

        this.card = document.getElementById('ws-exercise-card');
        this.titleNode = document.getElementById('ws-exercise-title');
        this.exerciseMuscleNode = document.getElementById('ws-exercise-muscle');
        this.exerciseMenuBtn = document.getElementById('ws-exercise-menu-btn');
        this.exerciseDropdown = document.getElementById('ws-exercise-dropdown');
        this.exerciseInfoBtn = document.getElementById('ws-exercise-info-btn');
        this.exerciseDeleteBtn = document.getElementById('ws-exercise-delete-btn');
        this.setsBody = document.getElementById('ws-sets-body');
        this.addSetBtn = document.getElementById('ws-add-set-btn');

        this.pickerOverlay = document.getElementById('ws-picker-overlay');
        this.pickerCloseBtn = document.getElementById('ws-picker-close');
        this.pickerSearchInput = document.getElementById('ws-picker-search');
        this.pickerResultsNode = document.getElementById('ws-picker-results');
        this.pickerMuscleTrack = document.getElementById('ws-picker-muscle-track');
        this.pickerArrLeft = document.getElementById('ws-picker-arr-left');
        this.pickerArrRight = document.getElementById('ws-picker-arr-right');
        this.pickerActiveFilter = document.getElementById('ws-picker-active-filter');
        this.pickerFilterText = document.getElementById('ws-picker-filter-text');
        this.pickerFilterClear = document.getElementById('ws-picker-filter-clear');

        this.statsOverlay = document.getElementById('ws-stats-overlay');
        this.statsClose = document.getElementById('ws-stats-close');
        this.statsMuscles = document.getElementById('ws-stats-muscles');
        this.statsSets = document.getElementById('ws-stats-sets');
        this.statsVolume = document.getElementById('ws-stats-volume');
        this.statsExercises = document.getElementById('ws-stats-exercises');
        this.statsReps = document.getElementById('ws-stats-reps');
        this.statsRecos = document.getElementById('ws-stats-recos');
        this.navStatsBtn = document.getElementById('ws-nav-stats-btn');

        this.state = null;
        this.clockTimer = null;
        this.searchTimer = null;
        this.activeMuscleFilter = null;
        this.lastRenderedExerciseCount = 0;
        this.lastViewportIsMobile = this.isMobile();
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

    escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    isMobile() {
        return window.innerWidth <= 768;
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
                    this.openInfoModal(exercise);
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

        this.exerciseMenuBtn?.addEventListener('click', (event) => {
            event.stopPropagation();
            if (!this.exerciseDropdown) return;
            const hidden = this.exerciseDropdown.hidden;
            this.exerciseDropdown.hidden = !hidden;
        });

        document.addEventListener('click', () => {
            document.querySelectorAll('.ws-exercise-dropdown').forEach((dropdown) => {
                dropdown.hidden = true;
            });
        });

        this.exerciseDeleteBtn?.addEventListener('click', () => {
            if (!this.state) return;
            const confirmed = window.confirm('¿Eliminar este ejercicio de la sesión?');
            if (!confirmed) return;

            this.state.exercises = this.state.exercises.filter(
                (exercise) => exercise.key !== this.state.active_key
            );
            this.state.active_key = this.state.exercises[0]?.key || null;
            this.persistLocal();
            this.render();

            if (this.exerciseDropdown) {
                this.exerciseDropdown.hidden = true;
            }
        });

        this.exerciseInfoBtn?.addEventListener('click', async () => {
            const exercise = this.getActiveExercise();
            if (!exercise) return;
            if (this.exerciseDropdown) {
                this.exerciseDropdown.hidden = true;
            }
            await this.openInfoModal(exercise);
        });

        this.pickerCloseBtn?.addEventListener('click', () => this.closePicker());
        this.pickerOverlay?.addEventListener('click', (event) => {
            if (event.target === this.pickerOverlay) {
                this.closePicker();
            }
        });

        this.pickerSearchInput?.addEventListener('input', () => {
            window.clearTimeout(this.searchTimer);
            this.searchTimer = window.setTimeout(() => {
                this.fetchExercises(this.pickerSearchInput.value.trim());
            }, 220);
        });

        this.pickerMuscleTrack?.addEventListener('click', (event) => {
            const chip = event.target.closest('.ws-picker-mchip');
            if (!chip) return;

            const muscle = chip.dataset.muscle;
            const label = chip.dataset.label;

            if (!muscle || !label) {
                return;
            }

            if (this.activeMuscleFilter === muscle) {
                this.clearMuscleFilter();
            } else {
                this.setMuscleFilter(muscle, label);
            }
        });

        this.pickerArrLeft?.addEventListener('click', () => {
            this.pickerMuscleTrack.scrollLeft -= 180;
        });

        this.pickerArrRight?.addEventListener('click', () => {
            this.pickerMuscleTrack.scrollLeft += 180;
        });

        this.pickerFilterClear?.addEventListener('click', () => {
            this.clearMuscleFilter();
        });

        this.statsClose?.addEventListener('click', () => {
            if (this.statsOverlay) {
                this.statsOverlay.hidden = true;
            }
        });

        this.statsOverlay?.addEventListener('click', (event) => {
            if (event.target === this.statsOverlay) {
                this.statsOverlay.hidden = true;
            }
        });

        this.navStatsBtn?.addEventListener('click', () => this.openStats());

        this.finishBtn?.addEventListener('click', () => this.finishSession());
        this.cancelBtn?.addEventListener('click', () => this.cancelSession());

        window.addEventListener('beforeunload', () => {
            if (this.state && Array.isArray(this.state.exercises) && this.state.exercises.length > 0) {
                window.localStorage.setItem(ACTIVE_SESSION_LEFT_KEY, '1');
            }
        });

        document.addEventListener('visibilitychange', () => {
            if (this.state && Array.isArray(this.state.exercises) && this.state.exercises.length > 0) {
                window.localStorage.setItem(ACTIVE_SESSION_LEFT_KEY, '1');
            }
        });

        window.addEventListener('resize', () => {
            const isMobile = this.isMobile();
            if (isMobile === this.lastViewportIsMobile) {
                return;
            }

            this.lastViewportIsMobile = isMobile;
            if (this.state) {
                this.render();
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

        try {
            window.dispatchEvent(new StorageEvent('storage', {
                key: STORAGE_KEY,
                newValue: JSON.stringify(this.state),
            }));
        } catch (_) {
            window.dispatchEvent(new Event('storage'));
        }
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
        window.localStorage.setItem(ACTIVE_SESSION_STARTED_KEY, '1');
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
            const previousValue = this.previousMap[previousKey] || '-';
            this.state.exercises.push({
                ...exercise,
                previous: previousValue,
                sets: [this.defaultSet(previousValue)],
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
        if (this.statsOverlay && !this.statsOverlay.hidden) {
            this.renderStats();
        }
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
            window.localStorage.removeItem(ACTIVE_SESSION_STARTED_KEY);
            const globalCard = document.getElementById('ws-global-card');
            if (globalCard) globalCard.hidden = true;
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

        window.localStorage.removeItem(STORAGE_KEY);
        window.localStorage.removeItem(ACTIVE_SESSION_LEFT_KEY);
        window.localStorage.removeItem(ACTIVE_SESSION_STARTED_KEY);
        const globalCard = document.getElementById('ws-global-card');
        if (globalCard) globalCard.hidden = true;

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

    setMuscleFilter(muscle, label) {
        this.activeMuscleFilter = muscle;

        if (this.pickerFilterText) {
            this.pickerFilterText.textContent = label;
        }

        if (this.pickerActiveFilter) {
            this.pickerActiveFilter.hidden = false;
        }

        this.pickerMuscleTrack?.querySelectorAll('.ws-picker-mchip').forEach((chip) => {
            chip.classList.toggle('is-active', chip.dataset.muscle === muscle);
        });

        this.fetchExercises(this.pickerSearchInput?.value?.trim() || '');
    }

    clearMuscleFilter() {
        this.activeMuscleFilter = null;

        if (this.pickerActiveFilter) {
            this.pickerActiveFilter.hidden = true;
        }

        this.pickerMuscleTrack?.querySelectorAll('.ws-picker-mchip').forEach((chip) => {
            chip.classList.remove('is-active');
        });

        this.fetchExercises(this.pickerSearchInput?.value?.trim() || '');
    }

    async fetchExerciseDetail(exercise) {
        if (!exercise) {
            return null;
        }

        const instructions = String(exercise.instructions || '').trim();
        const safetyInfo = String(exercise.safety_info || '').trim();
        if ((instructions !== '' || safetyInfo !== '') || !exercise.key || !this.exerciseDetailBaseUrl) {
            return exercise;
        }

        try {
            const response = await fetch(`${this.exerciseDetailBaseUrl}/${encodeURIComponent(exercise.key)}`, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                return exercise;
            }

            const resolved = await response.json();
            if (this.state) {
                const target = this.state.exercises.find((item) => item.key === exercise.key);
                if (target) {
                    target.instructions = resolved.instructions || null;
                    target.safety_info = resolved.safety_info || null;
                    target.muscle = resolved.muscle || target.muscle || null;
                    this.persistLocal();
                }
            }

            return { ...exercise, ...resolved };
        } catch (_) {
            return exercise;
        }
    }

    async openInfoModal(exercise) {
        const resolvedExercise = await this.fetchExerciseDetail(exercise);
        if (!resolvedExercise) return;

        const existing = document.getElementById('ws-info-modal');
        if (existing) {
            existing.remove();
        }

        const name = this.escapeHtml(resolvedExercise.name || 'Ejercicio');
        const muscle = this.escapeHtml(resolvedExercise.muscle || '');
        const instructions = this.escapeHtml(resolvedExercise.instructions || '');
        const safetyInfo = this.escapeHtml(resolvedExercise.safety_info || '');

        const modal = document.createElement('div');
        modal.id = 'ws-info-modal';
        modal.className = 'ws-info-modal';
        modal.innerHTML = `
            <div class="ws-info-modal__card">
                <div class="ws-info-modal__header">
                    <h4>${name}</h4>
                    <button type="button" class="ws-info-modal__close" id="ws-info-close">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                ${muscle ? `<span class="ws-info-modal__muscle">${muscle}</span>` : ''}
                ${instructions ? `
                    <div class="ws-info-modal__section">
                        <p class="ws-info-modal__label">
                            <i class="bi bi-list-check"></i> Instrucciones
                        </p>
                        <p class="ws-info-modal__text">${instructions}</p>
                    </div>` : ''}
                ${safetyInfo ? `
                    <div class="ws-info-modal__section">
                        <p class="ws-info-modal__label">
                            <i class="bi bi-shield-check"></i> Seguridad
                        </p>
                        <p class="ws-info-modal__text">${safetyInfo}</p>
                    </div>` : ''}
                ${!instructions && !safetyInfo ? `
                    <div class="ws-info-modal__section">
                        <p class="ws-info-modal__text">No hay informacion disponible para este ejercicio.</p>
                    </div>` : ''}
            </div>
        `;

        this.panel.appendChild(modal);
        modal.querySelector('#ws-info-close')?.addEventListener('click', () => modal.remove());
        modal.addEventListener('click', (event) => {
            if (event.target === modal) {
                modal.remove();
            }
        });
    }

    openStats() {
        if (!this.state || !this.statsOverlay) return;
        this.statsOverlay.hidden = false;
        this.renderStats();
    }

    renderStats() {
        if (!this.state) return;

        const exercises = this.state.exercises || [];
        const completedExercises = exercises.filter((exercise) =>
            (exercise.sets || []).some((set) => set.completed)
        );

        const muscles = [...new Set(completedExercises.map((exercise) => exercise.muscle).filter(Boolean))];
        if (this.statsMuscles) {
            this.statsMuscles.innerHTML = muscles.length
                ? muscles.map((muscle) => `
                    <span class="ws-stats-muscle-chip">
                        <i class="bi bi-check-circle-fill"></i> ${this.escapeHtml(muscle)}
                    </span>`).join('')
                : '<p class="ws-helper">Sin musculos registrados aun.</p>';
        }

        let totalSets = 0;
        let totalVolume = 0;
        let totalReps = 0;

        exercises.forEach((exercise) => {
            (exercise.sets || []).forEach((set) => {
                if (set.completed) {
                    totalSets += 1;
                    totalReps += Number(set.reps || 0);
                    totalVolume += Number(set.kg || 0) * Number(set.reps || 0);
                }
            });
        });

        if (this.statsSets) this.statsSets.textContent = String(totalSets);
        if (this.statsVolume) this.statsVolume.textContent = `${totalVolume.toFixed(1)} kg`;
        if (this.statsExercises) this.statsExercises.textContent = String(exercises.length);
        if (this.statsReps) this.statsReps.textContent = String(totalReps);

        if (this.statsRecos) {
            const recos = [];

            if (totalSets >= 9) {
                recos.push({
                    icon: 'bi-moon-stars',
                    text: 'Sesion intensa completada',
                    sub: 'Considera descanso manana',
                });
            }

            if (exercises.length === 1) {
                recos.push({
                    icon: 'bi-plus-circle',
                    text: 'Anade mas ejercicios',
                    sub: 'Lo ideal son 3-5 ejercicios por sesion',
                });
            }

            if (totalVolume > 0) {
                recos.push({
                    icon: 'bi-graph-up-arrow',
                    text: 'Intenta superar este volumen',
                    sub: `Tu marca hoy: ${totalVolume.toFixed(0)} kg`,
                });
            }

            this.statsRecos.innerHTML = recos.length
                ? recos.map((reco) => `
                    <div class="ws-reco-item">
                        <div class="ws-reco-icon">
                            <i class="bi ${reco.icon}"></i>
                        </div>
                        <div>
                            <p class="ws-reco-text">${this.escapeHtml(reco.text)}</p>
                            <small class="ws-reco-sub">${this.escapeHtml(reco.sub)}</small>
                        </div>
                    </div>`).join('')
                : '<p class="ws-helper">Completa series para ver recomendaciones.</p>';
        }
    }

    async fetchExercises(query) {
        if (!this.exerciseSearchUrl || !this.pickerResultsNode) {
            return;
        }

        this.pickerResultsNode.innerHTML = '<p class="ws-helper">Cargando ejercicios...</p>';

        const params = new URLSearchParams();
        params.set('per_page', '20');
        if (this.activeMuscleFilter) {
            params.set('muscle', this.activeMuscleFilter);
        }
        if (query) {
            params.set('q', query);
        }

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

            this.pickerResultsNode.innerHTML = items.map((item) => {
                const tags = [item.type, item.muscle, item.difficulty].filter(Boolean);
                return `
                    <article class="ws-picker-item">
                        <div>
                            <h5>${this.escapeHtml(item.name || 'Ejercicio')}</h5>
                            <div class="ws-picker-tags">${tags.map((tag) => `<span>${this.escapeHtml(tag)}</span>`).join('')}</div>
                        </div>
                        <button
                            type="button"
                            class="ws-btn ws-btn--secondary ws-picker-add"
                            data-payload='${JSON.stringify(item).replace(/'/g, '&apos;')}'
                        >
                            Anadir
                        </button>
                    </article>
                `;
            }).join('');
        } catch (_) {
            this.pickerResultsNode.innerHTML = '<p class="ws-helper">No se pudo cargar la lista de ejercicios.</p>';
        }
    }

    renderTabs() {
        const activeKey = this.state?.active_key;
        this.tabsNode.innerHTML = (this.state?.exercises || []).map((exercise) => {
            const isActive = exercise.key === activeKey;
            return `
                <button
                    type="button"
                    role="tab"
                    class="ws-tab ${isActive ? 'is-active' : ''}"
                    data-key="${exercise.key}"
                    aria-selected="${isActive ? 'true' : 'false'}"
                >
                    ${this.escapeHtml(exercise.name)}
                </button>
            `;
        }).join('');
    }

    renderMobileCards() {
        if (this.tabsRow) {
            this.tabsRow.hidden = true;
        }

        if (this.card) {
            this.card.hidden = true;
        }

        if (!this.mobileCardsContainer) {
            return;
        }

        const exercises = this.state?.exercises || [];

        if (!exercises.length) {
            this.lastRenderedExerciseCount = 0;
            this.mobileCardsContainer.innerHTML = `
                <div class="ws-mobile-empty">
                    <i class="bi bi-plus-circle"></i>
                    <p>No has anadido ejercicios.</p>
                    <p>Pulsa <strong>+</strong> para buscar y anadir.</p>
                    <button type="button" class="ws-btn ws-btn--secondary ws-mobile-add-btn" onclick="window.wsSession.openPicker()">
                        <i class="bi bi-plus-lg"></i> Anadir ejercicio
                    </button>
                </div>
            `;
            return;
        }

        const cards = exercises.map((exercise) => {
            const completedCount = (exercise.sets || []).filter((set) => set.completed).length;
            const isActive = exercise.key === this.state.active_key;

            return `
                <div class="ws-mobile-ex-card ${isActive ? 'is-active' : ''}" data-key="${exercise.key}" id="ws-mobile-card-${exercise.key}">
                    <div class="ws-mobile-ex-card__header" onclick="window.wsSession.toggleMobileCard('${exercise.key}')">
                        <div class="ws-mobile-ex-card__title-wrap">
                            <span class="ws-mobile-ex-card__title">${this.escapeHtml(exercise.name || 'Ejercicio')}</span>
                            ${exercise.muscle ? `<span class="ws-mobile-ex-card__muscle">${this.escapeHtml(exercise.muscle)}</span>` : ''}
                        </div>
                        <div class="ws-mobile-ex-card__header-right">
                            <span class="ws-mobile-ex-card__progress">
                                ${completedCount}/${(exercise.sets || []).length}
                                <i class="bi bi-check-circle-fill" style="color:${completedCount > 0 ? '#ffd100' : '#444'}"></i>
                            </span>
                            <div class="ws-mobile-ex-card__menu-wrap">
                                <button type="button" class="ws-exercise-menu__btn" onclick="event.stopPropagation();window.wsSession.toggleMobileMenu('${exercise.key}')">
                                    <i class="bi bi-three-dots-vertical"></i>
                                </button>
                                <div class="ws-exercise-dropdown" id="ws-mobile-menu-${exercise.key}" hidden>
                                    <button type="button" class="ws-exercise-dropdown__item" onclick="window.wsSession.showExerciseInfo('${exercise.key}')">
                                        <i class="bi bi-info-circle"></i> Info del ejercicio
                                    </button>
                                    <button type="button" class="ws-exercise-dropdown__item ws-exercise-dropdown__item--danger" onclick="window.wsSession.deleteExercise('${exercise.key}')">
                                        <i class="bi bi-trash3"></i> Eliminar ejercicio
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="ws-mobile-ex-card__body ${isActive ? '' : 'is-collapsed'}" id="ws-mobile-body-${exercise.key}">
                        <div class="ws-mobile-table-wrap">
                        <table class="ws-table ws-table--mobile">
                            <thead>
                                <tr>
                                    <th class="ws-col-set">SERIE</th>
                                    <th class="ws-col-prev">ANTERIOR</th>
                                    <th class="ws-col-reps">REPS</th>
                                    <th class="ws-col-kg">KG</th>
                                    <th class="ws-col-check">✓</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${(exercise.sets || []).map((set, setIndex) => `
                                    <tr class="${set.completed ? 'is-completed' : ''}">
                                        <td class="ws-col-set">S${setIndex + 1}</td>
                                        <td class="ws-col-prev">${this.escapeHtml(set.previous || exercise.previous || '-')}</td>
                                        <td class="ws-col-reps">
                                            <div class="ws-control">
                                                <button type="button" class="ws-stepper" onclick="window.wsSession.adjustSetFieldByKey('${exercise.key}', ${setIndex}, 'reps', -1)" ${set.completed ? 'disabled' : ''}>-</button>
                                                <input type="number" class="ws-input" value="${Number(set.reps || 12)}" onchange="window.wsSession.updateSetFieldByKey('${exercise.key}', ${setIndex}, 'reps', this.value)" ${set.completed ? 'disabled' : ''}>
                                                <button type="button" class="ws-stepper" onclick="window.wsSession.adjustSetFieldByKey('${exercise.key}', ${setIndex}, 'reps', 1)" ${set.completed ? 'disabled' : ''}>+</button>
                                            </div>
                                        </td>
                                        <td class="ws-col-kg">
                                            <div class="ws-control">
                                                <button type="button" class="ws-stepper" onclick="window.wsSession.adjustSetFieldByKey('${exercise.key}', ${setIndex}, 'kg', -1)" ${set.completed ? 'disabled' : ''}>-</button>
                                                <input type="number" class="ws-input" value="${Number(set.kg || 0)}" onchange="window.wsSession.updateSetFieldByKey('${exercise.key}', ${setIndex}, 'kg', this.value)" ${set.completed ? 'disabled' : ''}>
                                                <button type="button" class="ws-stepper" onclick="window.wsSession.adjustSetFieldByKey('${exercise.key}', ${setIndex}, 'kg', 1)" ${set.completed ? 'disabled' : ''}>+</button>
                                            </div>
                                        </td>
                                        <td class="ws-col-check">
                                            <button type="button" class="ws-complete-btn ${set.completed ? 'is-done' : ''}" onclick="window.wsSession.toggleSetCompletionByKey('${exercise.key}', ${setIndex})">
                                                <i class="bi bi-check-lg"></i>
                                            </button>
                                        </td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                        </div>
                        <button type="button" class="ws-btn ws-btn--secondary ws-add-set-btn" onclick="window.wsSession.addSetToExercise('${exercise.key}')">
                            + Anadir serie
                        </button>
                    </div>
                </div>
            `;
        }).join('');

        this.mobileCardsContainer.innerHTML = `
            <div class="ws-mobile-actions">
                <button type="button" class="ws-btn ws-btn--secondary ws-mobile-add-btn" onclick="window.wsSession.openPicker()">
                    <i class="bi bi-plus-lg"></i> Anadir ejercicio
                </button>
            </div>
            ${cards}
        `;

        const mobileCards = this.mobileCardsContainer.querySelectorAll('.ws-mobile-ex-card');
        if (exercises.length > this.lastRenderedExerciseCount && mobileCards.length) {
            const lastCard = mobileCards[mobileCards.length - 1];
            lastCard.classList.add('ws-card-enter');
            requestAnimationFrame(() => lastCard.classList.add('ws-card-enter--active'));
        }

        this.lastRenderedExerciseCount = exercises.length;
    }

    toggleMobileCard(key) {
        if (!this.state) return;
        const wasActive = this.state.active_key === key;
        this.state.active_key = wasActive ? null : key;
        this.persistLocal();
        this.render();
    }

    toggleMobileMenu(key) {
        document.querySelectorAll('.ws-exercise-dropdown').forEach((dropdown) => {
            if (dropdown.id !== `ws-mobile-menu-${key}`) {
                dropdown.hidden = true;
            }
        });

        const menu = document.getElementById(`ws-mobile-menu-${key}`);
        if (menu) {
            menu.hidden = !menu.hidden;
        }
    }

    showExerciseInfo(key) {
        const exercise = this.state?.exercises?.find((item) => item.key === key);
        if (!exercise) return;
        document.querySelectorAll('.ws-exercise-dropdown').forEach((dropdown) => {
            dropdown.hidden = true;
        });
        this.openInfoModal(exercise);
    }

    deleteExercise(key) {
        if (!this.state) return;
        const confirmed = window.confirm('¿Eliminar este ejercicio de la sesión?');
        if (!confirmed) return;

        this.state.exercises = this.state.exercises.filter((exercise) => exercise.key !== key);
        this.state.active_key = this.state.exercises[0]?.key || null;
        this.persistLocal();
        this.render();
    }

    adjustSetFieldByKey(exerciseKey, setIndex, field, step) {
        const exercise = this.state?.exercises?.find((item) => item.key === exerciseKey);
        if (!exercise || !exercise.sets[setIndex] || exercise.sets[setIndex].completed) return;

        if (field === 'kg') {
            exercise.sets[setIndex].kg = Math.max(0, Number(exercise.sets[setIndex].kg || 0) + step);
        }

        if (field === 'reps') {
            exercise.sets[setIndex].reps = Math.max(1, Number(exercise.sets[setIndex].reps || 1) + step);
        }

        this.persistLocal();
        this.render();
    }

    updateSetFieldByKey(exerciseKey, setIndex, field, value) {
        const exercise = this.state?.exercises?.find((item) => item.key === exerciseKey);
        if (!exercise || !exercise.sets[setIndex] || exercise.sets[setIndex].completed) return;

        if (field === 'kg') {
            exercise.sets[setIndex].kg = Math.max(0, Number(value || 0));
        }

        if (field === 'reps') {
            exercise.sets[setIndex].reps = Math.max(1, Number(value || 1));
        }

        this.persistLocal();
        if (this.statsOverlay && !this.statsOverlay.hidden) {
            this.renderStats();
        }
    }

    async toggleSetCompletionByKey(exerciseKey, setIndex) {
        if (!this.state) return;
        const previousActiveKey = this.state.active_key;
        this.state.active_key = exerciseKey;
        await this.toggleSetCompletion(setIndex);
        this.state.active_key = this.isMobile() ? exerciseKey : previousActiveKey;
        this.render();
    }

    addSetToExercise(exerciseKey) {
        const exercise = this.state?.exercises?.find((item) => item.key === exerciseKey);
        if (!exercise) return;

        const seed = exercise.sets[exercise.sets.length - 1] || this.defaultSet(exercise.previous);
        exercise.sets.push({
            ...seed,
            completed: false,
        });
        this.persistLocal();
        this.render();
    }

    renderRows(exercise) {
        this.setsBody.innerHTML = exercise.sets.map((set, index) => {
            const completed = set.completed ? 'is-completed' : '';
            const previous = this.escapeHtml(set.previous || exercise.previous || '-');

            return `
                <tr class="${completed}">
                    <td class="ws-col-set">S${index + 1}</td>
                    <td class="ws-col-prev">${previous}</td>
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
        }).join('');
    }

    render() {
        if (!this.state) {
            return;
        }

        if (this.isMobile()) {
            if (this.helperNode) {
                this.helperNode.hidden = true;
            }
            this.renderMobileCards();
            if (this.statsOverlay && !this.statsOverlay.hidden) {
                this.renderStats();
            }
            return;
        }

        if (this.tabsRow) {
            this.tabsRow.hidden = false;
        }

        if (this.mobileCardsContainer) {
            this.mobileCardsContainer.innerHTML = '';
        }

        this.renderTabs();
        const exercise = this.getActiveExercise();

        if (!exercise) {
            if (this.state.exercises?.length) {
                this.state.active_key = this.state.exercises[0].key;
                this.render();
                return;
            }
            this.card.hidden = true;
            this.helperNode.hidden = false;
            if (this.exerciseDropdown) {
                this.exerciseDropdown.hidden = true;
            }
            if (this.statsOverlay && !this.statsOverlay.hidden) {
                this.renderStats();
            }
            return;
        }

        this.helperNode.hidden = true;
        this.card.hidden = false;

        if (this.exerciseDropdown) {
            this.exerciseDropdown.hidden = true;
        }

        this.titleNode.textContent = exercise.name || 'Ejercicio';
        if (this.exerciseMuscleNode) {
            this.exerciseMuscleNode.textContent = exercise.muscle || '';
            this.exerciseMuscleNode.hidden = !exercise.muscle;
        }

        this.renderRows(exercise);

        if (this.statsOverlay && !this.statsOverlay.hidden) {
            this.renderStats();
        }
    }
}

const panel = document.getElementById('workout-session-panel');
if (panel) {
    window.wsSession = new WorkoutSession(panel);
}

export { WorkoutSession };
