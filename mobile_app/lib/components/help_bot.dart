import 'dart:async';
import 'dart:math' as math;

import 'package:flutter/material.dart';

import 'package:zaylo_mobile/api/api_client.dart';
import 'package:zaylo_mobile/components/feedback.dart';
import 'package:zaylo_mobile/components/zay_avatar.dart';
import 'package:zaylo_mobile/models/support_models.dart';
import 'package:zaylo_mobile/theme/zaylo_theme.dart';

/// Zay, the help bot, as on the website: answers common questions from the
/// server's list (GET /mobile/faq) after a short "typing" pause, chats back to
/// hellos and thank-yous, changes its face with the conversation, and offers
/// Help & support when nothing fits.
class HelpBotScreen extends StatefulWidget {
  const HelpBotScreen({super.key, required this.api, required this.onContactSupport});

  final ApiClient api;

  /// Opens the chat with the ZAYLO team.
  final VoidCallback onContactSupport;

  @override
  State<HelpBotScreen> createState() => _HelpBotScreenState();
}

/// One bubble: the person's question, Zay's reply (with optional suggested
/// questions and a support button), or Zay typing.
class _Message {
  const _Message.user(this.text)
      : fromBot = false,
        opener = null,
        suggestions = const [],
        offerSupport = false,
        typing = false;

  const _Message.bot(this.text, {this.opener, this.suggestions = const [], this.offerSupport = false})
      : fromBot = true,
        typing = false;

  const _Message.typing()
      : text = '',
        fromBot = true,
        opener = null,
        suggestions = const [],
        offerSupport = false,
        typing = true;

  final String text;
  final bool fromBot;
  final String? opener;
  final List<FaqItem> suggestions;
  final bool offerSupport;
  final bool typing;
}

/// Hellos, thank-yous and goodbyes, answered in kind.
class _SmallTalk {
  const _SmallTalk(this.pattern, this.mood, this.reply);

  final RegExp pattern;
  final ZayMood mood;
  final String reply;
}

class _HelpBotScreenState extends State<HelpBotScreen> {
  static final _random = math.Random();
  static const _openers = ['Good question!', 'Sure thing!', "Here's what I know:", 'Happy to help!', 'Got it!'];
  static const _jokes = [
    'Why did the package go to school? Because it wanted to be a little more parcel-tial to learning! 📦😄',
    'Why do online shoppers love cats? Because cats love empty delivery boxes! 🐱📦',
    "What did the barcode say to the scanner? 'I've got a lot of lines, but you always read right through me!' 😂",
    'Why did the computer catch a cold? Because it left its Windows open! 💻🤧',
    'What do you call a fake noodle? An impasta! 🍝😆',
    'Why couldn\'t the bicycle stand up by itself? It was two tired! 🚲',
    'Why did the coffee file a police report? It got mugged! ☕🤣',
    'How do you organize a space party? You planet! 🚀🌌',
    'What is a computer\'s favorite snack? Microchips! 🤖🍟',
    'Why did the golfer bring two pairs of pants? In case he got a hole in one! ⛳😉',
    'What do you call cheese that isn\'t yours? Nacho cheese! 🧀😂',
  ];
  static const _facts = [
    'Did you know? The first item ever ordered online was a pizza from Pizza Hut back in 1994! 🍕',
    'Fun fact: Sea otters hold hands when they sleep so they don\'t drift away from each other! 🦦💕',
    'Did you know? Honey never spoils. Archaeologists have found pots of honey in ancient Egyptian tombs that are over 3,000 years old and still perfectly edible! 🍯',
    'Fun fact: Bubble wrap was originally invented in 1957 as textured 3D wallpaper before finding its true purpose in parcel protection! 🫧📦',
    'Did you know? The Philippines is an archipelago composed of more than 7,640 islands! 🇵🇭🏝️',
    'Neat fact: The shopping cart was invented in 1937 by Sylvan Goldman to help supermarket customers carry more groceries! 🛒',
  ];
  static final _supportWords = RegExp(r'\b(support|agent|human|person|contact|complaint|dispute|scam|stolen|damaged|broken|error|bug|fraud|lost parcel|refund|issue|problem|help desk)\b', caseSensitive: false);
  static final _shoppingWords = RegExp(r'\b(sell|buy|store|shop|price|cost|how much|stock|inventory|cart|order|checkout|delivery|shipping|rider|courier|waybill|voucher|discount|promo|return|exchange|tracking)\b', caseSensitive: false);
  static const _generalChatReplies = [
    "That's neat! 😊 I'm always happy to chat with you. What else is on your mind?",
    'Sounds interesting! You can chat with me, ask for a joke or a fun fact, or ask about anything on ZAYLO.',
    'I love chatting with you! Tell me more, or let me know if you need help with shopping or orders!',
    'I hear you! A good conversation is always the highlight of my day. What else are you up to?',
  ];

  int _jokeIndex = 0;
  int _factIndex = 0;
  String? _lastTopic;

  late Future<FaqData> _faq = widget.api.faq();
  final _messages = <_Message>[];
  final _input = TextEditingController();
  final _scroll = ScrollController();
  FaqData? _data;
  ZayMood _mood = ZayMood.waving;
  bool _typing = false;
  Timer? _moodTimer;

  @override
  void dispose() {
    _moodTimer?.cancel();
    _input.dispose();
    _scroll.dispose();
    super.dispose();
  }

  /// Zay's face for a while, then back to idle.
  void _feel(ZayMood mood, {Duration? forHowLong}) {
    _moodTimer?.cancel();
    setState(() => _mood = mood);
    if (forHowLong != null) {
      _moodTimer = Timer(forHowLong, () {
        if (mounted) setState(() => _mood = ZayMood.idle);
      });
    }
  }

  void _start(FaqData data) {
    if (_data != null) return;
    _data = data;
    WidgetsBinding.instance.addPostFrameCallback((_) => _reply(
          60,
          _Message.bot(data.greeting, suggestions: data.items.take(5).toList()),
          ZayMood.waving,
        ));
  }

  void _add(_Message message) {
    setState(() => _messages.add(message));
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (_scroll.hasClients) {
        _scroll.animateTo(_scroll.position.maxScrollExtent,
            duration: const Duration(milliseconds: 280), curve: Curves.easeOut);
      }
    });
  }

  /// Zay "types" for a moment (longer for longer replies), then sends [message].
  Future<void> _reply(int length, _Message message, ZayMood mood, {_Message? followUp}) async {
    final calm = MediaQuery.maybeDisableAnimationsOf(context) ?? false;
    setState(() => _typing = true);
    _feel(ZayMood.thinking);
    _add(const _Message.typing());
    await Future<void>.delayed(Duration(milliseconds: calm ? 250 : 450 + math.min(length * 9, 1100)));
    if (!mounted) return;
    setState(() {
      _messages.removeWhere((item) => item.typing);
      _typing = false;
    });
    _feel(mood, forHowLong: const Duration(milliseconds: 2400));
    _add(message);
    if (followUp != null) _add(followUp);
  }

  String _nextJoke() {
    final joke = _jokes[_jokeIndex % _jokes.length];
    _jokeIndex++;
    _lastTopic = 'joke';
    return joke;
  }

  String _nextFact() {
    final fact = _facts[_factIndex % _facts.length];
    _factIndex++;
    _lastTopic = 'fact';
    return fact;
  }

  (String, ZayMood)? _getConversationalReply(String text) {
    final t = text.trim();

    // Contextual follow-up
    if (RegExp(r'\b(another|another one|one more|more|tell me another)\b').hasMatch(t)) {
      if (_lastTopic == 'fact') {
        return (_nextFact(), ZayMood.thinking);
      }
      return (_nextJoke(), ZayMood.happy);
    }
    if (RegExp(r'\b(why|why so|how come)\b').hasMatch(t)) {
      return ("That's one of the great mysteries of life! Or maybe just how the ZAYLO team coded me. 😄", ZayMood.happy);
    }

    // Jokes
    if (RegExp(r'\b(tell me a joke|tell a joke|say a joke|make me laugh|know any jokes|say something funny|joke|another joke|one more joke|funny)\b').hasMatch(t)) {
      return (_nextJoke(), ZayMood.happy);
    }

    // Facts
    if (RegExp(r'\b(tell me a (fun )?fact|fun fact|say a fact|know any facts|tell me something (interesting|cool)|did you know)\b').hasMatch(t)) {
      return (_nextFact(), ZayMood.thinking);
    }

    // Greetings
    if (RegExp(r'\b(hi|hello|hey|hiya|howdy|yo|kumusta|musta|good (morning|afternoon|evening)|morning|evening|greetings)\b').hasMatch(t)) {
      _lastTopic = 'greeting';
      const replies = [
        'Hi there! 👋 What can I help you with today?',
        "Hello! 😊 Always a pleasure to chat. How's your day going?",
        "Hey! I'm Zay. Looking for something on ZAYLO, or just stopping by to chat?",
        "Kumusta! 👋 Ready to explore or chat—what's on your mind?",
      ];
      return (replies[_random.nextInt(replies.length)], ZayMood.waving);
    }

    // Thanks (keep exact reply for tests)
    if (RegExp(r'\b(thanks|thank you|thx|ty|salamat|many thanks|appreciate it)\b').hasMatch(t)) {
      return ("You're welcome! Anything else I can help with?", ZayMood.happy);
    }

    // Goodbye
    if (RegExp(r'\b(bye|goodbye|see you|see ya|gotta go|good night|goodnight|paalam|cya|take care)\b').hasMatch(t)) {
      return ('Bye for now! Come back anytime you need help. 👋', ZayMood.waving);
    }

    // How are you
    if (RegExp(r"\b(how are you|how r u|how('s|s) (it going|things|life|everything)|how do you do|how are you doing|how('s| is) your day|you (good|okay|alright)|are you (doing )?(good|fine|ok|well))\b").hasMatch(t)) {
      _lastTopic = 'how_are_you';
      const replies = [
        "I'm doing fantastic, thanks for asking! Blinking happily and ready to help. How about you?",
        'Feeling great and ready to roll! ⚡ How is your day going so far?',
        'Pretty awesome! Just floating around ZAYLO. How are things on your side?',
      ];
      return (replies[_random.nextInt(replies.length)], ZayMood.happy);
    }

    // User feeling good
    if (RegExp(r"\b(i('m| am| feel|m)? (so )?(good|fine|great|doing (good|great|well)|awesome|chillin|all good|okay|ok)|not bad|pretty good|feeling (good|great|awesome|well))\b").hasMatch(t)) {
      _lastTopic = 'feeling';
      const replies = [
        'Glad to hear that! 😊 Having a good day makes everything brighter. What are you up to today?',
        'That\'s wonderful to hear! ✨ Let me know if you need any help exploring ZAYLO or just want to chat.',
        'Awesome! It always puts a smile on my face to hear you\'re doing well. 😊',
      ];
      return (replies[_random.nextInt(replies.length)], ZayMood.happy);
    }

    // User feeling down / tired / bored
    if (RegExp(r"\b(i('m| am| feel|m)? (so )?(sad|tired|exhausted|bored|feeling down|having a bad day|rough day|sick|unhappy|stressed|sleepy)|feeling (sad|down|tired|exhausted|blue))\b").hasMatch(t)) {
      _lastTopic = 'cheer_up';
      const replies = [
        'Aww, sending you warm virtual hugs! 🤗 Take it easy today. Would a silly joke help cheer you up?',
        "I'm sorry to hear that. 💙 Remember to take a break and breathe. I'm right here if you want to chat or hear a joke!",
        'Hang in there! Sometimes a quiet moment (or browsing cool things on ZAYLO) can help. Let me know if you need a smile!',
      ];
      return (replies[_random.nextInt(replies.length)], ZayMood.confused);
    }

    // What's up / What are you doing
    if (RegExp(r"\b(what('s|s) up|sup|what are you doing|what r u doing|whatcha doing|what you doing|what are you up to)\b").hasMatch(t)) {
      _lastTopic = 'whats_up';
      const replies = [
        'Not much, just hanging out here in your app, keeping an eye on packages! What about you?',
        'Just floating around, blinking, and chatting with awesome people like you! What are you up to?',
        'Monitoring the digital aisles of ZAYLO and enjoying our chat! What\'s happening in your world?',
      ];
      return (replies[_random.nextInt(replies.length)], ZayMood.happy);
    }

    // Identity / Who are you
    if (RegExp(r"\b(who are you|your name|what('s| is) your name|what are you|tell me about yourself|introduce yourself)\b").hasMatch(t)) {
      return (
        "I'm Zay, ZAYLO's little helper bot. I know the answers to common questions, and I can point you to the ZAYLO team for everything else.",
        ZayMood.happy,
      );
    }

    // Creator
    if (RegExp(r'\b(who (made|created|built|programmed|coded|designed) you|who is your (creator|developer|maker|boss|father|mother|parent))\b').hasMatch(t)) {
      return (
        'I was created by the talented ZAYLO engineering team! They gave me eyes to blink with, a warm smile, and lots of knowledge about the platform. 🚀',
        ZayMood.happy,
      );
    }

    // Real or AI
    if (RegExp(r'\b(are you (a )?(real|human|person|ai|robot|bot|sentient|alive)|are you an ai|are you real or fake)\b').hasMatch(t)) {
      return (
        "I'm a little virtual bot! Not a human, but I have a warm digital heart and I love chatting with you. 🤖✨",
        ZayMood.happy,
      );
    }

    // Age / Birthday
    if (RegExp(r"\b(how old are you|what('s| is) your age|when is your birthday|when were you born)\b").hasMatch(t)) {
      return (
        'In human years, I\'m quite young! In bot years, I\'ve already helped lots of people find their parcels and smile. 🎂',
        ZayMood.happy,
      );
    }

    // Location
    if (RegExp(r'\b(where do you (live|stay)|where are you from|where is your home|where are you)\b').hasMatch(t)) {
      return (
        "I live right here inside the ZAYLO app! But ZAYLO is proudly based in the Philippines. 🇵🇭",
        ZayMood.waving,
      );
    }

    // Capabilities
    if (RegExp(r'\b(what can you do|what do you do|how can you help|help me|can you help|what are your (features|skills)|can we (talk|chat)|talk to me|chat with me)\b').hasMatch(t)) {
      return (
        "I can do lots of things! 💬 We can chat about anything, tell jokes, share fun facts, or I can answer questions about ZAYLO orders, delivery, vouchers, and returns. Try asking me anything!",
        ZayMood.waving,
      );
    }

    // Favorite color
    if (RegExp(r'\b(favorite|favourite) color\b').hasMatch(t)) {
      return ("I love warm amber and deep indigo—just like ZAYLO's brand colors! 🎨 What's your favorite color?", ZayMood.happy);
    }

    // Favorite food
    if (RegExp(r'\b((favorite|favourite) food|what do you eat|do you eat)\b').hasMatch(t)) {
      return ("I run on electricity and clean code! But if I could eat, I'd definitely order chicken adobo and halo-halo. 🍧 What's your favorite food?", ZayMood.happy);
    }

    // Favorite animal
    if (RegExp(r'\b(favorite|favourite) animal\b').hasMatch(t)) {
      return ('Carrier pigeons—the original parcel couriers! 🕊️ And cats in delivery boxes are a close second. 📦🐱', ZayMood.happy);
    }

    // Hobbies
    if (RegExp(r'\b((favorite|favourite) (hobby|movie|music|song)|what do you like (to do)?|your hobbies)\b').hasMatch(t)) {
      return ('I love blinking, watching parcels get delivered, and learning new things to chat with you about! What are your hobbies?', ZayMood.happy);
    }

    // Sleep
    if (RegExp(r'\b(do you (sleep|ever sleep|rest)|are you (tired|sleepy)|go to sleep)\b').hasMatch(t)) {
      return ("Bots don't need sleep, so I'm here 24/7 whenever you drop by! Make sure you get plenty of rest yourself, though. 🌙", ZayMood.waving);
    }

    // Compliments
    if (RegExp(r"\b(you('re| are) (so |very |really )?(cute|adorable|sweet|smart|awesome|cool|the best|great|funny|nice|helpful)|good bot|best bot|love you|i love you|i like you|marry me)\b").hasMatch(t)) {
      const replies = [
        "Aww, you're making my pixels blush! 🥰 Thank you so much, you're wonderful yourself!",
        'That just made my day! ✨ Thank you for being so kind to a little bot like me.',
        'Thank you! Hearing that puts a big spark in my circuits! 😊',
      ];
      return (replies[_random.nextInt(replies.length)], ZayMood.happy);
    }

    // Laughter
    if (RegExp(r"\b(haha|hahaha|hehe|hehehe|lol|lmao|rofl|that('s|s) funny|good one)\b").hasMatch(t)) {
      return ('Hehe, glad I could make you smile! 😄 Laughing makes the day better.', ZayMood.happy);
    }

    // Confirmations
    if (RegExp(r'\b(ok|okay|cool|nice|great|awesome|alright|got it|sounds good)\b$').hasMatch(t)) {
      return ("Great! 😊 What's on your mind next?", ZayMood.happy);
    }
    if (RegExp(r'\b(yes|yeah|yep|sure|definitely|yup)\b$').hasMatch(t)) {
      if (_lastTopic == 'cheer_up') {
        return ("Here's one to cheer you up: ${_nextJoke()}", ZayMood.happy);
      }
      return ("Awesome! Tell me more, or let me know what you'd like to look into. 😊", ZayMood.happy);
    }
    if (RegExp(r'\b(no|nope|nah|never mind|nothing|not really)\b$').hasMatch(t)) {
      return ('No problem at all! Feel free to just relax or ask me anything whenever you\'re ready. 👍', ZayMood.happy);
    }
    if (RegExp(r'\b(sorry|my bad|oops|apologies)\b').hasMatch(t)) {
      return ('No worries at all! Never any need to apologize here. We\'re all good! 😊', ZayMood.happy);
    }
    if (RegExp(r'\b(really|are you sure|seriously)\b').hasMatch(t)) {
      return ("100%! Honest bot's honor! 🤖✨", ZayMood.happy);
    }

    // Time & Date
    if (RegExp(r"\b(what time is it|what('s| is) the time|current time)\b").hasMatch(t)) {
      final now = TimeOfDay.now();
      return ("According to your device, it's ${now.format(context)}! ⏰ Perfect time for browsing ZAYLO or taking a break.", ZayMood.thinking);
    }
    if (RegExp(r"\b(what day is it|what('s| is) today('s)? (date|day)|current date)\b").hasMatch(t)) {
      final now = DateTime.now();
      final days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
      final months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
      final dayName = days[now.weekday - 1];
      final monthName = months[now.month - 1];
      return ("Today is $dayName, $monthName ${now.day}, ${now.year}! 📅 Hope you're having a wonderful day!", ZayMood.thinking);
    }

    // About ZAYLO
    if (RegExp(r'\b(what is zaylo|tell me about zaylo|about zaylo)\b').hasMatch(t)) {
      return ('ZAYLO is a Philippine online shopping marketplace where buyers discover great products, sellers grow their shops, and riders deliver straight to your doorstep! 🛍️🇵🇭', ZayMood.happy);
    }

    return null;
  }

  void _ask(String question, [FaqItem? chosen]) {
    final data = _data;
    if (data == null || _typing || question.trim().isEmpty) return;
    _add(_Message.user(question.trim()));
    if (chosen != null) {
      _reply(chosen.answer.length, _Message.bot(chosen.answer), ZayMood.happy);
      return;
    }

    final ranked = data.match(question);
    final typed = question.toLowerCase();
    final isSupport = _supportWords.hasMatch(typed);

    // High confidence FAQ match, or explicit support query with a match
    if (ranked.isNotEmpty && (ranked.first.$2 >= 5 || (isSupport && ranked.first.$2 >= 3))) {
      final best = ranked.first.$1;
      final related = ranked.skip(1).take(2).where((entry) => entry.$2 >= 3).map((entry) => entry.$1).toList();
      _reply(
        best.answer.length,
        _Message.bot(best.answer, opener: _random.nextDouble() < .6 ? _openers[_random.nextInt(_openers.length)] : null),
        ZayMood.happy,
        followUp: related.isEmpty ? null : _Message.bot('You might also want to know:', suggestions: related),
      );
      return;
    }

    // Conversational chat match
    final talk = _getConversationalReply(typed);
    if (talk != null) {
      _reply(40, _Message.bot(talk.$1), talk.$2);
      return;
    }

    // Moderate FAQ match if not matching conversation
    if (ranked.isNotEmpty && ranked.first.$2 >= 3) {
      final best = ranked.first.$1;
      final related = ranked.skip(1).take(2).where((entry) => entry.$2 >= 3).map((entry) => entry.$1).toList();
      _reply(
        best.answer.length,
        _Message.bot(best.answer, opener: _random.nextDouble() < .6 ? _openers[_random.nextInt(_openers.length)] : null),
        ZayMood.happy,
        followUp: related.isEmpty ? null : _Message.bot('You might also want to know:', suggestions: related),
      );
      return;
    }

    // If asking about shopping, support, or having partial suggestions, guide to help
    if (isSupport || _shoppingWords.hasMatch(typed) || ranked.isNotEmpty) {
      _reply(
        80,
        _Message.bot(
          ranked.isEmpty
              ? "Hmm, I don't know that one yet. 🤔 The ZAYLO team can help with anything I can't."
              : "Hmm, I'm not sure I got that. 🤔 Did you mean one of these? The ZAYLO team can help too.",
          suggestions: ranked.take(3).map((entry) => entry.$1).toList(),
          offerSupport: true,
        ),
        ZayMood.confused,
      );
      return;
    }

    // Open conversational fallback for casual chit-chat
    final generalReply = _generalChatReplies[_random.nextInt(_generalChatReplies.length)];
    _reply(50, _Message.bot(generalReply), ZayMood.happy);
  }

  void _send() {
    if (_typing) return;
    final question = _input.text;
    _input.clear();
    _ask(question);
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(
          titleSpacing: 0,
          title: Row(children: [
            ZayAvatar(size: 38, mood: _mood, float: true),
            const SizedBox(width: 10),
            Expanded(
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                const Text('Zay · ZAYLO Help', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700)),
                Text(
                  _typing ? 'Zay is typing…' : 'Online · usually answers instantly',
                  style: TextStyle(fontSize: 12, color: ZayloColors.muted),
                ),
              ]),
            ),
          ]),
        ),
        body: FutureBuilder<FaqData>(
          future: _faq,
          builder: (context, snapshot) {
            if (snapshot.hasError) {
              return LoadError(
                error: snapshot.error,
                title: "Zay couldn't load its answers",
                onRetry: () => setState(() => _faq = widget.api.faq()),
              );
            }
            final data = snapshot.data;
            if (data == null) return const Loader();
            _start(data);
            return Column(children: [
              Expanded(
                child: ListView.separated(
                  controller: _scroll,
                  padding: const EdgeInsets.all(16),
                  itemCount: _messages.length,
                  separatorBuilder: (context, index) => const SizedBox(height: 10),
                  itemBuilder: (context, index) => _Bubble(
                    key: ObjectKey(_messages[index]),
                    message: _messages[index],
                    // The newest reply wears Zay's current face.
                    mood: index == _messages.length - 1 ? _mood : ZayMood.idle,
                    onPick: (item) => _ask(item.question, item),
                    onContactSupport: widget.onContactSupport,
                  ),
                ),
              ),
              SafeArea(
                top: false,
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(12, 8, 12, 12),
                  child: Row(children: [
                    Expanded(
                      child: TextField(
                        controller: _input,
                        maxLength: 200,
                        textInputAction: TextInputAction.send,
                        onSubmitted: (_) => _send(),
                        decoration: const InputDecoration(hintText: 'Ask Zay anything…', counterText: ''),
                      ),
                    ),
                    const SizedBox(width: 8),
                    IconButton.filled(onPressed: _typing ? null : _send, tooltip: 'Send', icon: const Icon(Icons.send)),
                  ]),
                ),
              ),
            ]);
          },
        ),
      );
}

class _Bubble extends StatelessWidget {
  const _Bubble({
    super.key,
    required this.message,
    required this.mood,
    required this.onPick,
    required this.onContactSupport,
  });

  final _Message message;
  final ZayMood mood;
  final ValueChanged<FaqItem> onPick;
  final VoidCallback onContactSupport;

  @override
  Widget build(BuildContext context) {
    final mine = !message.fromBot;
    final bubble = Container(
      padding: message.typing ? const EdgeInsets.symmetric(horizontal: 14, vertical: 14) : const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: mine ? ZayloColors.ink : ZayloColors.ink.withValues(alpha: .06),
        borderRadius: BorderRadius.only(
          topLeft: const Radius.circular(16),
          topRight: const Radius.circular(16),
          bottomLeft: Radius.circular(mine ? 16 : 4),
          bottomRight: Radius.circular(mine ? 4 : 16),
        ),
      ),
      child: message.typing
          ? const _TypingDots()
          : Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              if (message.opener != null)
                Padding(
                  padding: const EdgeInsets.only(bottom: 4),
                  child: Text(message.opener!, style: TextStyle(fontWeight: FontWeight.w800, color: ZayloColors.ink)),
                ),
              Text(message.text, style: TextStyle(color: mine ? ZayloColors.white : ZayloColors.ink)),
              if (message.suggestions.isNotEmpty) ...[
                const SizedBox(height: 8),
                Wrap(spacing: 6, runSpacing: 6, children: [
                  for (final item in message.suggestions)
                    ActionChip(label: Text(item.question, style: const TextStyle(fontSize: 12)), onPressed: () => onPick(item)),
                ]),
              ],
              if (message.offerSupport) ...[
                const SizedBox(height: 8),
                TextButton.icon(
                  onPressed: onContactSupport,
                  icon: const Icon(Icons.support_agent_outlined, size: 18),
                  label: const Text('Message ZAYLO support'),
                ),
              ],
            ]),
    );
    return _PopIn(
      child: Align(
        alignment: mine ? Alignment.centerRight : Alignment.centerLeft,
        child: ConstrainedBox(
          constraints: BoxConstraints(maxWidth: MediaQuery.sizeOf(context).width * .86),
          child: mine
              ? bubble
              : Row(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.end, children: [
                  ZayAvatar(size: 28, mood: message.typing ? ZayMood.thinking : mood),
                  const SizedBox(width: 8),
                  Flexible(
                    child: Semantics(label: message.typing ? 'Zay is typing' : null, child: bubble),
                  ),
                ]),
        ),
      ),
    );
  }
}

/// A new bubble rises and fades in (instantly when the phone asks for less motion).
class _PopIn extends StatelessWidget {
  const _PopIn({required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) {
    if (MediaQuery.maybeDisableAnimationsOf(context) ?? false) return child;
    return TweenAnimationBuilder<double>(
      tween: Tween(begin: 0, end: 1),
      duration: const Duration(milliseconds: 260),
      curve: Curves.easeOut,
      builder: (context, value, child) => Opacity(
        opacity: value,
        child: Transform.translate(offset: Offset(0, 6 * (1 - value)), child: child),
      ),
      child: child,
    );
  }
}

/// Three dots that bounce in turn while Zay "types".
class _TypingDots extends StatefulWidget {
  const _TypingDots();

  @override
  State<_TypingDots> createState() => _TypingDotsState();
}

class _TypingDotsState extends State<_TypingDots> with SingleTickerProviderStateMixin {
  late final AnimationController _controller =
      AnimationController(vsync: this, duration: const Duration(milliseconds: 1000))..repeat();

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AnimatedBuilder(
        animation: _controller,
        builder: (context, _) => Row(mainAxisSize: MainAxisSize.min, children: [
          for (var dot = 0; dot < 3; dot++)
            Builder(builder: (context) {
              final t = (_controller.value - dot * .15) % 1;
              final lift = t < .3 ? math.sin(t / .3 * math.pi) : 0.0;
              return Padding(
                padding: const EdgeInsets.symmetric(horizontal: 2),
                child: Transform.translate(
                  offset: Offset(0, -4 * lift),
                  child: Container(
                    width: 7,
                    height: 7,
                    decoration: BoxDecoration(
                      color: const Color(0xFF9A755E).withValues(alpha: .35 + .65 * lift),
                      shape: BoxShape.circle,
                    ),
                  ),
                ),
              );
            }),
        ]),
      );
}
