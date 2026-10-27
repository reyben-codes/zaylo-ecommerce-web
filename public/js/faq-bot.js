(() => {
    // Zay, the help bot (partials/faq-bot). Zay blinks now and then, follows the pointer with its eyes, "types"
    // before answering and changes its face with the conversation. Answers come from the account's questions
    // (FaqController), matched by keywords and words; small talk gets a friendly reply, and nothing fitting → a link
    // to Help & support.
    const root = document.querySelector('[data-faq-bot]');
    if (!root) return;

    const toggle = root.querySelector('[data-faq-toggle]');
    const panel = root.querySelector('[data-faq-panel]');
    const list = root.querySelector('[data-faq-messages]');
    const form = root.querySelector('[data-faq-form]');
    const input = root.querySelector('[data-faq-input]');
    const status = root.querySelector('[data-faq-status]');
    const teaser = root.querySelector('[data-faq-teaser]');
    const miniTemplate = root.querySelector('[data-faq-mini]');
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const STOP_WORDS = new Set(['how', 'what', 'when', 'where', 'why', 'who', 'can', 'the', 'and', 'for', 'my', 'do', 'does', 'did', 'is', 'are', 'a', 'an', 'i', 'to', 'of', 'in', 'on', 'it', 'me', 'you', 'your', 'with', 'or', 'be', 'get', 'not', 'there', 'this', 'that']);
    const OPENERS = ['Good question!', 'Sure thing!', 'Here\'s what I know:', 'Happy to help!', 'Got it!'];
    const SMALL_TALK = [
        { pattern: /\b(hi|hello|hey|hiya|kumusta|musta|good (morning|afternoon|evening))\b/, mood: 'waving', reply: () => 'Hi there! 👋 What can I help you with today?' },
        { pattern: /\b(thanks|thank you|thx|ty|salamat)\b/, mood: 'happy', reply: () => 'You\'re welcome! Anything else I can help with?' },
        { pattern: /\b(bye|goodbye|see you|paalam)\b/, mood: 'waving', reply: () => 'Bye for now! Tap me anytime you need help. 👋' },
        { pattern: /\b(who are you|your name|are you (a )?(bot|robot|human))\b/, mood: 'happy', reply: () => 'I\'m Zay, ZAYLO\'s little helper bot. I know the answers to common questions, and I can point you to the ZAYLO team for everything else.' },
        { pattern: /\b(ok|okay|cool|nice|great|awesome)\b$/, mood: 'happy', reply: () => 'Great! 😊 Ask me anything else whenever you like.' },
    ];
    let data = null;
    let closeTimer;
    let moodTimer;
    let busy = false;

    const later = (ms) => new Promise((resolve) => window.setTimeout(resolve, reducedMotion.matches ? Math.min(ms, 250) : ms));
    const pick = (items) => items[Math.floor(Math.random() * items.length)];

    // ---------------------------------------------------------------- Zay's face

    const faces = () => root.querySelectorAll('[data-zay]');

    /** Every Zay on the page blinks on its own rhythm, sometimes twice in a row. */
    const blinkLoop = (zay) => {
        const blink = () => {
            zay.classList.add('is-blinking');
            window.setTimeout(() => zay.classList.remove('is-blinking'), 140);
        };
        const next = () => window.setTimeout(() => {
            if (!document.hidden && zay.isConnected) {
                blink();
                if (Math.random() < 0.2) window.setTimeout(blink, 260);
            }
            if (zay.isConnected) next();
        }, 2200 + Math.random() * 4200);
        next();
    };

    /** Zay's mood for a while ([ms]), then back to idle. */
    const setMood = (mood, ms = 0) => {
        window.clearTimeout(moodTimer);
        faces().forEach((zay) => { zay.dataset.mood = mood; });
        if (ms) moodTimer = window.setTimeout(() => setMood('idle'), ms);
    };

    /** The eyes follow the pointer a little. */
    let looking = false;
    document.addEventListener('pointermove', (event) => {
        if (reducedMotion.matches || looking) return;
        looking = true;
        window.requestAnimationFrame(() => {
            looking = false;
            faces().forEach((zay) => {
                const box = zay.getBoundingClientRect();
                if (!box.width) return;
                const dx = event.clientX - (box.left + box.width / 2);
                const dy = event.clientY - (box.top + box.height / 2);
                const distance = Math.max(1, Math.hypot(dx, dy));
                const reach = Math.min(1, distance / 240) * 1.8;
                zay.style.setProperty('--look-x', `${(dx / distance) * reach}px`);
                zay.style.setProperty('--look-y', `${(dy / distance) * reach}px`);
            });
        });
    }, { passive: true });

    faces().forEach(blinkLoop);

    // ------------------------------------------------------------------ Matching

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

    // ------------------------------------------------------------------ Messages

    const node = (tag, className, text) => {
        const element = document.createElement(tag);
        if (className) element.className = className;
        if (text !== undefined) element.textContent = text;
        return element;
    };

    const scrollDown = () => { list.scrollTop = list.scrollHeight; };

    /** A message from Zay: its little face beside a bubble the [build] callback fills. */
    const botMessage = (build) => {
        const item = node('li', 'faq-bot__message faq-bot__message--bot');
        const mini = miniTemplate.content.firstElementChild.cloneNode(true);
        mini.classList.add('faq-bot__mini');
        const bubble = node('div', 'faq-bot__bubble');
        build(bubble);
        item.append(mini, bubble);
        list.append(item);
        blinkLoop(mini);
        scrollDown();
        return item;
    };

    const userMessage = (text) => {
        const item = node('li', 'faq-bot__message faq-bot__message--user');
        item.append(node('div', 'faq-bot__bubble', text));
        list.append(item);
        scrollDown();
    };

    const link = (parent, { url, label }, icon = 'fa-arrow-right') => {
        const anchor = node('a', 'faq-bot__link');
        anchor.href = url;
        anchor.innerHTML = `<i class="fas ${icon}" aria-hidden="true"></i> `;
        anchor.append(label);
        parent.append(anchor);
    };

    const chips = (parent, items) => {
        const group = node('div', 'faq-bot__chips');
        items.forEach((entry) => {
            const button = node('button', '', entry.q);
            button.type = 'button';
            button.addEventListener('click', () => ask(entry.q, entry));
            group.append(button);
        });
        parent.append(group);
    };

    /** Zay "types" for a moment (longer for longer replies) before [build] runs. */
    const typeThen = async (length, build) => {
        busy = true;
        setMood('thinking');
        if (status) status.textContent = 'Zay is typing…';
        const typing = botMessage((bubble) => {
            bubble.classList.add('faq-bot__typing');
            bubble.setAttribute('aria-label', 'Zay is typing');
            bubble.append(node('span'), node('span'), node('span'));
        });
        await later(450 + Math.min(length * 9, 1100));
        typing.remove();
        if (status) status.textContent = 'Online · usually answers instantly';
        busy = false;
        build();
    };

    const answer = (entry, opener = '') => typeThen(entry.a.length, () => {
        setMood('happy', 2200);
        botMessage((bubble) => {
            if (opener) bubble.append(node('strong', 'faq-bot__opener', opener));
            bubble.append(node('p', '', entry.a));
            if (entry.link) link(bubble, entry.link);
        });
    });

    const ask = async (question, chosen = null) => {
        if (busy) return;
        userMessage(question);
        if (chosen) {
            await answer(chosen);
            return;
        }
        const talk = SMALL_TALK.find(({ pattern }) => pattern.test(normalize(question)));
        const ranked = data.items.map((entry) => ({ entry, points: score(entry, question) }))
            .filter(({ points }) => points > 0).sort((a, b) => b.points - a.points);

        if (ranked.length && ranked[0].points >= 3) {
            await answer(ranked[0].entry, Math.random() < 0.6 ? pick(OPENERS) : '');
            const related = ranked.slice(1, 3).filter(({ points }) => points >= 3).map(({ entry }) => entry);
            if (related.length) botMessage((bubble) => { bubble.append(node('p', '', 'You might also want to know:')); chips(bubble, related); });
            return;
        }
        if (talk) {
            await typeThen(40, () => {
                setMood(talk.mood, 2400);
                botMessage((bubble) => bubble.append(node('p', '', talk.reply())));
            });
            return;
        }
        await typeThen(80, () => {
            setMood('confused', 2600);
            botMessage((bubble) => {
                bubble.append(node('p', '', ranked.length ? 'Hmm, I\'m not sure I got that. 🤔 Did you mean one of these?' : 'Hmm, I don\'t know that one yet. 🤔'));
                if (ranked.length) chips(bubble, ranked.slice(0, 3).map(({ entry }) => entry));
                bubble.append(node('p', '', 'The ZAYLO team can help with anything I can\'t:'));
                link(bubble, data.support, 'fa-life-ring');
            });
        });
    };

    // ---------------------------------------------------------- Opening and closing

    const load = async () => {
        if (data) return true;
        try {
            const response = await fetch(root.dataset.faqUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (!response.ok) throw new Error(String(response.status));
            data = await response.json();
        } catch (error) {
            setMood('confused', 2600);
            botMessage((bubble) => bubble.append(node('p', '', 'Oops, I couldn\'t load my answers. Check your connection and open me again.')));
            return false;
        }
        setMood('waving', 1600);
        await typeThen(60, () => botMessage((bubble) => {
            bubble.append(node('p', '', data.greeting));
            chips(bubble, data.items.slice(0, 5));
        }));
        return true;
    };

    const hideTeaser = () => {
        if (teaser) teaser.hidden = true;
        try { sessionStorage.setItem('zay-teaser-seen', '1'); } catch (error) { /* storage unavailable */ }
    };

    const open = async () => {
        window.clearTimeout(closeTimer);
        hideTeaser();
        panel.hidden = false;
        toggle.setAttribute('aria-expanded', 'true');
        // Next frame, so the opening transition runs from the closed state.
        window.requestAnimationFrame(() => panel.classList.add('is-open'));
        input.focus();
        await load();
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
        if (!question || !data || busy) return;
        input.value = '';
        ask(question);
    });

    // Once a visit, Zay waves and offers help after a few seconds.
    let teaserSeen = false;
    try { teaserSeen = sessionStorage.getItem('zay-teaser-seen') === '1'; } catch (error) { /* storage unavailable */ }
    if (teaser && !teaserSeen) {
        root.querySelector('[data-faq-teaser-close]').addEventListener('click', hideTeaser);
        window.setTimeout(() => {
            if (toggle.getAttribute('aria-expanded') === 'true') return;
            teaser.hidden = false;
            setMood('waving', 1800);
            window.setTimeout(() => { if (!teaser.hidden) hideTeaser(); }, 9000);
        }, 6000);
    }
})();
