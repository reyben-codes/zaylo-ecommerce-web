(() => {
    // The help bot (partials/faq-bot): loads the account's questions and answers on first open, suggests them as
    // buttons, and matches typed questions by their keywords and words. Nothing fits → a link to Help & support.
    const root = document.querySelector('[data-faq-bot]');
    if (!root) return;

    const toggle = root.querySelector('[data-faq-toggle]');
    const panel = root.querySelector('[data-faq-panel]');
    const list = root.querySelector('[data-faq-messages]');
    const form = root.querySelector('[data-faq-form]');
    const input = root.querySelector('[data-faq-input]');
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const STOP_WORDS = new Set(['how', 'what', 'when', 'where', 'why', 'who', 'can', 'the', 'and', 'for', 'my', 'do', 'does', 'did', 'is', 'are', 'a', 'an', 'i', 'to', 'of', 'in', 'on', 'it', 'me', 'you', 'your', 'with', 'or', 'be', 'get', 'not', 'there', 'this', 'that']);
    let data = null;
    let closeTimer;

    const normalize = (text) => text.toLowerCase().replace(/[^a-z0-9'\-\s]/g, ' ').replace(/\s+/g, ' ').trim();
    const words = (text) => normalize(text).split(' ').filter((word) => word.length > 2 && !STOP_WORDS.has(word));

    // A matching keyword (whole phrase) counts most; shared words in the question count a little.
    const score = (item, text) => {
        const typed = ` ${normalize(text)} `;
        const typedWords = new Set(words(text));
        let total = 0;
        item.keywords.forEach((keyword) => {
            if (typed.includes(` ${normalize(keyword)} `) || typed.includes(` ${normalize(keyword)}`)) total += keyword.includes(' ') ? 4 : 3;
        });
        words(item.q).forEach((word) => { if (typedWords.has(word)) total += 1; });
        return total;
    };

    const add = (who, build) => {
        const item = document.createElement('li');
        item.className = `faq-bot__message faq-bot__message--${who}`;
        build(item);
        list.append(item);
        list.scrollTop = list.scrollHeight;
        return item;
    };

    const text = (parent, value, tag = 'p') => {
        const node = document.createElement(tag);
        node.textContent = value;
        parent.append(node);
        return node;
    };

    const link = (parent, { url, label }, icon = 'fa-arrow-right') => {
        const anchor = document.createElement('a');
        anchor.href = url;
        anchor.className = 'faq-bot__link';
        anchor.innerHTML = `<i class="fas ${icon}" aria-hidden="true"></i> `;
        anchor.append(label);
        parent.append(anchor);
    };

    const chips = (parent, items) => {
        const group = document.createElement('div');
        group.className = 'faq-bot__chips';
        items.forEach((entry) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = entry.q;
            button.addEventListener('click', () => ask(entry.q, entry));
            group.append(button);
        });
        parent.append(group);
    };

    const answer = (entry) => add('bot', (item) => {
        text(item, entry.a);
        if (entry.link) link(item, entry.link);
    });

    const ask = (question, chosen = null) => {
        add('user', (item) => text(item, question));
        if (chosen) {
            answer(chosen);
            return;
        }
        const ranked = data.items.map((entry) => ({ entry, points: score(entry, question) }))
            .filter(({ points }) => points > 0).sort((a, b) => b.points - a.points);
        if (ranked.length && ranked[0].points >= 3) {
            answer(ranked[0].entry);
            const related = ranked.slice(1, 3).filter(({ points }) => points >= 3).map(({ entry }) => entry);
            if (related.length) add('bot', (item) => { text(item, 'Related questions:'); chips(item, related); });
            return;
        }
        add('bot', (item) => {
            text(item, ranked.length ? 'I\'m not sure I understood. Did you mean one of these?' : 'Sorry, I don\'t have an answer for that yet.');
            if (ranked.length) chips(item, ranked.slice(0, 3).map(({ entry }) => entry));
            text(item, 'You can also ask the ZAYLO team directly:');
            link(item, data.support, 'fa-life-ring');
        });
    };

    const load = async () => {
        if (data) return true;
        try {
            const response = await fetch(root.dataset.faqUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (!response.ok) throw new Error(String(response.status));
            data = await response.json();
        } catch (error) {
            add('bot', (item) => text(item, 'Help could not load. Check your connection and open it again.'));
            return false;
        }
        add('bot', (item) => {
            text(item, data.greeting);
            chips(item, data.items.slice(0, 5));
        });
        return true;
    };

    const open = async () => {
        window.clearTimeout(closeTimer);
        panel.hidden = false;
        toggle.setAttribute('aria-expanded', 'true');
        // Next frame, so the opening transition runs from the closed state.
        window.requestAnimationFrame(() => panel.classList.add('is-open'));
        const loaded = await load();
        if (loaded) input.focus();
    };

    const close = () => {
        panel.classList.remove('is-open');
        toggle.setAttribute('aria-expanded', 'false');
        closeTimer = window.setTimeout(() => { panel.hidden = true; }, reducedMotion.matches ? 0 : 300);
        toggle.focus();
    };

    toggle.addEventListener('click', () => (toggle.getAttribute('aria-expanded') === 'true' ? close() : open()));
    root.querySelector('[data-faq-close]').addEventListener('click', close);
    panel.addEventListener('keydown', (event) => { if (event.key === 'Escape') close(); });
    form.addEventListener('submit', (event) => {
        event.preventDefault();
        const question = input.value.trim();
        if (!question || !data) return;
        input.value = '';
        ask(question);
    });
})();
