const STORAGE_KEY = 'workout_templates';
const ACTIVE_SESSION_KEY = 'workout_session_active';
const ACTIVE_SESSION_LEFT_KEY = 'workout_session_left_tab';
const ACTIVE_SESSION_STARTED_KEY = 'workout_session_started';

const panel = document.getElementById('training-hub-panel');
if (!panel) {
    // noop
} else {
    const sessionUrl = panel.dataset.sessionUrl || '/entrenamiento/sesion';

    const startButton = document.getElementById('hub-start-training');
    const hubResumeBanner = document.getElementById('hub-resume-banner');
    const hubDiscardButton = document.getElementById('hub-discard-session');
    const hubResumeLink = document.getElementById('hub-resume-link');
    const choiceOverlay = document.getElementById('training-hub-choice');
    const cancelButton = document.getElementById('hub-choice-cancel');
    const confirmButton = document.getElementById('hub-choice-confirm');
    const templateWrap = document.getElementById('training-template-select-wrap');
    const templateSelect = document.getElementById('training-template-select');

    const getTemplates = () => {
        try {
            const raw = localStorage.getItem(STORAGE_KEY);
            const parsed = raw ? JSON.parse(raw) : [];
            return Array.isArray(parsed) ? parsed : [];
        } catch (_) {
            return [];
        }
    };

    const hasActiveSession = () => {
        try {
            const started = localStorage.getItem(ACTIVE_SESSION_STARTED_KEY) === '1';
            if (!started) return false;

            const raw = localStorage.getItem(ACTIVE_SESSION_KEY);
            if (!raw) return false;
            const parsed = JSON.parse(raw);
            return Array.isArray(parsed?.exercises) && parsed.exercises.length > 0;
        } catch (_) {
            return false;
        }
    };

    const selectedMode = () => (document.querySelector('input[name="start_mode"]:checked')?.value || 'empty');

    const syncTemplateSelect = () => {
        const templates = getTemplates();
        templateSelect.innerHTML = templates
            .map((template) => `<option value="${template.id}">${template.name}</option>`)
            .join('');

        const useTemplate = selectedMode() === 'template';
        templateWrap.hidden = !useTemplate;

        if (useTemplate && templates.length === 0) {
            alert('No hay plantillas guardadas. Crea una primero.');
            const emptyOption = document.querySelector('input[name="start_mode"][value="empty"]');
            if (emptyOption) {
                emptyOption.checked = true;
            }
            templateWrap.hidden = true;
        }
    };

    document.querySelectorAll('input[name="start_mode"]').forEach((input) => {
        input.addEventListener('change', syncTemplateSelect);
    });

    const checkAndShowBanner = () => {
        if (hasActiveSession()) {
            hubResumeBanner.hidden = false;
            if (hubResumeLink) {
                hubResumeLink.href = `${sessionUrl}?resume=1`;
            }
        }
    };

    checkAndShowBanner();

    hubDiscardButton?.addEventListener('click', () => {
        localStorage.removeItem(ACTIVE_SESSION_KEY);
        localStorage.removeItem(ACTIVE_SESSION_LEFT_KEY);
        localStorage.removeItem(ACTIVE_SESSION_STARTED_KEY);
        hubResumeBanner.hidden = true;
    });

    startButton?.addEventListener('click', () => {
        syncTemplateSelect();
        choiceOverlay.hidden = false;
    });

    cancelButton?.addEventListener('click', () => {
        choiceOverlay.hidden = true;
    });

    confirmButton?.addEventListener('click', () => {
        const mode = selectedMode();
        if (mode === 'template') {
            const templateId = templateSelect.value;
            if (!templateId) {
                alert('Selecciona una plantilla.');
                return;
            }
            window.location.assign(`${sessionUrl}?template_id=${encodeURIComponent(templateId)}`);
            return;
        }

        window.location.assign(`${sessionUrl}?start=empty`);
    });
}
