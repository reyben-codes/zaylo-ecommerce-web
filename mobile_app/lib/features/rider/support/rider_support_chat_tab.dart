import 'dart:async';

import 'package:flutter/material.dart';

import 'package:zaylo_mobile/api/api_exception.dart';
import 'package:zaylo_mobile/api/rider_api.dart';
import 'package:zaylo_mobile/components/feedback.dart';
import 'package:zaylo_mobile/models/rider/rider_activity_models.dart';
import 'package:zaylo_mobile/theme/zaylo_theme.dart';
import 'package:zaylo_mobile/utils/formatters.dart';
import 'package:zaylo_mobile/utils/ids.dart';

/// Chat with ZAYLO support. Staff reply from the admin Chat page; new replies
/// are fetched every [_pollEvery] while this tab is open.
class RiderSupportChatTab extends StatefulWidget {
  const RiderSupportChatTab({super.key, required this.api, required this.visible, this.onRead});

  final RiderApi api;

  /// Polling runs only while the tab is on screen.
  final bool visible;

  /// Called after the thread is opened, so the tab badge can clear.
  final VoidCallback? onRead;

  @override
  State<RiderSupportChatTab> createState() => _RiderSupportChatTabState();
}

class _RiderSupportChatTabState extends State<RiderSupportChatTab> {
  static const _pollEvery = Duration(seconds: 15);
  static const _maxLength = 2000;

  final _input = TextEditingController();
  final _scroll = ScrollController();
  List<RiderSupportMessage> _messages = [];

  /// Sent but not yet confirmed (or failed), keyed by client id.
  final _outbox = <String, RiderSupportMessage>{};
  bool _loading = false;
  Object? _error;
  Timer? _poll;

  @override
  void initState() {
    super.initState();
    if (widget.visible) _start();
  }

  @override
  void didUpdateWidget(RiderSupportChatTab oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.visible && !oldWidget.visible) _start();
    if (!widget.visible) _poll?.cancel();
  }

  @override
  void dispose() {
    _poll?.cancel();
    _input.dispose();
    _scroll.dispose();
    super.dispose();
  }

  void _start() {
    _load();
    _poll?.cancel();
    _poll = Timer.periodic(_pollEvery, (_) => _load(quiet: true));
  }

  Future<void> _load({bool quiet = false}) async {
    if (!quiet) setState(() => _loading = _messages.isEmpty);
    try {
      final messages = await widget.api.supportMessages();
      if (!mounted) return;
      final grew = messages.length != _messages.length;
      setState(() {
        _messages = messages;
        _outbox.removeWhere((clientId, _) => messages.any((message) => message.clientId == clientId));
        _error = null;
      });
      widget.onRead?.call();
      if (grew) _scrollToEnd();
    } on ApiException catch (error) {
      debugPrint('[ZAYLO] Support chat load failed: ${error.debugDescription}');
      if (mounted && !quiet) setState(() => _error = error);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _send([RiderSupportMessage? retry]) async {
    final body = retry?.body ?? _input.text.trim();
    if (body.isEmpty) return;
    final draft = retry?.copyWith(pending: true, failed: false) ??
        RiderSupportMessage(clientId: newUuidV4(), body: body, fromStaff: false, sentAt: DateTime.now(), pending: true);
    setState(() => _outbox[draft.clientId] = draft);
    if (retry == null) _input.clear();
    _scrollToEnd();
    try {
      final saved = await widget.api.sendSupportMessage(body, clientId: draft.clientId);
      if (!mounted) return;
      setState(() {
        _outbox.remove(draft.clientId);
        _messages = [..._messages.where((message) => message.clientId != saved.clientId), saved];
      });
    } on ApiException catch (error) {
      debugPrint('[ZAYLO] Support message failed: ${error.debugDescription}');
      if (!mounted) return;
      setState(() => _outbox[draft.clientId] = draft.copyWith(pending: false, failed: true));
      showMessage(context, error.message);
    }
  }

  void _scrollToEnd() => WidgetsBinding.instance.addPostFrameCallback((_) {
        if (_scroll.hasClients) {
          _scroll.animateTo(_scroll.position.maxScrollExtent,
              duration: const Duration(milliseconds: 250), curve: Curves.easeOut);
        }
      });

  @override
  Widget build(BuildContext context) {
    final thread = [..._messages, ..._outbox.values];
    return Column(children: [
      Expanded(
        child: _loading
            ? const Loader()
            : _error != null && thread.isEmpty
                ? LoadError(error: _error, onRetry: _load, title: 'Could not open support chat')
                : RefreshIndicator(
                    color: ZayloColors.ink,
                    onRefresh: _load,
                    child: ListView(
                      controller: _scroll,
                      physics: const AlwaysScrollableScrollPhysics(),
                      padding: const EdgeInsets.fromLTRB(16, 12, 16, 12),
                      children: [
                        const _SupportIntro(),
                        for (final message in thread) _Bubble(message: message, onRetry: () => _send(message)),
                      ],
                    ),
                  ),
      ),
      SafeArea(
        top: false,
        child: Container(
          padding: const EdgeInsets.fromLTRB(12, 8, 8, 8),
          decoration: BoxDecoration(border: Border(top: BorderSide(color: ZayloColors.line))),
          child: Row(crossAxisAlignment: CrossAxisAlignment.end, children: [
            Expanded(
              child: TextField(
                controller: _input,
                minLines: 1,
                maxLines: 4,
                maxLength: _maxLength,
                textCapitalization: TextCapitalization.sentences,
                decoration: const InputDecoration(
                  hintText: 'Message ZAYLO support',
                  counterText: '',
                  border: OutlineInputBorder(borderRadius: BorderRadius.all(Radius.circular(22))),
                  contentPadding: EdgeInsets.symmetric(horizontal: 16, vertical: 10),
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
      ),
    ]);
  }
}

class _SupportIntro extends StatelessWidget {
  const _SupportIntro();

  @override
  Widget build(BuildContext context) => Container(
        margin: const EdgeInsets.only(bottom: 14),
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(color: ZayloColors.paper, borderRadius: BorderRadius.circular(12)),
        child: const Row(children: [
          Icon(Icons.support_agent_outlined),
          SizedBox(width: 12),
          Expanded(
            child: Text('Message the ZAYLO support team about pickups, deliveries, payments, or your account. '
                'Include the order number if it is about a delivery.'),
          ),
        ]),
      );
}

class _Bubble extends StatelessWidget {
  const _Bubble({required this.message, required this.onRetry});

  final RiderSupportMessage message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    final mine = !message.fromStaff;
    final status = message.failed
        ? 'Not sent · tap to retry'
        : message.pending
            ? 'Sending…'
            : [if (!mine) 'ZAYLO Support', dateTimeLabel(message.sentAt)].join(' · ');
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
            borderRadius: BorderRadius.circular(16),
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
