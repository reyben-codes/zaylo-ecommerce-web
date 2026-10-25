// Print buttons ([data-print]) open the browser's print dialog; pages marked [data-auto-print] (the waybill label)
// open it as soon as they load. Buttons stay hidden without JavaScript, since they could not work.
(() => {
    document.querySelectorAll('[data-print]').forEach((button) => {
        button.hidden = false;
        button.addEventListener('click', () => window.print());
    });

    if (document.body.hasAttribute('data-auto-print')) {
        // Wait for the logo and barcode so they are on the printout.
        window.addEventListener('load', () => window.setTimeout(() => window.print(), 150));
    }
})();
