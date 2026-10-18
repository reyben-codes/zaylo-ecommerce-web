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

(() => {
    // Categories: at least one box must be ticked; the browser points to the first box until one is.
    const group = document.querySelector('[data-categories]');
    if (!group) return;
    const boxes = [...group.querySelectorAll('input[name="categories[]"]')];
    const error = group.querySelector('[data-categories-error]');
    const message = 'Choose at least one category.';
    const sync = () => {
        const none = !boxes.some((box) => box.checked);
        boxes[0].setCustomValidity(none ? message : '');
        group.toggleAttribute('aria-invalid', none && !error.hidden);
        if (!none) error.hidden = true;
    };
    boxes[0].addEventListener('invalid', () => {
        error.textContent = message;
        error.hidden = false;
        group.setAttribute('aria-invalid', 'true');
    });
    group.addEventListener('change', sync);
    sync();
})();

(() => {
    // More photos: preview chosen files, let the seller drop one before saving, and keep within the photo limits.
    const gallery = document.querySelector('[data-photo-gallery]');
    if (!gallery) return;
    const input = gallery.querySelector('[data-photo-input]');
    const previews = gallery.querySelector('[data-photo-previews]');
    const message = gallery.querySelector('[data-photo-message]');
    const removals = [...gallery.querySelectorAll('[data-photo-remove]')];
    const max = Number(gallery.dataset.max);
    const perUpload = Number(gallery.dataset.perUpload);
    const existing = Number(gallery.dataset.existing);
    // DataTransfer lets the file list be edited; without it the browser's own file picker list is used as is.
    const editable = typeof DataTransfer === 'function';
    let files = [];
    let urls = [];

    const say = (text) => {
        message.textContent = text;
        message.hidden = !text;
    };
    const room = () => Math.min(perUpload, max - (existing - removals.filter((box) => box.checked).length));

    const render = () => {
        urls.forEach((url) => URL.revokeObjectURL(url));
        urls = files.map((file) => URL.createObjectURL(file));
        previews.replaceChildren(...files.map((file, index) => {
            const item = document.createElement('li');
            item.className = 'product-form__photo-item';
            const image = document.createElement('img');
            image.src = urls[index];
            image.alt = `New photo: ${file.name}`;
            item.append(image);
            if (editable) {
                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'product-form__photo-drop';
                remove.setAttribute('aria-label', `Don't upload ${file.name}`);
                remove.innerHTML = '<i class="fas fa-xmark" aria-hidden="true"></i>';
                remove.addEventListener('click', () => {
                    files.splice(index, 1);
                    sync();
                    input.focus();
                });
                item.append(remove);
            }
            return item;
        }));
        previews.hidden = files.length === 0;
    };

    const sync = () => {
        if (editable) {
            const transfer = new DataTransfer();
            files.forEach((file) => transfer.items.add(file));
            input.files = transfer.files;
        }
        render();
    };

    input.addEventListener('change', () => {
        const chosen = [...input.files].filter((file) => file.type.startsWith('image/'));
        const combined = editable ? [...files, ...chosen] : chosen;
        const limit = Math.max(0, room());
        const tooBig = combined.filter((file) => file.size > 5 * 1024 * 1024);
        files = combined.filter((file) => file.size <= 5 * 1024 * 1024).slice(0, limit);
        if (tooBig.length) say(`${tooBig.length === 1 ? 'One photo is' : `${tooBig.length} photos are`} over 5 MB and won't be uploaded.`);
        else if (combined.length > limit) say(limit === 0 ? 'This product already has the most photos it can hold. Remove one to add another.' : `Only ${limit} more ${limit === 1 ? 'photo fits' : 'photos fit'} right now; the rest were left out.`);
        else say('');
        if (!editable && files.length !== combined.length) input.value = '';
        sync();
    });

    removals.forEach((box) => box.addEventListener('change', () => {
        box.closest('.product-form__photo-item').classList.toggle('is-removing', box.checked);
    }));
    removals.forEach((box) => box.closest('.product-form__photo-item').classList.toggle('is-removing', box.checked));
})();

(() => {
    // Add product: colors and sizes are entered here and saved together with the product.
    const panel = document.querySelector('[data-new-options]');
    if (!panel) return;
    const form = panel.closest('form');
    const cards = panel.querySelector('[data-option-cards]');
    const cardTemplate = panel.querySelector('[data-option-card-template]');
    const rowTemplate = panel.querySelector('[data-option-row-template]');
    const chips = [...panel.querySelectorAll('[data-option-chip]')];
    const addColor = panel.querySelector('[data-option-add-color]');
    const sizeList = panel.querySelector('#option-size-presets');
    const presets = JSON.parse(panel.dataset.sizePresets || '{}');
    const categories = form.querySelector('[data-categories]');
    const mainPrice = form.querySelector('[data-main-price]');
    const mainStock = form.querySelector('[data-main-stock]');
    const priceNote = form.querySelector('[data-main-price-note]');
    const stockNote = form.querySelector('[data-main-stock-note]');
    let nextColor = Number(panel.dataset.nextColor) || 0;
    let nextRow = 1000;

    const allCards = () => [...cards.querySelectorAll('[data-option-color]')];
    const colorOf = (card) => card.querySelector('[data-option-color-name]').value.trim();
    const rowsOf = (card) => [...card.querySelectorAll('[data-option-row]')];
    const sizedRows = (card) => rowsOf(card).filter((row) => row.querySelector('[name$="[size]"]').value.trim());
    // The rows that become options: sized rows, or a named color's first row when it has no sizes.
    const countedRows = (card) => (sizedRows(card).length ? sizedRows(card) : (colorOf(card) ? rowsOf(card).slice(0, 1) : []));
    const isBlank = (card) => !colorOf(card) && sizedRows(card).length === 0;
    // Standard sizes come from the first ticked category that has them (e.g. Shoes or Clothing).
    const sizesFor = () => [...(categories?.querySelectorAll('input:checked') || [])].map((box) => presets[box.value]).find(Boolean) || [];
    const clearRow = (row) => row.querySelectorAll('input').forEach((input) => { input.value = input.matches('[data-option-stock]') ? '0' : ''; });
    const fromTemplate = (template, replacements) => {
        let html = template.innerHTML;
        Object.entries(replacements).forEach(([token, value]) => { html = html.split(token).join(String(value)); });
        const holder = document.createElement('div');
        holder.innerHTML = html.trim();
        return holder.firstElementChild;
    };

    const refresh = () => {
        const list = allCards();
        let inUse = false;
        let missingPrice = false;
        let total = 0;
        list.forEach((card) => {
            const color = colorOf(card);
            const sized = sizedRows(card);
            const counted = countedRows(card);
            const stock = counted.reduce((sum, row) => sum + (Number(row.querySelector('[data-option-stock]').value) || 0), 0);
            if (counted.length) inUse = true;
            if (counted.some((row) => !row.querySelector('[data-option-price]').value)) missingPrice = true;
            total += stock;
            card.querySelector('[data-option-title]').textContent = color || (list.length > 1 ? 'Unnamed color' : 'Sizes');
            card.querySelector('[data-option-summary]').textContent = sized.length
                ? `${sized.length} ${sized.length === 1 ? 'size' : 'sizes'} · ${stock} in stock`
                : (color ? `One option · ${stock} in stock` : 'Size, price and stock');
            card.querySelector('[data-option-fill-sizes]').hidden = sizesFor().length === 0;
            // A card's only row is cleared rather than removed, so there is always a place to type.
            const rows = rowsOf(card);
            rows.forEach((row) => { row.querySelector('[data-option-remove-row]').hidden = rows.length < 2; });
        });
        const taken = new Set(list.map((card) => colorOf(card).toLowerCase()).filter(Boolean));
        chips.forEach((chip) => { chip.disabled = taken.has(chip.dataset.optionChip.toLowerCase()); });
        panel.querySelectorAll('[data-option-price]').forEach((input) => { input.placeholder = mainPrice?.value || 'Main'; });
        // With options, stock is their total and the main price only fills in prices left empty.
        if (mainStock && stockNote) {
            mainStock.readOnly = inUse;
            if (inUse) mainStock.value = total;
            stockNote.hidden = !inUse;
        }
        if (mainPrice && priceNote) {
            mainPrice.required = !inUse || missingPrice;
            priceNote.hidden = !inUse;
        }
    };

    const setupCard = (card) => {
        card.querySelector('[data-size-actions]').hidden = false;
        window.SmoothDisclosure?.init(card.parentElement);
    };

    const addRow = (card, size = '') => {
        const index = card.querySelector('[data-option-color-name]').name.match(/options\[([^\]]+)\]/)[1];
        const row = fromTemplate(rowTemplate, { __C__: index, __S__: nextRow++ });
        row.querySelector('[name$="[size]"]').value = size;
        card.querySelector('[data-option-rows]').append(row);
        refresh();
        return row;
    };

    const newCard = (color = '') => {
        // A quick-add color fills an untouched card (like the starting one) instead of leaving a blank one behind.
        const blank = color ? allCards().find(isBlank) : null;
        const card = blank || fromTemplate(cardTemplate, { __C__: nextColor++ });
        if (!blank) {
            cards.append(card);
            setupCard(card);
        }
        card.querySelector('[data-option-color-name]').value = color;
        const summary = card.querySelector(':scope > summary');
        if (summary.getAttribute('aria-expanded') === 'false' || !card.open) summary.click();
        refresh();
        return card;
    };

    panel.addEventListener('click', (event) => {
        const card = event.target.closest('[data-option-color]');
        if (event.target.closest('[data-option-add-size]')) {
            addRow(card).querySelector('[name$="[size]"]').focus();
        } else if (event.target.closest('[data-option-fill-sizes]')) {
            const taken = new Set(sizedRows(card).map((row) => row.querySelector('[name$="[size]"]').value.trim().toLowerCase()));
            const blanks = rowsOf(card).map((row) => row.querySelector('[name$="[size]"]')).filter((input) => !input.value.trim());
            sizesFor().filter((size) => !taken.has(size.toLowerCase())).forEach((size) => {
                const blank = blanks.shift();
                if (blank) blank.value = size;
                else addRow(card, size);
            });
            refresh();
        } else if (event.target.closest('[data-option-remove-row]')) {
            const row = event.target.closest('[data-option-row]');
            const next = row.nextElementSibling || row.previousElementSibling;
            if (next) {
                row.remove();
                next.querySelector('[name$="[size]"]').focus();
            } else {
                clearRow(row);
            }
            refresh();
        } else if (event.target.closest('[data-option-remove-color]')) {
            if (allCards().length > 1) {
                card.remove();
            } else {
                rowsOf(card).forEach((row, index) => { if (index) row.remove(); });
                card.querySelector('[data-option-color-name]').value = '';
                clearRow(rowsOf(card)[0]);
            }
            addColor.focus();
            refresh();
        } else if (event.target.closest('[data-option-add-color]')) {
            newCard().querySelector('[data-option-color-name]').focus();
        } else if (event.target.closest('[data-option-chip]')) {
            newCard(event.target.closest('[data-option-chip]').dataset.optionChip).querySelector('[data-option-row] [name$="[size]"]').focus();
        }
    });

    const updatePresets = () => {
        sizeList.replaceChildren(...sizesFor().map((size) => Object.assign(document.createElement('option'), { value: size })));
        refresh();
    };
    panel.addEventListener('input', refresh);
    mainPrice?.addEventListener('input', refresh);
    categories?.addEventListener('change', updatePresets);

    // A field that needs fixing inside a collapsed color opens that color so the browser can point to it.
    panel.addEventListener('invalid', (event) => {
        const card = event.target.closest('[data-option-color]');
        const summary = card?.querySelector(':scope > summary');
        if (summary && (summary.getAttribute('aria-expanded') === 'false' || !card.open)) summary.click();
    }, true);

    panel.querySelector('[data-option-chips]').hidden = false;
    panel.querySelectorAll(':scope > [data-size-actions]').forEach((actions) => { actions.hidden = false; });
    allCards().forEach(setupCard);
    updatePresets();
})();
