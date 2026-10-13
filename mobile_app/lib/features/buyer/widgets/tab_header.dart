import 'package:flutter/material.dart';

import 'package:zaylo_mobile/components/layout.dart';
import 'package:zaylo_mobile/theme/zaylo_theme.dart';

/// Centered page header for the Saved, Bag and Account tabs: a taupe kicker
/// over a Playfair title, matching the section headings on Home. A hairline
/// separates it from the page.
class TabHeader extends StatelessWidget implements PreferredSizeWidget {
  const TabHeader({super.key, required this.eyebrow, required this.title, this.count, this.countNoun = 'item'});

  final String eyebrow;
  final String title;

  /// Shown after the kicker when above zero, e.g. "READY FOR CHECKOUT · 2 ITEMS".
  final int? count;

  /// Singular noun for the count, e.g. "item" gives "1 item" / "3 items".
  final String countNoun;

  @override
  Size get preferredSize => const Size.fromHeight(84);

  @override
  Widget build(BuildContext context) {
    final count = this.count;
    final kicker = count != null && count > 0 ? '$eyebrow · $count ${count == 1 ? countNoun : '${countNoun}s'}' : eyebrow;
    return AppBar(
      automaticallyImplyLeading: false,
      centerTitle: true,
      toolbarHeight: preferredSize.height,
      backgroundColor: ZayloColors.paper,
      shape: Border(bottom: BorderSide(color: ZayloColors.line)),
      title: Column(mainAxisSize: MainAxisSize.min, children: [
        Eyebrow(kicker),
        const SizedBox(height: 4),
        Text(
          title,
          overflow: TextOverflow.ellipsis,
          textAlign: TextAlign.center,
          style: Theme.of(context).textTheme.headlineSmall?.copyWith(fontSize: 28, height: 1.1),
        ),
      ]),
    );
  }
}
