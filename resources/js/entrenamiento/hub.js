const STORAGE_KEY = 'workout_templates';

const panel = document.getElementById('training-hub-panel');
if (!panel) {
    // noop
} else {
    const sessionUrl = panel.dataset.sessionUrl || '/entrenamiento/sesion';

    const startButton = document.getElementById('hub-start-training');
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
