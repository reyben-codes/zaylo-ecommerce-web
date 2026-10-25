import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

/// One full set of colors. The dark set keeps the light set's roles, so
/// `ink` is always the strongest foreground and `white` is always the color
/// that reads on top of `ink` (a card surface in light mode, near-black in
/// dark mode).
@immutable
class ZayloPalette {
  const ZayloPalette({
    required this.brightness,
    required this.ink,
    required this.cream,
    required this.paper,
    required this.sand,
    required this.taupe,
    required this.muted,
    required this.line,
    required this.cardBorder,
    required this.white,
    required this.onInkMuted,
    required this.onInkFaint,
    required this.shadow,
    required this.success,
    required this.successSoft,
    required this.warning,
    required this.danger,
    required this.dangerSoft,
    required this.dangerBorder,
    required this.dangerText,
    required this.info,
    required this.accent,
    required this.bronze,
  });

  final Brightness brightness;
  final Color ink;
  final Color cream;
  final Color paper;
  final Color sand;
  final Color taupe;
  final Color muted;
  final Color line;

  /// Warm brown outline that lifts product cards off the page (web: --c-c7a78f).
  final Color cardBorder;
  final Color white;

  /// Secondary and tertiary copy on an `ink` background.
  final Color onInkMuted;
  final Color onInkFaint;
  final Color shadow;

  final Color success;
  final Color successSoft;
  final Color warning;
  final Color danger;
  final Color dangerSoft;
  final Color dangerBorder;
  final Color dangerText;
  final Color info;

  /// Bronze highlight for pressed buttons and focus, as on the web.
  final Color accent;

  /// The italic second line of the home hero headline, as on the web.
  final Color bronze;

  static const light = ZayloPalette(
    brightness: Brightness.light,
    ink: Color(0xFF1A1714),
    cream: Color(0xFFFAF7F2),
    paper: Color(0xFFFFFDFC),
    sand: Color(0xFFEDE4D8),
    taupe: Color(0xFF86786B),
    muted: Color(0xFF6D635A),
    line: Color(0xFFE7DED4),
    cardBorder: Color(0xFFC7A78F),
    white: Color(0xFFFFFFFF),
    onInkMuted: Color(0xFFD7CCC0),
    onInkFaint: Color(0xFFB9ACA0),
    shadow: Color(0xFF1A1714),
    success: Color(0xFF2E7D32),
    successSoft: Color(0xFFE8F5E9),
    warning: Color(0xFFB26A00),
    danger: Color(0xFFC0392B),
    dangerSoft: Color(0xFFFDECEA),
    dangerBorder: Color(0xFFF5C2BD),
    dangerText: Color(0xFF8A1C12),
    info: Color(0xFF1565C0),
    accent: Color(0xFF9A755E),
    bronze: Color(0xFFB28B6F),
  );

  /// Warm charcoal rather than pure black, to keep the storefront's
  /// paper-and-ink character at night.
  static const dark = ZayloPalette(
    brightness: Brightness.dark,
    ink: Color(0xFFF2ECE4),
    cream: Color(0xFF141210),
    paper: Color(0xFF1C1916),
    sand: Color(0xFF332C26),
    taupe: Color(0xFFA09282),
    muted: Color(0xFFB3A799),
    line: Color(0xFF3A332C),
    cardBorder: Color(0xFF755943),
    white: Color(0xFF221E1A),
    onInkMuted: Color(0xFF5E554D),
    onInkFaint: Color(0xFF7A6F64),
    shadow: Color(0xFF000000),
    success: Color(0xFF81C784),
    successSoft: Color(0xFF1C2E1E),
    warning: Color(0xFFF0B35A),
    danger: Color(0xFFEF7B70),
    dangerSoft: Color(0xFF3A1D1A),
    dangerBorder: Color(0xFF6B2C26),
    dangerText: Color(0xFFF4ACA4),
    info: Color(0xFF82B4EE),
    accent: Color(0xFFC9A088),
    bronze: Color(0xFFC39B7F),
  );
}

/// Centralized visual tokens. Change marketplace styling here, not in screens.
///
/// Values follow the active palette, which [ZayloColors.use] swaps when the
/// appearance changes; the app then rebuilds every widget so screens pick up
/// the new colors.
abstract final class ZayloColors {
  static ZayloPalette _palette = ZayloPalette.light;

  static ZayloPalette get palette => _palette;
  static bool get isDark => _palette.brightness == Brightness.dark;

  static void use(Brightness brightness) =>
      _palette = brightness == Brightness.dark ? ZayloPalette.dark : ZayloPalette.light;

  static Color get ink => _palette.ink;
  static Color get cream => _palette.cream;
  static Color get paper => _palette.paper;
  static Color get sand => _palette.sand;
  static Color get taupe => _palette.taupe;
  static Color get muted => _palette.muted;
  static Color get line => _palette.line;
  static Color get cardBorder => _palette.cardBorder;
  static Color get white => _palette.white;
  static Color get onInkMuted => _palette.onInkMuted;
  static Color get onInkFaint => _palette.onInkFaint;
  static Color get shadow => _palette.shadow;
  static Color get success => _palette.success;
  static Color get successSoft => _palette.successSoft;
  static Color get warning => _palette.warning;
  static Color get danger => _palette.danger;
  static Color get dangerSoft => _palette.dangerSoft;
  static Color get dangerBorder => _palette.dangerBorder;
  static Color get dangerText => _palette.dangerText;
  static Color get info => _palette.info;
  static Color get accent => _palette.accent;
  static Color get bronze => _palette.bronze;
}

/// Brand artwork. The two logo files are cropped differently, so dark mode
/// recolors the logo with a tint (pass it as `color` with
/// `BlendMode.srcIn`) rather than swapping files, which keeps its size.
abstract final class ZayloAssets {
  /// For page backgrounds (`cream`, `paper`, `white`).
  static const logo = 'assets/images/ZAYLO_LOGO_DARK.png';
  static Color? get logoTint => ZayloColors.isDark ? ZayloColors.ink : null;

  /// For `ink` backgrounds.
  static const logoOnInk = 'assets/images/ZAYLO_LOGO_LIGHT.png';
  static Color? get logoOnInkTint => ZayloColors.isDark ? ZayloColors.white : null;

  /// For photos under a dark scrim, which stay dark in both modes.
  static const logoOnPhoto = 'assets/images/ZAYLO_LOGO_LIGHT.png';
}

/// Button colors shared with the web storefront (public/css/style.css):
/// primary buttons are filled with ink and turn accent when pressed,
/// secondary buttons are outlined in ink and fill with ink when pressed, and
/// disabled buttons fade instead of turning grey. Only colors and shape live
/// here, so each button keeps its own size and padding.
abstract final class ZayloButtons {
  static const _disabledAlpha = .48;
  static final _shape = WidgetStatePropertyAll(RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)));

  static bool _pressed(Set<WidgetState> states) =>
      states.contains(WidgetState.pressed) || states.contains(WidgetState.hovered);

  static Color get _faded => ZayloColors.ink.withValues(alpha: _disabledAlpha);

  static ButtonStyle get primary => ButtonStyle(
        backgroundColor: WidgetStateProperty.resolveWith((states) {
          if (states.contains(WidgetState.disabled)) return _faded;
          return _pressed(states) ? ZayloColors.accent : ZayloColors.ink;
        }),
        foregroundColor: WidgetStatePropertyAll(ZayloColors.white),
        iconColor: WidgetStatePropertyAll(ZayloColors.white),
        overlayColor: const WidgetStatePropertyAll(Colors.transparent),
        elevation: const WidgetStatePropertyAll(0),
        shape: _shape,
      );

  static ButtonStyle get secondary => ButtonStyle(
        backgroundColor: WidgetStateProperty.resolveWith(
            (states) => !states.contains(WidgetState.disabled) && _pressed(states) ? ZayloColors.ink : Colors.transparent),
        foregroundColor: WidgetStateProperty.resolveWith(_secondaryForeground),
        iconColor: WidgetStateProperty.resolveWith(_secondaryForeground),
        side: WidgetStateProperty.resolveWith((states) =>
            BorderSide(color: states.contains(WidgetState.disabled) ? _faded : ZayloColors.ink)),
        overlayColor: const WidgetStatePropertyAll(Colors.transparent),
        shape: _shape,
      );

  static Color _secondaryForeground(Set<WidgetState> states) {
    if (states.contains(WidgetState.disabled)) return _faded;
    return _pressed(states) ? ZayloColors.white : ZayloColors.ink;
  }

  static ButtonStyle get text => ButtonStyle(
        foregroundColor: WidgetStateProperty.resolveWith((states) {
          if (states.contains(WidgetState.disabled)) return _faded;
          return _pressed(states) ? ZayloColors.accent : ZayloColors.ink;
        }),
        overlayColor: const WidgetStatePropertyAll(Colors.transparent),
      );
}

abstract final class ZayloSpace {
  static const page = 20.0;
  static const section = 36.0;
  static const card = 16.0;
}

ThemeData zayloTheme() {
  final dark = ZayloColors.isDark;
  return ThemeData(
    useMaterial3: true,
    brightness: ZayloColors.palette.brightness,
    scaffoldBackgroundColor: ZayloColors.cream,
    colorScheme: ColorScheme.fromSeed(
      seedColor: ZayloPalette.light.ink,
      brightness: ZayloColors.palette.brightness,
      primary: ZayloColors.ink,
      onPrimary: ZayloColors.white,
      surface: ZayloColors.paper,
      onSurface: ZayloColors.ink,
      onSurfaceVariant: ZayloColors.muted,
      outline: ZayloColors.line,
      outlineVariant: ZayloColors.line,
      error: ZayloColors.danger,
    ),
    // Mirrors the web storefront: Inter for UI copy and Playfair Display
    // for editorial headings.
    fontFamily: 'Inter',
    dividerColor: ZayloColors.line,
    iconTheme: IconThemeData(color: ZayloColors.ink),
    appBarTheme: AppBarTheme(
      elevation: 0,
      backgroundColor: ZayloColors.cream,
      foregroundColor: ZayloColors.ink,
      surfaceTintColor: Colors.transparent,
      scrolledUnderElevation: 0,
      centerTitle: true,
      titleTextStyle: TextStyle(
        fontFamily: 'Inter',
        color: ZayloColors.ink,
        fontSize: 17,
        fontWeight: FontWeight.w600,
        letterSpacing: -.2,
      ),
      systemOverlayStyle: dark ? SystemUiOverlayStyle.light : SystemUiOverlayStyle.dark,
    ),
    cardTheme: CardThemeData(color: ZayloColors.white, surfaceTintColor: Colors.transparent),
    dialogTheme: DialogThemeData(backgroundColor: ZayloColors.paper, surfaceTintColor: Colors.transparent),
    bottomSheetTheme: BottomSheetThemeData(backgroundColor: ZayloColors.paper, surfaceTintColor: Colors.transparent),
    drawerTheme: DrawerThemeData(backgroundColor: ZayloColors.paper, surfaceTintColor: Colors.transparent),
    snackBarTheme: SnackBarThemeData(
      backgroundColor: ZayloColors.ink,
      contentTextStyle: TextStyle(color: ZayloColors.white),
      actionTextColor: ZayloColors.white,
    ),
    filledButtonTheme: FilledButtonThemeData(style: ZayloButtons.primary),
    elevatedButtonTheme: ElevatedButtonThemeData(style: ZayloButtons.primary),
    outlinedButtonTheme: OutlinedButtonThemeData(style: ZayloButtons.secondary),
    textButtonTheme: TextButtonThemeData(style: ZayloButtons.text),
    textSelectionTheme: TextSelectionThemeData(cursorColor: ZayloColors.ink),
    textTheme: TextTheme(
      displaySmall: TextStyle(
        fontFamily: 'Playfair Display',
        color: ZayloColors.ink,
        fontSize: 35,
        height: .98,
        fontWeight: FontWeight.w600,
        letterSpacing: -1.1,
      ),
      headlineSmall: TextStyle(
        fontFamily: 'Playfair Display',
        color: ZayloColors.ink,
        fontSize: 27,
        fontWeight: FontWeight.w600,
        letterSpacing: -.6,
      ),
      titleMedium: TextStyle(
        color: ZayloColors.ink,
        fontWeight: FontWeight.w600,
      ),
      bodyMedium: TextStyle(color: ZayloColors.muted, height: 1.45),
      labelLarge: TextStyle(
        color: ZayloColors.ink,
        letterSpacing: .5,
        fontWeight: FontWeight.w700,
      ),
    ),
  );
}
