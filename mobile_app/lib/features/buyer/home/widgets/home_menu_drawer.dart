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
                  leading: const Icon(Icons.chair_alt_outlined),
                  title: const Text('Home and Garden'),
                  onTap: () => onCatalog('home_garden', 'Home and Garden')),
              ListTile(
                  leading: const Icon(Icons.kitchen_outlined),
                  title: const Text('Kitchen Appliances'),
                  onTap: () => onCatalog('kitchen_appliances', 'Kitchen Appliances')),
              ListTile(
                  leading: const Icon(Icons.chair_outlined),
                  title: const Text('Furniture & Decor'),
                  onTap: () => onCatalog('furniture_decor', 'Furniture & Decor')),
              ListTile(
                  leading: const Icon(Icons.yard_outlined),
                  title: const Text('Gardening Tools'),
                  onTap: () => onCatalog('gardening_tools', 'Gardening Tools')),
              ListTile(
                  leading: const Icon(Icons.deck_outlined),
                  title: const Text('Outdoor Living'),
                  onTap: () => onCatalog('outdoor_living', 'Outdoor Living')),
              ListTile(
                  leading: const Icon(Icons.handyman_outlined),
                  title: const Text('Home Improvement Tools'),
                  onTap: () => onCatalog('home_improvement_tools', 'Home Improvement Tools')),
              ListTile(
                  leading: const Icon(Icons.bed_outlined),
                  title: const Text('Bedding & Bath'),
                  onTap: () => onCatalog('bedding_bath', 'Bedding & Bath')),
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
