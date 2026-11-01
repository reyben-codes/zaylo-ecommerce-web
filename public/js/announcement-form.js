// Admin announcement form: "All of these" ticks every way of showing the announcement.
(() => {
    const all = document.querySelector('[data-announcement-channels-all]');
    if (!all) return;
    const channels = [...document.querySelectorAll('[data-announcement-channel]')];
    const sync = () => {
        const ticked = channels.filter((channel) => channel.checked).length;
        all.checked = ticked === channels.length;
        all.indeterminate = ticked > 0 && ticked < channels.length;
    };

    all.addEventListener('change', () => channels.forEach((channel) => { channel.checked = all.checked; }));
    channels.forEach((channel) => channel.addEventListener('change', sync));
    sync();
})();
