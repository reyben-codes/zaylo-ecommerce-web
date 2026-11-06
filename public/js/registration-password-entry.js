(() => {
    const block = event => {
        event.preventDefault();
        const note = document.getElementById('password-entry-note');
        if (note) note.textContent = 'Please type your password manually in both fields. Copying, cutting, pasting and drag-and-drop are not allowed.';
    };
    const init = (scope) => scope.querySelectorAll('[data-manual-password]').forEach(input => {
        if (input.dataset.manualBound) return;
        input.dataset.manualBound = 'true';
        // Use a marker rather than type=password so Show password keeps the same rules.
        ['copy', 'cut', 'paste', 'drop', 'dragstart', 'dragover'].forEach(type => input.addEventListener(type, block));
        input.addEventListener('beforeinput', event => {
            if (['insertFromPaste', 'insertFromPasteAsQuotation', 'insertFromDrop', 'deleteByCut', 'deleteByDrag'].includes(event.inputType)) block(event);
        });
    });

    init(document);
    // Forms the sign-in modal loads later (public/js/auth-modal.js) announce themselves with this event.
    document.addEventListener('zaylo:content', (event) => init(event.target));
})();
