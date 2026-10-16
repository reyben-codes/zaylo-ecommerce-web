import 'package:flutter/material.dart';

import 'package:zaylo_mobile/theme/zaylo_theme.dart';

class NetworkImageBox extends StatelessWidget {
  const NetworkImageBox({super.key, required this.url, this.fit = BoxFit.cover});

  final String? url;
  final BoxFit fit;

  static Widget get _placeholder => ColoredBox(
    color: ZayloColors.sand,
    child: Center(child: Icon(Icons.image_outlined, color: ZayloColors.taupe)),
  );

  @override
  Widget build(BuildContext context) => url == null
      ? _placeholder
      : Image.network(
          url!,
          fit: fit,
          errorBuilder: (_, __, ___) => _placeholder,
          loadingBuilder: (context, child, progress) =>
              progress == null ? child : ColoredBox(color: ZayloColors.sand),
        );
}

/// Uppercase taupe kicker above headings, as on the web store.
class Eyebrow extends StatelessWidget {
  const Eyebrow(this.text, {super.key});

  final String text;

  @override
  Widget build(BuildContext context) => Text(
        text.toUpperCase(),
        style: TextStyle(fontSize: 10, letterSpacing: 1.3, fontWeight: FontWeight.w800, color: ZayloColors.taupe),
      );
}

class SectionCard extends StatelessWidget {
  const SectionCard({super.key, required this.child, this.padding = const EdgeInsets.all(ZayloSpace.card)});

  final Widget child;
  final EdgeInsetsGeometry padding;

  // A Material surface (not a decorated box) so ink ripples from list
  // tiles and checkboxes inside the card remain visible.
  @override
  Widget build(BuildContext context) => Material(
        color: ZayloColors.paper,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(14),
          side: BorderSide(color: ZayloColors.line),
        ),
        clipBehavior: Clip.antiAlias,
        child: Padding(
          padding: padding,
          child: SizedBox(width: double.infinity, child: child),
        ),
      );
}

/// Label/value row for order and checkout summaries.
class SummaryRow extends StatelessWidget {
  const SummaryRow(this.label, this.value, {super.key, this.emphasize = false, this.valueColor});

  final String label;
  final String value;
  final bool emphasize;
  final Color? valueColor;

  @override
  Widget build(BuildContext context) {
    final style = TextStyle(
      fontSize: emphasize ? 16 : 13,
      fontWeight: emphasize ? FontWeight.w800 : FontWeight.w500,
      color: ZayloColors.ink,
    );
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(children: [
        Expanded(child: Text(label, style: style.copyWith(color: emphasize ? ZayloColors.ink : ZayloColors.muted))),
        Text(value, style: style.copyWith(color: valueColor)),
      ]),
    );
  }
}
