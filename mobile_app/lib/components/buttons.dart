import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import 'package:zaylo_mobile/theme/zaylo_theme.dart';

const _buttonText = WidgetStatePropertyAll(TextStyle(fontWeight: FontWeight.w700, letterSpacing: .5));

ButtonStyle get primaryButtonStyle => ZayloButtons.primary.copyWith(
  padding: const WidgetStatePropertyAll(EdgeInsets.symmetric(vertical: 16, horizontal: 22)),
  textStyle: _buttonText,
);

ButtonStyle get secondaryButtonStyle => ZayloButtons.secondary.copyWith(
  padding: const WidgetStatePropertyAll(EdgeInsets.symmetric(vertical: 15, horizontal: 20)),
  textStyle: _buttonText,
);

/// Full-width primary action that shows a spinner while [busy].
class PrimaryButton extends StatelessWidget {
  const PrimaryButton({super.key, required this.label, required this.onPressed, this.busy = false, this.icon});

  final String label;
  final VoidCallback? onPressed;
  final bool busy;
  final IconData? icon;

  @override
  Widget build(BuildContext context) => SizedBox(
        width: double.infinity,
        child: FilledButton(
          onPressed: busy ? null : onPressed,
          style: primaryButtonStyle,
          child: busy
              ? SizedBox(
                  height: 20,
                  width: 20,
                  child: CircularProgressIndicator(strokeWidth: 2, color: ZayloColors.white),
                )
              : Row(mainAxisSize: MainAxisSize.min, children: [
                  if (icon != null) ...[Icon(icon, size: 20), const SizedBox(width: 8)],
                  Flexible(child: Text(label, overflow: TextOverflow.ellipsis)),
                ]),
        ),
      );
}

/// Quantity picker: tap - or +, or type an exact amount (kept between 1 and [max], applied when typing ends).
class QuantityStepper extends StatefulWidget {
  const QuantityStepper({super.key, required this.value, required this.max, required this.onChanged, this.enabled = true});
  final int value;
  final int max;
  final ValueChanged<int> onChanged;
  final bool enabled;

  @override
  State<QuantityStepper> createState() => _QuantityStepperState();
}

class _QuantityStepperState extends State<QuantityStepper> {
  late final TextEditingController _controller = TextEditingController(text: '${widget.value}');
  final FocusNode _focus = FocusNode();
  // Submitting also ends focus; this keeps the typed amount from being sent twice before the parent rebuilds.
  int? _sent;

  @override
  void initState() {
    super.initState();
    _focus.addListener(() {
      if (!_focus.hasFocus) _commit();
    });
  }

  @override
  void didUpdateWidget(QuantityStepper oldWidget) {
    super.didUpdateWidget(oldWidget);
    _sent = null;
    if (!_focus.hasFocus && _controller.text != '${widget.value}') _controller.text = '${widget.value}';
  }

  @override
  void dispose() {
    _controller.dispose();
    _focus.dispose();
    super.dispose();
  }

  void _commit() {
    final typed = int.tryParse(_controller.text) ?? widget.value;
    final amount = typed.clamp(1, widget.max < 1 ? 1 : widget.max);
    _controller.text = '$amount';
    if (amount == widget.value || amount == _sent) return;
    _sent = amount;
    widget.onChanged(amount);
  }

  void _step(int by) {
    _focus.unfocus();
    widget.onChanged(widget.value + by);
  }

  @override
  Widget build(BuildContext context) => Container(
        decoration: BoxDecoration(
          border: Border.all(color: ZayloColors.line),
          borderRadius: BorderRadius.circular(9),
          color: ZayloColors.white,
        ),
        child: Row(mainAxisSize: MainAxisSize.min, children: [
          _StepButton(
            icon: Icons.remove,
            label: 'Decrease quantity',
            onTap: widget.enabled && widget.value > 1 ? () => _step(-1) : null,
          ),
          SizedBox(
            width: 44,
            child: TextField(
              controller: _controller,
              focusNode: _focus,
              enabled: widget.enabled,
              keyboardType: TextInputType.number,
              textInputAction: TextInputAction.done,
              inputFormatters: [FilteringTextInputFormatter.digitsOnly, LengthLimitingTextInputFormatter(4)],
              textAlign: TextAlign.center,
              style: const TextStyle(fontWeight: FontWeight.w700),
              decoration: const InputDecoration(
                isDense: true,
                filled: false,
                border: InputBorder.none,
                enabledBorder: InputBorder.none,
                focusedBorder: InputBorder.none,
                disabledBorder: InputBorder.none,
                contentPadding: EdgeInsets.symmetric(vertical: 8),
                semanticCounterText: '',
              ),
              onSubmitted: (_) => _commit(),
              onTapOutside: (_) => _focus.unfocus(),
            ),
          ),
          _StepButton(
            icon: Icons.add,
            label: 'Increase quantity',
            onTap: widget.enabled && widget.value < widget.max ? () => _step(1) : null,
          ),
        ]),
      );
}

class _StepButton extends StatelessWidget {
  const _StepButton({required this.icon, required this.label, required this.onTap});

  final IconData icon;
  final String label;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) => Semantics(
        button: true,
        label: label,
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(9),
          child: SizedBox(
            width: 36,
            height: 36,
            child: Icon(icon, size: 17, color: onTap == null ? ZayloColors.line : ZayloColors.ink),
          ),
        ),
      );
}
