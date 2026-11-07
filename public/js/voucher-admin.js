(() => {
    const targetSelect = document.getElementById('voucher-target');
    const targetWrap = document.getElementById('target-seller-wrap');
    const targetSeller = document.getElementById('voucher-target-seller');
    if (!targetSelect || !targetWrap) return;

    const sync = () => {
        const isSpecific = targetSelect.value === 'specific';
        targetWrap.style.display = isSpecific ? '' : 'none';
        if (targetSeller) {
            targetSeller.required = isSpecific;
        }
    };

    targetSelect.addEventListener('change', sync);
    sync();
})();

