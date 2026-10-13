(() => {
    document.querySelectorAll('.review-rating').forEach(rating => {
        const guide = rating.querySelector('.review-rating-guide');
        const update = () => {
            const selected = rating.querySelector('input[name="rating"]:checked');
            guide.textContent = selected
                ? `${selected.value} ${selected.value === '1' ? 'star' : 'stars'} — ${selected.dataset.ratingLabel}`
                : 'Select a star to rate your purchase.';
        };
        rating.addEventListener('change', update);
        rating.closest('form')?.addEventListener('reset', () => setTimeout(update, 0));
        update();
    });
})();
