import 'dart:async';
import 'dart:math' as math;

import 'package:flutter/material.dart';

import 'package:zaylo_mobile/components/layout.dart';
import 'package:zaylo_mobile/theme/zaylo_theme.dart';

/// Wraps a bag icon (the Bag tab, the product page's bag button) so an added product can fly into it. When the
/// product lands, the icon pops. The visible target on the current screen is picked automatically.
class CartTarget extends StatefulWidget {
  const CartTarget({super.key, required this.child});

  final Widget child;

  @override
  State<CartTarget> createState() => _CartTargetState();
}

class _CartTargetState extends State<CartTarget> with SingleTickerProviderStateMixin {
  /// Mounted targets, newest last.
  static final _targets = <_CartTargetState>[];

  late final _bump = AnimationController(vsync: this, duration: const Duration(milliseconds: 420));

  /// 1 → 1.35 → 0.92 → 1: a quick pop with a small settle.
  late final _scale = TweenSequence<double>([
    TweenSequenceItem(tween: Tween(begin: 1.0, end: 1.35).chain(CurveTween(curve: Curves.easeOut)), weight: 35),
    TweenSequenceItem(tween: Tween(begin: 1.35, end: 0.92).chain(CurveTween(curve: Curves.easeInOut)), weight: 35),
    TweenSequenceItem(tween: Tween(begin: 0.92, end: 1.0).chain(CurveTween(curve: Curves.easeOut)), weight: 30),
  ]).animate(_bump);

  @override
  void initState() {
    super.initState();
    _targets.add(this);
  }

  @override
  void dispose() {
    _targets.remove(this);
    _bump.dispose();
    super.dispose();
  }

  /// Where the icon is on screen, when it is showing on the current screen.
  Rect? get _rect {
    if (!mounted || !(ModalRoute.of(context)?.isCurrent ?? true)) return null;
    final box = context.findRenderObject();
    if (box is! RenderBox || !box.attached || !box.hasSize || box.size.isEmpty) return null;
    return box.localToGlobal(Offset.zero) & box.size;
  }

  void pop() {
    if (!mounted || MediaQuery.disableAnimationsOf(context)) return;
    _bump.forward(from: 0);
  }

  static _CartTargetState? get visible {
    for (final target in _targets.reversed) {
      if (target._rect != null) return target;
    }
    return null;
  }

  @override
  Widget build(BuildContext context) => ScaleTransition(scale: _scale, child: widget.child);
}

/// The add-to-bag animation: the product's photo lifts off where the buyer tapped and arcs into the bag icon,
/// which pops when it lands. With reduced motion, only the confirmation message shows.
abstract final class CartFlight {
  static const duration = Duration(milliseconds: 720);

  /// Where [context]'s widget is on screen, to start a flight from. Read it before awaiting the server, while
  /// the widget is still laid out.
  static Rect? rectOf(BuildContext? context) {
    final box = context?.findRenderObject();
    if (box is! RenderBox || !box.attached || !box.hasSize) return null;
    return box.localToGlobal(Offset.zero) & box.size;
  }

  /// Flies [imageUrl] from [from] into the visible bag icon. Completes when it lands (right away when there is
  /// nothing to fly to, or animations are off).
  static Future<void> fly(BuildContext context, {required Rect? from, required String? imageUrl}) async {
    final target = _CartTargetState.visible;
    final to = target?._rect;
    if (target == null || to == null) return;
    if (from == null || MediaQuery.disableAnimationsOf(context)) {
      target.pop();
      return;
    }
    final overlay = Overlay.maybeOf(context, rootOverlay: true);
    if (overlay == null) {
      target.pop();
      return;
    }
    late final OverlayEntry entry;
    final landed = Completer<void>();
    entry = OverlayEntry(
      builder: (_) => _Flight(
        from: from.center,
        to: to.center,
        imageUrl: imageUrl,
        onDone: () {
          entry.remove();
          landed.complete();
        },
      ),
    );
    overlay.insert(entry);
    await landed.future;
    target.pop();
  }
}

class _Flight extends StatefulWidget {
  const _Flight({required this.from, required this.to, required this.imageUrl, required this.onDone});

  final Offset from;
  final Offset to;
  final String? imageUrl;
  final VoidCallback onDone;

  @override
  State<_Flight> createState() => _FlightState();
}

class _FlightState extends State<_Flight> with SingleTickerProviderStateMixin {
  static const _startSize = 64.0;
  static const _endSize = 18.0;

  late final _controller = AnimationController(vsync: this, duration: CartFlight.duration)
    ..addStatusListener((status) {
      if (status == AnimationStatus.completed) widget.onDone();
    })
    ..forward();
  late final _progress = CurvedAnimation(parent: _controller, curve: Curves.easeInOutCubic);

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  /// A point on the arc: a quadratic curve that rises above both ends before dropping into the bag.
  Offset _pointAt(double t) {
    final from = widget.from;
    final to = widget.to;
    final control = Offset((from.dx + to.dx) / 2, math.min(from.dy, to.dy) - 140);
    final u = 1 - t;
    return from * (u * u) + control * (2 * u * t) + to * (t * t);
  }

  @override
  Widget build(BuildContext context) => IgnorePointer(
        child: AnimatedBuilder(
          animation: _progress,
          builder: (context, child) {
            final t = _progress.value;
            final size = _startSize + (_endSize - _startSize) * t;
            final center = _pointAt(t);
            return Stack(children: [
              Positioned(
                left: center.dx - size / 2,
                top: center.dy - size / 2,
                width: size,
                height: size,
                child: Opacity(opacity: t < .85 ? 1 : (1 - (t - .85) / .15 * .6), child: child),
              ),
            ]);
          },
          child: DecoratedBox(
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              border: Border.all(color: ZayloColors.white, width: 2),
              boxShadow: const [BoxShadow(color: Color(0x40241A16), blurRadius: 14, offset: Offset(0, 6))],
            ),
            child: ClipOval(child: NetworkImageBox(url: widget.imageUrl)),
          ),
        ),
      );
}
