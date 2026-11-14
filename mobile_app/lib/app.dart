import 'package:flutter/material.dart';
import 'dart:async';
import 'package:zaylo_mobile/notifications/push_notifications.dart';
import 'package:zaylo_mobile/notifications/push_navigation.dart';

import 'package:zaylo_mobile/api/rider_api.dart';
import 'package:zaylo_mobile/components/app_update.dart';
import 'package:zaylo_mobile/features/buyer/messages/chat_head_overlay.dart';
import 'package:zaylo_mobile/navigation/buyer_shell.dart';
import 'package:zaylo_mobile/navigation/rider_shell.dart';
import 'package:zaylo_mobile/state/app_state.dart';
import 'package:zaylo_mobile/theme/theme_controller.dart';
import 'package:zaylo_mobile/theme/zaylo_theme.dart';

/// Lets the floating chat bubble, which sits above every screen, open Messages.
final zayloNavigatorKey = GlobalKey<NavigatorState>();

class ZayloApp extends StatefulWidget {
  ZayloApp({super.key, required this.state, ThemeController? theme, this.rider}) : theme = theme ?? ThemeController();

  final AppState state;
  final ThemeController theme;

  /// The rider client whose saved session reopens the rider app on launch; one for the live server by default.
  final RiderApi? rider;

  @override
  State<ZayloApp> createState() => _ZayloAppState();
}

class _ZayloAppState extends State<ZayloApp> with WidgetsBindingObserver {
  StreamSubscription<PushTap>? _pushTaps;
  /// Suggests updating when a newer app has been published.
  late final _updates = AppUpdatePrompt(navigatorKey: zayloNavigatorKey, load: widget.state.api.appVersion);

  @override
  void initState() {
    super.initState();
    _pushTaps = PushNotifications.instance.taps.listen(_openPush);
    WidgetsBinding.instance.addPostFrameCallback((_) => PushNotifications.instance.flushPendingTap());
    WidgetsBinding.instance.addObserver(this);
    widget.theme.addListener(_applyTheme);
    ZayloColors.use(_brightness);
    WidgetsBinding.instance.addPostFrameCallback((_) async {
      await _updates.check();
      if (!WidgetsBinding.instance.runtimeType.toString().contains('Test')) {
        await _updates.checkPatchNotes();
      }
    });
    WidgetsBinding.instance.addPostFrameCallback((_) => _resumeRider());
  }

  /// A rider who signed in before (and never signed out) goes straight back to the rider app.
  Future<void> _resumeRider() async {
    try {
      final rider = widget.rider ?? RiderApi(baseUrl: widget.state.api.baseUrl);
      if (!await rider.resume()) return;
      zayloNavigatorKey.currentState?.push(MaterialPageRoute(builder: (_) => RiderShell(api: rider)));
    } finally {
      PushNotifications.instance.navigationReady();
    }
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      _updates.check();
      unawaited(PushNotifications.instance.sync());
    }
  }

  Future<void> _openPush(PushTap tap) async {
    try {
      final destination = await pushDestination(tap, widget.state);
      if (!mounted || destination == null || tap.session.api.token == null) return;
      // Recheck identity after asynchronous conversation loading/account changes.
      if (!tap.session.rider && widget.state.user?.id != tap.session.userId) return;
      zayloNavigatorKey.currentState?.push(MaterialPageRoute(builder: (_) => destination));
    } catch (_) {
      // Offline/stale notifications never prevent opening the app normally.
    }
  }

  @override
  void dispose() {
    _pushTaps?.cancel();
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
            navigatorKey: zayloNavigatorKey,
            // The Messenger-style chat bubble floats above every buyer screen (HideChatHead hides it).
            builder: (context, child) => ChatHeadOverlay(navigatorKey: zayloNavigatorKey, child: child!),
            debugShowCheckedModeBanner: false,
            theme: zayloTheme(),
            // MaterialApp's own theme animation would interpolate from the
            // old palette while screens have already switched.
            themeAnimationDuration: Duration.zero,
            home: const BuyerShell(),
          ),
        ),
      );
}
