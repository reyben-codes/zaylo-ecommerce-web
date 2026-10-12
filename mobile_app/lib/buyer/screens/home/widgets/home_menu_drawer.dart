import 'package:flutter/material.dart';

import '../../../../core/theme/zaylo_theme.dart';
import '../../../../core/widgets/appearance_picker.dart';

class HomeMenuDrawer extends StatelessWidget {
  const HomeMenuDrawer({super.key, 
    required this.signedIn,
    required this.onCatalog,
    required this.onOrders,
    required this.onSignIn,
    required this.onRegister,
    required this.onRiderLogin,
  });

  final bool signedIn;
  final void Function(String? category, String? title) onCatalog;
  final VoidCallback onOrders;
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
              const AppearancePicker(),
            ],
          ),
        ),
      );
}
