(() => {
    const validName = /^\p{L}[\p{L}\p{M} .'\u2019-]*$/u;
    const validPhone = /^09[0-9]{9}$/;
    const validIntlPhone = /^\+639[0-9]{9}$/;

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

    const fresh = (scope, selector) => [...scope.querySelectorAll(selector)].filter((input) => {
        if (input.dataset.detailsBound) return false;
        input.dataset.detailsBound = 'true';
        return true;
    });

    const init = (scope) => {
    fresh(scope, '[data-person-name]').forEach((input) => {
        bind(
            input,
            (value) => value.replace(/[^\p{L}\p{M} .'\u2019-]/gu, '').replace(/^[^\p{L}]+/u, ''),
            (value) => {
                if (!value) return '';
                if (!validName.test(value)) return 'Use letters, spaces, apostrophes, periods, or hyphens only.';
                const min = Number(input.getAttribute('minlength')) || 0;
                return [...value].length < min ? `Use at least ${min} characters.` : '';
            },
        );
    });

    fresh(scope, '[data-phone-number]').forEach((input) => {
        // Sign-up also accepts +639XXXXXXXXX, which the server saves as 09XXXXXXXXX.
        if (input.dataset.phoneNumber === 'international') {
            bind(
                input,
                (value) => {
                    const plus = value.trimStart().startsWith('+');
                    const digits = value.replace(/[^0-9]/g, '');
                    return plus ? `+${digits.slice(0, 12)}` : digits.slice(0, 11);
                },
                (value) => validPhone.test(value) || validIntlPhone.test(value) || !value ? '' : 'Enter 09XXXXXXXXX or +639XXXXXXXXX.',
            );
            return;
        }
        bind(
            input,
            (value) => value.replace(/[^0-9]/g, '').slice(0, 11),
            (value) => validPhone.test(value) || !value ? '' : 'Enter 11 digits starting with 09.',
        );
    });

    fresh(scope, '[data-adult-birthday]').forEach((input) => {
        const validate = () => input.setCustomValidity(
            input.value && input.max && input.value > input.max ? 'You must be at least 18 years old to register.' : '',
        );
        input.addEventListener('input', validate);
        input.addEventListener('change', validate);
        validate();
    });
    };

    init(document);
    // Forms the sign-in modal loads later (public/js/auth-modal.js) announce themselves with this event.
    document.addEventListener('zaylo:content', (event) => init(event.target));
})();
