import 'package:flutter/material.dart';

import '../../../core/theme/zaylo_theme.dart';

/// Small rounded label such as "Home" or "Default".
class AddressTag extends StatelessWidget {
  const AddressTag(this.text, {super.key, this.dark = false});

  final String text;
  final bool dark;

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
        decoration: BoxDecoration(
          color: dark ? ZayloColors.ink : ZayloColors.sand,
          borderRadius: BorderRadius.circular(20),
        ),
        child: Text(text,
            style: TextStyle(
                fontSize: 10, fontWeight: FontWeight.w700, color: dark ? ZayloColors.white : ZayloColors.ink)),
      );
}
