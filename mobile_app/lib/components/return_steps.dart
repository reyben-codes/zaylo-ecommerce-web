import 'package:flutter/material.dart';

import 'package:zaylo_mobile/models/support_models.dart';
import 'package:zaylo_mobile/theme/zaylo_theme.dart';
import 'package:zaylo_mobile/utils/formatters.dart';

/// What the person viewing a return does now, in a highlighted box.
class ReturnNextStep extends StatelessWidget {
  const ReturnNextStep({super.key, required this.text, this.finished = false});

  final String text;

  /// The return is complete (or was not approved), so the box reads as a status.
  final bool finished;

  @override
  Widget build(BuildContext context) => Container(
        width: double.infinity,
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: (finished ? ZayloColors.success : ZayloColors.ink).withValues(alpha: .07),
          borderRadius: BorderRadius.circular(10),
        ),
        child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Icon(finished ? Icons.check_circle_outline : Icons.arrow_circle_right_outlined,
              size: 18, color: finished ? ZayloColors.success : ZayloColors.ink),
          const SizedBox(width: 8),
          Expanded(
            child: Text.rich(TextSpan(children: [
              TextSpan(text: finished ? 'Status: ' : 'What you do now: ', style: const TextStyle(fontWeight: FontWeight.w700)),
              TextSpan(text: text),
            ]), style: TextStyle(fontSize: 13, color: ZayloColors.ink)),
          ),
        ]),
      );
}

/// Every step of a return as a timeline, the same as on the website: done steps
/// checked with their date, the current one outlined.
class ReturnSteps extends StatelessWidget {
  const ReturnSteps({super.key, required this.steps});

  final List<ReturnStep> steps;

  @override
  Widget build(BuildContext context) => Column(children: [
        for (final (index, step) in steps.indexed) _StepRow(step: step, last: index == steps.length - 1),
      ]);
}

class _StepRow extends StatelessWidget {
  const _StepRow({required this.step, required this.last});

  final ReturnStep step;
  final bool last;

  @override
  Widget build(BuildContext context) {
    final color = step.done ? ZayloColors.success : (step.current ? ZayloColors.ink : ZayloColors.muted);
    final state = step.done ? 'done' : (step.current ? 'current step' : 'to come');
    return Semantics(
      label: '${step.label}, $state',
      child: IntrinsicHeight(
        child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Column(children: [
            Icon(
              step.done ? Icons.check_circle : (step.current ? Icons.radio_button_checked : Icons.radio_button_unchecked),
              size: 18,
              color: color,
            ),
            if (!last) Expanded(child: Container(width: 2, color: step.done ? ZayloColors.success : ZayloColors.line)),
          ]),
          const SizedBox(width: 10),
          Expanded(
            child: Padding(
              padding: EdgeInsets.only(bottom: last ? 0 : 12),
              child: ExcludeSemantics(
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text(step.label,
                      style: TextStyle(
                        fontSize: 13,
                        fontWeight: step.current ? FontWeight.w800 : FontWeight.w600,
                        color: step.done || step.current ? ZayloColors.ink : ZayloColors.muted,
                      )),
                  if (step.detail != null) Text(step.detail!, style: TextStyle(fontSize: 12, color: ZayloColors.muted)),
                  if (step.at != null)
                    Text(dateTimeLabel(step.at), style: TextStyle(fontSize: 11, color: ZayloColors.muted)),
                ]),
              ),
            ),
          ),
        ]),
      ),
    );
  }
}
