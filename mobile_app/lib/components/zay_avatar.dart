import 'dart:async';
import 'dart:math' as math;

import 'package:flutter/material.dart';

/// How Zay, the help bot, looks right now.
enum ZayMood { idle, happy, thinking, confused, waving }

/// Zay, the help bot's character (the same face as on the website): blinks
/// every few seconds, sometimes twice, and changes its face with [mood]. With
/// [float] it also bobs gently, unless the phone asks for less motion.
class ZayAvatar extends StatefulWidget {
  const ZayAvatar({super.key, this.size = 40, this.mood = ZayMood.idle, this.float = false});

  final double size;
  final ZayMood mood;
  final bool float;

  @override
  State<ZayAvatar> createState() => _ZayAvatarState();
}

class _ZayAvatarState extends State<ZayAvatar> with TickerProviderStateMixin {
  static final _random = math.Random();

  late final AnimationController _blink =
      AnimationController(vsync: this, duration: const Duration(milliseconds: 140));
  late final AnimationController _motion =
      AnimationController(vsync: this, duration: const Duration(milliseconds: 3200));
  Timer? _nextBlink;
  Timer? _doubleBlink;

  @override
  void initState() {
    super.initState();
    _scheduleBlink();
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final calm = MediaQuery.maybeDisableAnimationsOf(context) ?? false;
    if (widget.float && !calm) {
      if (!_motion.isAnimating) _motion.repeat();
    } else {
      _motion.stop();
    }
  }

  void _scheduleBlink() {
    _nextBlink = Timer(Duration(milliseconds: 2200 + _random.nextInt(4200)), () async {
      if (!mounted) return;
      await _blinkOnce();
      if (mounted && _random.nextDouble() < .2) {
        _doubleBlink?.cancel();
        _doubleBlink = Timer(const Duration(milliseconds: 120), () async {
          if (mounted) await _blinkOnce();
          if (mounted) _scheduleBlink();
        });
      } else if (mounted) {
        _scheduleBlink();
      }
    });
  }

  Future<void> _blinkOnce() async {
    await _blink.forward(from: 0);
    if (mounted) await _blink.reverse();
  }

  @override
  void dispose() {
    _nextBlink?.cancel();
    _doubleBlink?.cancel();
    _blink.dispose();
    _motion.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => ExcludeSemantics(
        child: AnimatedBuilder(
          animation: Listenable.merge([_blink, _motion]),
          builder: (context, _) {
            final phase = _motion.value * 2 * math.pi;
            return Transform.translate(
              offset: Offset(0, widget.float ? -2.5 * (1 - math.cos(phase)) : 0),
              child: CustomPaint(
                size: Size.square(widget.size),
                painter: _ZayPainter(
                  mood: widget.mood,
                  blink: _blink.value,
                  // The waving hand swings with the bob (or holds still).
                  wave: widget.mood == ZayMood.waving ? math.sin(phase * 4) : 0,
                ),
              ),
            );
          },
        ),
      );
}

/// Draws Zay on a 64 × 64 grid, as in resources/views/partials/faq-bot-mascot.blade.php.
class _ZayPainter extends CustomPainter {
  const _ZayPainter({required this.mood, required this.blink, required this.wave});

  final ZayMood mood;

  /// 0 = eyes open, 1 = shut.
  final double blink;

  /// -1 … 1, how far the waving hand is turned.
  final double wave;

  static const _brown = Color(0xFF9A755E);
  static const _cream = Color(0xFFFFFAF3);
  static const _ink = Color(0xFF2B241F);

  @override
  void paint(Canvas canvas, Size size) {
    canvas.scale(size.width / 64);
    final fill = Paint();

    if (mood == ZayMood.confused) {
      canvas.translate(32, 58);
      canvas.rotate(-0.1);
      canvas.translate(-32, -58);
    }

    // Antenna, head and face.
    canvas.drawLine(const Offset(32, 10), const Offset(32, 4),
        Paint()..color = _brown..strokeWidth = 3..strokeCap = StrokeCap.round);
    canvas.drawCircle(const Offset(32, 4), 2.6,
        fill..color = mood == ZayMood.thinking ? const Color(0xFFF2CC86) : const Color(0xFFE0B66F));
    canvas.drawRRect(RRect.fromLTRBR(6, 10, 58, 56, const Radius.circular(21)), fill..color = _brown);
    canvas.drawRRect(RRect.fromLTRBR(12, 18, 52, 48, const Radius.circular(14)), fill..color = _cream);

    // Eyes: squashed when blinking or smiling, looking up and aside while thinking.
    final squash = math.max(0.08, (1 - blink) * (mood == ZayMood.happy || mood == ZayMood.waving ? .8 : 1));
    final look = mood == ZayMood.thinking ? const Offset(1.6, -2) : Offset.zero;
    for (final x in [23.0, 41.0]) {
      canvas.save();
      canvas.translate(x, 31);
      canvas.scale(1, squash);
      canvas.drawOval(Rect.fromCenter(center: Offset.zero, width: 11.2, height: 12.8), fill..color = Colors.white);
      canvas.drawCircle(look, 2.9, fill..color = _ink);
      canvas.drawCircle(look + const Offset(1.1, -1.4), 1, fill..color = Colors.white);
      canvas.restore();
    }

    // Cheeks.
    fill.color = const Color(0x8CE8998D);
    canvas.drawCircle(const Offset(16.5, 41), 2.6, fill);
    canvas.drawCircle(const Offset(47.5, 41), 2.6, fill);

    // Mouth.
    final line = Paint()
      ..color = _ink
      ..style = PaintingStyle.stroke
      ..strokeWidth = 2.2
      ..strokeCap = StrokeCap.round;
    switch (mood) {
      case ZayMood.idle:
        canvas.drawPath(Path()..moveTo(27, 41)..quadraticBezierTo(32, 45, 37, 41), line);
      case ZayMood.happy || ZayMood.waving:
        final smile = Path()..moveTo(25.5, 40)..quadraticBezierTo(32, 48, 38.5, 40)..close();
        canvas.drawPath(smile, Paint()..color = const Color(0xFFC0574B));
        canvas.drawPath(smile, line);
      case ZayMood.thinking:
        canvas.drawLine(const Offset(28, 43), const Offset(36, 43), line);
      case ZayMood.confused:
        canvas.drawPath(
            Path()
              ..moveTo(27, 43.5)
              ..quadraticBezierTo(30, 41, 32.5, 43)
              ..quadraticBezierTo(35, 45, 38, 42),
            line);
    }

    // A raised hand, waving.
    if (mood == ZayMood.waving) {
      canvas.save();
      canvas.translate(57, 45);
      canvas.rotate(-0.25 - 0.25 * wave);
      canvas.drawRRect(RRect.fromLTRBR(-4, -12, 4, 0, const Radius.circular(4)), fill..color = _brown);
      canvas.restore();
    }
  }

  @override
  bool shouldRepaint(_ZayPainter old) => old.mood != mood || old.blink != blink || old.wave != wave;
}
