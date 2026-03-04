document.querySelectorAll('.muscle-card input[type="radio"][name="muscle"]').forEach((input) => {
    input.addEventListener('mousedown', () => {
        input.dataset.wasChecked = input.checked ? '1' : '0';
    });

    input.addEventListener('click', () => {
        if (input.dataset.wasChecked === '1') {
            input.checked = false;
            const difficulty = document.getElementById('difficulty');
            if (difficulty) {
                difficulty.value = '';
            }
        }
    });
});

const TRAINING_SCROLL_KEY = 'bb:training-scroll-to-results';

document
    .querySelectorAll('form.search-form[action$="/entrenamiento/registrar"]')
    .forEach((form) => {
        form.addEventListener('submit', () => {
            const hasMuscleField = form.querySelector('[name="muscle"], [name="muscle_filter"]');
            if (hasMuscleField) {
                sessionStorage.setItem(TRAINING_SCROLL_KEY, '1');
            }
        });
    });

if (sessionStorage.getItem(TRAINING_SCROLL_KEY) === '1') {
    sessionStorage.removeItem(TRAINING_SCROLL_KEY);
    const resultsSection = document.getElementById('exercise-results');
    if (resultsSection) {
        window.setTimeout(() => {
            resultsSection.scrollIntoView({
                behavior: 'smooth',
                block: 'start',
            });
        }, 120);
    }
}
