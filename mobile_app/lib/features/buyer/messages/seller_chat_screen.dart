import 'dart:async';

import 'package:flutter/material.dart';

import 'package:zaylo_mobile/api/api_exception.dart';
import 'package:zaylo_mobile/api/buyer_api.dart';
import 'package:zaylo_mobile/components/feedback.dart';
import 'package:zaylo_mobile/components/layout.dart';
import 'package:zaylo_mobile/models/buyer/seller_chat_models.dart';
import 'package:zaylo_mobile/state/app_state.dart';
import 'package:zaylo_mobile/theme/zaylo_theme.dart';
import 'package:zaylo_mobile/utils/formatters.dart';
import 'package:zaylo_mobile/utils/ids.dart';

/// A conversation with a seller. It is the same thread the website and the
/// Seller Center show; new messages and "typing…" are fetched every
/// [_pollEvery] while the screen is open.
class SellerChatScreen extends StatefulWidget {
  const SellerChatScreen({super.key, required this.conversation});

  final SellerConversation conversation;

  @override
  State<SellerChatScreen> createState() => _SellerChatScreenState();
}

class _SellerChatScreenState extends State<SellerChatScreen> {
  static const _pollEvery = Duration(seconds: 5);

  /// Typing pings are repeated at most this often; the server keeps each for 6s.
  static const _typingEvery = Duration(seconds: 4);
  static const _maxLength = 2000;

  final _input = TextEditingController();
  final _scroll = ScrollController();
  final List<SellerChatMessage> _messages = [];

  /// Sent but not yet confirmed (or failed), keyed by client id.
  final _outbox = <String, SellerChatMessage>{};
  bool _loaded = false;
  bool _hasOlder = false;
  bool _loadingOlder = false;
  bool _typing = false;
  int _readThrough = 0;
  Object? _error;
  Timer? _poll;
  DateTime? _lastTypingPing;

  BuyerApi get _api => AppScope.read(context).api;
  int get _id => widget.conversation.id;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _loadLatest();
      _poll = Timer.periodic(_pollEvery, (_) => _loadNew());
    });
    _input.addListener(_onTyping);
  }

  @override
  void dispose() {
    _poll?.cancel();
    _input.dispose();
    _scroll.dispose();
    super.dispose();
  }

  Future<void> _loadLatest() async {
    try {
      final batch = await _api.sellerMessages(_id);
      if (!mounted) return;
      setState(() {
        _messages
          ..clear()
          ..addAll(batch.messages);
        _hasOlder = batch.hasOlder;
        _typing = batch.typing;
        _loaded = true;
        _error = null;
      });
      _scrollToEnd(jump: true);
      _markRead();
    } on ApiException catch (error) {
      if (mounted) setState(() => _error = error);
    }
  }

  Future<void> _loadNew() async {
    if (!_loaded) return;
    final lastId = _messages.lastOrNull?.id ?? 0;
    try {
      final batch = await _api.sellerMessages(_id, after: lastId);
      if (!mounted) return;
      final fresh = batch.messages.where((message) => !_messages.any((known) => known.id == message.id)).toList();
      setState(() {
        _messages.addAll(fresh);
        _typing = batch.typing;
      });
      if (fresh.isNotEmpty) {
        _scrollToEnd();
        _markRead();
      }
    } on ApiException catch (error) {
      debugPrint('[ZAYLO] Seller chat poll failed: ${error.debugDescription}');
    }
  }

  Future<void> _loadOlder() async {
    final firstId = _messages.firstOrNull?.id;
    if (firstId == null || _loadingOlder) return;
    setState(() => _loadingOlder = true);
    try {
      final batch = await _api.sellerMessages(_id, before: firstId);
      if (!mounted) return;
      setState(() {
        _messages.insertAll(0, batch.messages);
        _hasOlder = batch.hasOlder;
      });
    } on ApiException catch (error) {
      if (mounted) showMessage(context, error.message);
    } finally {
      if (mounted) setState(() => _loadingOlder = false);
    }
  }

  /// Tells the seller their messages were seen, once per new message.
  void _markRead() {
    final latest = _messages.lastWhere((message) => !message.mine, orElse: () => const SellerChatMessage(clientId: '', body: '', mine: true));
    final id = latest.id;
    if (id == null || id <= _readThrough) return;
    _readThrough = id;
    _api.markSellerChatRead(_id, id).catchError((Object error) {
      debugPrint('[ZAYLO] Marking seller chat read failed: $error');
    });
  }

  void _onTyping() {
    if (_input.text.trim().isEmpty) return;
    final now = DateTime.now();
    if (_lastTypingPing != null && now.difference(_lastTypingPing!) < _typingEvery) return;
    _lastTypingPing = now;
    _api.sendTyping(_id, true).catchError((Object error) {
      debugPrint('[ZAYLO] Typing signal failed: $error');
    });
  }

  Future<void> _send([SellerChatMessage? retry]) async {
    final body = retry?.body ?? _input.text.trim();
    if (body.isEmpty) return;
    final draft = retry?.copyWith(pending: true, failed: false) ??
        SellerChatMessage(clientId: newUuidV4(), body: body, mine: true, sentAt: DateTime.now(), pending: true);
    setState(() => _outbox[draft.clientId] = draft);
    if (retry == null) _input.clear();
    _lastTypingPing = null;
    _scrollToEnd();
    try {
      final saved = await _api.sendSellerMessage(_id, body, clientId: draft.clientId);
      if (!mounted) return;
      setState(() {
        _outbox.remove(draft.clientId);
        if (!_messages.any((message) => message.id == saved.id)) _messages.add(saved);
      });
    } on ApiException catch (error) {
      debugPrint('[ZAYLO] Seller message failed: ${error.debugDescription}');
      if (!mounted) return;
      setState(() => _outbox[draft.clientId] = draft.copyWith(pending: false, failed: true));
      showMessage(context, error.message);
    }
  }

  void _scrollToEnd({bool jump = false}) => WidgetsBinding.instance.addPostFrameCallback((_) {
        if (!_scroll.hasClients) return;
        final end = _scroll.position.maxScrollExtent;
        jump ? _scroll.jumpTo(end) : _scroll.animateTo(end, duration: const Duration(milliseconds: 250), curve: Curves.easeOut);
      });

  @override
  Widget build(BuildContext context) {
    final chat = widget.conversation;
    final thread = [..._messages, ..._outbox.values];
    return Scaffold(
      appBar: AppBar(
        title: Column(mainAxisSize: MainAxisSize.min, children: [
          Text(chat.name),
          Text(_typing ? 'typing…' : 'Seller',
              style: TextStyle(fontSize: 12, fontWeight: FontWeight.w500, color: ZayloColors.muted)),
        ]),
      ),
      body: !_loaded
          ? (_error != null ? LoadError(error: _error, onRetry: _loadLatest, title: 'Could not open the chat') : const Loader())
          : Column(children: [
              Expanded(
                child: ListView(
                  controller: _scroll,
                  padding: const EdgeInsets.fromLTRB(16, 12, 16, 12),
                  children: [
                    if (chat.productName != null) _ProductContext(chat: chat),
                    if (_hasOlder)
                      Center(
                        child: _loadingOlder
                            ? const Padding(padding: EdgeInsets.all(8), child: Loader())
                            : TextButton(onPressed: _loadOlder, child: const Text('Show earlier messages')),
                      ),
                    if (thread.isEmpty)
                      Padding(
                        padding: const EdgeInsets.symmetric(vertical: 24),
                        child: Text('Ask ${chat.name} about sizes, stock, or delivery before you order.',
                            textAlign: TextAlign.center, style: TextStyle(color: ZayloColors.muted)),
                      ),
                    for (final message in thread) _Bubble(message: message, onRetry: () => _send(message)),
                  ],
                ),
              ),
              _composer(chat.name),
            ]),
    );
  }

  Widget _composer(String sellerName) => SafeArea(
        top: false,
        child: Container(
          padding: const EdgeInsets.fromLTRB(12, 8, 8, 8),
          decoration: BoxDecoration(color: ZayloColors.paper, border: Border(top: BorderSide(color: ZayloColors.line))),
          child: Row(crossAxisAlignment: CrossAxisAlignment.end, children: [
            Expanded(
              child: TextField(
                controller: _input,
                minLines: 1,
                maxLines: 4,
                maxLength: _maxLength,
                textCapitalization: TextCapitalization.sentences,
                decoration: InputDecoration(
                  hintText: 'Message $sellerName',
                  counterText: '',
                  filled: true,
                  fillColor: ZayloColors.white,
                  border: const OutlineInputBorder(borderRadius: BorderRadius.all(Radius.circular(22))),
                  contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                ),
              ),
            ),
            const SizedBox(width: 6),
            IconButton.filled(
              tooltip: 'Send',
              onPressed: () => _send(),
              style: IconButton.styleFrom(backgroundColor: ZayloColors.ink, foregroundColor: ZayloColors.white),
              icon: const Icon(Icons.send),
            ),
          ]),
        ),
      );
}

class _ProductContext extends StatelessWidget {
  const _ProductContext({required this.chat});

  final SellerConversation chat;

  @override
  Widget build(BuildContext context) => Container(
        margin: const EdgeInsets.only(bottom: 14),
        padding: const EdgeInsets.all(10),
        decoration: BoxDecoration(
          color: ZayloColors.paper,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: ZayloColors.line),
        ),
        child: Row(children: [
          ClipRRect(
            borderRadius: BorderRadius.circular(8),
            child: SizedBox(width: 44, height: 44, child: NetworkImageBox(url: chat.productImageUrl)),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              const Eyebrow('About'),
              Text(chat.productName!,
                  maxLines: 1, overflow: TextOverflow.ellipsis, style: TextStyle(fontWeight: FontWeight.w600, color: ZayloColors.ink)),
            ]),
          ),
        ]),
      );
}

class _Bubble extends StatelessWidget {
  const _Bubble({required this.message, required this.onRetry});

  final SellerChatMessage message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    final mine = message.mine;
    final status = message.failed
        ? 'Not sent · tap to retry'
        : message.pending
            ? 'Sending…'
            : dateTimeLabel(message.sentAt);
    return Align(
      alignment: mine ? Alignment.centerRight : Alignment.centerLeft,
      child: GestureDetector(
        onTap: message.failed ? onRetry : null,
        child: Container(
          constraints: BoxConstraints(maxWidth: MediaQuery.sizeOf(context).width * .78),
          margin: const EdgeInsets.only(bottom: 8),
          padding: const EdgeInsets.fromLTRB(14, 10, 14, 8),
          decoration: BoxDecoration(
            color: mine ? ZayloColors.ink : ZayloColors.sand,
            borderRadius: BorderRadius.only(
              topLeft: const Radius.circular(16),
              topRight: const Radius.circular(16),
              bottomLeft: Radius.circular(mine ? 16 : 4),
              bottomRight: Radius.circular(mine ? 4 : 16),
            ),
            border: message.failed ? Border.all(color: Colors.redAccent) : null,
          ),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(message.body, style: TextStyle(color: mine ? ZayloColors.white : ZayloColors.ink)),
            const SizedBox(height: 4),
            Text(status,
                style: TextStyle(
                  fontSize: 11,
                  color: message.failed ? Colors.redAccent : (mine ? ZayloColors.onInkMuted : ZayloColors.muted),
                )),
          ]),
        ),
      ),
    );
  }
}
