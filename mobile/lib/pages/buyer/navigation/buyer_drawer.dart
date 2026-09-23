import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

class BuyerDrawer extends StatelessWidget {
  final String? userName;
  final String? userEmail;
  final VoidCallback? onProfileTap;
  final VoidCallback? onOrdersTap;
  final VoidCallback? onWishlistTap;
  final VoidCallback? onCartTap;
  final VoidCallback? onSettingsTap;
  final VoidCallback? onLogoutTap;

  const BuyerDrawer({
    super.key,
    this.userName,
    this.userEmail,
    this.onProfileTap,
    this.onOrdersTap,
    this.onWishlistTap,
    this.onCartTap,
    this.onSettingsTap,
    this.onLogoutTap,
  });

  static const Color background = Color(0xFFFAF7F2);
  static const Color dark = Color(0xFF1A1714);
  static const Color secondaryText = Color(0xFF6B5F54);
  static const Color border = Color(0xFFE8DED3);

  @override
  Widget build(BuildContext context) {
    return Drawer(
      backgroundColor: background,
      width: MediaQuery.of(context).size.width * 0.82,
      child: SafeArea(
        child: Column(
          children: [
            _buildDrawerHeader(),
            const Divider(
              height: 1,
              color: border,
            ),
            Expanded(
              child: ListView(
                padding: const EdgeInsets.symmetric(
                  vertical: 16,
                ),
                children: [
                  _buildSectionLabel('ACCOUNT'),
                  _buildMenuItem(
                    icon: Icons.person_outline,
                    title: 'Profile',
                    onTap: onProfileTap,
                  ),
                  _buildMenuItem(
                    icon: Icons.receipt_long_outlined,
                    title: 'My Orders',
                    onTap: onOrdersTap,
                  ),
                  const SizedBox(height: 20),
                  _buildSectionLabel('SHOPPING'),
                  _buildMenuItem(
                    icon: Icons.favorite_border,
                    title: 'Wishlist',
                    onTap: onWishlistTap,
                  ),
                  _buildMenuItem(
                    icon: Icons.shopping_bag_outlined,
                    title: 'Cart',
                    onTap: onCartTap,
                  ),
                  const SizedBox(height: 20),
                  _buildSectionLabel('PREFERENCES'),
                  _buildMenuItem(
                    icon: Icons.settings_outlined,
                    title: 'Settings',
                    onTap: onSettingsTap,
                  ),
                ],
              ),
            ),
            const Divider(
              height: 1,
              color: border,
            ),
            _buildMenuItem(
              icon: Icons.logout,
              title: 'Logout',
              onTap: onLogoutTap,
              isLogout: true,
            ),
            const Padding(
              padding: EdgeInsets.fromLTRB(24, 8, 24, 24),
              child: Align(
                alignment: Alignment.centerLeft,
                child: Text(
                  'ZAYLO — Refined everyday essentials.',
                  style: TextStyle(
                    color: secondaryText,
                    fontSize: 10,
                    letterSpacing: 0.3,
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildDrawerHeader() {
    return Padding(
      padding: const EdgeInsets.fromLTRB(24, 28, 20, 24),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Image.asset(
            'assets/images/ZAYLO_LOGO_DARK.png',
            height: 38,
          ),
          const SizedBox(height: 24),
          Text(
            userName ?? 'Welcome to ZAYLO',
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: GoogleFonts.playfairDisplay(
              color: dark,
              fontSize: 23,
              fontWeight: FontWeight.w600,
            ),
          ),
          const SizedBox(height: 5),
          Text(
            userEmail ?? 'Your personal shopping space',
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              color: secondaryText,
              fontSize: 12,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildSectionLabel(String title) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(24, 0, 24, 8),
      child: Text(
        title,
        style: const TextStyle(
          color: secondaryText,
          fontSize: 10,
          fontWeight: FontWeight.w700,
          letterSpacing: 1.4,
        ),
      ),
    );
  }

  Widget _buildMenuItem({
    required IconData icon,
    required String title,
    VoidCallback? onTap,
    bool isLogout = false,
  }) {
    return ListTile(
      contentPadding: const EdgeInsets.symmetric(
        horizontal: 24,
        vertical: 2,
      ),
      leading: Icon(
        icon,
        size: 21,
        color: isLogout ? Colors.red.shade700 : dark,
      ),
      title: Text(
        title,
        style: TextStyle(
          color: isLogout ? Colors.red.shade700 : dark,
          fontSize: 14,
          fontWeight: FontWeight.w500,
        ),
      ),
      trailing: Icon(
        Icons.arrow_forward_ios,
        size: 13,
        color: isLogout ? Colors.red.shade700 : secondaryText,
      ),
      onTap: onTap,
    );
  }
}
