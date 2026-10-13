(() => {
    const picker = document.querySelector('[data-variant-picker]');
    const options = [...(picker?.querySelectorAll('input[name="product_variant_id"]') ?? [])];
    if (!options.length) return;
    // The buy form only exists for signed-in buyers while something is in stock.
    const form = document.querySelector('.product-buy-form');
    const money = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });
    const selectedName = picker.querySelector('[data-variant-selected]');
    const gallery = document.querySelector('[data-product-gallery]');

    // Show the chosen option's photo, reusing the gallery thumbnail when there is one.
    const showPhoto = (src) => {
        if (!src || !gallery) return;
        const thumbnail = [...gallery.querySelectorAll('[data-gallery-thumbnail]')].find((button) => button.dataset.imageSrc === src);
        if (thumbnail) {
            thumbnail.click();
            return;
        }
        const main = gallery.querySelector('[data-gallery-main]');
        if (main) main.src = src;
    };

    const showPrice = (option) => {
        if (!option) return;
        const price = Number(option.dataset.price), regular = Number(option.dataset.regular);
        document.querySelector('[data-sale-price]').textContent = money.format(price);
        const original = document.querySelector('[data-sale-regular]');
        const savings = document.querySelector('[data-sale-saving]');
        original.textContent = money.format(regular);
        original.hidden = savings.hidden = price >= regular;
        savings.textContent = `${regular > 0 ? Math.round((1 - price / regular) * 100) : 0}% off`;
    };

    const limitQuantity = (stock) => {
        const quantity = form?.querySelector('[name="quantity"]');
        if (!quantity) return;
        quantity.max = Math.max(1, Math.min(99, stock));
        if (Number(quantity.value) > Number(quantity.max)) quantity.value = quantity.max;
        quantity.dispatchEvent(new Event('input', { bubbles: true }));
    };

    // Color first, then that color's own sizes.
    if (picker.matches('[data-sized-picker]')) {
        const colors = [...picker.querySelectorAll('[data-color-option]')];
        const groups = [...picker.querySelectorAll('[data-size-group]')];
        const colorName = picker.querySelector('[data-color-selected]');
        const sizesOf = (group) => [...group.querySelectorAll('input[name="product_variant_id"]')];

        const refreshSize = () => {
            const group = groups.find((item) => !item.hidden);
            if (!group) return;
            const sizes = sizesOf(group);
            const chosen = sizes.find((input) => input.checked);
            const available = sizes.filter((input) => !input.hasAttribute('data-sold-out'));
            group.querySelector('[data-size-selected]').textContent = chosen ? chosen.dataset.name : (available.length ? 'Select a size' : 'Sold out');
            // Until a size is picked, show the color's starting price and allow up to its largest stock.
            showPrice(chosen ?? available[0] ?? sizes[0]);
            limitQuantity(chosen ? Number(chosen.dataset.stock) : Math.max(0, ...available.map((input) => Number(input.dataset.stock))));
        };

        const showColor = (changePhoto) => {
            const color = colors.find((input) => input.checked) ?? colors[0];
            const previous = options.find((input) => input.checked);
            groups.forEach((group) => {
                const active = group.dataset.sizeGroup === color.value;
                group.hidden = !active;
                // Hidden sizes are disabled so they neither submit nor block the form's "choose a size" check.
                sizesOf(group).forEach((input) => { input.disabled = !active || input.hasAttribute('data-sold-out'); });
            });
            const group = groups.find((item) => item.dataset.sizeGroup === color.value);
            if (previous && !group.contains(previous)) {
                previous.checked = false;
                // Keep the buyer's size when the new color has it in stock.
                const same = sizesOf(group).find((input) => input.dataset.name === previous.dataset.name && !input.disabled);
                if (same) same.checked = true;
            }
            colorName.textContent = color.dataset.name;
            if (changePhoto) showPhoto(color.dataset.image);
            refreshSize();
        };

        picker.classList.add('is-enhanced');
        colors.forEach((input) => input.addEventListener('change', () => showColor(true)));
        options.forEach((input) => input.addEventListener('change', refreshSize));
        showColor(false);
        return;
    }

    const update = (changePhoto) => {
        const option = options.find((input) => input.checked);
        if (!option) return;
        showPrice(option);
        if (selectedName) selectedName.textContent = option.dataset.name;
        limitQuantity(Number(option.dataset.stock));
        if (changePhoto) showPhoto(option.dataset.image);
    };

    options.forEach((input) => input.addEventListener('change', () => update(true)));
    update(false);
})();
