import 'dart:async';
import 'dart:math' as math;

import 'package:flutter/material.dart';

import 'package:zaylo_mobile/components/help_bot.dart';
import 'package:zaylo_mobile/components/zay_avatar.dart';
import 'package:zaylo_mobile/features/buyer/account/support_screen.dart';
import 'package:zaylo_mobile/state/app_state.dart';
import 'package:zaylo_mobile/theme/zaylo_theme.dart';

/// Zay, the floating help chatbot (matching partials/faq-bot.blade.php and
/// public/css/faq-bot.css on the website): floats at the bottom corner with
/// Zay's animated face, waves with a "Hi! I'm Zay. Need a hand?" teaser, opens
/// a floating chat panel on tap, can be dragged to either side, and can be
/// hidden by dropping it on the ✕ at the bottom.
class ChatHeadOverlay extends StatefulWidget {
  const ChatHeadOverlay({
    super.key,
    required this.navigatorKey,
    required this.child,
    this.unreadPoll = const Duration(seconds: 15),
    this.teaserDelay = const Duration(seconds: 4),
  });

  /// The app's navigator; the overlay sits above it, outside its routes.
  final GlobalKey<NavigatorState> navigatorKey;
  final Widget child;

  /// How often the top-bar Messages badge checks for new seller messages.
  final Duration unreadPoll;

  /// How long after sign-in before Zay waves and shows the teaser bubble.
  final Duration teaserDelay;

  @override
  State<ChatHeadOverlay> createState() => _ChatHeadOverlayState();
}

class _ChatHeadOverlayState extends State<ChatHeadOverlay> with TickerProviderStateMixin, WidgetsBindingObserver {
  static const _size = 62.0;
  static const _edge = 16.0;

  /// Clear of the floating bottom navigation bar.
  static const _bottomClearance = 104.0;

  Offset? _position;
  bool _dragging = false;
  bool _overTarget = false;
  bool _panelOpen = false;
  bool _panelActivated = false;
  bool _teaserVisible = false;
  bool _teaserSeen = false;
  int _teaserTicks = 0;
  ZayMood _mood = ZayMood.waving;

  Timer? _poll;
  Timer? _teaserPoll;

  /// Glides Zay to the nearest side after a drag.
  late final AnimationController _snap;
  late final Animation<double> _snapCurve;
  Tween<Offset>? _snapTween;

  /// Smooth 280ms expansion and collapse for the floating chat panel.
  late final AnimationController _panelController;
  late final Animation<double> _panelAnimation;

  AppState get _app => AppScope.read(context);

  @override
  void initState() {
    super.initState();
    _snap = AnimationController(vsync: this, duration: const Duration(milliseconds: 300));
    _snapCurve = CurvedAnimation(parent: _snap, curve: Curves.easeOutCubic);
    _snap.addListener(() => setState(() => _position = _snapTween?.evaluate(_snapCurve)));

    _panelController = AnimationController(vsync: this, duration: const Duration(milliseconds: 280));
    _panelAnimation = CurvedAnimation(
      parent: _panelController,
      curve: Curves.easeOut,
      reverseCurve: Curves.easeIn,
    );
    _panelController.addStatusListener((_) {
      if (mounted) setState(() {});
    });

    WidgetsBinding.instance.addObserver(this);
    _poll = Timer.periodic(widget.unreadPoll, (_) => _app.refreshChatUnread());
    _teaserPoll = Timer.periodic(widget.teaserDelay, (_) => _tickTeaser());
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _poll?.cancel();
    _teaserPoll?.cancel();
    _snap.dispose();
    _panelController.dispose();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      _app.refreshChatUnread();
      _poll ??= Timer.periodic(widget.unreadPoll, (_) => _app.refreshChatUnread());
    } else if (state == AppLifecycleState.paused) {
      _poll?.cancel();
      _poll = null;
    }
  }

  void _tickTeaser() {
    if (!mounted) return;
    final app = _app;
    final visible = app.signedIn && !app.chatHead.dismissed && !app.chatHead.hidden;
    if (!visible) return;
    _teaserTicks++;
    if (_teaserTicks == 1 && !_panelOpen && !_teaserSeen) {
      setState(() {
        _teaserVisible = true;
        _mood = ZayMood.waving;
      });
    } else if (_teaserTicks == 2 && !_panelOpen) {
      setState(() => _mood = ZayMood.idle);
    } else if (_teaserTicks >= 3) {
      _hideTeaser();
      _teaserPoll?.cancel();
      _teaserPoll = null;
    }
  }

  void _hideTeaser() {
    _teaserSeen = true;
    if (mounted && (_teaserVisible || _mood == ZayMood.waving)) {
      setState(() {
        _teaserVisible = false;
        if (!_panelOpen) _mood = ZayMood.idle;
      });
    }
  }

  bool get _reduceMotion => MediaQuery.maybeDisableAnimationsOf(context) ?? false;

  Rect _bounds(Size screen, EdgeInsets padding) => Rect.fromLTRB(
        _edge,
        padding.top + _edge,
        math.max(_edge, screen.width - _size - _edge),
        math.max(padding.top + _edge, screen.height - padding.bottom - _size - _bottomClearance),
      );

  Offset _targetCenter(Size screen, EdgeInsets padding) => Offset(screen.width / 2, screen.height - padding.bottom - 72);

  void _togglePanel() {
    if (_panelOpen) {
      _closePanel();
    } else {
      _openPanel();
    }
  }

  void _openPanel() {
    _hideTeaser();
    setState(() {
      _panelOpen = true;
      _panelActivated = true;
    });
    if (_reduceMotion) {
      _panelController.value = 1;
    } else {
      _panelController.forward();
    }
  }

  void _closePanel() {
    if (!_panelOpen) return;
    FocusManager.instance.primaryFocus?.unfocus();
    setState(() => _panelOpen = false);
    if (_reduceMotion) {
      _panelController.value = 0;
    } else {
      _panelController.reverse();
    }
  }

  void _openSupport() {
    _closePanel();
    final navigator = widget.navigatorKey.currentState;
    if (navigator == null) return;
    navigator.push(MaterialPageRoute(builder: (_) => const SupportScreen()));
  }

  void _dragStart(DragStartDetails details) {
    _snap.stop();
    if (_panelOpen) _closePanel();
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
        _position = null;
        _panelOpen = false;
        _teaserVisible = false;
      });
      _panelController.value = 0;
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
    final bounds = _bounds(screen, padding);
    final current = _position ?? Offset(bounds.left, bounds.bottom);
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

    return ListenableBuilder(
      listenable: app.chatHead,
      builder: (context, _) {
        final head = app.chatHead;
        final visible = app.signedIn && !head.dismissed && !head.hidden;
        if (!visible && _panelOpen) {
          WidgetsBinding.instance.addPostFrameCallback((_) {
            if (mounted && _panelOpen) _closePanel();
          });
        }

        return LayoutBuilder(builder: (context, constraints) {
          final screen = constraints.biggest;
          final padding = MediaQuery.paddingOf(context);
          final viewInsets = MediaQuery.viewInsetsOf(context);
          final bounds = _bounds(screen, padding);
          // Default to the bottom-left like .faq-bot__toggle on the website.
          final position = _position ?? Offset(bounds.left, bounds.bottom);
          final target = _targetCenter(screen, padding);
          final onLeft = position.dx + _size / 2 <= screen.width / 2;

          final panelWidth = math.min(370.0, math.max(260.0, screen.width - 32));
          final panelLeft = (onLeft
                  ? position.dx.clamp(16.0, math.max(16.0, screen.width - panelWidth - 16))
                  : (position.dx + _size - panelWidth).clamp(16.0, math.max(16.0, screen.width - panelWidth - 16)))
              .toDouble();
          final bottomAnchor = math.max(
            screen.height - position.dy + 12,
            viewInsets.bottom + padding.bottom + 12,
          );
          final availableHeight = screen.height - padding.top - bottomAnchor - 16;
          final panelHeight = math.min(480.0, math.max(240.0, availableHeight));
          final showPanel = visible && (_panelOpen || !_panelController.isDismissed);

          return Overlay.wrap(
            child: Stack(children: [
              widget.child,
              if (visible && _dragging)
                Positioned(
                  key: const ValueKey('chat-head-dismiss-target'),
                  left: target.dx - 32,
                  top: target.dy - 32,
                  child: IgnorePointer(child: _DismissTarget(active: _overTarget)),
                ),
              if (visible && _teaserVisible && !_panelOpen && !_dragging)
                Positioned(
                  key: const ValueKey('faq-bot-teaser'),
                  left: onLeft ? position.dx : null,
                  right: onLeft ? null : screen.width - (position.dx + _size),
                  bottom: screen.height - position.dy + 10,
                  child: _TeaserBubble(
                    onLeft: onLeft,
                    onOpen: _openPanel,
                    onDismiss: _hideTeaser,
                  ),
                ),
              if (_panelActivated)
                Positioned(
                  key: const ValueKey('faq-bot-panel'),
                  left: panelLeft,
                  bottom: bottomAnchor,
                  width: panelWidth,
                  height: panelHeight,
                  child: Offstage(
                    offstage: !showPanel,
                    child: ExcludeSemantics(
                      excluding: !_panelOpen,
                      child: ExcludeFocus(
                        excluding: !_panelOpen,
                        child: IgnorePointer(
                          ignoring: !_panelOpen,
                          child: AnimatedBuilder(
                            animation: _panelAnimation,
                            builder: (context, child) {
                              final t = _panelAnimation.value;
                              return Opacity(
                                opacity: t.clamp(0.0, 1.0),
                                child: Transform.translate(
                                  offset: Offset(0, 14 * (1 - t)),
                                  child: Transform.scale(
                                    scale: 0.96 + 0.04 * t,
                                    alignment: onLeft ? Alignment.bottomLeft : Alignment.bottomRight,
                                    child: child,
                                  ),
                                ),
                              );
                            },
                            child: HelpBotPanel(
                              api: app.api,
                              active: _panelActivated,
                              onClose: _closePanel,
                              onContactSupport: _openSupport,
                              onMoodChanged: (mood) {
                                if (mounted && _mood != mood) setState(() => _mood = mood);
                              },
                            ),
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              if (visible)
                Positioned(
                  key: const ValueKey('chat-head-position'),
                  left: position.dx,
                  top: position.dy,
                  child: GestureDetector(
                    key: const ValueKey('chat-head'),
                    onTap: _togglePanel,
                    onPanStart: _dragStart,
                    onPanUpdate: (details) {
                      _position ??= position;
                      _dragUpdate(details, screen, padding);
                    },
                    onPanEnd: (_) => _dragEnd(screen, padding),
                    child: _ZayToggleButton(
                      mood: _mood,
                      panelOpen: _panelOpen,
                      dragging: _dragging,
                      size: _size,
                    ),
                  ),
                ),
            ]),
          );
        });
      },
    );
  }
}

/// The circular floating button (.faq-bot__toggle on the website) holding Zay.
class _ZayToggleButton extends StatelessWidget {
  const _ZayToggleButton({
    required this.mood,
    required this.panelOpen,
    required this.dragging,
    required this.size,
  });

  final ZayMood mood;
  final bool panelOpen;
  final bool dragging;
  final double size;

  @override
  Widget build(BuildContext context) => Semantics(
        button: true,
        toggled: panelOpen,
        label: panelOpen ? 'Close ZAYLO help' : 'Ask Zay, open ZAYLO help',
        hint: 'Drag to move. Drop on the close button at the bottom to hide.',
        child: Material(
          type: MaterialType.transparency,
          child: AnimatedContainer(
            duration: const Duration(milliseconds: 200),
            curve: Curves.easeOut,
            width: size,
            height: size,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: ZayloColors.isDark ? ZayloColors.sand : const Color(0xFFF5F0EA),
              border: Border.all(
                color: ZayloColors.isDark ? ZayloColors.paper : const Color(0xFFFFFDFB),
                width: 3,
              ),
              boxShadow: [
                BoxShadow(
                  color: const Color(0x38241A16),
                  blurRadius: dragging ? 28 : 22,
                  offset: Offset(0, dragging ? 10 : 6),
                ),
              ],
            ),
            child: ZayAvatar(
              size: 46,
              mood: mood,
              float: !panelOpen && !dragging,
            ),
          ),
        ),
      );
}

/// The speech bubble above Zay ("Hi! I'm Zay. Need a hand?"), matching .faq-bot__teaser.
class _TeaserBubble extends StatelessWidget {
  const _TeaserBubble({
    required this.onLeft,
    required this.onOpen,
    required this.onDismiss,
  });

  final bool onLeft;
  final VoidCallback onOpen;
  final VoidCallback onDismiss;

  @override
  Widget build(BuildContext context) => TweenAnimationBuilder<double>(
        tween: Tween(begin: MediaQuery.maybeDisableAnimationsOf(context) ?? false ? 1 : 0, end: 1),
        duration: const Duration(milliseconds: 320),
        curve: Curves.easeOut,
        builder: (context, value, child) => Opacity(
          opacity: value,
          child: Transform.translate(offset: Offset(0, 6 * (1 - value)), child: child),
        ),
        child: Material(
          color: Colors.transparent,
          child: Container(
            padding: const EdgeInsets.fromLTRB(14, 8, 8, 8),
            decoration: BoxDecoration(
              color: ZayloColors.white,
              border: Border.all(color: ZayloColors.line),
              borderRadius: BorderRadius.only(
                topLeft: const Radius.circular(14),
                topRight: const Radius.circular(14),
                bottomLeft: Radius.circular(onLeft ? 4 : 14),
                bottomRight: Radius.circular(onLeft ? 14 : 4),
              ),
              boxShadow: const [BoxShadow(color: Color(0x26241A16), blurRadius: 24, offset: Offset(0, 8))],
            ),
            child: Row(mainAxisSize: MainAxisSize.min, children: [
              GestureDetector(
                onTap: onOpen,
                child: Text(
                  "Hi! I'm Zay. Need a hand?",
                  style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: ZayloColors.ink),
                ),
              ),
              const SizedBox(width: 6),
              SizedBox(
                width: 24,
                height: 24,
                child: IconButton(
                  padding: EdgeInsets.zero,
                  iconSize: 16,
                  tooltip: 'Dismiss',
                  onPressed: onDismiss,
                  icon: Icon(Icons.close_rounded, color: ZayloColors.muted),
                ),
              ),
            ]),
          ),
        ),
      );
}

/// The ✕ that appears at the bottom while dragging; it grows when Zay is over it.
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
