import 'package:flutter/material.dart';

import '../theme/theme_controller.dart';
import '../theme/zaylo_theme.dart';

/// System / Light / Dark switch for settings screens. Renders nothing when
/// the app has no [ThemeScope].
class AppearancePicker extends StatelessWidget {
  const AppearancePicker({super.key, this.padding = const EdgeInsets.fromLTRB(16, 12, 16, 14)});

  final EdgeInsetsGeometry padding;

  @override
  Widget build(BuildContext context) {
    final controller = ThemeScope.maybeOf(context);
    if (controller == null) return const SizedBox.shrink();
    return Padding(
      padding: padding,
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          Icon(Icons.contrast, color: ZayloColors.ink),
          const SizedBox(width: 16),
          Text('Appearance', style: TextStyle(fontWeight: FontWeight.w600, color: ZayloColors.ink, fontSize: 14)),
        ]),
        const SizedBox(height: 12),
        SizedBox(
          width: double.infinity,
          child: SegmentedButton<ThemeMode>(
            showSelectedIcon: false,
            style: SegmentedButton.styleFrom(
              backgroundColor: ZayloColors.white,
              foregroundColor: ZayloColors.ink,
              selectedBackgroundColor: ZayloColors.ink,
              selectedForegroundColor: ZayloColors.white,
              side: BorderSide(color: ZayloColors.line),
              textStyle: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600),
            ),
            segments: const [
              ButtonSegment(value: ThemeMode.system, icon: Icon(Icons.brightness_auto_outlined, size: 18), label: Text('System')),
              ButtonSegment(value: ThemeMode.light, icon: Icon(Icons.light_mode_outlined, size: 18), label: Text('Light')),
              ButtonSegment(value: ThemeMode.dark, icon: Icon(Icons.dark_mode_outlined, size: 18), label: Text('Dark')),
            ],
            selected: {controller.mode},
            onSelectionChanged: (selection) => controller.setMode(selection.single),
          ),
        ),
      ]),
    );
  }
}
