import 'package:flutter/material.dart';

import 'package:zaylo_mobile/api/api_exception.dart';
import 'package:zaylo_mobile/auth/buyer/login_screen.dart';
import 'package:zaylo_mobile/features/buyer/account/notifications_screen.dart';
import 'package:zaylo_mobile/state/app_state.dart';
import 'package:zaylo_mobile/theme/zaylo_theme.dart';

/// The bell in the Home top bar: opens the buyer's notifications, with a badge
/// for unread ones. Guests are asked to sign in first.
class NotificationBellButton extends StatefulWidget {
  const NotificationBellButton({super.key});

  @override
  State<NotificationBellButton> createState() => _NotificationBellButtonState();
}

class _NotificationBellButtonState extends State<NotificationBellButton> {
  int _unread = 0;
  int? _loadedForUser;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final userId = AppScope.of(context).user?.id;
    if (userId != _loadedForUser) {
      _loadedForUser = userId;
      userId == null ? _unread = 0 : _loadUnread();
    }
  }

  Future<void> _loadUnread() async {
    try {
      final unread = (await AppScope.read(context).api.notifications()).unreadCount;
      if (mounted) setState(() => _unread = unread);
    } on ApiException {
      // The bell still opens the list without a badge.
    }
  }

  Future<void> _open() async {
    final signedIn = AppScope.read(context).signedIn;
    await Navigator.of(context).push(MaterialPageRoute(
      builder: (_) => signedIn ? const NotificationsScreen() : const LoginScreen(),
    ));
    // Opening notifications marks them read.
    if (mounted && AppScope.read(context).signedIn) await _loadUnread();
  }

  @override
  Widget build(BuildContext context) => IconButton(
        visualDensity: VisualDensity.compact,
        tooltip: _unread > 0 ? 'Notifications, $_unread unread' : 'Notifications',
        onPressed: _open,
        icon: Badge(
          isLabelVisible: _unread > 0,
          label: Text(_unread > 99 ? '99+' : '$_unread'),
          backgroundColor: ZayloColors.ink,
          child: const Icon(Icons.notifications_none_rounded),
        ),
      );
}
