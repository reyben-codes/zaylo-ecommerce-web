import 'package:flutter/material.dart';

import 'package:zaylo_mobile/components/cart_flight.dart';
import 'package:zaylo_mobile/components/cart_icon.dart';
import 'package:zaylo_mobile/features/buyer/account/account_screen.dart';
import 'package:zaylo_mobile/features/buyer/bag/bag_screen.dart';
import 'package:zaylo_mobile/features/buyer/home/storefront_screen.dart';
import 'package:zaylo_mobile/features/buyer/saved/saved_screen.dart';
import 'package:zaylo_mobile/navigation/floating_nav_bar.dart';
import 'package:zaylo_mobile/state/app_state.dart';

/// Bottom navigation: Home, Saved, Bag, Account. Tabs stay alive so each
/// keeps its scroll position; any screen can switch tabs via AppState.selectedTab.
/// The floating nav bar slides away while scrolling down and returns when the
/// scroll stops. Tapping Home while on Home glides back to the top.
class BuyerShell extends StatefulWidget {
  const BuyerShell({super.key});

  @override
  State<BuyerShell> createState() => _BuyerShellState();
}

class _BuyerShellState extends State<BuyerShell> {
  static const _homeTab = 0;
  static const _bagTab = 2;

  final _navVisibility = NavBarVisibility();
  final _homeScroll = ScrollController();
  late final _tabs = [
    StorefrontScreen(scrollController: _homeScroll),
    const SavedScreen(),
    const BagScreen(),
    const AccountScreen()
  ];

  @override
  void dispose() {
    _navVisibility.dispose();
    _homeScroll.dispose();
    super.dispose();
  }

  /// Back to the top of Home: a quick glide that takes a little longer from
  /// further down (capped so it never drags), or a jump with reduced motion.
  void _scrollHomeToTop() {
    if (!_homeScroll.hasClients || _homeScroll.offset <= 0) return;
    if (MediaQuery.disableAnimationsOf(context)) {
      _homeScroll.jumpTo(0);
      return;
    }
    final milliseconds = (300 + _homeScroll.offset / 8).clamp(300, 700).round();
    _homeScroll.animateTo(0, duration: Duration(milliseconds: milliseconds), curve: Curves.easeOutCubic);
  }

  @override
  Widget build(BuildContext context) {
    final state = AppScope.of(context);
    return ValueListenableBuilder<int>(
      valueListenable: state.selectedTab,
      builder: (context, tab, _) => PopScope(
        // Back from another tab returns Home before leaving the app.
        canPop: tab == 0,
        onPopInvokedWithResult: (didPop, _) {
          if (!didPop) state.selectedTab.value = 0;
        },
        child: Scaffold(
          extendBody: true,
          body: NotificationListener<ScrollNotification>(
            // The Bag keeps the bar up: its checkout bar sits right above it.
            onNotification: (notification) => tab != _bagTab && _navVisibility.handleScroll(notification),
            child: IndexedStack(index: tab, children: _tabs),
          ),
          bottomNavigationBar: FloatingNavBar(
            visibility: _navVisibility,
            selectedIndex: tab,
            onDestinationSelected: (index) {
              _navVisibility.value = true;
              if (index == _homeTab && tab == _homeTab) _scrollHomeToTop();
              state.selectedTab.value = index;
            },
            destinations: [
              const NavigationDestination(
                icon: Icon(Icons.home_outlined),
                selectedIcon: Icon(Icons.home),
                label: 'Home',
              ),
              NavigationDestination(
                icon: Badge(
                  isLabelVisible: state.savedCount > 0,
                  label: Text('${state.savedCount}'),
                  child: const Icon(Icons.favorite_border),
                ),
                selectedIcon: const Icon(Icons.favorite),
                label: 'Saved',
              ),
              NavigationDestination(
                icon: CartTarget(
                  child: Badge(
                    isLabelVisible: state.cartCount > 0,
                    label: Text('${state.cartCount}'),
                    child: const CartIcon(),
                  ),
                ),
                selectedIcon: CartTarget(
                  child: Badge(
                    isLabelVisible: state.cartCount > 0,
                    label: Text('${state.cartCount}'),
                    child: const CartIcon(filled: true),
                  ),
                ),
                label: 'Cart',
              ),
              const NavigationDestination(
                icon: Icon(Icons.person_outline),
                selectedIcon: Icon(Icons.person),
                label: 'Account',
              ),
            ],
          ),
        ),
      ),
    );
  }
}
