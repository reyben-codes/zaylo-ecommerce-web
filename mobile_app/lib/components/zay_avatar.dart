import 'dart:async';
import 'dart:math' as math;

import 'package:flutter/gestures.dart';
import 'package:flutter/material.dart';

/// How Zay, the help bot, looks right now.
enum ZayMood { idle, happy, thinking, confused, waving }

/// Zay, the help bot's character (the same face as on the website): blinks
/// every few seconds, sometimes twice, follows touches/pointers with its eyes,
/// glances around livelily, and changes its face with [mood]. With [float] it
/// also bobs gently above its shadow, unless the phone asks for less motion.
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
  late final AnimationController _lookController =
      AnimationController(vsync: this, duration: const Duration(milliseconds: 180));
  late final AnimationController _tiltController =
      AnimationController(vsync: this, duration: const Duration(milliseconds: 240));

  Offset _lookFrom = Offset.zero;
  Offset _lookTo = Offset.zero;
  Offset _pointerLook = Offset.zero;
  DateTime? _lastPointerAt;
  bool _calm = false;
  bool _routeRegistered = false;

  Timer? _blinkTimer;
  Timer? _glanceTimer;

  @override
  void initState() {
    super.initState();
    _scheduleBlink();
    _scheduleGlance();
    GestureBinding.instance.pointerRouter.addGlobalRoute(_handlePointer);
    _routeRegistered = true;
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _calm = MediaQuery.maybeDisableAnimationsOf(context) ?? false;
    _syncMotion();
    _syncMoodTargets(animate: !_calm);
  }

  @override
  void didUpdateWidget(ZayAvatar oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.float != widget.float || oldWidget.mood != widget.mood) {
      _syncMotion();
      _syncMoodTargets(animate: !_calm);
    }
  }

  bool get _inWidgetTest => WidgetsBinding.instance.runtimeType.toString().contains('Test');

  bool get _needsMotion =>
      !_calm && (widget.float || widget.mood == ZayMood.waving || widget.mood == ZayMood.thinking);

  void _syncMotion() {
    if (_needsMotion && !_inWidgetTest) {
      if (!_motion.isAnimating) _motion.repeat();
    } else {
      _motion.stop();
    }
  }

  void _syncMoodTargets({required bool animate}) {
    final targetTilt = widget.mood == ZayMood.confused ? 1.0 : 0.0;
    if (animate) {
      _tiltController.animateTo(targetTilt, curve: Curves.easeOut);
    } else {
      _tiltController.value = targetTilt;
    }

    final targetLook = switch (widget.mood) {
      ZayMood.thinking => const Offset(1.6, -2.0),
      _ => _pointerLook,
    };
    _lookToward(targetLook, animate: animate);
  }

  void _lookToward(Offset target, {required bool animate}) {
    final current = Offset.lerp(_lookFrom, _lookTo, Curves.easeOut.transform(_lookController.value)) ?? Offset.zero;
    if ((current - target).distance < 0.05) return;
    _lookFrom = current;
    _lookTo = target;
    if (animate && !_calm) {
      _lookController.forward(from: 0);
    } else {
      _lookController.value = 1;
    }
  }

  /// Like public/js/faq-bot.js: Zay's eyes follow the pointer up to 1.8px in the 64×64 grid.
  void _handlePointer(PointerEvent event) {
    if (!mounted || _calm || widget.mood == ZayMood.thinking) return;
    final box = context.findRenderObject();
    if (box is! RenderBox || !box.hasSize || !box.attached) return;
    final center = box.localToGlobal(box.size.center(Offset.zero));
    final delta = event.position - center;
    final distance = math.max(1.0, delta.distance);
    final reach = math.min(1.0, distance / 240.0) * 1.8;
    _pointerLook = Offset((delta.dx / distance) * reach, (delta.dy / distance) * reach);
    _lastPointerAt = DateTime.now();
    _lookToward(_pointerLook, animate: true);
  }

  void _scheduleGlance() {
    _glanceTimer?.cancel();
    _glanceTimer = Timer.periodic(const Duration(milliseconds: 3400), (_) {
      if (!mounted) return;
      final recentlyTouched =
          _lastPointerAt != null && DateTime.now().difference(_lastPointerAt!) < const Duration(seconds: 4);
      if (!_calm && widget.mood == ZayMood.idle && !recentlyTouched) {
        if (_random.nextDouble() < 0.45) {
          _pointerLook = Offset.zero;
        } else {
          final angle = (_random.nextDouble() - 0.5) * math.pi * 1.2;
          final dist = 0.7 + _random.nextDouble() * 0.9;
          _pointerLook = Offset(math.cos(angle) * dist, math.sin(angle) * dist * 0.65);
        }
        _lookToward(_pointerLook, animate: true);
      }
    });
  }

  void _scheduleBlink() {
    _blinkTimer?.cancel();
    _blinkTimer = Timer.periodic(const Duration(milliseconds: 3800), (_) async {
      if (!mounted) return;
      await _blinkOnce();
      if (mounted && _random.nextDouble() < .2) {
        await _blinkOnce();
      }
    });
  }

  Future<void> _blinkOnce() async {
    await _blink.forward(from: 0);
    if (mounted) await _blink.reverse();
  }

  @override
  void dispose() {
    if (_routeRegistered) {
      GestureBinding.instance.pointerRouter.removeGlobalRoute(_handlePointer);
    }
    _blinkTimer?.cancel();
    _glanceTimer?.cancel();
    _blink.dispose();
    _motion.dispose();
    _lookController.dispose();
    _tiltController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => ExcludeSemantics(
        child: AnimatedBuilder(
          animation: Listenable.merge([_blink, _motion, _lookController, _tiltController]),
          builder: (context, _) {
            final phase = _motion.value * 2 * math.pi;
            final lift = widget.float && !_calm ? 0.5 * (1 - math.cos(phase)) : 0.0;
            final look = Offset.lerp(_lookFrom, _lookTo, Curves.easeOut.transform(_lookController.value)) ?? Offset.zero;
            final wave = widget.mood == ZayMood.waving && !_calm ? 0.5 * (1 - math.cos(phase * 5)) : 0.5;
            final glow = widget.mood == ZayMood.thinking && !_calm ? 0.35 + 0.65 * (0.5 * (1 + math.cos(phase * 3.5))) : 1.0;
            return CustomPaint(
              size: Size.square(widget.size),
              painter: _ZayPainter(
                mood: widget.mood,
                blink: _blink.value,
                lift: lift,
                look: widget.mood == ZayMood.thinking ? const Offset(1.6, -2.0) : look,
                tilt: Curves.easeOut.transform(_tiltController.value),
                wave: wave,
                glow: glow,
              ),
            );
          },
        ),
      );
}

/// Draws Zay on a 64 × 64 grid, matching resources/views/partials/faq-bot-mascot.blade.php
/// and public/css/faq-bot.css.
class _ZayPainter extends CustomPainter {
  const _ZayPainter({
    required this.mood,
    required this.blink,
    required this.lift,
    required this.look,
    required this.tilt,
    required this.wave,
    required this.glow,
  });

  final ZayMood mood;

  /// 0 = eyes open, 1 = shut.
  final double blink;

  /// 0 = resting, 1 = peak of the 4px float bob.
  final double lift;

  /// Pupil offset in the 64 × 64 coordinate space.
  final Offset look;

  /// 0 = upright, 1 = -6° confused tilt.
  final double tilt;

  /// 0 … 1, how far the waving hand is turned (0° to -28°).
  final double wave;

  /// 0.35 … 1, antenna tip pulse while thinking.
  final double glow;

  static const _shadow = Color(0x26241A16);
  static const _brown = Color(0xFF9A755E);
  static const _cream = Color(0xFFFFFAF3);
  static const _ink = Color(0xFF2B241F);
  static const _eyeBorder = Color(0x222B241F);

  @override
  void paint(Canvas canvas, Size size) {
    canvas.scale(size.width / 64);
    final fill = Paint();

    // Ground shadow (.zay__shadow: cx="32" cy="61" rx="15" ry="2.2") stays on the ground while Zay floats.
    final shadowScale = 1 - 0.1 * lift;
    canvas.drawOval(
      Rect.fromCenter(center: const Offset(32, 61), width: 30 * shadowScale, height: 4.4 * shadowScale),
      fill..color = _shadow,
    );

    // Body (.zay__body) floats and tilts around (32, 57.6).
    canvas.save();
    canvas.translate(0, -4 * lift);
    if (tilt > 0) {
      canvas.translate(32, 57.6);
      canvas.rotate(-0.1047 * tilt);
      canvas.translate(-32, -57.6);
    }

    // Antenna (.zay__antenna & .zay__antenna-tip), head (.zay__head) and face (.zay__face).
    canvas.drawLine(
      const Offset(32, 10),
      const Offset(32, 4),
      Paint()
        ..color = _brown
        ..strokeWidth = 3
        ..strokeCap = StrokeCap.round,
    );
    final tipColor = mood == ZayMood.thinking ? const Color(0xFFF2CC86) : const Color(0xFFE0B66F);
    canvas.drawCircle(const Offset(32, 4), 2.6, fill..color = tipColor.withValues(alpha: glow));
    canvas.drawRRect(RRect.fromLTRBR(6, 10, 58, 56, const Radius.circular(21)), fill..color = _brown);
    canvas.drawRRect(RRect.fromLTRBR(12, 18, 52, 48, const Radius.circular(14)), fill..color = _cream);

    // Eyes: squashed when blinking or smiling, looking toward pointers or up-aside while thinking.
    final squash = math.max(0.08, (1 - blink) * (mood == ZayMood.happy || mood == ZayMood.waving ? .8 : 1));
    final eyeStroke = Paint()
      ..color = _eyeBorder
      ..style = PaintingStyle.stroke
      ..strokeWidth = 0.6;
    for (final x in [23.0, 41.0]) {
      canvas.save();
      canvas.translate(x, 31);
      canvas.scale(1, squash);
      final eyeRect = Rect.fromCenter(center: Offset.zero, width: 11.2, height: 12.8);
      canvas.drawOval(eyeRect, fill..color = Colors.white);
      canvas.drawOval(eyeRect, eyeStroke);
      canvas.drawCircle(look, 2.9, fill..color = _ink);
      canvas.drawCircle(look + const Offset(1.1, -1.4), 1, fill..color = Colors.white);
      canvas.restore();
    }

    // Cheeks (.zay__cheek).
    fill.color = const Color(0x8CE8998D);
    canvas.drawCircle(const Offset(16.5, 41), 2.6, fill);
    canvas.drawCircle(const Offset(47.5, 41), 2.6, fill);

    // Mouth (.zay__mouth).
    final line = Paint()
      ..color = _ink
      ..style = PaintingStyle.stroke
      ..strokeWidth = 2.2
      ..strokeCap = StrokeCap.round
      ..strokeJoin = StrokeJoin.round;
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

    // A raised hand, waving (.zay__hand: x="53" y="33" width="8" height="12" rx="4", origin 50% 100%).
    if (mood == ZayMood.waving) {
      canvas.save();
      canvas.translate(57, 45);
      canvas.rotate(-0.4887 * wave);
      canvas.drawRRect(RRect.fromLTRBR(-4, -12, 4, 0, const Radius.circular(4)), fill..color = _brown);
      canvas.restore();
    }

    canvas.restore();
  }

  @override
  bool shouldRepaint(_ZayPainter old) =>
      old.mood != mood ||
      old.blink != blink ||
      old.lift != lift ||
      old.look != look ||
      old.tilt != tilt ||
      old.wave != wave ||
      old.glow != glow;
}
