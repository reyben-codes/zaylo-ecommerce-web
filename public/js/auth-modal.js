// Sign-in / sign-up modal (resources/views/partials/auth-modal.blade.php, App\Support\AuthModal).
// Any link to a sign-in, sign-up, email-code or forgot-password URL opens its panel here instead of loading a
// page; the address bar follows along, so Back closes the modal and a refresh reopens it. Forms post in the
// background: errors appear beside their fields, and the server's next step (another panel, or the account
// page) follows on. Sign-up splits into short steps. Without JavaScript the same URLs are ordinary pages.
(() => {
    const dialog = document.querySelector('[data-auth-modal]');
    if (!dialog || typeof dialog.showModal !== 'function' || !window.fetch) return;

    const root = document.documentElement;
    const body = dialog.querySelector('[data-auth-body]');
    const loading = dialog.querySelector('[data-auth-loading]');
    const tagline = dialog.querySelector('[data-auth-tagline]');
    const closeLink = dialog.querySelector('[data-auth-close]');
    const paths = JSON.parse(dialog.dataset.paths || '[]');
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const PANEL_HEADERS = { 'X-Auth-Modal': '1', 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' };
    const FORM_HEADERS = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };

    let currentUrl = null;   // the panel on show, as path + query
    let behindUrl = null;    // the page under the modal, restored on close
    let returnPath = '';     // where shoppers go back to after signing in (empty: their account page)
    let baseTitle = document.title;
    let opener = null;
    let request = 0;
    let ignorePop = false;
    let loadingTimer = 0;

    const here = () => location.pathname + location.search;
    const toUrl = (href) => new URL(href, location.href);
    const pathOf = (url) => url.pathname + url.search;
    const isPanelUrl = (href) => {
        try {
            const url = toUrl(href);
            return url.origin === location.origin && paths.includes(url.pathname);
        } catch {
            return false;
        }
    };
    const animate = (element, className) => {
        if (reduceMotion.matches || !element) return;
        element.classList.remove(className);
        void element.offsetWidth; // restart the animation
        element.classList.add(className);
    };

    // ---------------------------------------------------------------- opening and closing

    const show = () => {
        if (dialog.open) return;
        dialog.classList.remove('is-closing');
        dialog.showModal();
        root.classList.add('auth-modal-open');
    };

    const setLoading = (on) => {
        window.clearTimeout(loadingTimer);
        // A short delay keeps fast loads from flashing a spinner.
        if (on) loadingTimer = window.setTimeout(() => { loading.hidden = false; }, 140);
        else loading.hidden = true;
        body.setAttribute('aria-busy', String(on));
    };

    // Each modal history entry knows the page underneath, so Back, Forward and close all restore it.
    const remember = (mode, target) => {
        if (mode === 'none') return;
        const pushed = mode === 'push' || Boolean(history.state?.pushed);
        const state = { authModal: target, pushed, behind: behindUrl };
        if (mode === 'push') history.pushState(state, '', target);
        else history.replaceState(state, '', target);
    };

    async function open(href, { history: mode = 'push', source = null, reload = false } = {}) {
        const url = toUrl(href);
        const target = pathOf(url);

        const wasOpen = dialog.open;
        if (!wasOpen) {
            opener = source || document.activeElement;
            // Coming back with Forward, the address bar already shows the panel; the entry remembers the page.
            behindUrl = mode === 'none' && history.state?.behind ? history.state.behind : here();
            returnPath = behindUrl;
            baseTitle = document.title;
        }
        show();
        // Moving between panels replaces the entry, so one Back (or close) always returns to the page.
        const historyMode = mode === 'push' && wasOpen ? 'replace' : mode;

        // Reopening the panel that is already loaded keeps whatever was typed into it.
        if (!reload && target === currentUrl && body.querySelector('[data-auth-panel]')) {
            remember(historyMode, target);
            focusPanel();
            return;
        }

        const id = ++request;
        setLoading(true);
        let response;
        let html;
        try {
            response = await fetch(url, { headers: PANEL_HEADERS, credentials: 'same-origin' });
            html = await response.text();
        } catch {
            if (id !== request) return;
            setLoading(false);
            showLoadError(url.href);
            return;
        }
        if (id !== request) return;

        const finalUrl = toUrl(response.url || url.href);
        // Already signed in, or a page outside the modal (such as the app download page): go there.
        if ((response.redirected && !isPanelUrl(finalUrl.href)) || !response.ok) {
            location.assign(response.ok ? finalUrl.href : url.href);
            return;
        }

        setLoading(false);
        swap(html);
        currentUrl = pathOf(finalUrl);
        remember(historyMode, currentUrl);
    }

    function close({ fromHistory = false } = {}) {
        if (!dialog.open || dialog.classList.contains('is-closing')) return;
        request++;
        setLoading(false);

        if (!fromHistory) {
            if (history.state?.pushed) {
                ignorePop = true;
                history.back();
            } else {
                history.replaceState(null, '', behindUrl || '/');
            }
        }
        document.title = baseTitle;

        const finish = () => {
            dialog.classList.remove('is-closing');
            dialog.close();
            root.classList.remove('auth-modal-open');
            if (opener && opener.isConnected && typeof opener.focus === 'function') opener.focus({ preventScroll: true });
            opener = null;
        };
        if (reduceMotion.matches) {
            finish();
            return;
        }
        dialog.classList.add('is-closing');
        let done = false;
        const end = () => {
            if (done) return;
            done = true;
            dialog.removeEventListener('animationend', end);
            finish();
        };
        dialog.addEventListener('animationend', end);
        window.setTimeout(end, 320);
    }

    function showLoadError(href) {
        body.innerHTML = '<div class="auth-panel"><div class="auth-alert auth-alert--error" role="alert">We couldn’t load this. Check your internet connection and try again.</div><button type="button" class="btn-primary auth-submit" data-auth-retry>Try again</button></div>';
        body.querySelector('[data-auth-retry]').addEventListener('click', () => open(href, { history: 'none', reload: true }));
        body.querySelector('[data-auth-retry]').focus();
    }

    // ---------------------------------------------------------------- panels

    function swap(html) {
        body.innerHTML = html; // our own server's panel markup
        body.scrollTop = 0;
        animate(body, 'is-swapping');
        setupPanel();
    }

    function setupPanel() {
        const panel = body.querySelector('[data-auth-panel]');
        if (!panel) return;

        const [first, second] = (panel.dataset.tagline || '').split('|');
        const lines = tagline?.querySelectorAll('p') || [];
        if (first && lines[0] && lines[0].textContent !== first) {
            lines[0].textContent = first;
            lines[1].textContent = second || '';
            animate(tagline, 'is-changing');
        }

        panel.querySelectorAll('[data-return-to]').forEach((input) => { input.value = returnPath; });

        // Field helpers (names, phone numbers, addresses, documents, the email code) set themselves up first.
        body.dispatchEvent(new CustomEvent('zaylo:content', { bubbles: true }));
        panel.querySelectorAll('[data-auth-wizard]').forEach(setupWizard);
        focusPanel();
    }

    function focusPanel() {
        const panel = body.querySelector('[data-auth-panel]');
        if (!panel) return;
        const wizardTitle = panel.querySelector('.auth-wizard.is-enhanced') && panel.querySelector('[data-wizard-title]');
        const field = panel.querySelector('input[autofocus]');
        const target = wizardTitle || (field && field.offsetParent !== null ? field : panel.querySelector('#auth-modal-title'));
        target?.focus({ preventScroll: true });
    }

    // ---------------------------------------------------------------- sign-up wizard

    function setupWizard(form) {
        if (form.wizard) return;
        const panel = form.closest('[data-auth-panel]');
        const progress = panel.querySelector('[data-wizard-progress]');
        const count = progress.querySelector('[data-wizard-count]');
        const title = progress.querySelector('[data-wizard-title]');
        const bar = progress.querySelector('[data-wizard-bar]');
        const back = form.querySelector('[data-wizard-back]');
        const next = form.querySelector('[data-wizard-next]');
        const submit = form.querySelector('[data-wizard-submit]');
        const allSteps = [...form.querySelectorAll('[data-wizard-step]')];
        // Steps for fields switched off (a bicycle needs no license) are skipped.
        const steps = () => allSteps.filter((step) => !step.closest('[inert]'));
        let index = 0;

        form.noValidate = true;
        form.classList.add('is-enhanced');
        progress.hidden = false;

        const render = (direction = 0, focus = true) => {
            const active = steps();
            index = Math.max(0, Math.min(index, active.length - 1));
            const current = active[index];
            const last = index === active.length - 1;
            const total = active.length + 1; // the email code comes after

            allSteps.forEach((step) => {
                step.hidden = step !== current;
                step.classList.remove('is-entering', 'is-entering-back');
            });
            if (direction) animate(current, direction > 0 ? 'is-entering' : 'is-entering-back');

            count.textContent = `Step ${index + 1} of ${total}`;
            title.textContent = current.dataset.stepTitle || '';
            bar.replaceChildren(...Array.from({ length: total }, (_, position) => {
                const item = document.createElement('li');
                if (position < index) item.className = 'is-done';
                if (position === index) item.className = 'is-current';
                return item;
            }));

            back.hidden = index === 0;
            next.hidden = last;
            submit.hidden = !last;
            next.textContent = `Continue to ${(active[index + 1]?.dataset.stepTitle || 'next step').replace(/^Your /, '').toLowerCase()}`;

            if (direction) body.scrollTo({ top: 0, behavior: reduceMotion.matches ? 'auto' : 'smooth' });
            if (focus && direction) title.focus({ preventScroll: true });
        };

        const confirmPasswords = () => {
            const password = form.querySelector('[data-new-password]');
            const confirmation = form.querySelector('[data-password-confirmation]');
            confirmation?.setCustomValidity(confirmation.value && confirmation.value !== password?.value ? 'Your passwords must match.' : '');
        };

        const firstInvalid = (step) => {
            confirmPasswords();
            return [...step.querySelectorAll('input, select, textarea')]
                .find((field) => !field.disabled && !field.closest('[inert]') && !field.checkValidity());
        };

        const goTo = (step, direction = 0) => {
            const position = steps().indexOf(step);
            if (position === -1) return;
            const moved = position !== index;
            const dir = direction || Math.sign(position - index);
            index = position;
            render(moved ? dir : 0);
        };

        const advance = () => {
            const current = steps()[index];
            const invalid = firstInvalid(current);
            if (invalid) {
                invalid.reportValidity();
                return;
            }
            index += 1;
            render(1);
        };

        next.addEventListener('click', advance);
        back.addEventListener('click', () => {
            index -= 1;
            render(-1);
        });
        // Choosing a vehicle can add or remove steps; keep the count right without moving.
        form.addEventListener('change', () => render(0, false));
        form.querySelectorAll('[data-new-password], [data-password-confirmation]').forEach((field) => {
            field.addEventListener('input', () => form.querySelector('[data-password-confirmation]')?.setCustomValidity(''));
        });

        form.wizard = {
            isLast: () => index === steps().length - 1,
            advance,
            goTo,
            stepOf: (field) => allSteps.find((step) => step.contains(field)),
            /** Checks every step before sending; opens the first one with a problem. */
            validateAll() {
                for (const step of steps()) {
                    const invalid = firstInvalid(step);
                    if (invalid) {
                        goTo(step);
                        window.requestAnimationFrame(() => invalid.reportValidity());
                        return false;
                    }
                }
                return true;
            },
        };

        render();

        // Errors the server sent with the page (a form posted without JavaScript) go beside their fields too.
        let errors = {};
        try { errors = JSON.parse(form.dataset.errors || '{}'); } catch { errors = {}; }
        if (Object.keys(errors).length) showFieldErrors(form, errors);
    }

    // ---------------------------------------------------------------- errors

    const alertsOf = (form) => form.closest('[data-auth-panel]')?.querySelector('[data-auth-alerts]');

    function showAlert(form, message, { tone = 'error', hint = null, list = [] } = {}) {
        const alerts = alertsOf(form);
        if (!alerts) return;
        const box = document.createElement('div');
        box.className = `auth-alert auth-alert--${tone}`;
        box.setAttribute('role', tone === 'error' ? 'alert' : 'status');
        const text = document.createElement('p');
        text.textContent = message;
        box.append(text);
        if (list.length) {
            const items = document.createElement('ul');
            list.forEach((entry) => {
                const item = document.createElement('li');
                item.textContent = entry;
                items.append(item);
            });
            box.append(items);
        }
        if (hint?.url && isPanelUrl(hint.url)) {
            const link = document.createElement('a');
            link.href = hint.url;
            link.textContent = `${hint.label} →`;
            text.append(' ', link);
        }
        alerts.replaceChildren(box);
    }

    function clearErrors(form) {
        alertsOf(form)?.replaceChildren();
        form.querySelectorAll('.auth-field-error').forEach((error) => error.remove());
        form.querySelectorAll('[aria-invalid="true"]').forEach((field) => field.removeAttribute('aria-invalid'));
    }

    const findField = (form, key) => {
        const names = [key, `${key}[]`, key.replace(/\.\d+$/, '[]'), key.replace(/\.(\w+)/g, '[$1]')];
        for (const name of names) {
            const field = form.querySelector(`[name="${window.CSS?.escape ? CSS.escape(name) : name}"]`);
            if (field) return field;
        }
        return null;
    };

    /** Puts each message under its field; returns the messages that have no field on this form. */
    function showFieldErrors(form, errors) {
        const unplaced = [];
        let first = null;
        Object.entries(errors).forEach(([key, messages]) => {
            const message = [].concat(messages)[0];
            const field = findField(form, key);
            if (!field || field.type === 'hidden') {
                unplaced.push(message);
                return;
            }
            const holder = field.closest('.form-group, .terms-consent, fieldset') || field.parentElement;
            const id = `error-${key.replace(/[^\w-]/g, '-')}`;
            holder.querySelector(`#${id}`)?.remove();
            const note = document.createElement('p');
            note.className = 'auth-field-error';
            note.id = id;
            note.textContent = message;
            holder.append(note);
            field.setAttribute('aria-invalid', 'true');
            const described = (field.getAttribute('aria-describedby') || '').split(' ').filter((part) => part && part !== id);
            field.setAttribute('aria-describedby', [...described, id].join(' '));
            first ??= field;
        });

        if (first && form.wizard) {
            form.wizard.goTo(form.wizard.stepOf(first));
        }
        if (first) window.requestAnimationFrame(() => first.focus({ preventScroll: false }));
        return unplaced;
    }

    // Typing in a field clears its message.
    dialog.addEventListener('input', (event) => {
        const field = event.target.closest?.('[aria-invalid="true"]');
        if (!field || field.classList.contains('otp-input')) return;
        field.removeAttribute('aria-invalid');
        const holder = field.closest('.form-group, .terms-consent, fieldset');
        holder?.querySelectorAll('.auth-field-error').forEach((error) => error.remove());
    });

    // ---------------------------------------------------------------- submitting

    const setBusy = (form, button, on) => {
        if (on) form.dataset.busy = 'true';
        else delete form.dataset.busy;
        form.setAttribute('aria-busy', String(on));
        if (!button) return;
        button.disabled = on;
        button.classList.toggle('is-busy', on);
        if (on && button.dataset.busyLabel) button.setAttribute('aria-label', button.dataset.busyLabel);
        else button.removeAttribute('aria-label');
    };

    // address-selector.js switches off the "no province" choice while submitting; switch it back on after.
    const restoreProvince = (form) => form.querySelectorAll('[data-location="province"]').forEach((select) => {
        if (select.value === '__none__') select.disabled = false;
    });

    async function submitForm(form, submitter) {
        const button = submitter || form.querySelector('[type=submit]:not([hidden])');
        let payload;
        try { payload = new FormData(form, submitter || undefined); } catch { payload = new FormData(form); }
        restoreProvince(form);
        clearErrors(form);
        setBusy(form, button, true);

        let response;
        let data = {};
        try {
            response = await fetch(form.action, { method: 'POST', body: payload, headers: FORM_HEADERS, credentials: 'same-origin' });
            data = await response.json().catch(() => ({}));
        } catch {
            setBusy(form, button, false);
            showAlert(form, 'No connection. Check your internet and try again.');
            return;
        }

        if (response.ok && data.redirect) {
            // Resend code / reset link: show this panel again with the server's message.
            if (form.hasAttribute('data-auth-reload')) {
                await open(currentUrl, { history: 'none', reload: true });
                return;
            }
            if (isPanelUrl(data.redirect)) {
                await open(data.redirect, { history: 'replace', reload: true });
                return;
            }
            location.assign(data.redirect); // signed in: leave the button spinning while the page loads
            return;
        }

        setBusy(form, button, false);
        if (response.status === 419) {
            showAlert(form, 'This form expired. Reloading so you can try again…');
            window.setTimeout(() => location.reload(), 1500);
            return;
        }
        if (response.status === 429) {
            showAlert(form, data.message && !/^Too Many Attempts\.?$/i.test(data.message) ? data.message : 'Too many attempts. Please wait a minute, then try again.');
            return;
        }
        if (response.status === 422) {
            const unplaced = showFieldErrors(form, data.errors || {});
            if (form.wizard) {
                showAlert(form, 'Please check the highlighted details.', { list: unplaced });
            } else if (data.hint || unplaced.length) {
                showAlert(form, unplaced[0] || data.message, { hint: data.hint });
            }
            return;
        }
        showAlert(form, data.message || 'Something went wrong. Please try again.');
    }

    dialog.addEventListener('submit', (event) => {
        const form = event.target;
        // The email code form submits itself (email-verification.js); native forms load a page as usual.
        if (event.defaultPrevented || !form.matches('[data-auth-form]') || form.hasAttribute('data-auth-native')) return;
        event.preventDefault();
        if (form.dataset.busy) return;
        if (form.wizard) {
            restoreProvince(form);
            if (!form.wizard.isLast()) {
                form.wizard.advance(); // Enter on an earlier step moves on instead of sending
                return;
            }
            if (!form.wizard.validateAll()) return;
        }
        submitForm(form, event.submitter);
    });

    // ---------------------------------------------------------------- wiring

    // Show / hide password buttons in every panel.
    dialog.addEventListener('click', (event) => {
        const button = event.target.closest('[data-password]');
        if (!button) return;
        const input = dialog.querySelector(`#${button.dataset.password}`);
        if (!input) return;
        const visible = input.type === 'password';
        input.type = visible ? 'text' : 'password';
        button.textContent = visible ? 'Hide' : 'Show';
        button.setAttribute('aria-pressed', String(visible));
        button.setAttribute('aria-label', `${visible ? 'Hide' : 'Show'} ${input.id === 'password_confirmation' ? 'confirmation password' : 'password'}`);
    });

    // Links to sign-in pages anywhere on the page (and inside the modal) open their panel.
    document.addEventListener('click', (event) => {
        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        const link = event.target.closest?.('a[href]');
        if (!link || (link.target && link.target !== '_self') || link.hasAttribute('download') || link.hasAttribute('data-auth-native')) return;
        if (!isPanelUrl(link.href)) return;
        event.preventDefault();
        open(link.href, { source: link });
    });

    closeLink.addEventListener('click', (event) => {
        event.preventDefault();
        close();
    });

    dialog.addEventListener('cancel', (event) => {
        event.preventDefault();
        close();
    });

    // A click on the dimmed area closes; a drag that starts inside the form and ends outside does not.
    let pressedOutside = false;
    dialog.addEventListener('pointerdown', (event) => { pressedOutside = event.target === dialog; });
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog && pressedOutside) close();
        pressedOutside = false;
    });

    window.addEventListener('popstate', (event) => {
        if (ignorePop) {
            ignorePop = false;
            return;
        }
        if (event.state?.authModal) open(event.state.authModal, { history: 'none' });
        else if (dialog.open) close({ fromHistory: true });
    });

    // Opened by the server (a direct visit to /login and the like): the page underneath is the home page.
    if (dialog.hasAttribute('open')) {
        dialog.close();
        show();
        currentUrl = dialog.dataset.initialUrl || here();
        behindUrl = toUrl(closeLink.href).pathname;
        returnPath = '';
        baseTitle = 'ZAYLO';
        history.replaceState({ authModal: currentUrl, pushed: false, behind: behindUrl }, '', location.href);
        setupPanel();
    }
})();
