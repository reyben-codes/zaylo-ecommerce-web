import 'package:flutter/material.dart';

import '../core/widgets/floating_nav_bar.dart';
import 'screens/account/account_screen.dart';
import 'screens/bag/bag_screen.dart';
import 'screens/home/storefront_screen.dart';
import 'screens/saved/saved_screen.dart';
import 'state/app_state.dart';

/// Bottom navigation: Home, Saved, Bag, Account. Tabs stay alive so each
/// keeps its scroll position; any screen can switch tabs via AppState.selectedTab.
/// The floating nav bar slides away while scrolling down and returns when the
/// scroll stops.
class AppShell extends StatefulWidget {
  const AppShell({super.key});

  @override
  State<AppShell> createState() => _AppShellState();
}

class _AppShellState extends State<AppShell> {
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
                  child: const Icon(Icons.shopping_bag_outlined),
                ),
                selectedIcon: const Icon(Icons.shopping_bag),
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
