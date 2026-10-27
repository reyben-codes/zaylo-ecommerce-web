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
  static final _smallTalk = [
    _SmallTalk(RegExp(r'\b(hi|hello|hey|hiya|kumusta|musta|good (morning|afternoon|evening))\b'), ZayMood.waving,
        'Hi there! 👋 What can I help you with today?'),
    _SmallTalk(RegExp(r'\b(thanks|thank you|thx|ty|salamat)\b'), ZayMood.happy, "You're welcome! Anything else I can help with?"),
    _SmallTalk(RegExp(r'\b(bye|goodbye|see you|paalam)\b'), ZayMood.waving, 'Bye for now! Come back anytime you need help. 👋'),
    _SmallTalk(RegExp(r'\b(who are you|your name|are you (a )?(bot|robot|human))\b'), ZayMood.happy,
        "I'm Zay, ZAYLO's little helper bot. I know the answers to common questions, and I can point you to the "
        'ZAYLO team for everything else.'),
  ];

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

  void _ask(String question, [FaqItem? chosen]) {
    final data = _data;
    if (data == null || _typing || question.trim().isEmpty) return;
    _add(_Message.user(question.trim()));
    if (chosen != null) {
      _reply(chosen.answer.length, _Message.bot(chosen.answer), ZayMood.happy);
      return;
    }
    final ranked = data.match(question);
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
    final typed = question.toLowerCase();
    final talk = _smallTalk.where((entry) => entry.pattern.hasMatch(typed)).firstOrNull;
    if (talk != null) {
      _reply(40, _Message.bot(talk.reply), talk.mood);
      return;
    }
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
