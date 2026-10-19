import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';

import 'package:zaylo_mobile/theme/zaylo_theme.dart';

/// Whether the floating nav bar is showing. Feed it the page's scroll
/// notifications: it hides while the user scrolls down and comes back when
/// they scroll up or the scroll comes to rest.
class NavBarVisibility extends ValueNotifier<bool> {
  NavBarVisibility() : super(true);

  /// For `NotificationListener<ScrollNotification>.onNotification`.
  bool handleScroll(ScrollNotification notification) {
    // Sideways carousels and chip rows never hide the bar.
    if (notification.metrics.axis != Axis.vertical) return false;
    if (notification is ScrollUpdateNotification) {
      final delta = notification.scrollDelta ?? 0;
      if (delta > 0 && notification.metrics.pixels > 0) {
        value = false;
      } else if (delta < 0) {
        value = true;
      }
    } else if (notification is ScrollEndNotification) {
      value = true;
    }
    return false;
  }
}

/// Whether a floating button is showing: hidden while the page scrolls in
/// either direction, back once the scroll comes to rest.
class ScrollIdleVisibility extends ValueNotifier<bool> {
  ScrollIdleVisibility() : super(true);

  /// For `NotificationListener<ScrollNotification>.onNotification`.
  bool handleScroll(ScrollNotification notification) {
    // Sideways carousels and chip rows never hide the button.
    if (notification.metrics.axis != Axis.vertical) return false;
    if (notification is ScrollUpdateNotification && (notification.scrollDelta ?? 0) != 0) {
      value = false;
    } else if (notification is ScrollEndNotification) {
      value = true;
    }
    return false;
  }
}

/// A rounded bottom bar that floats above the page, as in the Facebook app.
///
/// Use it as the `bottomNavigationBar` of a `Scaffold(extendBody: true)` so
/// content scrolls behind it; the body then sees the bar's height in
/// `MediaQuery.paddingOf(context).bottom` and should pad its lists by that.
class FloatingNavBar extends StatelessWidget {
  const FloatingNavBar({
    super.key,
    required this.visibility,
    required this.selectedIndex,
    required this.onDestinationSelected,
    required this.destinations,
  });

  final ValueListenable<bool> visibility;
  final int selectedIndex;
  final ValueChanged<int> onDestinationSelected;
  final List<Widget> destinations;

  @override
  Widget build(BuildContext context) => SafeArea(
        top: false,
        minimum: const EdgeInsets.fromLTRB(16, 0, 16, 12),
        child: ValueListenableBuilder<bool>(
          valueListenable: visibility,
          builder: (context, visible, child) => AnimatedSlide(
            offset: visible ? Offset.zero : const Offset(0, 2),
            duration: const Duration(milliseconds: 250),
            curve: Curves.easeOutCubic,
            child: AnimatedOpacity(
              opacity: visible ? 1 : 0,
              duration: const Duration(milliseconds: 200),
              child: IgnorePointer(ignoring: !visible, child: child),
            ),
          ),
          child: DecoratedBox(
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(36),
              boxShadow: [
                BoxShadow(
                    color: ZayloColors.shadow.withValues(alpha: ZayloColors.isDark ? .5 : .12),
                    blurRadius: 24,
                    offset: const Offset(0, 8)),
                BoxShadow(
                    color: ZayloColors.shadow.withValues(alpha: ZayloColors.isDark ? .3 : .06),
                    blurRadius: 4,
                    offset: const Offset(0, 1)),
              ],
            ),
            child: ClipRRect(
              borderRadius: BorderRadius.circular(36),
              // The outer SafeArea already clears the system inset.
              child: MediaQuery.removePadding(
                context: context,
                removeBottom: true,
                removeLeft: true,
                removeRight: true,
                child: NavigationBar(
                  height: 68,
                  elevation: 0,
                  backgroundColor: ZayloColors.paper,
                  surfaceTintColor: Colors.transparent,
                  indicatorColor: ZayloColors.sand,
                  labelBehavior: NavigationDestinationLabelBehavior.alwaysHide,
                  selectedIndex: selectedIndex,
                  onDestinationSelected: onDestinationSelected,
                  destinations: destinations,
                ),
              ),
            ),
          ),
        ),
      );
}

/// A top bar that slides up out of view on the same cue as [FloatingNavBar].
/// Stack it over the page and pad the page's content by the bar's height.
class AutoHideTopBar extends StatelessWidget {
  const AutoHideTopBar({super.key, required this.visibility, required this.child});

  final ValueListenable<bool> visibility;
  final Widget child;

  @override
  Widget build(BuildContext context) => ValueListenableBuilder<bool>(
        valueListenable: visibility,
        builder: (context, visible, child) => AnimatedSlide(
          offset: visible ? Offset.zero : const Offset(0, -1),
          duration: const Duration(milliseconds: 250),
          curve: Curves.easeOutCubic,
          child: AnimatedOpacity(
            opacity: visible ? 1 : 0,
            duration: const Duration(milliseconds: 200),
            child: IgnorePointer(ignoring: !visible, child: child),
          ),
        ),
        child: child,
      );
}
