import 'package:flutter/material.dart';

import 'package:zaylo_mobile/theme/theme_controller.dart';
import 'package:zaylo_mobile/theme/zaylo_theme.dart';

/// Light/dark switching shared by the toggles below, matching the web's
/// public/js/theme.js. Null when the app has no [ThemeScope].
VoidCallback? _toggleFor(BuildContext context) {
  final controller = ThemeScope.maybeOf(context);
  if (controller == null) return null;
  return () => controller.toggle(
        current: ZayloColors.palette.brightness,
        system: MediaQuery.platformBrightnessOf(context),
      );
}

String get _switchLabel => 'Switch to ${ZayloColors.isDark ? 'light' : 'dark'} mode';

/// The web navbar's round theme toggle: a moon in light mode, a sun in dark
/// mode. Renders nothing when the app has no [ThemeScope].
class ThemeToggleButton extends StatelessWidget {
  const ThemeToggleButton({super.key});

  @override
  Widget build(BuildContext context) {
    final toggle = _toggleFor(context);
    if (toggle == null) return const SizedBox.shrink();
    return IconButton(
      tooltip: _switchLabel,
      onPressed: toggle,
      isSelected: ZayloColors.isDark,
      icon: Icon(ZayloColors.isDark ? Icons.light_mode : Icons.dark_mode, size: 17),
      style: ButtonStyle(
        fixedSize: const WidgetStatePropertyAll(Size.square(38)),
        minimumSize: const WidgetStatePropertyAll(Size.square(38)),
        padding: const WidgetStatePropertyAll(EdgeInsets.zero),
        tapTargetSize: MaterialTapTargetSize.shrinkWrap,
        shape: const WidgetStatePropertyAll(CircleBorder()),
        side: WidgetStatePropertyAll(BorderSide(color: ZayloColors.line)),
        foregroundColor: WidgetStatePropertyAll(ZayloColors.ink),
        backgroundColor: WidgetStateProperty.resolveWith((states) =>
            states.contains(WidgetState.pressed) || states.contains(WidgetState.hovered)
                ? ZayloColors.sand
                : Colors.transparent),
        overlayColor: const WidgetStatePropertyAll(Colors.transparent),
      ),
    );
  }
}

/// The web seller sidebar's labelled toggle, for menus and settings lists:
/// it names the mode it switches to. Renders nothing when the app has no
/// [ThemeScope].
class ThemeToggleTile extends StatelessWidget {
  const ThemeToggleTile({super.key});

  @override
  Widget build(BuildContext context) {
    final toggle = _toggleFor(context);
    if (toggle == null) return const SizedBox.shrink();
    return Semantics(
      label: _switchLabel,
      excludeSemantics: true,
      button: true,
      child: ListTile(
        leading: Icon(ZayloColors.isDark ? Icons.light_mode : Icons.dark_mode, color: ZayloColors.ink),
        title: Text(ZayloColors.isDark ? 'Light mode' : 'Dark mode'),
        onTap: toggle,
      ),
    );
  }
}
