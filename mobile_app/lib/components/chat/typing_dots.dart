import 'dart:math' as math;

import 'package:flutter/material.dart';

import 'package:zaylo_mobile/theme/zaylo_theme.dart';

/// Three dots that rise and fall in turn — the typing animation used in a chat
/// and on the floating chat head. Shown still when the phone turns animations off.
class TypingDots extends StatefulWidget {
  const TypingDots({super.key, this.color});

  /// Defaults to the muted text color of the current palette.
  final Color? color;

  @override
  State<TypingDots> createState() => _TypingDotsState();
}

class _TypingDotsState extends State<TypingDots> with SingleTickerProviderStateMixin {
  late final AnimationController _controller;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(vsync: this, duration: const Duration(milliseconds: 1200));
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    // Respect the phone's "remove animations" setting.
    if (MediaQuery.disableAnimationsOf(context)) {
      _controller.stop();
    } else if (!_controller.isAnimating) {
      _controller.repeat();
    }
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AnimatedBuilder(
        animation: _controller,
        builder: (context, _) => Row(mainAxisSize: MainAxisSize.min, children: [
          for (var dot = 0; dot < 3; dot++) ...[
            if (dot > 0) const SizedBox(width: 5),
            _dot(dot),
          ],
        ]),
      );

  /// Each dot rises in turn: a sine bump offset by a third of the cycle.
  Widget _dot(int index) {
    final phase = (_controller.value - index / 3) % 1;
    final lift = phase < 0.5 ? math.sin(phase * 2 * math.pi) : 0.0;
    return Transform.translate(
      offset: Offset(0, -4 * lift),
      child: Opacity(
        opacity: 0.45 + 0.55 * lift,
        child: Container(
          width: 7,
          height: 7,
          decoration: BoxDecoration(color: widget.color ?? ZayloColors.muted, shape: BoxShape.circle),
        ),
      ),
    );
  }
}

/// The other person is typing: the typing dots inside a bubble shaped like
/// theirs, with their name beside it.
class TypingIndicator extends StatelessWidget {
  const TypingIndicator({super.key, required this.name});

  final String name;

  @override
  Widget build(BuildContext context) => Semantics(
        liveRegion: true,
        label: '$name is typing',
        child: ExcludeSemantics(
          child: Padding(
            padding: const EdgeInsets.only(top: 3, bottom: 8),
            child: Row(children: [
              DecoratedBox(
                decoration: BoxDecoration(
                  color: ZayloColors.sand,
                  borderRadius: const BorderRadius.only(
                    topLeft: Radius.circular(16),
                    topRight: Radius.circular(16),
                    bottomRight: Radius.circular(16),
                    bottomLeft: Radius.circular(4),
                  ),
                ),
                child: const Padding(
                  padding: EdgeInsets.symmetric(horizontal: 16, vertical: 14),
                  child: TypingDots(),
                ),
              ),
              const SizedBox(width: 8),
              Flexible(
                child: Text('$name is typing…',
                    overflow: TextOverflow.ellipsis, style: TextStyle(fontSize: 12, color: ZayloColors.muted)),
              ),
            ]),
          ),
        ),
      );
}
