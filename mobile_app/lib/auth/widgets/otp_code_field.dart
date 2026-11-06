import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import 'package:zaylo_mobile/theme/zaylo_theme.dart';

/// Where the code check is up to; the boxes change colour and motion for each.
enum OtpStatus { idle, verifying, success, error }

/// Six animated digit boxes over one invisible text field. Keeping a single
/// real field means the keyboard's one-time-code suggestion (iOS offers codes
/// it finds in Mail and Messages), long-press paste and Android autofill all
/// fill every box at once. [onChanged] gets the digits typed so far.
class OtpCodeField extends StatefulWidget {
  const OtpCodeField({
    super.key,
    required this.controller,
    required this.focusNode,
    required this.status,
    required this.onChanged,
    this.length = 6,
  });

  final TextEditingController controller;
  final FocusNode focusNode;
  final OtpStatus status;
  final ValueChanged<String> onChanged;
  final int length;

  /// Pulls a code out of copied text such as "Your code is 123 456."
  static String extractCode(String? text, {int length = 6}) {
    final source = text ?? '';
    final grouped = RegExp(r'(?:^|\D)(\d(?:[\s-]?\d){5})(?!\d)').firstMatch(source);
    final digits = (grouped?.group(1) ?? source).replaceAll(RegExp(r'\D'), '');
    return digits.length > length ? digits.substring(0, length) : digits;
  }

  @override
  State<OtpCodeField> createState() => _OtpCodeFieldState();
}

class _OtpCodeFieldState extends State<OtpCodeField> with TickerProviderStateMixin {
  late final AnimationController _caret = AnimationController(vsync: this, duration: const Duration(milliseconds: 1000));
  late final AnimationController _wave = AnimationController(vsync: this, duration: const Duration(milliseconds: 900));
  late final AnimationController _shake = AnimationController(vsync: this, duration: const Duration(milliseconds: 420));
  late final AnimationController _confirm = AnimationController(vsync: this, duration: const Duration(milliseconds: 620));

  @override
  void initState() {
    super.initState();
    widget.controller.addListener(_keepCaretAtEnd);
    widget.focusNode.addListener(_syncCaret);
    _syncCaret();
  }

  @override
  void didUpdateWidget(OtpCodeField old) {
    super.didUpdateWidget(old);
    if (old.controller != widget.controller) {
      old.controller.removeListener(_keepCaretAtEnd);
      widget.controller.addListener(_keepCaretAtEnd);
    }
    if (old.focusNode != widget.focusNode) {
      old.focusNode.removeListener(_syncCaret);
      widget.focusNode.addListener(_syncCaret);
    }
    if (old.status == widget.status) return;
    final animate = !_reduceMotion;
    if (widget.status == OtpStatus.verifying && animate) {
      _wave.repeat();
    } else {
      _wave
        ..stop()
        ..value = 0;
    }
    if (widget.status == OtpStatus.error && animate) _shake.forward(from: 0);
    if (widget.status == OtpStatus.success && animate) _confirm.forward(from: 0);
    _syncCaret();
  }

  @override
  void dispose() {
    widget.controller.removeListener(_keepCaretAtEnd);
    widget.focusNode.removeListener(_syncCaret);
    _caret.dispose();
    _wave.dispose();
    _shake.dispose();
    _confirm.dispose();
    super.dispose();
  }

  bool get _reduceMotion => MediaQuery.maybeDisableAnimationsOf(context) ?? false;

  bool get _locked => widget.status == OtpStatus.verifying || widget.status == OtpStatus.success;

  /// Digits always go in order, so a tap in the middle must not move the insertion point.
  void _keepCaretAtEnd() {
    final value = widget.controller.value;
    final end = value.text.length;
    if (value.selection.baseOffset != end || value.selection.extentOffset != end) {
      widget.controller.selection = TextSelection.collapsed(offset: end);
    }
  }

  void _syncCaret() {
    if (!mounted) return;
    final blink = widget.focusNode.hasFocus && !_locked && !_reduceMotion;
    if (blink && !_caret.isAnimating) {
      _caret.repeat();
    } else if (!blink) {
      _caret.stop();
    }
  }

  @override
  Widget build(BuildContext context) {
    final transparent = Colors.transparent;
    return Semantics(
      label: 'Email verification code',
      child: Stack(children: [
        AnimatedBuilder(
          animation: Listenable.merge([widget.controller, widget.focusNode, _caret, _wave, _shake, _confirm]),
          builder: (context, _) {
            final shakeOffset = math.sin(_shake.value * math.pi * 4) * 8 * (1 - _shake.value);
            return Transform.translate(
              offset: Offset(shakeOffset, 0),
              child: Row(children: [
                for (var index = 0; index < widget.length; index++) ...[
                  if (index > 0) const SizedBox(width: 8),
                  Expanded(child: _box(index)),
                ],
              ]),
            );
          },
        ),
        // The real field: invisible, but it receives taps, typing, paste and autofill.
        Positioned.fill(
          child: Theme(
            data: Theme.of(context).copyWith(
              textSelectionTheme: TextSelectionThemeData(selectionColor: transparent, selectionHandleColor: transparent),
            ),
            child: TextField(
              controller: widget.controller,
              focusNode: widget.focusNode,
              autofocus: true,
              readOnly: _locked,
              keyboardType: TextInputType.number,
              textInputAction: TextInputAction.done,
              autofillHints: const [AutofillHints.oneTimeCode],
              inputFormatters: [
                FilteringTextInputFormatter.digitsOnly,
                LengthLimitingTextInputFormatter(widget.length),
              ],
              showCursor: false,
              enableSuggestions: false,
              autocorrect: false,
              style: TextStyle(color: transparent, fontSize: 1, height: 1),
              decoration: const InputDecoration.collapsed(hintText: ''),
              onChanged: widget.onChanged,
            ),
          ),
        ),
      ]),
    );
  }

  Widget _box(int index) {
    final text = widget.controller.text;
    final digit = index < text.length ? text[index] : '';
    final active = widget.focusNode.hasFocus && !_locked && index == math.min(text.length, widget.length - 1);

    final (border, fill, ink) = switch (widget.status) {
      OtpStatus.success => (ZayloColors.success, ZayloColors.successSoft, ZayloColors.success),
      OtpStatus.error => (ZayloColors.danger, ZayloColors.dangerSoft, ZayloColors.dangerText),
      OtpStatus.verifying => (ZayloColors.accent, ZayloColors.white, ZayloColors.ink),
      OtpStatus.idle => (
          active ? ZayloColors.accent : (digit.isNotEmpty ? ZayloColors.muted : ZayloColors.line),
          ZayloColors.white,
          ZayloColors.ink,
        ),
    };

    // Checking: a wave runs across the boxes. Verified: each box bumps in turn.
    final waveT = (_wave.value - index * .1) % 1;
    final lift = waveT < .3 ? -5 * math.sin(math.pi * waveT / .3) : 0.0;
    final confirmT = ((_confirm.value - index * .08) / .5).clamp(0.0, 1.0);
    final scale = 1 + .1 * math.sin(math.pi * confirmT);
    final duration = _reduceMotion ? Duration.zero : const Duration(milliseconds: 200);

    return Transform.translate(
      offset: Offset(0, lift),
      child: Transform.scale(
        scale: scale,
        child: AnimatedContainer(
          duration: duration,
          curve: Curves.easeOut,
          height: 56,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: fill,
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: border, width: active ? 2 : 1.5),
            boxShadow: active ? [BoxShadow(color: ZayloColors.accent.withValues(alpha: .22), blurRadius: 0, spreadRadius: 3)] : null,
          ),
          child: AnimatedSwitcher(
            duration: _reduceMotion ? Duration.zero : const Duration(milliseconds: 220),
            switchInCurve: Curves.easeOutBack,
            switchOutCurve: Curves.easeIn,
            transitionBuilder: (child, animation) => FadeTransition(
              opacity: animation,
              child: ScaleTransition(scale: Tween(begin: .55, end: 1.0).animate(animation), child: child),
            ),
            child: digit.isNotEmpty
                ? Text(
                    digit,
                    key: ValueKey('digit-$index-$digit'),
                    style: TextStyle(
                      fontSize: 24,
                      fontWeight: FontWeight.w600,
                      color: ink,
                      fontFeatures: const [FontFeature.tabularFigures()],
                    ),
                  )
                : active && (_reduceMotion || _caret.value < .5)
                    ? Container(key: ValueKey('caret-$index'), width: 2, height: 24, color: ZayloColors.accent)
                    : SizedBox(key: ValueKey('empty-$index'), width: 2, height: 24),
          ),
        ),
      ),
    );
  }
}
