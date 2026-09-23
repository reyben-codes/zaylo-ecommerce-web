(() => {
    const message = 'Use at least 8 characters, one uppercase letter, one lowercase letter, and one symbol.';

    document.querySelectorAll('[data-new-password]').forEach((input) => {
        const validate = () => {
            const value = input.value;
            const valid = [...value].length >= 8
                && /\p{Lu}/u.test(value)
                && /\p{Ll}/u.test(value)
                && /[\p{P}\p{S}]/u.test(value);
            input.setCustomValidity(valid || !value ? '' : message);
        };

        input.addEventListener('input', validate);
        validate();
    });
})();
