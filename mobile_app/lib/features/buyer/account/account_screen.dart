import 'package:flutter/material.dart';

import 'package:zaylo_mobile/api/api_exception.dart';
import 'package:zaylo_mobile/components/buttons.dart';
import 'package:zaylo_mobile/components/complaints.dart';
import 'package:zaylo_mobile/components/help_bot.dart';
import 'package:zaylo_mobile/components/layout.dart';
import 'package:zaylo_mobile/components/policies.dart';
import 'package:zaylo_mobile/components/profile_photo.dart';
import 'package:zaylo_mobile/components/theme_toggle.dart';
import 'package:zaylo_mobile/features/buyer/account/identity_screen.dart';
import 'package:zaylo_mobile/features/buyer/account/notifications_screen.dart';
import 'package:zaylo_mobile/features/buyer/account/password_screen.dart';
import 'package:zaylo_mobile/features/buyer/account/profile_screen.dart';
import 'package:zaylo_mobile/features/buyer/account/support_screen.dart';
import 'package:zaylo_mobile/features/buyer/address/addresses_screen.dart';
import 'package:zaylo_mobile/features/buyer/messages/seller_inbox_screen.dart';
import 'package:zaylo_mobile/features/buyer/orders/orders_screen.dart';
import 'package:zaylo_mobile/features/buyer/widgets/shop_actions.dart';
import 'package:zaylo_mobile/features/buyer/widgets/signed_out_prompt.dart';
import 'package:zaylo_mobile/features/buyer/widgets/tab_header.dart';
import 'package:zaylo_mobile/state/app_state.dart';
import 'package:zaylo_mobile/theme/zaylo_theme.dart';

class AccountScreen extends StatefulWidget {
  const AccountScreen({super.key});

  @override
  State<AccountScreen> createState() => _AccountScreenState();
}

class _AccountScreenState extends State<AccountScreen> {
  int _unread = 0;
  int _unreadSupport = 0;
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

  /// Badges for notifications and support replies. Each is
  /// optional; the screen still works when one cannot load.
  Future<void> _loadUnread() async {
    final state = AppScope.read(context);
    final api = state.api;
    Future<int> count(Future<int> Function() load) async {
      try {
        return await load();
      } on ApiException {
        return 0;
      }
    }

    final counts = await Future.wait([
      count(() async => (await api.notifications()).unreadCount),
      count(api.supportUnreadCount),
    ]);
    if (!mounted) return;
    setState(() {
      _unread = counts[0];
      _unreadSupport = counts[1];
    });
    // The ID badge follows approvals made while the app was open.
    try {
      state.updateUser(await api.me());
    } on ApiException {
      // Keep the profile we have.
    }
  }

  void _push(Widget screen) => Navigator.of(context).push(MaterialPageRoute(builder: (_) => screen)).then((_) {
        _loadUnread();
        if (mounted) AppScope.read(context).refreshChatUnread();
      });

  static Widget? _badge(int count) => count > 0 ? Badge(label: Text('$count'), backgroundColor: Colors.redAccent) : null;

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
              ProfilePhotoEditor(
                api: state.api,
                name: user.name,
                photoUrl: user.avatarUrl,
                onChanged: (url) => state.updateUser(user.withAvatar(url)),
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
                icon: Icons.chat_bubble_outline,
                label: 'Messages',
                trailing: _badge(AppScope.of(context).chatUnread),
                onTap: () => _push(const SellerInboxScreen()),
              ),
              _MenuTile(
                icon: Icons.notifications_none,
                label: 'Notifications',
                trailing: _badge(_unread),
                onTap: () => _push(const NotificationsScreen()),
              ),
              _MenuTile(
                icon: Icons.badge_outlined,
                label: 'ID verification',
                trailing: user.identityVerified
                    ? Icon(Icons.verified, size: 18, color: ZayloColors.success)
                    : Text('Required to order', style: TextStyle(fontSize: 11, color: ZayloColors.warning)),
                onTap: () => _push(const IdentityScreen()),
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
          const SectionCard(padding: EdgeInsets.zero, child: ThemeToggleTile()),
          const SizedBox(height: 12),
          SectionCard(
            padding: EdgeInsets.zero,
            child: _MenuTile(
              icon: Icons.description_outlined,
              label: 'Terms & policies',
              onTap: () => _push(PoliciesScreen(api: state.api)),
              last: true,
            ),
          ),
          const SizedBox(height: 22),
          const Eyebrow('Help'),
          const SizedBox(height: 9),
          SectionCard(
            padding: EdgeInsets.zero,
            child: Column(children: [
              _MenuTile(
                icon: Icons.smart_toy_outlined,
                label: 'Help bot (FAQs)',
                onTap: () => _push(HelpBotScreen(
                  api: state.api,
                  // Replaces the bot with the support chat.
                  onContactSupport: () => Navigator.of(context)
                      .pushReplacement(MaterialPageRoute(builder: (_) => const SupportScreen())),
                )),
              ),
              _MenuTile(
                icon: Icons.support_agent_outlined,
                label: 'Help & support',
                trailing: _badge(_unreadSupport),
                onTap: () => _push(const SupportScreen()),
              ),
              _MenuTile(
                icon: Icons.report_gmailerrorred_outlined,
                label: 'Complaints',
                onTap: () => _push(ComplaintsScreen(
                  api: state.api,
                  emptyMessage: 'Report a problem from an order’s details and follow the case here.',
                )),
                last: true,
              ),
            ]),
          ),
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
