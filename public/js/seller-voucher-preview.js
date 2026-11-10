/**
 * Real-time buyer voucher preview for the Seller Center voucher creation form.
 * Mirrors how the voucher appears to buyers on the web and mobile checkout.
 */
(() => {
    'use strict';

    function initVoucherPreview() {
        const form = document.getElementById('seller-voucher-form');
        if (!form) return;

        const codeInput = document.getElementById('seller-voucher-code');
        const nameInput = document.getElementById('seller-voucher-name');
        const typeInput = document.getElementById('seller-voucher-type');
        const valueInput = document.getElementById('seller-voucher-value');
        const minSpendInput = document.getElementById('seller-voucher-min');
        const maxDiscountInput = document.getElementById('seller-voucher-max');
        const limitInput = document.getElementById('seller-voucher-limit');
        const startsInput = document.getElementById('seller-voucher-starts');
        const endsInput = document.getElementById('seller-voucher-ends');

        const previewBadge = document.getElementById('preview-badge-text');
        const previewCode = document.getElementById('preview-code');
        const previewName = document.getElementById('preview-name');
        const previewDesc = document.getElementById('preview-desc');
        const previewValidity = document.getElementById('preview-validity');
        const previewUsage = document.getElementById('preview-usage-badge-text');

        function formatPesos(amount) {
            const num = parseFloat(amount);
            if (isNaN(num)) return '₱0.00';
            return '₱' + num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function formatDate(dateStr) {
            if (!dateStr) return null;
            const d = new Date(dateStr);
            if (isNaN(d.getTime())) return null;
            return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        }

        function updatePreview() {
            // Code
            const rawCode = (codeInput?.value || '').trim().toUpperCase();
            if (previewCode) {
                previewCode.textContent = rawCode || 'CODE';
            }

            // Name
            const rawName = (nameInput?.value || '').trim();
            if (previewName) {
                previewName.textContent = rawName;
                previewName.style.display = rawName ? 'block' : 'none';
            }

            // Type & Value
            const type = typeInput?.value || 'percent';
            const rawVal = parseFloat(valueInput?.value);
            const hasVal = !isNaN(rawVal) && rawVal > 0;

            let offerLabel = 'DISCOUNT';
            if (type === 'percent') {
                offerLabel = hasVal ? (parseFloat(rawVal.toFixed(2)) + '% OFF') : '% OFF';
            } else {
                offerLabel = hasVal ? (formatPesos(rawVal) + ' OFF') : '₱ OFF';
            }
            if (previewBadge) {
                previewBadge.textContent = offerLabel;
            }

            // Description
            let desc = '';
            if (type === 'percent') {
                desc = hasVal ? `${parseFloat(rawVal.toFixed(2))}% off` : 'Discount off';
                const maxVal = parseFloat(maxDiscountInput?.value);
                if (!isNaN(maxVal) && maxVal > 0) {
                    desc += `, up to ${formatPesos(maxVal)}`;
                }
            } else {
                desc = hasVal ? `${formatPesos(rawVal)} off` : 'Discount off';
            }

            const minSpend = parseFloat(minSpendInput?.value);
            if (!isNaN(minSpend) && minSpend > 0) {
                desc += `, min. spend ${formatPesos(minSpend)}`;
            }
            if (previewDesc) {
                previewDesc.textContent = desc;
            }

            // Validity
            const endDateStr = formatDate(endsInput?.value);
            const startDateStr = formatDate(startsInput?.value);
            let validityText = 'Valid until ended';
            if (endDateStr) {
                validityText = `Ends ${endDateStr}`;
            } else if (startDateStr) {
                validityText = `Starts ${startDateStr}`;
            }
            if (previewValidity) {
                previewValidity.textContent = validityText;
            }

            // Usage Limit
            const rawLimit = parseInt(limitInput?.value, 10);
            let usageText = 'Unlimited claims';
            if (!isNaN(rawLimit) && rawLimit > 0) {
                if (rawLimit === 1) {
                    usageText = '1 claim only';
                } else {
                    usageText = `Limited: ${rawLimit.toLocaleString()} claims`;
                }
            }
            if (previewUsage) {
                previewUsage.textContent = usageText;
            }
        }

        form.addEventListener('input', updatePreview);
        form.addEventListener('change', updatePreview);
        updatePreview();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initVoucherPreview);
    } else {
        initVoucherPreview();
    }
})();
