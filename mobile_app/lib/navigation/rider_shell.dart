import 'dart:async';

import 'package:flutter/material.dart';

import 'package:zaylo_mobile/api/api_exception.dart';
import 'package:zaylo_mobile/api/rider_api.dart';
import 'package:zaylo_mobile/components/feedback.dart';
import 'package:zaylo_mobile/features/rider/account/rider_account_tab.dart';
import 'package:zaylo_mobile/features/rider/dashboard/rider_dashboard_tab.dart';
import 'package:zaylo_mobile/features/rider/history/rider_history_tab.dart';
import 'package:zaylo_mobile/features/rider/support/rider_support_chat_tab.dart';
import 'package:zaylo_mobile/models/auth_models.dart';
import 'package:zaylo_mobile/models/rider/rider_activity_models.dart';
import 'package:zaylo_mobile/navigation/floating_nav_bar.dart';
import 'package:zaylo_mobile/theme/zaylo_theme.dart';

/// The signed-in rider app: Home, History, Chat (ZAYLO support), and Account
/// tabs. Sign out lives in Account. [api] carries the rider's token; the
/// buyer session in AppState is left untouched.
class RiderShell extends StatefulWidget {
  const RiderShell({super.key, required this.api, this.user});

  final RiderApi api;
  final UserProfile? user;

  @override
  State<RiderShell> createState() => _RiderShellState();
}

class _RiderShellState extends State<RiderShell> {
  static const _homeTab = 0;
  static const _historyTab = 1;
  static const _chatTab = 2;
  static const _titles = ['Home', 'Delivery history', 'Support chat', 'Account'];

  int _tab = _homeTab;
  final _historyPeriod = ValueNotifier(RiderPeriod.all);
  final _navVisibility = NavBarVisibility();
  int _unreadSupport = 0;
  Timer? _unreadPoll;
  bool _signingOut = false;

  @override
  void initState() {
    super.initState();
    _refreshUnread();
    _unreadPoll = Timer.periodic(const Duration(seconds: 30), (_) => _refreshUnread());
  }

  @override
  void dispose() {
    _unreadPoll?.cancel();
    _historyPeriod.dispose();
    _navVisibility.dispose();
    super.dispose();
  }

  Future<void> _refreshUnread() async {
    if (_tab == _chatTab) return; // the open chat marks replies as read
    try {
      final unread = await widget.api.supportUnreadCount();
      if (mounted) setState(() => _unreadSupport = unread);
    } on ApiException catch (error) {
      debugPrint('[ZAYLO] Support unread check failed: ${error.debugDescription}');
    }
  }

  void _showTab(int tab) {
    _navVisibility.value = true;
    setState(() => _tab = tab);
  }

  void _openHistory(RiderPeriod period) {
    _historyPeriod.value = period;
    _showTab(_historyTab);
  }

  Future<void> _signOut() async {
    if (_signingOut) return;
    _signingOut = true;
    _unreadPoll?.cancel();
    try {
      await widget.api.logout();
    } on ApiException {
      // The token is dropped either way; the server expires it on its own.
    }
    widget.api.token = null;
    if (!mounted) return;
    Navigator.of(context).pop();
    showMessage(context, 'Signed out of your rider account.');
  }

  /// Back from another tab returns Home; back on Home asks before signing out.
  Future<void> _onBack() async {
    if (_tab != _homeTab) {
      _showTab(_homeTab);
      return;
    }
    final leave = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Leave rider mode?'),
        content: const Text('You will be signed out of your rider account.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(dialogContext, false), child: const Text('Stay')),
          TextButton(onPressed: () => Navigator.pop(dialogContext, true), child: const Text('Sign out')),
        ],
      ),
    );
    if (leave == true) await _signOut();
  }

  @override
  Widget build(BuildContext context) => PopScope(
        canPop: false,
        onPopInvokedWithResult: (didPop, _) {
          if (!didPop) _onBack();
        },
        child: Scaffold(
          extendBody: true,
          backgroundColor: ZayloColors.white,
          appBar: AppBar(
            backgroundColor: ZayloColors.white,
            automaticallyImplyLeading: false,
            centerTitle: _tab == _homeTab,
            title: _tab == _homeTab
                ? Image.asset(ZayloAssets.logo,
                    height: 28, color: ZayloAssets.logoTint, colorBlendMode: BlendMode.srcIn)
                : Text(_titles[_tab]),
          ),
          // The floating nav bar covers the bottom inset; each tab pads its own list.
          body: NotificationListener<ScrollNotification>(
            // Chat keeps the bar up: its message box sits right above it.
            onNotification: (notification) => _tab != _chatTab && _navVisibility.handleScroll(notification),
            child: IndexedStack(index: _tab, children: [
              for (final (index, tab) in [
                RiderDashboardTab(api: widget.api, onOpenHistory: _openHistory),
                RiderHistoryTab(api: widget.api, period: _historyPeriod),
                RiderSupportChatTab(
                  api: widget.api,
                  visible: _tab == _chatTab,
                  onRead: () {
                    if (_unreadSupport != 0) setState(() => _unreadSupport = 0);
                  },
                ),
                RiderAccountTab(
                  api: widget.api,
                  onSignOut: _signOut,
                  onContactSupport: () => _showTab(_chatTab),
                ),
              ].indexed)
                // Hidden tabs keep their state but stop animating (e.g. spinners).
                TickerMode(enabled: index == _tab, child: tab),
            ]),
          ),
          bottomNavigationBar: FloatingNavBar(
            visibility: _navVisibility,
            selectedIndex: _tab,
            onDestinationSelected: _showTab,
            destinations: [
              const NavigationDestination(
                  icon: Icon(Icons.home_outlined), selectedIcon: Icon(Icons.home), label: 'Home'),
              const NavigationDestination(icon: Icon(Icons.history), label: 'History'),
              NavigationDestination(
                icon: Badge(
                  isLabelVisible: _unreadSupport > 0,
                  label: Text('$_unreadSupport'),
                  child: const Icon(Icons.chat_bubble_outline),
                ),
                selectedIcon: const Icon(Icons.chat_bubble),
                label: 'Chat',
              ),
              const NavigationDestination(
                  icon: Icon(Icons.person_outline), selectedIcon: Icon(Icons.person), label: 'Account'),
            ],
          ),
        ),
      );
}
