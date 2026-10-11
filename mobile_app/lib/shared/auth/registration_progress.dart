import 'package:flutter/material.dart';

import '../../core/theme/zaylo_theme.dart';

/// Numbered step indicator for multi-step sign-up. [step] is 1-based.
class RegistrationProgress extends StatelessWidget {
  const RegistrationProgress({super.key, required this.step, required this.labels});

  final int step;
  final List<String> labels;

  @override
  Widget build(BuildContext context) => Row(children: [
        for (var index = 0; index < labels.length; index++) ...[
          if (index > 0) const Expanded(child: Divider(indent: 10, endIndent: 10)),
          _StepMarker(
            number: index + 1,
            label: labels[index],
            selected: step == index + 1,
            complete: step > index + 1 && index < labels.length - 1,
          ),
        ],
      ]);
}

class _StepMarker extends StatelessWidget {
  const _StepMarker({required this.number, required this.label, required this.selected, required this.complete});

  final int number;
  final String label;
  final bool selected;
  final bool complete;

  @override
  Widget build(BuildContext context) => Column(children: [
        CircleAvatar(
          radius: 13,
          backgroundColor: selected || complete ? ZayloColors.ink : ZayloColors.line,
          foregroundColor: selected || complete ? ZayloColors.white : ZayloColors.muted,
          child: Text(complete ? '✓' : '$number', style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700)),
        ),
        const SizedBox(height: 5),
        // Long labels wrap under the marker instead of pushing the row wider.
        ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 76),
          child: Text(label,
              textAlign: TextAlign.center,
              style: TextStyle(
                fontSize: 10,
                color: selected || complete ? ZayloColors.ink : ZayloColors.muted,
                fontWeight: selected ? FontWeight.w700 : FontWeight.w400,
              )),
        ),
      ]);
}
