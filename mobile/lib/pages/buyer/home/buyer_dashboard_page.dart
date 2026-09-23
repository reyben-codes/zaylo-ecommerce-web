import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import '../../../services/api_service.dart';
import '../navigation/buyer_drawer.dart';
import '../shop/products_page.dart';
import '../shop/product_overview_page.dart';

class BuyerDashboardPage extends StatefulWidget {
  final String token;
  final VoidCallback? onProfileTap;

  const BuyerDashboardPage({
    super.key,
    required this.token,
    this.onProfileTap,
  });

  @override
  State<BuyerDashboardPage> createState() => _BuyerDashboardPageState();
}

class _BuyerDashboardPageState extends State<BuyerDashboardPage> {
  final GlobalKey<ScaffoldState> _scaffoldKey = GlobalKey<ScaffoldState>();
  static const background = Color(0xFFFAF7F2);
  static const dark = Color(0xFF1A1714);
  static const secondaryText = Color(0xFF6B5F54);

  bool _isLoading = true;
  String? _errorMessage;

  Map<String, dynamic>? _user;
  List<dynamic> _categories = [];
  List<dynamic> _flashProducts = [];
  List<dynamic> _suggestedProducts = [];
  List<dynamic> _shops = [];

  @override
  void initState() {
    super.initState();
    _loadDashboard();
  }

  Future<void> _loadDashboard() async {
    try {
      final data = await ApiService.getDashboard(widget.token);
      final shopsData = await ApiService.getShops(token: widget.token);

      if (!mounted) return;

      setState(() {
  _user = data['user'] as Map<String, dynamic>?;
  _categories = (data['categories'] as Map<String, dynamic>?)
          ?.entries
          .map((entry) => {
                'key': entry.key,
                ...entry.value as Map<String, dynamic>,
              })
          .toList() ??
      [];

  _flashProducts = data['flash_products'] as List<dynamic>? ?? [];

  _suggestedProducts =
      data['suggested_products'] as List<dynamic>? ?? [];

  _shops = shopsData['shops'] as List<dynamic>? ?? [];

  _isLoading = false;
});
    } catch (e, stackTrace) {
  debugPrint('DASHBOARD ERROR: $e');
  debugPrint('DASHBOARD STACK TRACE: $stackTrace');

  if (!mounted) return;

  setState(() {
    _errorMessage = e.toString().replaceFirst('Exception: ', '');
    _isLoading = false;
  });
}
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
  key: _scaffoldKey,
  backgroundColor: background,

  drawer: BuyerDrawer(
  userName: _user?['name']?.toString(),
  userEmail: _user?['email']?.toString(),
  onProfileTap: () {
    Navigator.pop(context);

    widget.onProfileTap?.call();
  },
),

  body: SafeArea(
        child: _isLoading
            ? const Center(
                child: CircularProgressIndicator(
                  color: dark,
                ),
              )
            : _errorMessage != null
                ? _buildError()
                : RefreshIndicator(
                    color: dark,
                    onRefresh: _loadDashboard,
                    child: CustomScrollView(
                      slivers: [
                        SliverToBoxAdapter(
                          child: _buildHeader(),
                        ),
                        SliverToBoxAdapter(
                          child: _buildWelcome(),
                        ),
                        SliverToBoxAdapter(
                          child: _buildCategories(),
                        ),
                        SliverToBoxAdapter(
                          child: _buildFlashSales(),
                        ),
                        SliverToBoxAdapter(
                          child: _buildFeaturedShops(),
                        ),
                        SliverToBoxAdapter(
                          child: _buildSuggestedProducts(),
                        ),
                      ],
                    ),
                  ),
      ),
    );
  }

  Widget _buildHeader() {
  return Container(
    color: Colors.white,
    padding: const EdgeInsets.symmetric(
      horizontal: 16,
      vertical: 12,
    ),
    child: SizedBox(
      height: 40,
      child: Stack(
        alignment: Alignment.center,
        children: [
          // Centered ZAYLO logo
          Center(
            child: Image.asset(
              'assets/images/ZAYLO_LOGO_DARK.png',
              height: 40,
            ),
          ),

          // Hamburger menu
          Align(
            alignment: Alignment.centerLeft,
            child: IconButton(
              onPressed: () {
                _scaffoldKey.currentState?.openDrawer();
              },
              padding: EdgeInsets.zero,
              icon: const Icon(
                Icons.menu,
                color: dark,
                size: 26,
              ),
            ),
          ),

          // Favorite + Cart
          Align(
            alignment: Alignment.centerRight,
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                IconButton(
                  onPressed: () {},
                  icon: const Icon(
                    Icons.favorite_border,
                    color: dark,
                  ),
                ),
                IconButton(
                  onPressed: () {},
                  icon: const Icon(
                    Icons.shopping_bag_outlined,
                    color: dark,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    ),
  );
}

  Widget _buildWelcome() {
    final name = _user?['name'] ?? 'Buyer';

    return Container(
      padding: const EdgeInsets.fromLTRB(
        24,
        32,
        24,
        35,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'WELCOME BACK',
            style: TextStyle(
              fontSize: 10,
              letterSpacing: 2,
              color: secondaryText,
            ),
          ),
          const SizedBox(height: 10),
          Text(
            'Hello, $name.',
            style: GoogleFonts.playfairDisplay(
              fontSize: 34,
              fontWeight: FontWeight.w600,
              color: dark,
            ),
          ),
          const SizedBox(height: 8),
          const Text(
            'Discover products from trusted ZAYLO marketplace sellers.',
            style: TextStyle(
              fontSize: 13,
              height: 1.5,
              color: secondaryText,
            ),
          ),
          const SizedBox(height: 22),
          SizedBox(
            height: 46,
            child: ElevatedButton(
              onPressed: () {
          Navigator.push(
            context,
          MaterialPageRoute(
            builder: (_) => ProductsPage(
              token: widget.token,
             ),
            ),
          );
         },
              style: ElevatedButton.styleFrom(
                backgroundColor: dark,
                foregroundColor: Colors.white,
                elevation: 0,
                shape: const RoundedRectangleBorder(
                  borderRadius: BorderRadius.zero,
                ),
              ),
              child: const Text(
                'SHOP MARKETPLACE',
                style: TextStyle(
                  fontSize: 10,
                  fontWeight: FontWeight.w600,
                  letterSpacing: 1.3,
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildCategories() {
    if (_categories.isEmpty) {
      return const SizedBox.shrink();
    }

    return Container(
      color: const Color(0xFFF5F0EA),
      padding: const EdgeInsets.symmetric(
        vertical: 28,
        horizontal: 20,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'SHOP BY CATEGORY',
            style: GoogleFonts.playfairDisplay(
              fontSize: 24,
              fontWeight: FontWeight.w600,
              color: dark,
            ),
          ),
          const SizedBox(height: 18),
          SizedBox(
            height: 105,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              itemCount: _categories.length,
              separatorBuilder: (_, _) =>
                  const SizedBox(width: 22),
              itemBuilder: (context, index) {
                final category = _categories[index];

                final label = category is Map<String, dynamic>
                    ? category['label'] ?? category['name'] ?? ''
                    : category.toString();

                return Container(
                  width: 110,
                  padding: const EdgeInsets.all(14),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    border: Border.all(
                      color: const Color(0xFFECE4DB),
                    ),
                  ),
                  child: Center(
                    child: Text(
                      label.toString().toUpperCase(),
                      textAlign: TextAlign.center,
                      style: const TextStyle(
                        fontSize: 10,
                        fontWeight: FontWeight.w600,
                        letterSpacing: 0.7,
                        color: dark,
                      ),
                    ),
                  ),
                );
              },
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildFlashSales() {
    if (_flashProducts.isEmpty) {
      return const SizedBox.shrink();
    }

    return _buildProductSection(
      title: 'FLASH SALES',
      subtitle: 'Limited-time selections from ZAYLO sellers.',
      products: _flashProducts,
    );
  }

    Widget _buildFeaturedShops() {
    final featuredShops = _shops
        .where((shop) => shop['featured'] == true)
        .toList();

    if (featuredShops.isEmpty) {
      return const SizedBox.shrink();
    }

    return Container(
      color: const Color(0xFFF5F0EA),
      padding: const EdgeInsets.fromLTRB(
        20,
        35,
        20,
        35,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'FEATURED SHOPS',
            style: GoogleFonts.playfairDisplay(
              fontSize: 25,
              fontWeight: FontWeight.w600,
              color: dark,
            ),
          ),
          const SizedBox(height: 5),
          const Text(
            'Discover curated collections from ZAYLO sellers.',
            style: TextStyle(
              fontSize: 12,
              color: secondaryText,
            ),
          ),
          const SizedBox(height: 20),
          SizedBox(
            height: 150,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              itemCount: featuredShops.length,
              separatorBuilder: (_, _) =>
                  const SizedBox(width: 14),
              itemBuilder: (context, index) {
                final shop = featuredShops[index];

                final name = shop['name'] ?? 'Shop';
                final tagline = shop['tagline'] ?? '';
                final productCount = shop['products_count'] ?? 0;

                return Container(
                  width: 250,
                  padding: const EdgeInsets.all(18),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    border: Border.all(
                      color: const Color(0xFFECE4DB),
                    ),
                    boxShadow: const [
                      BoxShadow(
                        color: Color(0x12000000),
                        blurRadius: 12,
                        offset: Offset(0, 5),
                      ),
                    ],
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        name.toString(),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: GoogleFonts.playfairDisplay(
                          fontSize: 20,
                          fontWeight: FontWeight.w600,
                          color: dark,
                        ),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        tagline.toString(),
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          fontSize: 11,
                          height: 1.4,
                          color: secondaryText,
                        ),
                      ),
                      const Spacer(),
                      Row(
                        children: [
                          Text(
                            '$productCount PRODUCTS',
                            style: const TextStyle(
                              fontSize: 9,
                              fontWeight: FontWeight.w600,
                              letterSpacing: 0.8,
                              color: dark,
                            ),
                          ),
                          const Spacer(),
                          const Icon(
                            Icons.arrow_forward,
                            size: 16,
                            color: dark,
                          ),
                        ],
                      ),
                    ],
                  ),
                );
              },
            ),
          ),
        ],
      ),
    );
  }


  Widget _buildSuggestedProducts() {
    if (_suggestedProducts.isEmpty) {
      return const SizedBox.shrink();
    }

    return _buildProductSection(
      title: 'SUGGESTED FOR YOU',
      subtitle: 'Curated picks worth discovering.',
      products: _suggestedProducts,
    );
  }

  Widget _buildProductSection({
    required String title,
    required String subtitle,
    required List<dynamic> products,
  }) {
    return Container(
      padding: const EdgeInsets.fromLTRB(
        20,
        35,
        20,
        35,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title,
            style: GoogleFonts.playfairDisplay(
              fontSize: 25,
              fontWeight: FontWeight.w600,
              color: dark,
            ),
          ),
          const SizedBox(height: 5),
          Text(
            subtitle,
            style: const TextStyle(
              fontSize: 12,
              color: secondaryText,
            ),
          ),
          const SizedBox(height: 20),
          GridView.builder(
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            itemCount: products.length,
            gridDelegate:
                const SliverGridDelegateWithFixedCrossAxisCount(
              crossAxisCount: 2,
              crossAxisSpacing: 12,
              mainAxisSpacing: 18,
              childAspectRatio: 0.68,
            ),
            itemBuilder: (context, index) {
              return _buildProductCard(products[index]);
            },
          ),
        ],
      ),
    );
  }

  Widget _buildProductCard(dynamic product) {
    final imageUrl = product['image_url'];
    final name = product['name'] ?? 'Product';
    final price = product['price'] ?? '0.00';
    final originalPrice = product['original_price'];
    final badge = product['badge'];

    void openProduct() {
      final productId = product['id'];

      if (productId == null) {
        return;
      }

      Navigator.push(
        context,
        MaterialPageRoute(
          builder: (_) => ProductOverviewPage(
            productId: int.parse(productId.toString()),
            token: widget.token,
          ),
        ),
      );
    }

    return GestureDetector(
      onTap: openProduct,
      child: Container(
        decoration: BoxDecoration(
          color: Colors.white,
          border: Border.all(
            color: const Color(0xFFECE4DB),
          ),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(
              child: Stack(
                children: [
                  SizedBox(
                    width: double.infinity,
                    child: imageUrl != null &&
                            imageUrl.toString().isNotEmpty
                        ? Image.network(
                            imageUrl.toString(),
                            fit: BoxFit.cover,
                            errorBuilder: (_, _, _) {
                              return const Center(
                                child: Icon(
                                  Icons.image_outlined,
                                  color: secondaryText,
                                  size: 35,
                                ),
                              );
                            },
                          )
                        : const Center(
                            child: Icon(
                              Icons.image_outlined,
                              color: secondaryText,
                              size: 35,
                            ),
                          ),
                  ),
                  if (badge != null && badge.toString().isNotEmpty)
                    Positioned(
                      top: 8,
                      left: 8,
                      child: Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 7,
                          vertical: 4,
                        ),
                        color: dark,
                        child: Text(
                          badge.toString().toUpperCase(),
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 7,
                            fontWeight: FontWeight.w600,
                            letterSpacing: 0.5,
                          ),
                        ),
                      ),
                    ),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.all(10),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    name.toString(),
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w500,
                      color: dark,
                    ),
                  ),
                  const SizedBox(height: 6),
                  Row(
                    children: [
                      Text(
                        '₱$price',
                        style: const TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.w600,
                          color: dark,
                        ),
                      ),
                      if (originalPrice != null) ...[
                        const SizedBox(width: 6),
                        Expanded(
                          child: Text(
                            '₱$originalPrice',
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              fontSize: 9,
                              color: secondaryText,
                              decoration: TextDecoration.lineThrough,
                            ),
                          ),
                        ),
                      ],
                    ],
                  ),
                  const SizedBox(height: 8),
                  SizedBox(
                    width: double.infinity,
                    height: 32,
                    child: OutlinedButton(
                      onPressed: openProduct,
                      style: OutlinedButton.styleFrom(
                        foregroundColor: dark,
                        side: const BorderSide(
                          color: Color(0xFFE5DFD8),
                        ),
                        shape: const RoundedRectangleBorder(
                          borderRadius: BorderRadius.zero,
                        ),
                        padding: EdgeInsets.zero,
                      ),
                      child: const Text(
                        'VIEW PRODUCT',
                        style: TextStyle(
                          fontSize: 8,
                          fontWeight: FontWeight.w600,
                          letterSpacing: 0.7,
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildError() {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(
              Icons.error_outline,
              size: 45,
              color: dark,
            ),
            const SizedBox(height: 15),
            const Text(
              'Unable to load dashboard.',
              style: TextStyle(
                fontSize: 16,
                fontWeight: FontWeight.w600,
                color: dark,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              _errorMessage ?? 'Unknown error.',
              textAlign: TextAlign.center,
              style: const TextStyle(
                fontSize: 12,
                color: secondaryText,
              ),
            ),
            const SizedBox(height: 20),
            ElevatedButton(
              onPressed: () {
                setState(() {
                  _isLoading = true;
                  _errorMessage = null;
                });
                _loadDashboard();
              },
              style: ElevatedButton.styleFrom(
                backgroundColor: dark,
                foregroundColor: Colors.white,
                elevation: 0,
                shape: const RoundedRectangleBorder(
                  borderRadius: BorderRadius.zero,
                ),
              ),
              child: const Text('TRY AGAIN'),
            ),
          ],
        ),
      ),
    );
  }
}