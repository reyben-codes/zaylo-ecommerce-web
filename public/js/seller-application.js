(() => {
    // Seller applications, and logistics applications that share the same fields and checks.
    document.querySelectorAll('[data-seller-application], [data-business-application]').forEach((root) => {
        const type = root.querySelector('[data-seller-type]');
        const documents = [...root.querySelectorAll('[data-seller-document]')];

        // Show only the documents the chosen seller type needs; hidden ones are disabled so they neither submit nor block the form.
        const showDocuments = () => {
            documents.forEach((wrapper) => {
                const input = wrapper.querySelector('input[type=file]');
                const show = !type.value || wrapper.dataset.types.split(' ').includes(type.value);
                wrapper.classList.toggle('is-collapsed', !show);
                wrapper.inert = !show;
                input.disabled = !show;
                input.required = show && !!type.value && !input.hasAttribute('data-on-file');
            });
        };
        type?.addEventListener('change', showDocuments);
        if (type) showDocuments();

        // TIN: digits only, grouped as 000-000-000-000 while typing.
        root.querySelectorAll('[data-tin]').forEach((input) => {
            input.addEventListener('input', () => {
                const digits = input.value.replace(/[^0-9]/g, '').slice(0, 12);
                input.value = digits.match(/.{1,3}/g)?.join('-') ?? '';
            });
        });

        root.querySelectorAll('[data-uppercase]').forEach((input) => {
            input.addEventListener('input', () => { input.value = input.value.replace(/\s+/g, '').toUpperCase(); });
        });

        // Checkbox groups that need at least one choice; the first box carries the message so the browser can point to it.
        root.querySelectorAll('[data-require-one]').forEach((group) => {
            const boxes = [...group.querySelectorAll('input[type=checkbox]')];
            const check = () => boxes[0]?.setCustomValidity(boxes.some((box) => box.checked) ? '' : group.dataset.requireOne);
            boxes.forEach((box) => box.addEventListener('change', check));
            check();
        });

        root.querySelectorAll('[data-digits-only]').forEach((input) => {
            input.addEventListener('input', () => { input.value = input.value.replace(/[^0-9]/g, ''); });
        });

        // Check file type and size before uploading.
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
