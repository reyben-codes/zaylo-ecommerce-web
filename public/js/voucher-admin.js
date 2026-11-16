(() => {
    const targetWrap = document.getElementById('target-seller-wrap');
    const targetRadios = document.querySelectorAll('input[name="target_type"]');
    const targetSelect = document.getElementById('voucher-target');
    const shopTiles = document.querySelectorAll('.target-shop-tile');
    const shopRadios = document.querySelectorAll('input[name="target_seller_id"]');
    const searchInput = document.getElementById('target-shops-search-input');
    const noneNote = document.getElementById('target-shops-none');
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    let targetAnimation = null;
    let targetExpanded = true;

    const finishTargetAnimation = () => {
        if (!targetWrap) return;
        if (targetAnimation) {
            targetAnimation.onfinish = null;
            targetAnimation.cancel();
            targetAnimation = null;
        }
        targetWrap.hidden = !targetExpanded;
        targetWrap.style.overflow = '';
    };

    const showTarget = (expanded, animate) => {
        if (!targetWrap) return;
        const startHeight = targetWrap.hidden ? 0 : targetWrap.getBoundingClientRect().height;
        targetExpanded = expanded;
        if (!expanded && targetWrap.contains(document.activeElement)) {
            document.querySelector('input[name="target_type"]:checked')?.focus();
        }
        targetWrap.inert = !expanded;
        if (targetAnimation) {
            targetAnimation.onfinish = null;
            targetAnimation.cancel();
            targetAnimation = null;
        }
        if (!animate || reducedMotion.matches || !targetWrap.animate) {
            finishTargetAnimation();
            return;
        }
        targetWrap.hidden = false;
        const endHeight = expanded ? targetWrap.getBoundingClientRect().height : 0;
        targetWrap.style.overflow = 'hidden';
        targetAnimation = targetWrap.animate(
            [{ height: `${startHeight}px` }, { height: `${endHeight}px` }],
            { duration: 300, easing: 'cubic-bezier(.22, 1, .36, 1)' },
        );
        targetAnimation.onfinish = finishTargetAnimation;
    };

    const getTargetType = () => {
        if (targetRadios.length > 0) {
            const checked = document.querySelector('input[name="target_type"]:checked');
            return checked ? checked.value : 'all';
        }
        return targetSelect ? targetSelect.value : 'all';
    };

    const syncTargetType = (animate = true) => {
        const type = getTargetType();
        const isSpecific = type === 'specific';

        showTarget(isSpecific, animate);

        targetRadios.forEach((radio) => {
            const card = radio.closest('.target-type-card');
            if (card) {
                card.classList.toggle('is-selected', radio.checked);
            }
        });

        // Set or remove required constraint on shop radios
        if (shopRadios.length > 0) {
            shopRadios.forEach((radio) => {
                radio.required = isSpecific;
            });
        }
    };

    targetRadios.forEach((radio) => {
        radio.addEventListener('change', syncTargetType);
    });
    if (targetSelect) {
        targetSelect.addEventListener('change', syncTargetType);
    }

    // Shop square tiles selection
    const syncShopSelection = () => {
        shopTiles.forEach((tile) => {
            const radio = tile.querySelector('input[type="radio"]');
            tile.classList.toggle('is-selected', radio ? radio.checked : false);
        });
    };

    shopRadios.forEach((radio) => {
        radio.addEventListener('change', syncShopSelection);
    });

    // Live search filter for square shop tiles
    if (searchInput) {
        searchInput.addEventListener('input', () => {
            const query = searchInput.value.trim().toLowerCase();
            let visibleCount = 0;

            shopTiles.forEach((tile) => {
                const name = tile.getAttribute('data-shop-name') || '';
                const owner = tile.getAttribute('data-owner') || '';
                const matches = !query || name.includes(query) || owner.includes(query);

                tile.hidden = !matches;
                if (matches) visibleCount++;
            });

            if (noneNote) {
                noneNote.hidden = !(visibleCount === 0 && query !== '');
            }
        });
    }

    window.addEventListener('resize', finishTargetAnimation);
    reducedMotion.addEventListener('change', finishTargetAnimation);
    syncTargetType(false);
    syncShopSelection();
})();
