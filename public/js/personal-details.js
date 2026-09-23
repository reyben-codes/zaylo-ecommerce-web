(() => {
    const validName = /^\p{L}[\p{L}\p{M} .'\u2019-]*$/u;
    const validPhone = /^09[0-9]{9}$/;

    function keepCaret(input, cleaned, clean) {
        if (cleaned === input.value) return;

        const caret = input.selectionStart;
        const newCaret = caret === null ? null : clean(input.value.slice(0, caret)).length;
        input.value = cleaned;
        if (newCaret !== null && document.activeElement === input) {
            input.setSelectionRange(newCaret, newCaret);
        }
    }

    function bind(input, clean, error) {
        const validate = () => input.setCustomValidity(error(input.value));
        const update = () => {
            keepCaret(input, clean(input.value), clean);
            validate();
        };

        input.addEventListener('input', (event) => {
            if (!event.isComposing) update();
        });
        input.addEventListener('compositionend', update);
        input.addEventListener('change', update);
        validate();
    }

    document.querySelectorAll('[data-person-name]').forEach((input) => {
        bind(
            input,
            (value) => value.replace(/[^\p{L}\p{M} .'\u2019-]/gu, '').replace(/^[^\p{L}]+/u, ''),
            (value) => validName.test(value) || !value ? '' : 'Use letters, spaces, apostrophes, periods, or hyphens only.',
        );
    });

    document.querySelectorAll('[data-phone-number]').forEach((input) => {
        bind(
            input,
            (value) => value.replace(/[^0-9]/g, '').slice(0, 11),
            (value) => validPhone.test(value) || !value ? '' : 'Enter 11 digits starting with 09.',
        );
    });
})();
