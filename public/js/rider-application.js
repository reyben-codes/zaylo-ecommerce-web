(() => {
    document.querySelectorAll('[data-rider-application]').forEach((root) => {
        const vehicle = root.querySelector('[data-vehicle-type]');
        const unlicensed = (root.dataset.unlicensed || '').split(' ').filter(Boolean);
        const sections = [...root.querySelectorAll('[data-rider-licensed]')];

        // Bicycles need no plate, license or OR/CR: fold those fields away and switch them off so they neither submit nor block.
        const showLicensedFields = () => {
            const needed = !unlicensed.includes(vehicle.value);
            sections.forEach((section) => {
                section.classList.toggle('is-collapsed', !needed);
                section.inert = !needed;
                section.querySelectorAll('input').forEach((input) => {
                    input.disabled = !needed;
                    input.required = needed;
                });
            });
        };
        vehicle?.addEventListener('change', showLicensedFields);
        if (vehicle) showLicensedFields();

        // License: upper case, dashes added as you type (N01-23-456789).
        root.querySelectorAll('[data-license]').forEach((input) => {
            input.addEventListener('input', () => {
                const raw = input.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
                const letter = raw.slice(0, 1).replace(/[^A-Z]/g, '');
                const digits = raw.slice(letter ? 1 : 0).replace(/[^0-9]/g, '').slice(0, 10);
                const groups = [digits.slice(0, 2), digits.slice(2, 4), digits.slice(4, 10)].filter(Boolean);
                input.value = letter + (groups.length ? groups.join('-') : '');
            });
        });

        root.querySelectorAll('[data-plate]').forEach((input) => {
            input.addEventListener('input', () => { input.value = input.value.toUpperCase().replace(/[^A-Z0-9 -]/g, ''); });
        });

        root.querySelectorAll('[data-license-expiry]').forEach((input) => {
            const check = () => input.setCustomValidity(input.value && input.min && input.value < input.min ? "Your driver's license has expired. Renew it before applying." : '');
            input.addEventListener('input', check);
            input.addEventListener('change', check);
        });

        root.querySelectorAll('input[type=file][data-max-bytes]').forEach((input) => {
            input.addEventListener('change', () => {
                const file = input.files?.[0];
                let message = '';
                if (file && !/\.(jpe?g|png|pdf)$/i.test(file.name)) {
                    message = `${input.dataset.documentLabel}: upload a JPG, PNG or PDF file.`;
                } else if (file && file.size > Number(input.dataset.maxBytes)) {
                    message = `${input.dataset.documentLabel}: the file must be 5 MB or smaller.`;
                }
                input.setCustomValidity(message);
                if (message) input.reportValidity();
            });
        });
    });
})();
