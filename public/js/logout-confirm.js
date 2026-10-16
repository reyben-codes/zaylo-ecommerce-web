// Asks for confirmation before submitting any form marked with data-logout-confirm.
// Without JavaScript the form submits normally.
(function () {
    'use strict';

    var dialog = null;
    var pendingForm = null;
    var returnFocus = null;

    function buildDialog() {
        dialog = document.createElement('dialog');
        dialog.className = 'logout-confirm';
        dialog.setAttribute('aria-labelledby', 'logoutConfirmTitle');
        dialog.setAttribute('aria-describedby', 'logoutConfirmMessage');
        dialog.innerHTML =
            '<div class="logout-confirm__panel">' +
                '<span class="logout-confirm__icon" aria-hidden="true"><i class="fa-solid fa-arrow-right-from-bracket"></i></span>' +
                '<h2 class="logout-confirm__title" id="logoutConfirmTitle">Log out?</h2>' +
                '<p class="logout-confirm__message" id="logoutConfirmMessage">Are you sure you want to log out of your ZAYLO account?</p>' +
                '<div class="logout-confirm__actions">' +
                    '<button type="button" class="logout-confirm__button logout-confirm__button--secondary" data-logout-cancel>Cancel</button>' +
                    '<button type="button" class="logout-confirm__button logout-confirm__button--primary" data-logout-accept>Log out</button>' +
                '</div>' +
            '</div>';

        dialog.querySelector('[data-logout-cancel]').addEventListener('click', close);
        dialog.querySelector('[data-logout-accept]').addEventListener('click', accept);

        // Escape key: let our close() run so the exit animation and focus restore apply.
        dialog.addEventListener('cancel', function (event) {
            event.preventDefault();
            close();
        });

        // Clicking the backdrop (outside the panel) cancels.
        dialog.addEventListener('click', function (event) {
            if (event.target === dialog) close();
        });

        document.body.appendChild(dialog);
    }

    function open(form, trigger) {
        if (!dialog) buildDialog();
        pendingForm = form;
        returnFocus = trigger || document.activeElement;
        dialog.classList.remove('is-closing');
        if (!dialog.open) dialog.showModal();
        dialog.querySelector('[data-logout-cancel]').focus();
    }

    function close() {
        if (!dialog || !dialog.open || dialog.classList.contains('is-closing')) return;
        pendingForm = null;

        var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        var finish = function () {
            dialog.classList.remove('is-closing');
            dialog.close();
            if (returnFocus && typeof returnFocus.focus === 'function') returnFocus.focus();
            returnFocus = null;
        };

        if (reduceMotion) {
            finish();
            return;
        }

        dialog.classList.add('is-closing');
        var done = false;
        var onEnd = function () {
            if (done) return;
            done = true;
            dialog.removeEventListener('animationend', onEnd);
            finish();
        };
        dialog.addEventListener('animationend', onEnd);
        window.setTimeout(onEnd, 300);
    }

    function accept() {
        var form = pendingForm;
        if (!form) return;
        dialog.querySelectorAll('button').forEach(function (button) { button.disabled = true; });
        // form.submit() skips the submit event, so this does not re-open the dialog.
        form.submit();
    }

    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-logout-confirm')) return;
        if (typeof HTMLDialogElement !== 'function') return; // Very old browsers: submit normally.

        event.preventDefault();
        open(form, event.submitter);
    });
})();
