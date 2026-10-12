import 'package:flutter/material.dart';

import 'buyer/api/buyer_api.dart';
import 'buyer/app_shell.dart';
import 'buyer/state/app_state.dart';
import 'core/theme/theme_controller.dart';
import 'core/theme/zaylo_theme.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  runApp(ZayloApp(
    state: AppState(api: BuyerApi())..restore(),
    theme: ThemeController()..restore(),
  ));
}

class ZayloApp extends StatefulWidget {
  ZayloApp({super.key, required this.state, ThemeController? theme}) : theme = theme ?? ThemeController();

  final AppState state;
  final ThemeController theme;

  @override
  State<ZayloApp> createState() => _ZayloAppState();
}

class _ZayloAppState extends State<ZayloApp> with WidgetsBindingObserver {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    widget.theme.addListener(_applyTheme);
    ZayloColors.use(_brightness);
  }

  @override
  void dispose() {
    widget.theme.removeListener(_applyTheme);
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangePlatformBrightness() => _applyTheme();

  Brightness get _brightness => switch (widget.theme.mode) {
        ThemeMode.light => Brightness.light,
        ThemeMode.dark => Brightness.dark,
        ThemeMode.system => WidgetsBinding.instance.platformDispatcher.platformBrightness,
      };

  void _applyTheme() {
    if (_brightness == ZayloColors.palette.brightness) return;
    ZayloColors.use(_brightness);
    setState(() {});
    // Screens read ZayloColors directly rather than through an inherited
    // widget, so const and cached subtrees must be told to rebuild. State is
    // kept: this only marks elements dirty.
    void rebuild(Element element) {
      element.markNeedsBuild();
      element.visitChildren(rebuild);
    }

    (context as Element).visitChildren(rebuild);
  }

  @override
  Widget build(BuildContext context) => ThemeScope(
        controller: widget.theme,
        child: AppScope(
          state: widget.state,
          child: MaterialApp(
            title: 'ZAYLO',
            debugShowCheckedModeBanner: false,
            theme: zayloTheme(),
            // MaterialApp's own theme animation would interpolate from the
            // old palette while screens have already switched.
            themeAnimationDuration: Duration.zero,
            home: const AppShell(),
          ),
        ),
      );
}
