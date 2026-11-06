// Email code entry in the sign-in modal's last sign-up step (/email/verify, every role).
// One real input sits over six display boxes, so typing, pasting and the browser's one-time-code
// autofill all work. The sixth digit submits automatically in the background; the boxes then show
// checking, success or error, and the page follows the server's redirect once verified.
// Without JavaScript the plain input and the Verify button still submit the form normally.
(() => {
    const init = (scope) => {
        const form = scope.querySelector('#verificationForm');
        const field = form?.querySelector('[data-otp-field]');
        if (!form || !field || !window.fetch || form.dataset.otpBound) return;
        form.dataset.otpBound = 'true';

        const LENGTH = 6;
        const input = field.querySelector('input');
        const slots = [...field.querySelectorAll('.otp-slot')];
        const status = form.querySelector('#otp-status');
        const button = form.querySelector('#verify-button');
        const pasteButton = form.querySelector('[data-otp-paste]');
        const serverAlert = scope.querySelector('#verification-error');
        const buttonLabel = button.innerHTML;
        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

        let state = 'idle';
        let busy = false;
        let shown = '';
        let clearTimer = 0;

        field.classList.add('is-enhanced');

        /** Pulls a 6-digit code out of pasted or copied text, e.g. "Your code is 123 456." */
        const extractCode = (text) => {
            const grouped = String(text || '').match(/(?:^|\D)(\d(?:[\s-]?\d){5})(?!\d)/);
            return (grouped ? grouped[1] : String(text || '')).replace(/\D/g, '').slice(0, LENGTH);
        };

        const setState = (next) => {
            state = next;
            field.dataset.state = next;
            form.setAttribute('aria-busy', next === 'verifying' ? 'true' : 'false');
            if (next === 'error') input.setAttribute('aria-invalid', 'true');
            else input.removeAttribute('aria-invalid');
        };

        const announce = (message, tone = '') => {
            status.textContent = message;
            if (tone) status.dataset.tone = tone;
            else delete status.dataset.tone;
        };

        const render = () => {
            const value = input.value;
            const focused = document.activeElement === input;
            const locked = state === 'verifying' || state === 'success';
            slots.forEach((slot, index) => {
                const digit = value[index] || '';
                const wasEmpty = !shown[index];
                slot.querySelector('.otp-digit').textContent = digit;
                slot.classList.toggle('is-filled', digit !== '');
                slot.classList.toggle('is-active', focused && !locked && index === Math.min(value.length, LENGTH - 1));
                if (digit && wasEmpty && !reduceMotion.matches) {
                    slot.classList.remove('is-popped');
                    void slot.offsetWidth; // restart the pop animation
                    slot.classList.add('is-popped');
                }
            });
            shown = value;
        };

        const keepCaretAtEnd = () => {
            const end = input.value.length;
            try { input.setSelectionRange(end, end); } catch { /* some autofill states reject this */ }
        };

        const setButton = (mode) => {
            button.classList.toggle('is-success', mode === 'success');
            button.disabled = mode !== 'ready';
            if (mode === 'verifying') button.innerHTML = '<span class="otp-spinner" aria-hidden="true"></span>Verifying…';
            else if (mode === 'success') button.innerHTML = '<i class="fa-solid fa-check" aria-hidden="true"></i>&nbsp; Verified';
            else button.innerHTML = buttonLabel;
        };

        const unlock = () => {
            busy = false;
            input.readOnly = false;
            setButton('ready');
            if (pasteButton) pasteButton.disabled = false;
        };

        const fail = (message, { keepCode = false } = {}) => {
            unlock();
            setState('error');
            announce(message, 'error');
            if (serverAlert) serverAlert.hidden = true;
            render();
            window.clearTimeout(clearTimer);
            if (keepCode) {
                input.focus();
                return;
            }
            // Let the shake play, then empty the boxes so the next try starts clean.
            clearTimer = window.setTimeout(() => {
                field.classList.add('is-clearing');
                window.setTimeout(() => {
                    field.classList.remove('is-clearing');
                    input.value = '';
                    render();
                    input.focus();
                }, reduceMotion.matches ? 0 : 180);
            }, reduceMotion.matches ? 250 : 650);
        };

        const verify = async () => {
            if (busy) return;
            if (input.value.length !== LENGTH) {
                fail('Enter all six digits from your email.', { keepCode: true });
                return;
            }

            busy = true;
            window.clearTimeout(clearTimer);
            input.readOnly = true;
            if (pasteButton) pasteButton.disabled = true;
            setState('verifying');
            setButton('verifying');
            announce('Checking your code…');
            render();

            let response;
            let data = {};
            try {
                response = await fetch(form.action, {
                    method: 'POST',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: new FormData(form),
                    credentials: 'same-origin',
                });
                data = await response.json().catch(() => ({}));
            } catch {
                fail('No connection. Check your internet, then tap Verify to try again.', { keepCode: true });
                return;
            }

            if (response.ok && data.redirect) {
                setState('success');
                setButton('success');
                announce('Email verified! Taking you to your account…', 'success');
                render();
                window.setTimeout(() => window.location.assign(data.redirect), reduceMotion.matches ? 400 : 1100);
                return;
            }
            if (response.status === 419) {
                fail('This page expired. Reloading so you can try again…', { keepCode: true });
                window.setTimeout(() => window.location.reload(), 1500);
                return;
            }
            if (response.status === 409 && data.redirect) {
                fail(data.message, { keepCode: true });
                window.setTimeout(() => window.location.assign(data.redirect), 2200);
                return;
            }
            if (response.status === 429) {
                fail('Too many tries in a row. Please wait a minute, then enter the code again.');
                return;
            }
            fail(data.errors?.otp?.[0] || data.message || 'We could not check your code. Please try again.');
        };

        const accept = (code) => {
            if (busy) return;
            input.value = code;
            if (state === 'error') {
                window.clearTimeout(clearTimer);
                field.classList.remove('is-clearing');
                setState('typing');
                announce('');
            }
            render();
            keepCaretAtEnd();
            if (code.length === LENGTH) verify();
        };

        input.addEventListener('input', () => accept(input.value.replace(/\D/g, '').slice(0, LENGTH)));

        input.addEventListener('paste', (event) => {
            const text = event.clipboardData?.getData('text');
            if (text == null) return;
            event.preventDefault();
            const code = extractCode(text);
            if (code) accept(code);
            else announce('That doesn’t look like a 6-digit code.', 'error');
        });

        input.addEventListener('keydown', (event) => {
            // Digits always go in order, so moving the caret around would only confuse the boxes.
            if (['ArrowLeft', 'ArrowRight', 'Home', 'End', 'ArrowUp', 'ArrowDown'].includes(event.key)) event.preventDefault();
        });

        ['focus', 'click'].forEach((type) => input.addEventListener(type, () => {
            if (state === 'idle') setState('typing');
            render();
            window.requestAnimationFrame(keepCaretAtEnd);
        }));

        input.addEventListener('blur', () => {
            if (state === 'typing') setState('idle');
            render();
        });

        form.addEventListener('submit', (event) => {
            event.preventDefault();
            verify();
        });

        // Reads the code the person copied from the email (the browser may ask for permission first).
        if (pasteButton && navigator.clipboard?.readText) {
            pasteButton.hidden = false;
            pasteButton.addEventListener('click', async () => {
                try {
                    const code = extractCode(await navigator.clipboard.readText());
                    if (code.length === LENGTH) {
                        input.focus();
                        accept(code);
                    } else {
                        announce('No 6-digit code on your clipboard. Copy the code from the email first.', 'error');
                        input.focus();
                    }
                } catch {
                    announce('Clipboard access was blocked. Tap the boxes and paste the code instead.', 'error');
                    input.focus();
                }
            });
        }

        // Coming back with the browser's Back button must not leave the page stuck on "Verifying…".
        window.addEventListener('pageshow', (event) => {
            if (!event.persisted || !form.isConnected) return;
            unlock();
            input.value = '';
            setState('idle');
            announce('');
            render();
        });

        setState(document.activeElement === input ? 'typing' : 'idle');
        render();
    };

    init(document);
    // The sign-in modal (public/js/auth-modal.js) loads this form after the page, then announces it.
    document.addEventListener('zaylo:content', (event) => init(event.target));
})();
