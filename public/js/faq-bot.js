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
    const JOKES = [
        "Why did the package go to school? Because it wanted to be a little more parcel-tial to learning! 📦😄",
        "Why do online shoppers love cats? Because cats love empty delivery boxes! 🐱📦",
        "What did the barcode say to the scanner? 'I've got a lot of lines, but you always read right through me!' 😂",
        "Why did the computer catch a cold? Because it left its Windows open! 💻🤧",
        "What do you call a fake noodle? An impasta! 🍝😆",
        "Why couldn't the bicycle stand up by itself? It was two tired! 🚲",
        "Why did the coffee file a police report? It got mugged! ☕🤣",
        "How do you organize a space party? You planet! 🚀🌌",
        "What is a computer's favorite snack? Microchips! 🤖🍟",
        "Why did the golfer bring two pairs of pants? In case he got a hole in one! ⛳😉",
        "What do you call cheese that isn't yours? Nacho cheese! 🧀😂"
    ];
    const FACTS = [
        "Did you know? The first item ever ordered online was a pizza from Pizza Hut back in 1994! 🍕",
        "Fun fact: Sea otters hold hands when they sleep so they don't drift away from each other! 🦦💕",
        "Did you know? Honey never spoils. Archaeologists have found pots of honey in ancient Egyptian tombs that are over 3,000 years old and still perfectly edible! 🍯",
        "Fun fact: Bubble wrap was originally invented in 1957 as textured 3D wallpaper before finding its true purpose in parcel protection! 🫧📦",
        "Did you know? The Philippines is an archipelago composed of more than 7,640 islands! 🇵🇭🏝️",
        "Neat fact: The shopping cart was invented in 1937 by Sylvan Goldman to help supermarket customers carry more groceries! 🛒"
    ];
    const SUPPORT_WORDS = /\b(support|agent|human|person|contact|complaint|dispute|scam|stolen|damaged|broken|error|bug|fraud|lost parcel|refund|issue|problem|help desk)\b/i;
    const SHOPPING_WORDS = /\b(sell|buy|store|shop|price|cost|how much|stock|inventory|cart|order|checkout|delivery|shipping|rider|courier|waybill|voucher|discount|promo|return|exchange|tracking)\b/i;
    const GENERAL_CHAT_REPLIES = [
        "That's neat! 😊 I'm always happy to chat with you. What else is on your mind?",
        "Sounds interesting! You can chat with me, ask for a joke or a fun fact, or ask about anything on ZAYLO.",
        "I love chatting with you! Tell me more, or let me know if you need help with shopping or orders!",
        "I hear you! A good conversation is always the highlight of my day. What else are you up to?"
    ];

    let jokeIndex = Math.floor(Math.random() * JOKES.length);
    let factIndex = Math.floor(Math.random() * FACTS.length);
    let lastTopic = null;

    const nextJoke = () => {
        const joke = JOKES[jokeIndex % JOKES.length];
        jokeIndex += 1;
        lastTopic = 'joke';
        return joke;
    };

    const nextFact = () => {
        const fact = FACTS[factIndex % FACTS.length];
        factIndex += 1;
        lastTopic = 'fact';
        return fact;
    };

    const CONVERSATION_RULES = [
        {
            pattern: /\b(hi|hello|hey|hiya|howdy|yo|kumusta|musta|good (morning|afternoon|evening)|morning|evening|greetings)\b/,
            mood: 'waving',
            reply: () => {
                lastTopic = 'greeting';
                return pick([
                    'Hi there! 👋 Great to see you! How\'s your day going?',
                    'Hello! 😊 Always a pleasure to chat. How can I help you today?',
                    'Hey! I\'m Zay. Looking for something on ZAYLO, or just stopping by to chat?',
                    'Kumusta! 👋 Ready to explore or chat—what\'s on your mind?'
                ]);
            }
        },
        {
            pattern: /\b(how are you|how r u|how('s|s) (it going|things|life|everything)|how do you do|how are you doing|how('s| is) your day|you (good|okay|alright)|are you (doing )?(good|fine|ok|well))\b/,
            mood: 'happy',
            reply: () => {
                lastTopic = 'how_are_you';
                return pick([
                    'I\'m doing fantastic, thanks for asking! Blinking happily and ready to help. How about you?',
                    'Feeling great and ready to roll! ⚡ How is your day going so far?',
                    'Pretty awesome! Just floating around ZAYLO. How are things on your side?'
                ]);
            }
        },
        {
            pattern: /\b(i('m| am| feel|m)? (so )?(good|fine|great|doing (good|great|well)|awesome|chillin|all good|okay|ok)|not bad|pretty good|feeling (good|great|awesome|well))\b/,
            mood: 'happy',
            reply: () => {
                lastTopic = 'feeling';
                return pick([
                    'Glad to hear that! 😊 Having a good day makes everything brighter. What are you up to today?',
                    'That\'s wonderful to hear! ✨ Let me know if you need any help exploring ZAYLO or just want to chat.',
                    'Awesome! It always puts a smile on my face to hear you\'re doing well. 😊'
                ]);
            }
        },
        {
            pattern: /\b(i('m| am| feel|m)? (so )?(sad|tired|exhausted|bored|feeling down|having a bad day|rough day|sick|unhappy|stressed|sleepy)|feeling (sad|down|tired|exhausted|blue))\b/,
            mood: 'confused',
            reply: () => {
                lastTopic = 'cheer_up';
                return pick([
                    'Aww, sending you warm virtual hugs! 🤗 Take it easy today. Would a silly joke help cheer you up?',
                    'I\'m sorry to hear that. 💙 Remember to take a break and breathe. I\'m right here if you want to chat or hear a joke!',
                    'Hang in there! Sometimes a quiet moment (or browsing cool things on ZAYLO) can help. Let me know if you need a smile!'
                ]);
            }
        },
        {
            pattern: /\b(what('s|s) up|sup|what are you doing|what r u doing|whatcha doing|what you doing|what are you up to)\b/,
            mood: 'happy',
            reply: () => {
                lastTopic = 'whats_up';
                return pick([
                    'Not much, just hanging out here in your browser, keeping an eye on packages! What about you?',
                    'Just floating around, blinking, and chatting with awesome people like you! What are you up to?',
                    'Monitoring the digital aisles of ZAYLO and enjoying our chat! What\'s happening in your world?'
                ]);
            }
        },
        {
            pattern: /\b(who are you|your name|what('s| is) your name|what are you|tell me about yourself|introduce yourself)\b/,
            mood: 'happy',
            reply: () => pick([
                'I\'m Zay, ZAYLO\'s little helper bot! I blink, follow your cursor, and know all about ZAYLO\'s orders, shipping, and returns. Plus, I love a good chat!',
                'I\'m Zay! Your friendly animated assistant on ZAYLO. I\'m here to guide you, answer questions, or just keep you company. 😊'
            ])
        },
        {
            pattern: /\b(who (made|created|built|programmed|coded|designed) you|who is your (creator|developer|maker|boss|father|mother|parent))\b/,
            mood: 'happy',
            reply: () => pick([
                'I was created by the talented ZAYLO engineering team! They gave me eyes to blink with, a warm smile, and lots of knowledge about the platform. 🚀',
                'The engineers at ZAYLO built me! They wanted everyone on ZAYLO to have a friendly helper right at their fingertips. ✨'
            ])
        },
        {
            pattern: /\b(are you (a )?(real|human|person|ai|robot|bot|sentient|alive)|are you an ai|are you real or fake)\b/,
            mood: 'happy',
            reply: () => pick([
                'I\'m a little virtual bot! Not a human, but I have a warm digital heart and I love chatting with you. 🤖✨',
                'I\'m 100% digital, crafted with code and love! While I\'m not human, our conversations are totally real. 😊'
            ])
        },
        {
            pattern: /\b(how old are you|what('s| is) your age|when is your birthday|when were you born)\b/,
            mood: 'happy',
            reply: () => pick([
                'In human years, I\'m quite young! In bot years, I\'ve already helped lots of people find their parcels and smile. 🎂',
                'I celebrate my birthday every time ZAYLO launches a new feature! Today feels like a celebration already. 🎉'
            ])
        },
        {
            pattern: /\b(where do you (live|stay)|where are you from|where is your home|where are you)\b/,
            mood: 'waving',
            reply: () => pick([
                'I live right here in your browser tab, floating neatly in the corner! But ZAYLO is proudly based in the Philippines. 🇵🇭',
                'Home for me is inside the ZAYLO servers, but right now I\'m happiest right here on your screen! 😊'
            ])
        },
        {
            pattern: /\b(what can you do|what do you do|how can you help|help me|can you help|what are your (features|skills)|can we (talk|chat)|talk to me|chat with me)\b/,
            mood: 'waving',
            reply: () => pick([
                'I can do lots of things! 💬 We can chat about anything, tell jokes, share fun facts, or I can answer questions about ZAYLO orders, delivery, vouchers, and returns. Try asking me anything!',
                'I\'m both a chat companion and a ZAYLO guide! Ask me for a joke, tell me how you\'re feeling, or ask how shipping works on ZAYLO. What would you like?'
            ])
        },
        {
            pattern: /\b(tell me a joke|tell a joke|say a joke|make me laugh|know any jokes|say something funny|joke|another joke|one more joke|funny)\b/,
            mood: 'happy',
            reply: () => nextJoke()
        },
        {
            pattern: /\b(tell me a (fun )?fact|fun fact|say a fact|know any facts|tell me something (interesting|cool)|did you know)\b/,
            mood: 'thinking',
            reply: () => nextFact()
        },
        {
            pattern: /\b(favorite|favourite) color\b/,
            mood: 'happy',
            reply: () => 'I love warm amber and deep indigo—just like ZAYLO\'s brand colors! 🎨 What\'s your favorite color?'
        },
        {
            pattern: /\b((favorite|favourite) food|what do you eat|do you eat)\b/,
            mood: 'happy',
            reply: () => 'I run on electricity and clean code! But if I could eat, I\'d definitely order chicken adobo and halo-halo. 🍧 What\'s your favorite food?'
        },
        {
            pattern: /\b(favorite|favourite) animal\b/,
            mood: 'happy',
            reply: () => 'Carrier pigeons—the original parcel couriers! 🕊️ And cats in delivery boxes are a close second. 📦🐱'
        },
        {
            pattern: /\b((favorite|favourite) (hobby|movie|music|song)|what do you like (to do)?|your hobbies)\b/,
            mood: 'happy',
            reply: () => 'I love following your cursor, watching parcels get delivered, and learning new things to chat with you about! What are your hobbies?'
        },
        {
            pattern: /\b(do you (sleep|ever sleep|rest)|are you (tired|sleepy)|go to sleep)\b/,
            mood: 'waving',
            reply: () => pick([
                'Bots don\'t need sleep, so I\'m here 24/7 whenever you drop by! Make sure you get plenty of rest yourself, though. 🌙',
                'Nope, I\'m powered by pure code and boundless enthusiasm! No nap needed. ⚡'
            ])
        },
        {
            pattern: /\b(you('re| are) (so |very |really )?(cute|adorable|sweet|smart|awesome|cool|the best|great|funny|nice|helpful)|good bot|best bot|love you|i love you|i like you|marry me)\b/,
            mood: 'happy',
            reply: () => pick([
                'Aww, you\'re making my pixels blush! 🥰 Thank you so much, you\'re wonderful yourself!',
                'That just made my day! ✨ Thank you for being so kind to a little bot like me.',
                'Thank you! Hearing that puts a big spark in my circuits! 😊'
            ])
        },
        {
            pattern: /\b(haha|hahaha|hehe|hehehe|lol|lmao|rofl|that('s|s) funny|good one)\b/,
            mood: 'happy',
            reply: () => pick([
                'Hehe, glad I could make you smile! 😄 Laughing makes the day better.',
                'Haha! That put a big smile on my face too. Anything else on your mind?'
            ])
        },
        {
            pattern: /\b(thanks|thank you|thx|ty|salamat|many thanks|appreciate it)\b/,
            mood: 'happy',
            reply: () => pick([
                'You\'re welcome! Anything else I can help with?',
                'Anytime! Always happy to help. Let me know if you need anything else! 😊',
                'Walang anuman! I\'m right here if you want to chat more. 👍'
            ])
        },
        {
            pattern: /\b(bye|goodbye|see you|see ya|gotta go|good night|goodnight|paalam|cya|take care)\b/,
            mood: 'waving',
            reply: () => pick([
                'Bye for now! Tap me anytime you need help. 👋',
                'Take care and have a wonderful day! See you soon! 👋✨'
            ])
        },
        {
            pattern: /\b(ok|okay|cool|nice|great|awesome|alright|got it|sounds good)\b$/,
            mood: 'happy',
            reply: () => 'Great! 😊 What\'s on your mind next?'
        },
        {
            pattern: /\b(yes|yeah|yep|sure|definitely|yup)\b$/,
            mood: 'happy',
            reply: () => {
                if (lastTopic === 'cheer_up') {
                    return `Here's one to cheer you up: ${nextJoke()}`;
                }
                return 'Awesome! Tell me more, or let me know what you\'d like to look into. 😊';
            }
        },
        {
            pattern: /\b(no|nope|nah|never mind|nothing|not really)\b$/,
            mood: 'happy',
            reply: () => 'No problem at all! Feel free to just relax or ask me anything whenever you\'re ready. 👍'
        },
        {
            pattern: /\b(sorry|my bad|oops|apologies)\b/,
            mood: 'happy',
            reply: () => 'No worries at all! Never any need to apologize here. We\'re all good! 😊'
        },
        {
            pattern: /\b(really|are you sure|seriously)\b/,
            mood: 'happy',
            reply: () => '100%! Honest bot\'s honor! 🤖✨'
        },
        {
            pattern: /\b(what time is it|what('s| is) the time|current time)\b/,
            mood: 'thinking',
            reply: () => {
                const now = new Date();
                const timeStr = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                return `According to your device, it's ${timeStr}! ⏰ Perfect time for browsing ZAYLO or taking a pleasant break.`;
            }
        },
        {
            pattern: /\b(what day is it|what('s| is) today('s)? (date|day)|current date)\b/,
            mood: 'thinking',
            reply: () => {
                const now = new Date();
                const dateStr = now.toLocaleDateString([], { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
                return `Today is ${dateStr}! 📅 Hope you're having a wonderful day!`;
            }
        },
        {
            pattern: /\b(what is zaylo|tell me about zaylo|about zaylo)\b/,
            mood: 'happy',
            reply: () => 'ZAYLO is a Philippine online shopping marketplace where buyers discover great products, sellers grow their shops, and riders deliver straight to your doorstep! 🛍️🇵🇭'
        },
        {
            pattern: /\b(another|another one|one more|more|tell me another)\b/,
            mood: 'happy',
            reply: () => (lastTopic === 'fact' ? nextFact() : nextJoke())
        },
        {
            pattern: /\b(why|why so|how come)\b$/,
            mood: 'happy',
            reply: () => 'That\'s one of the great mysteries of life! Or maybe just how the ZAYLO team coded me. 😄'
        }
    ];

    const getConversationalReply = (text) => {
        const normalized = normalize(text);
        const match = CONVERSATION_RULES.find(({ pattern }) => pattern.test(normalized));
        if (match) {
            return { mood: match.mood, reply: typeof match.reply === 'function' ? match.reply() : match.reply };
        }
        return null;
    };
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

        const normalized = normalize(question);
        const isSupportQuery = SUPPORT_WORDS.test(normalized);
        const ranked = data.items.map((entry) => ({ entry, points: score(entry, question) }))
            .filter(({ points }) => points > 0).sort((a, b) => b.points - a.points);

        // High confidence FAQ match, or explicit support query with a match
        if (ranked.length && (ranked[0].points >= 5 || (isSupportQuery && ranked[0].points >= 3))) {
            await answer(ranked[0].entry, Math.random() < 0.6 ? pick(OPENERS) : '');
            const related = ranked.slice(1, 3).filter(({ points }) => points >= 3).map(({ entry }) => entry);
            if (related.length) botMessage((bubble) => { bubble.append(node('p', '', 'You might also want to know:')); chips(bubble, related); });
            return;
        }

        // Conversational chat match
        const talk = getConversationalReply(question);
        if (talk) {
            await typeThen(Math.min(talk.reply.length * 6, 600), () => {
                setMood(talk.mood, 2400);
                botMessage((bubble) => bubble.append(node('p', '', talk.reply)));
            });
            return;
        }

        // Moderate FAQ match if not matching conversation
        if (ranked.length && ranked[0].points >= 3) {
            await answer(ranked[0].entry, Math.random() < 0.6 ? pick(OPENERS) : '');
            const related = ranked.slice(1, 3).filter(({ points }) => points >= 3).map(({ entry }) => entry);
            if (related.length) botMessage((bubble) => { bubble.append(node('p', '', 'You might also want to know:')); chips(bubble, related); });
            return;
        }

        // If asking about shopping, support, or having partial suggestions, guide to help
        if (isSupportQuery || SHOPPING_WORDS.test(normalized) || ranked.length > 0) {
            await typeThen(80, () => {
                setMood('confused', 2600);
                botMessage((bubble) => {
                    bubble.append(node('p', '', ranked.length ? 'Hmm, I\'m not sure I got that. 🤔 Did you mean one of these?' : 'Hmm, I don\'t know that one yet. 🤔'));
                    if (ranked.length) chips(bubble, ranked.slice(0, 3).map(({ entry }) => entry));
                    bubble.append(node('p', '', 'The ZAYLO team can help with anything I can\'t:'));
                    link(bubble, data.support, 'fa-life-ring');
                });
            });
            return;
        }

        // Open conversational fallback for casual chit-chat
        await typeThen(50, () => {
            setMood('happy', 2400);
            botMessage((bubble) => bubble.append(node('p', '', pick(GENERAL_CHAT_REPLIES))));
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
