(() => {
    // Seller applications, and logistics applications that share the same fields and checks.
    const init = (scope) => scope.querySelectorAll('[data-seller-application], [data-business-application]').forEach((root) => {
        if (root.dataset.applicationBound) return;
        root.dataset.applicationBound = 'true';
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

        // Branch Hub preview update
        root.querySelectorAll('[data-branch-name]').forEach((input) => {
            const prefix = input.dataset.networkPrefix || 'Logistics';
            const preview = root.querySelector('[data-branch-preview]');
            const companyInput = root.querySelector('input[name="company_name"]');
            const updatePreview = () => {
                const branch = input.value.trim();
                const full = branch ? `${prefix} - ${branch}` : `${prefix} - `;
                if (preview) preview.textContent = full;
                if (companyInput) companyInput.value = branch ? full : '';
            };
            input.addEventListener('input', updatePreview);
            if (input.value) updatePreview();
        });

        // Hub address validation against main network hub
        const mainStreet = root.dataset.mainStreet;
        const mainCity = root.dataset.mainCity;
        const mainBrgy = root.dataset.mainBarangay;
        const mainNetwork = root.dataset.mainNetwork || 'main';

        if (mainStreet && mainCity) {
            const streetInput = root.querySelector('input[name="street_address"]');
            const citySelect = root.querySelector('select[name="city_code"]');
            const brgySelect = root.querySelector('select[name="barangay_code"]');
            const warning = root.querySelector('[data-hub-address-warning]');

            const normalize = (str) => {
                if (!str) return '';
                return str.toLowerCase()
                    .replace(/\bstreet\b/g, 'st')
                    .replace(/\bavenue\b/g, 'ave')
                    .replace(/\broad\b/g, 'rd')
                    .replace(/\bdrive\b/g, 'dr')
                    .replace(/\bbuilding\b/g, 'bldg')
                    .replace(/\bbarangay\b/g, 'brgy')
                    .replace(/\bpoblacion\b/g, 'pob')
                    .replace(/\bhighway\b/g, 'hwy')
                    .replace(/\bblock\b/g, 'blk')
                    .replace(/\blot\b/g, 'lt')
                    .replace(/\bfloor\b/g, 'flr')
                    .replace(/\bunit\b/g, 'unit')
                    .replace(/[^a-z0-9]/g, '');
            };

            const normMain = normalize(mainStreet);

            const checkAddress = () => {
                if (!streetInput) return;
                const currentStreet = streetInput.value.trim();
                const currentCity = citySelect ? citySelect.value : '';
                const currentBrgy = brgySelect ? brgySelect.value : '';

                if (!currentStreet || !currentCity) {
                    streetInput.setCustomValidity('');
                    if (warning) {
                        warning.hidden = true;
                        warning.textContent = '';
                    }
                    return;
                }

                const normCurrent = normalize(currentStreet);
                const cityMatch = currentCity === mainCity;
                const brgyMatch = !mainBrgy || !currentBrgy || currentBrgy === mainBrgy;
                const streetMatch = normCurrent === normMain || normCurrent.includes(normMain) || normMain.includes(normCurrent);

                if (cityMatch && brgyMatch && streetMatch) {
                    const msg = `This address matches the ${mainNetwork} main network hub. Regional branch hubs must be located at a different address.`;
                    streetInput.setCustomValidity(msg);
                    if (warning) {
                        warning.textContent = msg;
                        warning.hidden = false;
                    }
                } else {
                    streetInput.setCustomValidity('');
                    if (warning) {
                        warning.hidden = true;
                        warning.textContent = '';
                    }
                }
            };

            streetInput?.addEventListener('input', checkAddress);
            citySelect?.addEventListener('change', checkAddress);
            brgySelect?.addEventListener('change', checkAddress);
        }
    });

    init(document);
    // Forms the sign-in modal loads later (public/js/auth-modal.js) announce themselves with this event.
    document.addEventListener('zaylo:content', (event) => init(event.target));
})();
