(() => {
    const message = 'Use at least 8 characters, one uppercase letter, one lowercase letter, one number, and one symbol.';

    const init = (scope) => scope.querySelectorAll('[data-new-password]').forEach((input) => {
        if (input.dataset.requirementsBound) return;
        input.dataset.requirementsBound = 'true';
        const validate = () => {
            const value = input.value;
            const valid = [...value].length >= 8
                && /\p{Lu}/u.test(value)
                && /\p{Ll}/u.test(value)
                && /\p{Nd}/u.test(value)
                && /[\p{P}\p{S}]/u.test(value);
            input.setCustomValidity(valid || !value ? '' : message);
        };

        input.addEventListener('input', validate);
        validate();
    });

    init(document);
    // Forms the sign-in modal loads later (public/js/auth-modal.js) announce themselves with this event.
    document.addEventListener('zaylo:content', (event) => init(event.target));
})();
