(() => {
    const note = document.getElementById('password-entry-note');
    const block = event => {
        event.preventDefault();
        if (note) note.textContent = 'Please type your password manually in both fields. Copying, cutting, pasting and drag-and-drop are not allowed.';
    };
    document.querySelectorAll('[data-manual-password]').forEach(input => {
        // Use a marker rather than type=password so Show password keeps the same rules.
        ['copy', 'cut', 'paste', 'drop', 'dragstart', 'dragover'].forEach(type => input.addEventListener(type, block));
        input.addEventListener('beforeinput', event => {
            if (['insertFromPaste', 'insertFromPasteAsQuotation', 'insertFromDrop', 'deleteByCut', 'deleteByDrag'].includes(event.inputType)) block(event);
        });
    });
})();
