// One confirmation modal for the whole site. A form (or the button that submits it) opts in with:
//   data-confirm="Message"            the question to ask (required)
//   data-confirm-title="Title"        heading (default "Are you sure?")
//   data-confirm-button="Delete"      label of the confirming button (default "Confirm")
//   data-confirm-tone="danger"        red confirm button and warning icon for destructive actions
//   data-confirm-if="selector"        only ask when the form contains a match (e.g. ticked "remove" boxes)
// data-logout-confirm is the same modal with the log-out wording. Without JavaScript, or in browsers without
// <dialog>, forms submit normally.
(() => {
    'use strict';

    const LOGOUT = {
        title: 'Log out?',
        message: 'Are you sure you want to log out of your ZAYLO account?',
        button: 'Log out',
        icon: 'fa-arrow-right-from-bracket',
        tone: 'primary',
    };

    let dialog = null;
    let pending = null;
    let returnFocus = null;

    const build = () => {
        dialog = document.createElement('dialog');
        dialog.className = 'confirm-dialog';
        dialog.setAttribute('aria-labelledby', 'confirmDialogTitle');
        dialog.setAttribute('aria-describedby', 'confirmDialogMessage');
        dialog.innerHTML = `
            <div class="confirm-dialog__panel">
                <span class="confirm-dialog__icon" aria-hidden="true"><i class="fa-solid" data-confirm-icon></i></span>
                <h2 class="confirm-dialog__title" id="confirmDialogTitle"></h2>
                <p class="confirm-dialog__message" id="confirmDialogMessage"></p>
                <div class="confirm-dialog__actions">
                    <button type="button" class="confirm-dialog__button confirm-dialog__button--secondary" data-confirm-cancel>Cancel</button>
                    <button type="button" class="confirm-dialog__button confirm-dialog__button--primary" data-confirm-accept></button>
                </div>
            </div>`;

        dialog.querySelector('[data-confirm-cancel]').addEventListener('click', close);
        dialog.querySelector('[data-confirm-accept]').addEventListener('click', accept);
        // Escape: run close() so the exit animation and focus return still happen.
        dialog.addEventListener('cancel', (event) => {
            event.preventDefault();
            close();
        });
        // A click on the backdrop (outside the panel) cancels.
        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) close();
        });
        document.body.appendChild(dialog);
    };

    const optionsFor = (form, submitter) => {
        if (form.hasAttribute('data-logout-confirm')) return LOGOUT;
        // A button's own settings win over the form's, so one form can confirm "Reject" but not "Approve".
        const source = submitter?.hasAttribute('data-confirm') ? submitter : form;
        const tone = source.dataset.confirmTone === 'danger' ? 'danger' : 'primary';

        return {
            title: source.dataset.confirmTitle || 'Are you sure?',
            message: source.dataset.confirm,
            button: source.dataset.confirmButton || 'Confirm',
            icon: tone === 'danger' ? 'fa-triangle-exclamation' : 'fa-circle-question',
            tone,
        };
    };

    const needsConfirm = (form, submitter) => {
        if (form.hasAttribute('data-logout-confirm')) return true;
        const source = submitter?.hasAttribute('data-confirm') ? submitter : (form.hasAttribute('data-confirm') ? form : null);
        if (!source || !source.dataset.confirm) return false;
        const condition = source.dataset.confirmIf;

        return !condition || form.querySelector(condition) !== null;
    };

    const open = (form, submitter) => {
        if (!dialog) build();
        const options = optionsFor(form, submitter);
        pending = { form, submitter };
        returnFocus = submitter || document.activeElement;

        dialog.querySelector('[data-confirm-icon]').className = `fa-solid ${options.icon}`;
        dialog.querySelector('#confirmDialogTitle').textContent = options.title;
        dialog.querySelector('#confirmDialogMessage').textContent = options.message;
        const accept = dialog.querySelector('[data-confirm-accept]');
        accept.textContent = options.button;
        dialog.classList.toggle('confirm-dialog--danger', options.tone === 'danger');
        dialog.querySelectorAll('button').forEach((button) => { button.disabled = false; });

        dialog.classList.remove('is-closing');
        if (!dialog.open) dialog.showModal();
        // Start on Cancel so an accidental Enter does not confirm.
        dialog.querySelector('[data-confirm-cancel]').focus();
    };

    function close() {
        if (!dialog || !dialog.open || dialog.classList.contains('is-closing')) return;
        pending = null;
        const finish = () => {
            dialog.classList.remove('is-closing');
            dialog.close();
            if (returnFocus && typeof returnFocus.focus === 'function') returnFocus.focus();
            returnFocus = null;
        };

        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            finish();
            return;
        }
        dialog.classList.add('is-closing');
        let done = false;
        const onEnd = () => {
            if (done) return;
            done = true;
            dialog.removeEventListener('animationend', onEnd);
            finish();
        };
        dialog.addEventListener('animationend', onEnd);
        window.setTimeout(onEnd, 300);
    }

    function accept() {
        if (!pending) return;
        const { form, submitter } = pending;
        dialog.querySelectorAll('button').forEach((button) => { button.disabled = true; });
        form.dataset.confirmed = 'true';
        // requestSubmit keeps the clicked button's name/value and lets the form's other submit handlers run.
        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit(submitter && submitter.form === form ? submitter : undefined);
        } else {
            if (submitter?.name) {
                const value = document.createElement('input');
                Object.assign(value, { type: 'hidden', name: submitter.name, value: submitter.value });
                form.appendChild(value);
            }
            form.submit();
        }
    }

    // Capture phase, so this runs before any other submit handler on the form.
    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || typeof HTMLDialogElement !== 'function') return;
        if (form.dataset.confirmed === 'true') {
            delete form.dataset.confirmed;
            return;
        }
        if (!needsConfirm(form, event.submitter)) return;

        event.preventDefault();
        event.stopImmediatePropagation();
        open(form, event.submitter);
    }, true);
})();
