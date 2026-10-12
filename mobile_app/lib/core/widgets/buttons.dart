import 'package:flutter/material.dart';

import '../theme/zaylo_theme.dart';

ButtonStyle get primaryButtonStyle => FilledButton.styleFrom(
  backgroundColor: ZayloColors.ink,
  foregroundColor: ZayloColors.white,
  disabledBackgroundColor: ZayloColors.line,
  padding: const EdgeInsets.symmetric(vertical: 16, horizontal: 22),
  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
  textStyle: const TextStyle(fontWeight: FontWeight.w700, letterSpacing: .5),
);

ButtonStyle get secondaryButtonStyle => OutlinedButton.styleFrom(
  foregroundColor: ZayloColors.ink,
  side: BorderSide(color: ZayloColors.ink),
  padding: const EdgeInsets.symmetric(vertical: 15, horizontal: 20),
  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
  textStyle: const TextStyle(fontWeight: FontWeight.w700, letterSpacing: .5),
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
                  child: CircularProgressIndicator(strokeWidth: 2, color: ZayloColors.ink),
                )
              : Row(mainAxisSize: MainAxisSize.min, children: [
                  if (icon != null) ...[Icon(icon, size: 20), const SizedBox(width: 8)],
                  Flexible(child: Text(label, overflow: TextOverflow.ellipsis)),
                ]),
        ),
      );
}

class QuantityStepper extends StatelessWidget {
  const QuantityStepper({super.key, required this.value, required this.max, required this.onChanged, this.enabled = true});

  final int value;
  final int max;
  final ValueChanged<int> onChanged;
  final bool enabled;

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
            onTap: enabled && value > 1 ? () => onChanged(value - 1) : null,
          ),
          SizedBox(
            width: 32,
            child: Text('$value', textAlign: TextAlign.center, style: const TextStyle(fontWeight: FontWeight.w700)),
          ),
          _StepButton(
            icon: Icons.add,
            label: 'Increase quantity',
            onTap: enabled && value < max ? () => onChanged(value + 1) : null,
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
