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
