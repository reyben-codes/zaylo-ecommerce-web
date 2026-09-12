(() => {
    document.querySelectorAll('[data-address-form]').forEach(form => {
        const fields = Object.fromEntries(['region', 'province', 'city', 'barangay'].map(key => [key, form.querySelector('[data-location="' + key + '"]')]));
        const status = form.querySelector('[data-location-status]');
        const retry = form.querySelector('[data-location-retry]');
        const save = form.querySelector('[data-address-save]');
        const note = form.querySelector('[data-province-note]');
        const base = form.dataset.locationsUrl;
        let generation = 0;
        let controller;
        let freeCities = [];
        let ready = false;
        let retryAction;

        function clear(key, text) {
            fields[key].replaceChildren(new Option(text, ''));
            fields[key].disabled = true;
        }
        function populate(key, items, text, selected = '') {
            fields[key].replaceChildren(new Option(text, ''));
            items.forEach(item => fields[key].add(new Option(item.name, item.code)));
            fields[key].disabled = false;
            fields[key].value = selected || '';
        }
        async function get(path, signal) {
            const response = await fetch(base + '/' + path, { headers: { Accept: 'application/json' }, signal });
            if (!response.ok) throw new Error('Locations could not be loaded. Please try again.');
            const data = await response.json();
            if (!Array.isArray(data)) throw new Error('Locations could not be loaded. Please try again.');
            return data;
        }
        function canSave() {
            save.disabled = !ready || !fields.region.value || !fields.city.value || !fields.barangay.value;
        }
        async function run(action) {
            controller?.abort();
            controller = new AbortController();
            const current = ++generation;
            ready = false;
            save.disabled = true;
            retry.hidden = true;
            status.textContent = 'Loading locations…';
            retryAction = () => run(action);
            try {
                await action(controller.signal);
                if (current !== generation) return;
                ready = true;
                status.textContent = 'Locations loaded. Choose your delivery location.';
                canSave();
            } catch (error) {
                if (current !== generation || error.name === 'AbortError') return;
                status.textContent = error.message || 'Locations could not be loaded. Please try again.';
                retry.hidden = false;
            }
        }
        async function loadBarangays(signal, selected = '') {
            if (!fields.city.value) return;
            const items = await get('cities-municipalities/' + fields.city.value + '/barangays', signal);
            if (!signal.aborted) populate('barangay', items, 'Choose a barangay', selected);
        }
        async function loadCities(signal, city = '', barangay = '') {
            let items;
            if (fields.province.value === '__none__' || fields.province.disabled) {
                items = freeCities;
            } else if (fields.province.value) {
                items = await get('provinces/' + fields.province.value + '/cities-municipalities', signal);
            } else {
                return;
            }
            if (signal.aborted) return;
            populate('city', items, 'Choose a city / municipality', city);
            await loadBarangays(signal, barangay);
        }
        async function loadRegion(signal, province = '', city = '', barangay = '') {
            if (!fields.region.value) return;
            const [provinces, independent] = await Promise.all([
                get('regions/' + fields.region.value + '/provinces', signal),
                get('regions/' + fields.region.value + '/cities-municipalities', signal),
            ]);
            if (signal.aborted) return;
            freeCities = independent;
            note.hidden = !freeCities.length;
            if (!provinces.length && freeCities.length) {
                clear('province', 'Not applicable');
            } else {
                populate('province', provinces, 'Choose a province', province);
                if (freeCities.length) fields.province.add(new Option('No province / independent locality', '__none__'));
                if (!province && city && freeCities.some(item => item.code === city)) fields.province.value = '__none__';
            }
            await loadCities(signal, city, barangay);
        }
        fields.region.addEventListener('change', () => {
            clear('province', 'Choose a region first');
            clear('city', 'Choose a province first');
            clear('barangay', 'Choose a city / municipality first');
            note.hidden = true;
            freeCities = [];
            run(signal => loadRegion(signal));
        });
        fields.province.addEventListener('change', () => {
            clear('city', 'Choose a province first');
            clear('barangay', 'Choose a city / municipality first');
            run(signal => loadCities(signal));
        });
        fields.city.addEventListener('change', () => {
            clear('barangay', 'Choose a city / municipality first');
            run(signal => loadBarangays(signal));
        });
        fields.barangay.addEventListener('change', canSave);
        retry.addEventListener('click', () => retryAction?.());
        form.addEventListener('submit', event => {
            if (save.disabled) {
                event.preventDefault();
                return;
            }
            // The UI-only independent-locality option must never become a PSGC code.
            if (fields.province.value === '__none__') fields.province.disabled = true;
        });
        window.addEventListener('pageshow', () => {
            if (fields.province.value === '__none__') fields.province.disabled = false;
        });
        run(async signal => {
            const regions = await get('regions', signal);
            if (signal.aborted) return;
            populate('region', regions, 'Choose a region', fields.region.dataset.selected);
            await loadRegion(signal, fields.province.dataset.selected, fields.city.dataset.selected, fields.barangay.dataset.selected);
        });
    });
})();
