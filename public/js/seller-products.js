(() => {
    // Preview a newly chosen product photo before saving.
    const input = document.querySelector('[data-image-input]');
    const preview = document.querySelector('[data-image-preview]');
    let objectUrl = null;

    input?.addEventListener('change', () => {
        const file = input.files?.[0];
        if (!file || !preview || !file.type.startsWith('image/')) return;
        if (objectUrl) URL.revokeObjectURL(objectUrl);
        objectUrl = URL.createObjectURL(file);
        preview.src = objectUrl;
        preview.classList.remove('is-placeholder');
    });
})();

(() => {
    // Add product: tapping a color chip adds or removes it from the comma-separated Colors field.
    const field = document.querySelector('[data-color-labels]');
    if (!field) return;
    const input = field.querySelector('input[name="colors"]');
    const chips = [...field.querySelectorAll('[data-color-chip]')];
    const names = () => input.value.split(',').map((name) => name.trim()).filter(Boolean);
    const sync = () => {
        const chosen = new Set(names().map((name) => name.toLowerCase()));
        chips.forEach((chip) => chip.setAttribute('aria-pressed', String(chosen.has(chip.dataset.colorChip.toLowerCase()))));
    };

    chips.forEach((chip) => chip.addEventListener('click', () => {
        const color = chip.dataset.colorChip;
        const current = names();
        const kept = current.filter((name) => name.toLowerCase() !== color.toLowerCase());
        input.value = (kept.length === current.length ? [...current, color] : kept).join(', ');
        sync();
    }));
    input.addEventListener('input', sync);
    field.querySelector('[data-color-chips]').hidden = false;
    sync();
})();

(() => {
    // Colors & sizes: add blank size rows, or fill in a category's standard sizes.
    const editor = document.querySelector('[data-size-editor]');
    if (!editor) return;
    // Rows under existing colors post as new[i]; rows under "Add a color" post as added[i].
    const rowName = /^(new|added)\[(\d+)\]/;
    let nextIndex = Math.max(-1, ...[...editor.querySelectorAll('[name^="new["], [name^="added["]')].map((input) => Number(input.name.match(rowName)?.[2] ?? -1))) + 1;

    const addRow = (group, size = '') => {
        const rows = group.querySelectorAll('[data-new-size]');
        const template = rows[rows.length - 1];
        const row = template.cloneNode(true);
        const index = nextIndex++;
        row.querySelectorAll('[name], [id], label[for]').forEach((element) => {
            if (element.name) element.name = element.name.replace(rowName, `$1[${index}]`);
            if (element.id) element.id = element.id.replace(/-\d+$/, `-${index}`);
            if (element.htmlFor) element.htmlFor = element.htmlFor.replace(/-\d+$/, `-${index}`);
        });
        row.querySelector('[name$="[size]"]').value = size;
        row.querySelector('[name$="[stock]"]').value = '0';
        row.querySelector('[name$="[price]"]').value = template.querySelector('[name$="[price]"]').value;
        template.after(row);
        return row;
    };

    editor.querySelectorAll('[data-color-group], [data-new-color]').forEach((group) => {
        group.querySelector('[data-size-actions]').hidden = false;
        group.querySelector('[data-add-size]').addEventListener('click', () => addRow(group).querySelector('[name$="[size]"]').focus());

        group.querySelector('[data-fill-sizes]')?.addEventListener('click', () => {
            const taken = new Set([...group.querySelectorAll('.product-form__variation input[type="text"]')].map((input) => input.value.trim().toLowerCase()).filter(Boolean));
            const blanks = [...group.querySelectorAll('[data-new-size] [name$="[size]"]')].filter((input) => !input.value.trim());
            JSON.parse(group.querySelector('[data-fill-sizes]').dataset.fillSizes)
                .filter((size) => !taken.has(size.toLowerCase()))
                .forEach((size) => {
                    const blank = blanks.shift();
                    if (blank) blank.value = size;
                    else addRow(group, size);
                });
        });
    });
})();

(() => {
    // Colors & sizes: each color is a collapsible card (animated by smooth-disclosure.js).
    const editor = document.querySelector('[data-size-editor]');
    if (!editor) return;
    const groups = [...editor.querySelectorAll('details[data-color-group]')];
    const isOpen = (group) => group.querySelector(':scope > summary').getAttribute('aria-expanded') !== 'false' && group.open;
    const setOpen = (group, open) => {
        if (isOpen(group) !== open) group.querySelector(':scope > summary').click();
    };

    const toggle = editor.querySelector('[data-size-groups-toggle]');
    const refreshToggle = () => {
        if (!toggle) return;
        const allOpen = groups.every(isOpen);
        toggle.textContent = allOpen ? 'Collapse all' : 'Expand all';
        toggle.setAttribute('aria-label', allOpen ? 'Collapse all colors' : 'Expand all colors');
    };
    if (toggle) {
        toggle.hidden = false;
        toggle.addEventListener('click', () => {
            const open = !groups.every(isOpen);
            groups.forEach((group) => setOpen(group, open));
            refreshToggle();
        });
        groups.forEach((group) => group.querySelector(':scope > summary').addEventListener('click', () => setTimeout(refreshToggle)));
        refreshToggle();
    }

    // A field that needs fixing inside a collapsed color opens that color so the browser can point to it.
    editor.addEventListener('invalid', (event) => {
        const group = event.target.closest('details[data-color-group], details[data-new-color]');
        if (group && !isOpen(group)) setOpen(group, true);
    }, true);
})();
