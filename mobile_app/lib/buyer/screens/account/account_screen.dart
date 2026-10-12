import 'package:flutter/material.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/theme/zaylo_theme.dart';
import '../../../core/widgets/appearance_picker.dart';
import '../../../core/widgets/buttons.dart';
import '../../../core/widgets/layout.dart';
import '../../state/app_state.dart';
import '../../widgets/shop_actions.dart';
import '../../widgets/signed_out_prompt.dart';
import '../../widgets/tab_header.dart';
import '../address/addresses_screen.dart';
import '../orders/orders_screen.dart';
import 'notifications_screen.dart';
import 'password_screen.dart';
import 'profile_screen.dart';

class AccountScreen extends StatefulWidget {
  const AccountScreen({super.key});

  @override
  State<AccountScreen> createState() => _AccountScreenState();
}

class _AccountScreenState extends State<AccountScreen> {
  int _unread = 0;
  int? _lastUserId;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final userId = AppScope.of(context).user?.id;
    if (userId != null && userId != _lastUserId) {
      _lastUserId = userId;
      WidgetsBinding.instance.addPostFrameCallback((_) => _loadUnread());
    }
  }

  Future<void> _loadUnread() async {
    try {
      final feed = await AppScope.read(context).api.notifications();
      if (mounted) setState(() => _unread = feed.unreadCount);
    } on ApiException {
      // The badge is optional; the screen still works without it.
    }
  }

  void _push(Widget screen) =>
      Navigator.of(context).push(MaterialPageRoute(builder: (_) => screen)).then((_) => _loadUnread());

  Future<void> _signOut() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Sign out?'),
        content: const Text('Your bag and saved items stay in your account for next time.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Cancel')),
          TextButton(onPressed: () => Navigator.pop(context, true), child: const Text('Sign out')),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;
    await AppScope.read(context).signOut();
    if (mounted) ShopActions.showTab(context, 0);
  }

  @override
  Widget build(BuildContext context) {
    final state = AppScope.of(context);
    final user = state.user;
    if (user == null) {
      return const SignedOutPrompt(
        icon: Icons.person_outline,
        heading: 'Account',
        eyebrow: 'Your ZAYLO',
        title: 'Welcome to ZAYLO',
        message: 'Sign in to track orders, manage addresses and update your profile.',
      );
    }
    return Scaffold(
      appBar: const TabHeader(eyebrow: 'Your ZAYLO', title: 'Account'),
      body: ListView(
        padding: EdgeInsets.fromLTRB(ZayloSpace.page, ZayloSpace.page, ZayloSpace.page, ZayloSpace.page + MediaQuery.paddingOf(context).bottom),
        children: [
          SectionCard(
            child: Row(children: [
              CircleAvatar(
                radius: 28,
                backgroundColor: ZayloColors.ink,
                child: Text(user.initials,
                    style: TextStyle(color: ZayloColors.white, fontWeight: FontWeight.w700, fontSize: 18)),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text('Hi, ${user.firstName}', style: Theme.of(context).textTheme.headlineSmall?.copyWith(fontSize: 22)),
                  Text(user.email, style: const TextStyle(fontSize: 13)),
                ]),
              ),
            ]),
          ),
          const SizedBox(height: 22),
          Row(children: [
            const Expanded(child: Eyebrow('My orders')),
            TextButton(
              onPressed: () => _push(const OrdersScreen()),
              style: TextButton.styleFrom(foregroundColor: ZayloColors.ink),
              child: const Text('View all  →'),
            ),
          ]),
          SectionCard(
            padding: const EdgeInsets.symmetric(vertical: 14, horizontal: 6),
            child: Row(children: [
              _OrderShortcut(Icons.inventory_2_outlined, 'To ship', () => _push(const OrdersScreen(initialTab: 'to_ship'))),
              _OrderShortcut(Icons.local_shipping_outlined, 'Shipped', () => _push(const OrdersScreen(initialTab: 'shipped'))),
              _OrderShortcut(Icons.task_alt, 'Delivered', () => _push(const OrdersScreen(initialTab: 'delivered'))),
              _OrderShortcut(Icons.cancel_outlined, 'Cancelled', () => _push(const OrdersScreen(initialTab: 'cancelled'))),
            ]),
          ),
          const SizedBox(height: 22),
          const Eyebrow('Settings'),
          const SizedBox(height: 9),
          SectionCard(
            padding: EdgeInsets.zero,
            child: Column(children: [
              _MenuTile(
                icon: Icons.notifications_none,
                label: 'Notifications',
                trailing: _unread > 0 ? Badge(label: Text('$_unread'), backgroundColor: Colors.redAccent) : null,
                onTap: () => _push(const NotificationsScreen()),
              ),
              _MenuTile(icon: Icons.location_on_outlined, label: 'Saved addresses', onTap: () => _push(const AddressesScreen())),
              _MenuTile(icon: Icons.person_outline, label: 'Profile settings', onTap: () => _push(const ProfileScreen())),
              _MenuTile(
                icon: Icons.lock_outline,
                label: user.hasPassword ? 'Change password' : 'Create a password',
                onTap: () => _push(const PasswordScreen()),
                last: true,
              ),
            ]),
          ),
          const SizedBox(height: 12),
          const SectionCard(padding: EdgeInsets.zero, child: AppearancePicker()),
          const SizedBox(height: 22),
          SizedBox(
            width: double.infinity,
            child: OutlinedButton.icon(
              onPressed: _signOut,
              style: secondaryButtonStyle,
              icon: const Icon(Icons.logout),
              label: const Text('SIGN OUT'),
            ),
          ),
          const SizedBox(height: 18),
          Center(
            child: Text('Driven by passion. Defined by your origin',
                style: TextStyle(fontSize: 11, color: ZayloColors.taupe)),
          ),
        ],
      ),
    );
  }
}

class _OrderShortcut extends StatelessWidget {
  const _OrderShortcut(this.icon, this.label, this.onTap);

  final IconData icon;
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Expanded(
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(10),
          child: Padding(
            padding: const EdgeInsets.symmetric(vertical: 6),
            child: Column(children: [
              Icon(icon, color: ZayloColors.ink),
              const SizedBox(height: 6),
              Text(label, style: TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: ZayloColors.ink)),
            ]),
          ),
        ),
      );
}

class _MenuTile extends StatelessWidget {
  const _MenuTile({required this.icon, required this.label, required this.onTap, this.trailing, this.last = false});

  final IconData icon;
  final String label;
  final VoidCallback onTap;
  final Widget? trailing;
  final bool last;

  @override
  Widget build(BuildContext context) => Column(children: [
        ListTile(
          leading: Icon(icon, color: ZayloColors.ink),
          title: Text(label, style: TextStyle(fontWeight: FontWeight.w600, color: ZayloColors.ink, fontSize: 14)),
          trailing: Row(mainAxisSize: MainAxisSize.min, children: [
            if (trailing != null) ...[trailing!, const SizedBox(width: 8)],
            Icon(Icons.chevron_right, color: ZayloColors.muted),
          ]),
          onTap: onTap,
        ),
        if (!last) Divider(height: 1, indent: 56, color: ZayloColors.line),
      ]);
}
