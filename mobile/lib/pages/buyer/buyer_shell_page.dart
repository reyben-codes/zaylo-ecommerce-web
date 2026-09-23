import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'home/buyer_dashboard_page.dart';
import 'shop/products_page.dart';

class BuyerShellPage extends StatefulWidget {
  final String token;

  const BuyerShellPage({
    super.key,
    required this.token,
  });

  @override
  State<BuyerShellPage> createState() => _BuyerShellPageState();
}

class _BuyerShellPageState extends State<BuyerShellPage> {
  static const background = Color(0xFFFAF7F2);
  static const dark = Color(0xFF1A1714);
  static const secondaryText = Color(0xFF6B5F54);

  int _currentIndex = 0;

  void _selectTab(int index) {
    setState(() {
      _currentIndex = index;
    });
  }

  @override
  Widget build(BuildContext context) {
    final pages = [
  BuyerDashboardPage(
    token: widget.token,
    onProfileTap: () {
      _selectTab(4);
    },
  ),
  ProductsPage(token: widget.token),
  const _PlaceholderPage(
    title: 'WISHLIST',
    message: 'Your saved products will appear here.',
    icon: Icons.favorite_border,
  ),
  const _PlaceholderPage(
    title: 'CART',
    message: 'Your shopping cart will appear here.',
    icon: Icons.shopping_bag_outlined,
  ),
  const _PlaceholderPage(
    title: 'ACCOUNT',
    message: 'Your account details will appear here.',
    icon: Icons.person_outline,
  ),
];

    return Scaffold(
      backgroundColor: background,
      body: IndexedStack(
        index: _currentIndex,
        children: pages,
      ),
      bottomNavigationBar: Container(
        decoration: const BoxDecoration(
          color: Colors.white,
          border: Border(
            top: BorderSide(
              color: Color(0xFFECE4DB),
            ),
          ),
        ),
        child: SafeArea(
          child: Padding(
            padding: const EdgeInsets.symmetric(
              horizontal: 8,
              vertical: 6,
            ),
            child: BottomNavigationBar(
              currentIndex: _currentIndex,
              onTap: _selectTab,
              backgroundColor: Colors.white,
              elevation: 0,
              type: BottomNavigationBarType.fixed,
              selectedItemColor: dark,
              unselectedItemColor: secondaryText,
              selectedLabelStyle: const TextStyle(
                fontSize: 10,
                fontWeight: FontWeight.w600,
              ),
              unselectedLabelStyle: const TextStyle(
                fontSize: 10,
              ),
              items: const [
                BottomNavigationBarItem(
                  icon: Icon(Icons.home_outlined),
                  activeIcon: Icon(Icons.home),
                  label: 'Home',
                ),
                BottomNavigationBarItem(
                  icon: Icon(Icons.grid_view_outlined),
                  activeIcon: Icon(Icons.grid_view),
                  label: 'Shop',
                ),
                BottomNavigationBarItem(
                  icon: Icon(Icons.favorite_border),
                  activeIcon: Icon(Icons.favorite),
                  label: 'Wishlist',
                ),
                BottomNavigationBarItem(
                  icon: Icon(Icons.shopping_bag_outlined),
                  activeIcon: Icon(Icons.shopping_bag),
                  label: 'Cart',
                ),
                BottomNavigationBarItem(
                  icon: Icon(Icons.person_outline),
                  activeIcon: Icon(Icons.person),
                  label: 'Account',
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _PlaceholderPage extends StatelessWidget {
  final String title;
  final String message;
  final IconData icon;

  const _PlaceholderPage({
    required this.title,
    required this.message,
    required this.icon,
  });

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: _BuyerShellPageState.background,
      appBar: AppBar(
        backgroundColor: Colors.white,
        foregroundColor: _BuyerShellPageState.dark,
        elevation: 0,
        title: Text(
          title,
          style: GoogleFonts.playfairDisplay(
            fontSize: 22,
            fontWeight: FontWeight.w600,
          ),
        ),
      ),
      body: Center(
        child: Padding(
          padding: const EdgeInsets.all(32),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(
                icon,
                size: 42,
                color: _BuyerShellPageState.secondaryText,
              ),
              const SizedBox(height: 18),
              Text(
                message,
                textAlign: TextAlign.center,
                style: const TextStyle(
                  color: _BuyerShellPageState.secondaryText,
                  fontSize: 14,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}