import 'package:flutter/material.dart';

import 'package:zaylo_mobile/api/api_client.dart';
import 'package:zaylo_mobile/components/feedback.dart';
import 'package:zaylo_mobile/models/support_models.dart';
import 'package:zaylo_mobile/theme/zaylo_theme.dart';

/// The help bot, as on the website: answers common questions from the
/// server's list (GET /mobile/faq), suggests questions to tap, and offers
/// Help & support when nothing fits.
class HelpBotScreen extends StatefulWidget {
  const HelpBotScreen({super.key, required this.api, required this.onContactSupport});

  final ApiClient api;

  /// Opens the chat with the ZAYLO team.
  final VoidCallback onContactSupport;

  @override
  State<HelpBotScreen> createState() => _HelpBotScreenState();
}

/// One bubble: the person's question, or the bot's answer with optional
/// suggested questions and a support button.
class _Message {
  const _Message.user(this.text)
      : fromBot = false,
        suggestions = const [],
        offerSupport = false;

  const _Message.bot(this.text, {this.suggestions = const [], this.offerSupport = false}) : fromBot = true;

  final String text;
  final bool fromBot;
  final List<FaqItem> suggestions;
  final bool offerSupport;
}

class _HelpBotScreenState extends State<HelpBotScreen> {
  late Future<FaqData> _faq = widget.api.faq();
  final _messages = <_Message>[];
  final _input = TextEditingController();
  final _scroll = ScrollController();
  FaqData? _data;

  @override
  void dispose() {
    _input.dispose();
    _scroll.dispose();
    super.dispose();
  }

  void _start(FaqData data) {
    if (_data != null) return;
    _data = data;
    _messages.add(_Message.bot(data.greeting, suggestions: data.items.take(5).toList()));
  }

  void _say(_Message message) {
    setState(() => _messages.add(message));
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (_scroll.hasClients) {
        _scroll.animateTo(_scroll.position.maxScrollExtent,
            duration: const Duration(milliseconds: 280), curve: Curves.easeOut);
      }
    });
  }

  void _ask(String question, [FaqItem? chosen]) {
    final data = _data;
    if (data == null || question.trim().isEmpty) return;
    _say(_Message.user(question.trim()));
    if (chosen != null) {
      _say(_Message.bot(chosen.answer));
      return;
    }
    final ranked = data.match(question);
    if (ranked.isNotEmpty && ranked.first.$2 >= 3) {
      final related = ranked.skip(1).take(2).where((entry) => entry.$2 >= 3).map((entry) => entry.$1).toList();
      _say(_Message.bot(ranked.first.$1.answer));
      if (related.isNotEmpty) _say(_Message.bot('Related questions:', suggestions: related));
      return;
    }
    _say(_Message.bot(
      ranked.isEmpty
          ? "Sorry, I don't have an answer for that yet. You can ask the ZAYLO team directly."
          : "I'm not sure I understood. Did you mean one of these? You can also ask the ZAYLO team.",
      suggestions: ranked.take(3).map((entry) => entry.$1).toList(),
      offerSupport: true,
    ));
  }

  void _send() {
    final question = _input.text;
    _input.clear();
    _ask(question);
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Help bot')),
        body: FutureBuilder<FaqData>(
          future: _faq,
          builder: (context, snapshot) {
            if (snapshot.hasError) {
              return LoadError(
                error: snapshot.error,
                title: 'Help could not load',
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
                    message: _messages[index],
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
                        decoration: const InputDecoration(hintText: 'Ask a question…', counterText: ''),
                      ),
                    ),
                    const SizedBox(width: 8),
                    IconButton.filled(onPressed: _send, tooltip: 'Ask', icon: const Icon(Icons.send)),
                  ]),
                ),
              ),
            ]);
          },
        ),
      );
}

class _Bubble extends StatelessWidget {
  const _Bubble({required this.message, required this.onPick, required this.onContactSupport});

  final _Message message;
  final ValueChanged<FaqItem> onPick;
  final VoidCallback onContactSupport;

  @override
  Widget build(BuildContext context) {
    final mine = !message.fromBot;
    return Align(
      alignment: mine ? Alignment.centerRight : Alignment.centerLeft,
      child: ConstrainedBox(
        constraints: BoxConstraints(maxWidth: MediaQuery.sizeOf(context).width * .82),
        child: Container(
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(
            color: mine ? ZayloColors.ink : ZayloColors.ink.withValues(alpha: .06),
            borderRadius: BorderRadius.only(
              topLeft: const Radius.circular(14),
              topRight: const Radius.circular(14),
              bottomLeft: Radius.circular(mine ? 14 : 4),
              bottomRight: Radius.circular(mine ? 4 : 14),
            ),
          ),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
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
        ),
      ),
    );
  }
}
