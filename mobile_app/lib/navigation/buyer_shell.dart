import 'package:flutter/material.dart';

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
/// scroll stops.
class BuyerShell extends StatefulWidget {
  const BuyerShell({super.key});

  @override
  State<BuyerShell> createState() => _BuyerShellState();
}

class _BuyerShellState extends State<BuyerShell> {
  static const _bagTab = 2;
  static const _tabs = [StorefrontScreen(), SavedScreen(), BagScreen(), AccountScreen()];

  final _navVisibility = NavBarVisibility();

  @override
  void dispose() {
    _navVisibility.dispose();
    super.dispose();
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
                icon: Badge(
                  isLabelVisible: state.cartCount > 0,
                  label: Text('${state.cartCount}'),
                  child: const CartIcon(),
                ),
                selectedIcon: Badge(
                  isLabelVisible: state.cartCount > 0,
                  label: Text('${state.cartCount}'),
                  child: const CartIcon(filled: true),
                ),
                label: 'Bag',
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
