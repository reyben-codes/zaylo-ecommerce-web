import 'dart:async';
import 'dart:math' as math;

import 'package:flutter/material.dart';

import 'package:zaylo_mobile/api/api_exception.dart';
import 'package:zaylo_mobile/components/chat/typing_dots.dart';
import 'package:zaylo_mobile/features/buyer/messages/seller_chat_screen.dart';
import 'package:zaylo_mobile/features/buyer/messages/seller_inbox_screen.dart';
import 'package:zaylo_mobile/models/buyer/seller_chat_models.dart';
import 'package:zaylo_mobile/state/app_state.dart';
import 'package:zaylo_mobile/theme/zaylo_theme.dart';

/// A Messenger-style chat head floating over the buyer screens, like the
/// website's chat bubble: it shows unread messages, can be dragged anywhere
/// and glides to the nearest side, and is dismissed by dropping it on the ✕
/// that appears at the bottom while dragging (opening Messages brings it back).
/// After a chat it shows that person and reopens the conversation on tap.
class ChatHeadOverlay extends StatefulWidget {
  const ChatHeadOverlay({
    super.key,
    required this.navigatorKey,
    required this.child,
    this.unreadPoll = const Duration(seconds: 15),
    this.typingPoll = const Duration(seconds: 3),
  });

  /// The app's navigator; the overlay sits above it, outside its routes.
  final GlobalKey<NavigatorState> navigatorKey;
  final Widget child;

  /// How often the unread badge checks for new messages while the app is open.
  final Duration unreadPoll;

  /// How often the head checks whether someone is typing to the buyer (the website's bubble checks as often).
  final Duration typingPoll;

  @override
  State<ChatHeadOverlay> createState() => _ChatHeadOverlayState();
}

class _ChatHeadOverlayState extends State<ChatHeadOverlay> with TickerProviderStateMixin, WidgetsBindingObserver {
  static const _size = 58.0;
  static const _edge = 10.0;

  /// Clear of the floating bottom navigation bar.
  static const _bottomClearance = 104.0;

  Offset? _position;
  bool _dragging = false;
  bool _overTarget = false;
  int _lastUnread = 0;
  Timer? _poll;

  /// People typing to the buyer right now; the head shows typing dots beside it.
  List<ChatTyping> _typing = const [];
  Timer? _typingPoll;
  bool _checkingTyping = false;

  /// Glides the head to the nearest side after a drag.
  late final AnimationController _snap;
  late final Animation<double> _snapCurve;
  Tween<Offset>? _snapTween;

  /// A little bounce when a new message arrives.
  late final AnimationController _bounce;

  AppState get _app => AppScope.read(context);

  @override
  void initState() {
    super.initState();
    // Created up front, not lazily: dispose() must never be the first to touch them.
    _snap = AnimationController(vsync: this, duration: const Duration(milliseconds: 300));
    _snapCurve = CurvedAnimation(parent: _snap, curve: Curves.easeOutCubic);
    _snap.addListener(() => setState(() => _position = _snapTween?.evaluate(_snapCurve)));
    _bounce = AnimationController(vsync: this, duration: const Duration(milliseconds: 520));
    WidgetsBinding.instance.addObserver(this);
    _poll = Timer.periodic(widget.unreadPoll, (_) => _app.refreshChatUnread());
    _typingPoll = Timer.periodic(widget.typingPoll, (_) => _checkTyping());
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _poll?.cancel();
    _typingPoll?.cancel();
    _snap.dispose();
    _bounce.dispose();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      _app.refreshChatUnread();
      _poll ??= Timer.periodic(widget.unreadPoll, (_) => _app.refreshChatUnread());
      _typingPoll ??= Timer.periodic(widget.typingPoll, (_) => _checkTyping());
    } else if (state == AppLifecycleState.paused) {
      _poll?.cancel();
      _poll = null;
      _typingPoll?.cancel();
      _typingPoll = null;
    }
  }

  /// Asks only while the head is on screen; an open chat shows its own typing indicator.
  Future<void> _checkTyping() async {
    final app = _app;
    final head = app.chatHead;
    if (!app.signedIn || head.dismissed || head.hidden) {
      if (_typing.isNotEmpty) setState(() => _typing = const []);
      return;
    }
    if (_checkingTyping) return;
    _checkingTyping = true;
    try {
      final typing = await app.api.chatTyping();
      final changed = typing.map((t) => t.conversationId).join(',') != _typing.map((t) => t.conversationId).join(',');
      if (mounted && changed) setState(() => _typing = typing);
    } on ApiException {
      // Offline: the next check tries again.
    } finally {
      _checkingTyping = false;
    }
  }

  String? get _typingLabel => switch (_typing.length) {
        0 => null,
        1 => '${_typing.first.name} is typing',
        final count => '$count people are typing',
      };

  bool get _reduceMotion => MediaQuery.disableAnimationsOf(context);

  Rect _bounds(Size screen, EdgeInsets padding) => Rect.fromLTRB(
        _edge,
        padding.top + _edge,
        screen.width - _size - _edge,
        math.max(padding.top + _edge, screen.height - padding.bottom - _size - _bottomClearance),
      );

  Offset _targetCenter(Size screen, EdgeInsets padding) => Offset(screen.width / 2, screen.height - padding.bottom - 72);

  Future<void> _open() async {
    final navigator = widget.navigatorKey.currentState;
    if (navigator == null) return;
    final head = _app.chatHead;
    // Someone typing right now takes priority: open straight into that chat.
    final typing = _typing.isNotEmpty ? _typing.first : null;
    final conversation = typing?.conversationId ?? head.conversationId;
    final title = typing?.name ?? head.partnerName;
    setState(() => _typing = const []);
    await navigator.push(MaterialPageRoute(
      builder: (_) => conversation != null
          ? SellerChatScreen(conversation: SellerConversation(id: conversation, name: title ?? 'Seller'))
          : const SellerInboxScreen(),
    ));
    if (mounted) _app.refreshChatUnread();
  }

  void _dragStart(DragStartDetails details) {
    _snap.stop();
    setState(() => _dragging = true);
  }

  void _dragUpdate(DragUpdateDetails details, Size screen, EdgeInsets padding) {
    final next = (_position ?? Offset.zero) + details.delta;
    final center = next + const Offset(_size / 2, _size / 2);
    setState(() {
      _position = Offset(next.dx.clamp(0, screen.width - _size), next.dy.clamp(padding.top, screen.height - _size));
      _overTarget = (center - _targetCenter(screen, padding)).distance < 64;
    });
  }

  void _dragEnd(Size screen, EdgeInsets padding) {
    if (_overTarget) {
      setState(() {
        _dragging = false;
        _overTarget = false;
        _position = null; // back to its default spot when it returns
        _typing = const [];
      });
      _app.chatHead.dismiss();
      final messenger = ScaffoldMessenger.maybeOf(widget.navigatorKey.currentContext ?? context);
      messenger
        ?..hideCurrentSnackBar()
        ..showSnackBar(const SnackBar(
          behavior: SnackBarBehavior.floating,
          content: Text('Chat bubble hidden. Open Messages from your account to bring it back.'),
        ));
      return;
    }
    // Glide to the nearest side, like Messenger.
    final bounds = _bounds(screen, padding);
    final current = _position ?? bounds.bottomRight;
    final goal = Offset(
      current.dx + _size / 2 < screen.width / 2 ? bounds.left : bounds.right,
      current.dy.clamp(bounds.top, bounds.bottom),
    );
    setState(() => _dragging = false);
    if (_reduceMotion) {
      setState(() => _position = goal);
    } else {
      _snapTween = Tween(begin: current, end: goal);
      _snap.forward(from: 0);
    }
  }

  @override
  Widget build(BuildContext context) {
    final app = AppScope.of(context);
    // A new unread message makes the head bounce (started after this frame is built).
    if (app.chatUnread > _lastUnread && !_reduceMotion) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) _bounce.forward(from: 0);
      });
    }
    _lastUnread = app.chatUnread;

    return ListenableBuilder(
      listenable: app.chatHead,
      builder: (context, _) {
        final head = app.chatHead;
        final visible = app.signedIn && !head.dismissed && !head.hidden;
        return LayoutBuilder(builder: (context, constraints) {
          final screen = constraints.biggest;
          final padding = MediaQuery.paddingOf(context);
          final bounds = _bounds(screen, padding);
          final position = _position ?? Offset(bounds.right, bounds.top + (bounds.height) * 0.55);
          final target = _targetCenter(screen, padding);
          // The typing dots sit on the side of the head that faces the middle of the screen.
          final dotsOnLeft = position.dx + _size / 2 > screen.width / 2;
          final headTop = position.dy + 4;

          // Keys keep the head's gesture alive while the close target appears and disappears mid-drag.
          return Stack(children: [
            widget.child,
            if (visible && _dragging)
              Positioned(
                key: const ValueKey('chat-head-dismiss-target'),
                left: target.dx - 32,
                top: target.dy - 32,
                child: IgnorePointer(child: _DismissTarget(active: _overTarget)),
              ),
            if (visible && _typing.isNotEmpty && !_dragging)
              Positioned(
                key: const ValueKey('chat-head-typing'),
                left: dotsOnLeft ? position.dx - _TypingBubble.width - 4 : position.dx + _size + 14,
                top: headTop + _size / 2 - _TypingBubble.height / 2,
                child: IgnorePointer(child: _TypingBubble(tailOnRight: dotsOnLeft)),
              ),
            if (visible)
              Positioned(
                key: const ValueKey('chat-head-position'),
                left: position.dx,
                top: position.dy,
                child: GestureDetector(
                  key: const ValueKey('chat-head'),
                  onTap: _open,
                  onPanStart: _dragStart,
                  onPanUpdate: (details) {
                    _position ??= position;
                    _dragUpdate(details, screen, padding);
                  },
                  onPanEnd: (_) => _dragEnd(screen, padding),
                  child: AnimatedBuilder(
                    animation: _bounce,
                    builder: (context, child) =>
                        Transform.scale(scale: 1 + 0.16 * math.sin(math.pi * _bounce.value), child: child),
                    child: _ChatHead(
                      unread: app.chatUnread,
                      partnerName: head.partnerName,
                      typingLabel: _typingLabel,
                      dragging: _dragging,
                      size: _size,
                    ),
                  ),
                ),
              ),
          ]);
        });
      },
    );
  }
}

class _ChatHead extends StatelessWidget {
  const _ChatHead({
    required this.unread,
    required this.partnerName,
    required this.dragging,
    required this.size,
    this.typingLabel,
  });

  final int unread;
  final String? partnerName;
  final bool dragging;
  final double size;

  /// "Ana is typing", while someone types to the buyer.
  final String? typingLabel;

  @override
  Widget build(BuildContext context) {
    final partner = partnerName;
    final label = partner == null ? 'Open messages' : 'Open chat with $partner';
    final withUnread = unread > 0 ? '$label, $unread unread' : label;
    return Semantics(
      button: true,
      liveRegion: typingLabel != null,
      label: typingLabel == null ? withUnread : '$withUnread. $typingLabel',
      hint: 'Drag to move. Drop on the close button at the bottom to hide.',
      child: Material(
        type: MaterialType.transparency,
        child: SizedBox(
          width: size + 8,
          height: size + 8,
          child: Stack(clipBehavior: Clip.none, children: [
            Positioned(
              left: 4,
              top: 4,
              child: AnimatedContainer(
                duration: const Duration(milliseconds: 150),
                width: size,
                height: size,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: partner == null ? ZayloColors.ink : ZayloColors.sand,
                  border: Border.all(color: ZayloColors.white, width: 2.5),
                  boxShadow: [
                    BoxShadow(
                      color: const Color(0x40000000),
                      blurRadius: dragging ? 18 : 10,
                      offset: Offset(0, dragging ? 8 : 4),
                    ),
                  ],
                ),
                alignment: Alignment.center,
                child: partner == null
                    ? Icon(Icons.chat_bubble_rounded, color: ZayloColors.white, size: 26)
                    : Text(_initials(partner),
                        style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: ZayloColors.ink)),
              ),
            ),
            // The person's face carries a small chat mark, as on the website.
            if (partner != null)
              Positioned(
                right: 0,
                bottom: 0,
                child: Container(
                  width: 22,
                  height: 22,
                  decoration: BoxDecoration(
                    color: ZayloColors.ink,
                    shape: BoxShape.circle,
                    border: Border.all(color: ZayloColors.white, width: 2),
                  ),
                  child: Icon(Icons.chat_bubble_rounded, color: ZayloColors.white, size: 11),
                ),
              ),
            if (unread > 0)
              Positioned(
                right: 0,
                top: 0,
                child: Container(
                  constraints: const BoxConstraints(minWidth: 22),
                  height: 22,
                  padding: const EdgeInsets.symmetric(horizontal: 6),
                  decoration: BoxDecoration(
                    color: const Color(0xFFE53935),
                    borderRadius: BorderRadius.circular(11),
                    border: Border.all(color: ZayloColors.white, width: 2),
                  ),
                  alignment: Alignment.center,
                  child: Text(unread > 99 ? '99+' : '$unread',
                      style: TextStyle(color: ZayloColors.white, fontSize: 11, fontWeight: FontWeight.w800, height: 1)),
                ),
              ),
          ]),
        ),
      ),
    );
  }

  static String _initials(String name) {
    final parts = name.trim().split(RegExp(r'\s+')).where((part) => part.isNotEmpty).toList();
    if (parts.isEmpty) return '?';
    return (parts.first[0] + (parts.length > 1 ? parts.last[0] : '')).toUpperCase();
  }
}

/// A little speech bubble of typing dots beside the head, like Messenger's chat heads.
class _TypingBubble extends StatelessWidget {
  const _TypingBubble({required this.tailOnRight});

  static const width = 62.0;
  static const height = 34.0;

  /// The pointed corner faces the head.
  final bool tailOnRight;

  @override
  Widget build(BuildContext context) => TweenAnimationBuilder<double>(
        tween: Tween(begin: MediaQuery.disableAnimationsOf(context) ? 1 : 0.8, end: 1),
        duration: const Duration(milliseconds: 220),
        curve: Curves.easeOutBack,
        builder: (context, scale, child) => Transform.scale(scale: scale, child: child),
        child: Container(
          width: width,
          height: height,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: ZayloColors.white,
            border: Border.all(color: ZayloColors.line),
            borderRadius: BorderRadius.only(
              topLeft: const Radius.circular(17),
              topRight: const Radius.circular(17),
              bottomLeft: Radius.circular(tailOnRight ? 17 : 4),
              bottomRight: Radius.circular(tailOnRight ? 4 : 17),
            ),
            boxShadow: const [BoxShadow(color: Color(0x33241A16), blurRadius: 14, offset: Offset(0, 4))],
          ),
          child: TypingDots(color: ZayloColors.taupe),
        ),
      );
}

/// The ✕ that appears at the bottom while dragging; it grows when the head is over it.
class _DismissTarget extends StatelessWidget {
  const _DismissTarget({required this.active});

  final bool active;

  @override
  Widget build(BuildContext context) => AnimatedScale(
        scale: active ? 1.2 : 1,
        duration: const Duration(milliseconds: 150),
        child: Container(
          width: 64,
          height: 64,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            color: active ? const Color(0xFFE53935) : const Color(0xCC1A1714),
            boxShadow: const [BoxShadow(color: Color(0x33000000), blurRadius: 12, offset: Offset(0, 4))],
          ),
          child: Icon(Icons.close_rounded, color: ZayloColors.white, size: 30),
        ),
      );
}
