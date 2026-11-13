// Refresh the daily dashboard at midnight in the Philippines, including after a sleeping tab resumes.
(() => {
    const day = () => new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Manila' }).format(new Date());
    const initialDay = day();
    const refresh = () => {
        if (day() !== initialDay) window.location.reload();
    };
    const offset = 8 * 60 * 60 * 1000;
    const dayLength = 24 * 60 * 60 * 1000;
    setTimeout(refresh, dayLength - ((Date.now() + offset) % dayLength) + 1000);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) refresh();
    });
})();
