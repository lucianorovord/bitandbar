(function () {
    const STORAGE_KEY = 'workout_session_active';
    const SESSION_URL = '/entrenamiento/sesion';
    const IS_MOBILE_BREAKPOINT = 768;

    const card = document.getElementById('ws-global-card');
    const clockEl = document.getElementById('ws-global-clock');
    const clockPanelEl = document.getElementById('ws-global-clock-panel');
    const nameEl = document.getElementById('ws-global-name');
    const setsEl = document.getElementById('ws-global-sets');
    const ctaBtn = document.getElementById('ws-global-cta');
    const cardInner = document.getElementById('ws-global-card-inner');
    const panel = document.getElementById('ws-global-panel');
    const panelClose = document.getElementById('ws-global-panel-close');
    const exercisesNode = document.getElementById('ws-global-exercises');

    const isSessionPage = window.location.pathname.includes('/entrenamiento/sesion');
    if (isSessionPage || !card) return;

    let clockTimer = null;
    let state = null;

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function readState() {
        try {
            const raw = window.localStorage.getItem(STORAGE_KEY);
            if (!raw) return null;
            const parsed = JSON.parse(raw);
            if (!parsed || !Array.isArray(parsed.exercises) || parsed.exercises.length === 0) {
                return null;
            }
            return parsed;
        } catch (_) {
            return null;
        }
    }

    function isMobile() {
        return window.innerWidth <= IS_MOBILE_BREAKPOINT;
    }

    function formatClock(startedAt) {
        const elapsed = Math.max(0, Date.now() - new Date(startedAt).getTime());
        const totalSecs = Math.floor(elapsed / 1000);
        const hours = Math.floor(totalSecs / 3600);
        const minutes = Math.floor((totalSecs % 3600) / 60);
        const seconds = totalSecs % 60;
        const mm = String(minutes).padStart(2, '0');
        const ss = String(seconds).padStart(2, '0');

        return hours > 0 ? `${String(hours).padStart(2, '0')}:${mm}:${ss}` : `${mm}:${ss}`;
    }

    function countCompletedSets(exercises) {
        let total = 0;
        exercises.forEach((exercise) => {
            (exercise.sets || []).forEach((set) => {
                if (set.completed) total += 1;
            });
        });
        return total;
    }

    function renderExercises(exercises) {
        if (!exercisesNode) return;

        exercisesNode.innerHTML = exercises.map((exercise) => {
            const completedSets = (exercise.sets || []).filter((set) => set.completed);
            const totalSets = (exercise.sets || []).length;
            const lastSet = completedSets[completedSets.length - 1];
            const lastInfo = lastSet
                ? `${Number(lastSet.kg || 0)}kg × ${Number(lastSet.reps || 0)} reps`
                : 'Sin series completadas';

            return `
                <div class="ws-global-exercise-item">
                    <div class="ws-global-exercise-item__info">
                        <span class="ws-global-exercise-item__name">${escapeHtml(exercise.name || 'Ejercicio')}</span>
                        <span class="ws-global-exercise-item__meta">
                            ${completedSets.length}/${totalSets} series · ${escapeHtml(lastInfo)}
                        </span>
                    </div>
                    <div class="ws-global-exercise-item__sets">
                        ${(exercise.sets || []).map((set) => `
                            <span class="ws-global-set-dot ${set.completed ? 'done' : ''}"></span>
                        `).join('')}
                    </div>
                </div>
            `;
        }).join('');
    }

    function startClock() {
        if (clockTimer) {
            window.clearInterval(clockTimer);
        }

        const update = function () {
            if (!state || !state.started_at) return;
            const formatted = formatClock(state.started_at);
            if (clockEl) clockEl.textContent = formatted;
            if (clockPanelEl) clockPanelEl.textContent = formatted;
        };

        update();
        clockTimer = window.setInterval(update, 1000);
    }

    function closePanel() {
        if (!panel) return;
        panel.hidden = true;
        card.classList.remove('is-expanded');
    }

    function openPanel() {
        if (!panel || !state) return;
        renderExercises(state.exercises || []);
        panel.hidden = false;
        card.classList.add('is-expanded');
    }

    function syncCard() {
        state = readState();

        if (!state) {
            card.hidden = true;
            closePanel();
            if (clockTimer) {
                window.clearInterval(clockTimer);
                clockTimer = null;
            }
            return;
        }

        card.hidden = false;
        if (nameEl) {
            nameEl.textContent = state.training_name || 'Entrenamiento activo';
        }

        const completedSets = countCompletedSets(state.exercises || []);
        if (setsEl) {
            setsEl.textContent = `${completedSets} series completadas`;
        }

        startClock();

        if (panel && !panel.hidden) {
            renderExercises(state.exercises || []);
        }
    }

    function handleCardClick() {
        if (isMobile()) {
            if (panel && panel.hidden) {
                openPanel();
            } else {
                closePanel();
            }
            return;
        }

        window.location.href = `${SESSION_URL}?resume=1`;
    }

    function init() {
        syncCard();
        if (card.hidden) return;

        cardInner?.addEventListener('click', handleCardClick);
        ctaBtn?.addEventListener('click', function (event) {
            event.stopPropagation();
            window.location.href = `${SESSION_URL}?resume=1`;
        });
        panelClose?.addEventListener('click', function (event) {
            event.stopPropagation();
            closePanel();
        });

        window.addEventListener('storage', function (event) {
            if (event.key === STORAGE_KEY || event.key === null) {
                syncCard();
            }
        });

        window.addEventListener('resize', function () {
            if (!isMobile()) {
                closePanel();
            }
        });

        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) {
                syncCard();
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
