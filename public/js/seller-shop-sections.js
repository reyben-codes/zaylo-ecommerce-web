// Seller Center > Shop sections: the product checklist in each section's editor.
// The page carries one <template> of the seller's products; it is copied into an editor just before it opens
// (so smooth-disclosure.js measures the full height), with that section's products ticked. A hidden
// products_submitted field then tells the server to replace the section's products; without JavaScript the list
// never appears, and saving changes only the name and visibility.
(() => {
    const template = document.getElementById('section-product-template');
    if (!template) return;

    const fill = (picker) => {
        const list = picker.querySelector('[data-picker-list]');
        if (!list || picker.dataset.filled) return;
        picker.dataset.filled = 'true';

        let selected = [];
        try { selected = JSON.parse(picker.dataset.selected || '[]').map(Number); } catch { selected = []; }
        list.append(template.content.cloneNode(true));
        list.querySelectorAll('input[type=checkbox]').forEach((box) => { box.checked = selected.includes(Number(box.value)); });

        const marker = document.createElement('input');
        Object.assign(marker, { type: 'hidden', name: 'products_submitted', value: '1' });
        picker.append(marker);

        const tools = picker.querySelector('[data-picker-tools]');
        const search = picker.querySelector('[data-picker-search]');
        const count = picker.querySelector('[data-picker-count]');
        const empty = picker.querySelector('[data-picker-empty]');
        const items = [...list.querySelectorAll('.section-picker__item')];
        tools.hidden = false;

        const updateCount = () => {
            const ticked = items.filter((item) => item.querySelector('input').checked).length;
            count.textContent = `${ticked} of ${items.length} selected`;
        };
        list.addEventListener('change', updateCount);
        updateCount();

        search.addEventListener('input', () => {
            const words = search.value.trim().toLowerCase().split(/\s+/).filter(Boolean);
            let shown = 0;
            items.forEach((item) => {
                const match = words.every((word) => item.dataset.name.includes(word));
                item.hidden = !match;
                if (match) shown += 1;
            });
            empty.hidden = shown > 0;
        });
        // Enter in the search box filters; it must not save the section.
        search.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') event.preventDefault();
        });
    };

    // Capture phase: runs before smooth-disclosure.js starts the opening animation.
    document.addEventListener('click', (event) => {
        const summary = event.target.closest('.shop-section__editor > summary');
        const picker = summary?.parentElement.querySelector('[data-section-picker]');
        if (picker) fill(picker);
    }, true);

    // An editor already open on load (just added, or showing an error).
    document.querySelectorAll('.shop-section__editor[open] [data-section-picker]').forEach(fill);
})();
