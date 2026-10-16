import 'dart:async';

import 'package:flutter/material.dart';

import 'package:zaylo_mobile/api/api_exception.dart';
import 'package:zaylo_mobile/components/feedback.dart';
import 'package:zaylo_mobile/theme/zaylo_theme.dart';
import 'package:zaylo_mobile/utils/formatters.dart';
import 'package:zaylo_mobile/utils/ids.dart';

/// One message in a rider–buyer delivery chat. [mine] is relative to whoever
/// is viewing: the rider in the rider app, the buyer in the buyer app.
class DeliveryChatMessage {
  const DeliveryChatMessage({
    required this.clientId,
    required this.body,
    required this.mine,
    this.id,
    this.read = false,
    this.sentAt,
    this.pending = false,
    this.failed = false,
  });

  final int? id;
  final String clientId;
  final String body;
  final bool mine;
  final bool read;
  final DateTime? sentAt;

  /// Local-only states while a send is in flight or after it failed.
  final bool pending;
  final bool failed;

  factory DeliveryChatMessage.fromJson(Map<String, dynamic> json) => DeliveryChatMessage(
        id: json['id'] as int?,
        clientId: json['client_id'] as String? ?? '',
        body: json['body'] as String? ?? '',
        mine: json['mine'] as bool? ?? false,
        read: json['read'] as bool? ?? false,
        sentAt: DateTime.tryParse(json['sent_at'] as String? ?? '')?.toLocal(),
      );

  DeliveryChatMessage copyWith({bool? pending, bool? failed}) => DeliveryChatMessage(
        id: id,
        clientId: clientId,
        body: body,
        mine: mine,
        read: read,
        sentAt: sentAt,
        pending: pending ?? this.pending,
        failed: failed ?? this.failed,
      );
}

/// GET /mobile/rider/deliveries/{id}/chat and GET /mobile/orders/{id}/chat.
class DeliveryChat {
  const DeliveryChat({
    required this.open,
    required this.otherName,
    required this.otherRole,
    required this.messages,
    this.orderNumber,
    this.closedReason,
  });

  /// Messages can be sent only while the delivery is in progress.
  final bool open;
  final String? closedReason;

  /// The other person's first name and role ("Buyer" or "Rider").
  final String otherName;
  final String otherRole;
  final String? orderNumber;
  final List<DeliveryChatMessage> messages;

  factory DeliveryChat.fromJson(Map<String, dynamic> json) => DeliveryChat(
        open: json['open'] as bool? ?? false,
        closedReason: json['closed_reason'] as String?,
        otherName: json['other_name'] as String? ?? '',
        otherRole: json['other_role'] as String? ?? '',
        orderNumber: json['order_number'] as String?,
        messages: (json['messages'] as List<dynamic>? ?? const [])
            .map((item) => DeliveryChatMessage.fromJson(item as Map<String, dynamic>))
            .toList(),
      );
}

/// Chat between the rider delivering an order and the buyer receiving it.
/// The rider and buyer apps pass their own [load] and [send] calls. New
/// messages are fetched every [_pollEvery] while the screen is open.
class DeliveryChatScreen extends StatefulWidget {
  const DeliveryChatScreen({super.key, required this.load, required this.send, this.title = 'Chat'});

  final Future<DeliveryChat> Function() load;
  final Future<DeliveryChatMessage> Function(String body, String clientId) send;

  /// Shown until the chat loads and names the other person.
  final String title;

  @override
  State<DeliveryChatScreen> createState() => _DeliveryChatScreenState();
}

class _DeliveryChatScreenState extends State<DeliveryChatScreen> {
  static const _pollEvery = Duration(seconds: 10);
  static const _maxLength = 2000;

  final _input = TextEditingController();
  final _scroll = ScrollController();
  DeliveryChat? _chat;

  /// Sent but not yet confirmed (or failed), keyed by client id.
  final _outbox = <String, DeliveryChatMessage>{};
  Object? _error;
  Timer? _poll;

  @override
  void initState() {
    super.initState();
    _load();
    _poll = Timer.periodic(_pollEvery, (_) => _load(quiet: true));
  }

  @override
  void dispose() {
    _poll?.cancel();
    _input.dispose();
    _scroll.dispose();
    super.dispose();
  }

  Future<void> _load({bool quiet = false}) async {
    try {
      final chat = await widget.load();
      if (!mounted) return;
      final grew = chat.messages.length != (_chat?.messages.length ?? -1);
      setState(() {
        _chat = chat;
        _outbox.removeWhere((clientId, _) => chat.messages.any((message) => message.clientId == clientId));
        _error = null;
      });
      if (grew) _scrollToEnd();
    } on ApiException catch (error) {
      debugPrint('[ZAYLO] Delivery chat load failed: ${error.debugDescription}');
      if (mounted && !quiet) setState(() => _error = error);
    }
  }

  Future<void> _send([DeliveryChatMessage? retry]) async {
    final body = retry?.body ?? _input.text.trim();
    if (body.isEmpty) return;
    final draft = retry?.copyWith(pending: true, failed: false) ??
        DeliveryChatMessage(clientId: newUuidV4(), body: body, mine: true, sentAt: DateTime.now(), pending: true);
    setState(() => _outbox[draft.clientId] = draft);
    if (retry == null) _input.clear();
    _scrollToEnd();
    try {
      final saved = await widget.send(body, draft.clientId);
      if (!mounted) return;
      final chat = _chat;
      setState(() {
        _outbox.remove(draft.clientId);
        if (chat != null) {
          _chat = DeliveryChat(
            open: chat.open,
            closedReason: chat.closedReason,
            otherName: chat.otherName,
            otherRole: chat.otherRole,
            orderNumber: chat.orderNumber,
            messages: [...chat.messages.where((message) => message.clientId != saved.clientId), saved],
          );
        }
      });
    } on ApiException catch (error) {
      debugPrint('[ZAYLO] Delivery message failed: ${error.debugDescription}');
      if (!mounted) return;
      setState(() => _outbox[draft.clientId] = draft.copyWith(pending: false, failed: true));
      showMessage(context, error.message);
      // The delivery may have just finished; refresh to show the chat as closed.
      _load(quiet: true);
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
    final chat = _chat;
    final thread = [...?chat?.messages, ..._outbox.values];
    return Scaffold(
      appBar: AppBar(
        title: chat == null
            ? Text(widget.title)
            : Column(mainAxisSize: MainAxisSize.min, children: [
                Text(chat.otherName),
                Text(
                  [chat.otherRole, if (chat.orderNumber != null) chat.orderNumber!].join(' · '),
                  style: TextStyle(fontSize: 12, fontWeight: FontWeight.w500, color: ZayloColors.muted),
                ),
              ]),
      ),
      body: chat == null
          ? (_error != null
              ? LoadError(error: _error, onRetry: _load, title: 'Could not open the chat')
              : const Loader())
          : Column(children: [
              Expanded(
                child: RefreshIndicator(
                  color: ZayloColors.ink,
                  onRefresh: _load,
                  child: ListView(
                    controller: _scroll,
                    physics: const AlwaysScrollableScrollPhysics(),
                    padding: const EdgeInsets.fromLTRB(16, 12, 16, 12),
                    children: [
                      _ChatIntro(chat: chat),
                      for (final message in thread)
                        _Bubble(message: message, otherName: chat.otherName, onRetry: () => _send(message)),
                    ],
                  ),
                ),
              ),
              if (chat.open) _composer() else _ClosedNotice(chat.closedReason ?? 'This chat is closed.'),
            ]),
    );
  }

  Widget _composer() => SafeArea(
        top: false,
        child: Container(
          padding: const EdgeInsets.fromLTRB(12, 8, 8, 8),
          decoration: BoxDecoration(
            color: ZayloColors.paper,
            border: Border(top: BorderSide(color: ZayloColors.line)),
          ),
          child: Row(crossAxisAlignment: CrossAxisAlignment.end, children: [
            Expanded(
              child: TextField(
                controller: _input,
                minLines: 1,
                maxLines: 4,
                maxLength: _maxLength,
                textCapitalization: TextCapitalization.sentences,
                decoration: InputDecoration(
                  hintText: 'Message ${_chat?.otherName ?? ''}'.trim(),
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

class _ChatIntro extends StatelessWidget {
  const _ChatIntro({required this.chat});

  final DeliveryChat chat;

  @override
  Widget build(BuildContext context) {
    final aboutRider = chat.otherRole == 'Rider';
    return Container(
      margin: const EdgeInsets.only(bottom: 14),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: ZayloColors.paper,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: ZayloColors.line),
      ),
      child: Row(children: [
        Icon(aboutRider ? Icons.two_wheeler_outlined : Icons.person_pin_circle_outlined, color: ZayloColors.ink),
        const SizedBox(width: 12),
        Expanded(
          child: Text(aboutRider
              ? '${chat.otherName} is delivering your order. Share landmarks, gate codes, or where to leave the parcel.'
              : 'Message ${chat.otherName} about this delivery, like when you are close or if you cannot find the address.'),
        ),
      ]),
    );
  }
}

class _ClosedNotice extends StatelessWidget {
  const _ClosedNotice(this.reason);

  final String reason;

  @override
  Widget build(BuildContext context) => SafeArea(
        top: false,
        child: Container(
          width: double.infinity,
          padding: const EdgeInsets.fromLTRB(20, 14, 20, 14),
          decoration: BoxDecoration(
            color: ZayloColors.paper,
            border: Border(top: BorderSide(color: ZayloColors.line)),
          ),
          child: Row(children: [
            Icon(Icons.lock_outline, size: 18, color: ZayloColors.muted),
            const SizedBox(width: 10),
            Expanded(child: Text(reason, style: const TextStyle(fontSize: 13))),
          ]),
        ),
      );
}

class _Bubble extends StatelessWidget {
  const _Bubble({required this.message, required this.otherName, required this.onRetry});

  final DeliveryChatMessage message;
  final String otherName;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    final mine = message.mine;
    final status = message.failed
        ? 'Not sent · tap to retry'
        : message.pending
            ? 'Sending…'
            : [
                if (!mine) otherName,
                dateTimeLabel(message.sentAt),
                if (mine && message.read) 'Seen',
              ].join(' · ');
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
