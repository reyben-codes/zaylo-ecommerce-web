import 'package:flutter/material.dart';

import 'package:zaylo_mobile/components/app_update.dart';
import 'package:zaylo_mobile/components/theme_toggle.dart';
import 'package:zaylo_mobile/state/chat_head_state.dart';
import 'package:zaylo_mobile/theme/zaylo_theme.dart';

class HomeMenuDrawer extends StatelessWidget {
  const HomeMenuDrawer({super.key, 
    required this.signedIn,
    required this.onCatalog,
    required this.onOrders,
    required this.onMessages,
    required this.onSignIn,
    required this.onRegister,
    required this.onRiderLogin,
  });

  final bool signedIn;
  final void Function(String? category, String? title) onCatalog;
  final VoidCallback onOrders;
  final VoidCallback onMessages;
  final VoidCallback onSignIn;
  final VoidCallback onRegister;
  final VoidCallback onRiderLogin;

  @override
  Widget build(BuildContext context) => Drawer(
        backgroundColor: ZayloColors.paper,
        child: SafeArea(
          child: ListView(
            padding: const EdgeInsets.all(ZayloSpace.page),
            children: [
              Image.asset(ZayloAssets.logo,
                  width: 154,
                  height: 44,
                  fit: BoxFit.contain,
                  alignment: Alignment.centerLeft,
                  color: ZayloAssets.logoTint,
                  colorBlendMode: BlendMode.srcIn),
              const SizedBox(height: 30),
              ListTile(
                  leading: const Icon(Icons.storefront_outlined),
                  title: const Text('All products'),
                  onTap: () => onCatalog(null, null)),
              ListTile(
                  leading: const Icon(Icons.new_releases_outlined),
                  title: const Text('New arrivals'),
                  onTap: () => onCatalog(null, 'New arrivals')),
              ListTile(
                  leading: const Icon(Icons.checkroom_outlined),
                  title: const Text('Fashion'),
                  onTap: () => onCatalog('fashion', 'Fashion')),
              ListTile(
                  leading: const Icon(Icons.devices_outlined),
                  title: const Text('Electronics'),
                  onTap: () => onCatalog('electronics', 'Electronics')),
              ListTile(
                  leading: const Icon(Icons.chair_outlined),
                  title: const Text('Home & Living'),
                  onTap: () => onCatalog('home_living', 'Home & Living')),
              ListTile(
                  leading: const Icon(Icons.spa_outlined),
                  title: const Text('Beauty & Health'),
                  onTap: () => onCatalog('beauty_health', 'Beauty & Health')),
              const Divider(),
              ListTile(leading: const Icon(Icons.local_shipping_outlined), title: const Text('My orders'), onTap: onOrders),
              ListTile(leading: const Icon(Icons.chat_bubble_outline), title: const Text('Messages'), onTap: onMessages),
              if (!signedIn) ...[
                ListTile(leading: const Icon(Icons.person_outline), title: const Text('Log in'), onTap: onSignIn),
                ListTile(
                    leading: const Icon(Icons.person_add_alt_1_outlined),
                    title: const Text('Create new account'),
                    onTap: onRegister),
                const Divider(),
                ListTile(
                    leading: const Icon(Icons.two_wheeler_outlined),
                    title: const Text('Login as a Rider'),
                    onTap: onRiderLogin),
              ],
              const Divider(),
              const ThemeToggleTile(),
              ListTile(
                leading: Icon(Icons.smart_toy_outlined, color: ZayloColors.ink),
                title: const Text('Chatbot'),
                onTap: () {
                  final chatHead = HideChatHead.maybeOf(context);
                  Navigator.pop(context);
                  chatHead?.restore();
                },
              ),
              ListTile(
                leading: Icon(Icons.auto_awesome_outlined, color: ZayloColors.ink),
                title: const Text('Patch notes'),
                onTap: () {
                  final rootContext = Navigator.of(context, rootNavigator: true).context;
                  Navigator.pop(context);
                  showPatchNotesDialog(rootContext);
                },
              ),
            ],
          ),
        ),
      );
}
