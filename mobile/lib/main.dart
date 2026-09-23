import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'pages/auth/login_page.dart';

void main() {
  runApp(const ZayloApp());
}

// ============================================================
// MAIN APPLICATION
// ============================================================

class ZayloApp extends StatelessWidget {
  const ZayloApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      debugShowCheckedModeBanner: false,
      title: 'ZAYLO',
      theme: ThemeData(
        scaffoldBackgroundColor: const Color(0xFFFAF7F2),
        colorScheme: ColorScheme.fromSeed(
          seedColor: const Color(0xFF1A1714),
        ),
        fontFamily: GoogleFonts.inter().fontFamily,
      ),
      home: const HomePage(),
    );
  }
}

// ============================================================
// HOME PAGE
// PUBLIC / NOT LOGGED IN
// ============================================================

class HomePage extends StatelessWidget {
  const HomePage({super.key});

  static const background = Color(0xFFFAF7F2);
  static const dark = Color(0xFF1A1714);
  static const brown = Color(0xFFB28B6F);
  static const secondaryText = Color(0xFF6B5F54);

  // ==========================================================
  // CATEGORIES
  // ==========================================================

  final List<Map<String, String>> categories = const [
    {
      'name': 'Men',
      'count': '120+ Items',
      'image':
          'https://images.unsplash.com/photo-1602810318383-e386cc2a3ccf?w=300&h=300&fit=crop&auto=format',
    },
    {
      'name': 'Women',
      'count': '160+ Items',
      'image':
          'https://images.unsplash.com/photo-1580618672591-eb180b1a973f?w=300&h=300&fit=crop&auto=format',
    },
    {
      'name': 'Bags',
      'count': '220+ Items',
      'image':
          'https://images.unsplash.com/photo-1548036328-c9fa89d128fa?w=300&h=300&fit=crop&auto=format',
    },
    {
      'name': 'Shoes',
      'count': '140+ Items',
      'image':
          'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=300&h=300&fit=crop&auto=format',
    },
    {
      'name': 'Watches',
      'count': '250+ Items',
      'image':
          'https://images.unsplash.com/photo-1524592094714-0f0654e20314?w=300&h=300&fit=crop&auto=format',
    },
    {
      'name': 'Accessories',
      'count': '320+ Items',
      'image':
          'https://images.unsplash.com/photo-1611652022419-a9419f74343d?w=300&h=300&fit=crop&auto=format',
    },
  ];

  // ==========================================================
  // LOGIN NAVIGATION
  // ==========================================================

  void _goToLogin(BuildContext context) {
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => const LoginPage(),
      ),
    );
  }

  // ==========================================================
  // BUILD
  // ==========================================================

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: background,
      body: SafeArea(
        child: CustomScrollView(
          slivers: [
            SliverToBoxAdapter(
              child: _buildHeader(context),
            ),

            SliverToBoxAdapter(
              child: _buildWelcome(context),
            ),

            SliverToBoxAdapter(
              child: _buildCategories(context),
            ),

            SliverToBoxAdapter(
              child: _buildFeaturedProducts(context),
            ),

            SliverToBoxAdapter(
              child: _buildFeaturedShops(context),
            ),
          ],
        ),
      ),
    );
  }

  // ==========================================================
  // HEADER
  // ==========================================================

  Widget _buildHeader(BuildContext context) {
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
            // LOGO
            Center(
              child: Image.asset(
                'assets/images/ZAYLO_LOGO_DARK.png',
                height: 40,
              ),
            ),

            // MENU
            Align(
              alignment: Alignment.centerLeft,
              child: IconButton(
                onPressed: () {
                  _goToLogin(context);
                },
                padding: EdgeInsets.zero,
                icon: const Icon(
                  Icons.menu,
                  color: dark,
                  size: 26,
                ),
              ),
            ),

            // LOGIN
            Align(
              alignment: Alignment.centerRight,
              child: TextButton(
                onPressed: () {
                  _goToLogin(context);
                },
                style: TextButton.styleFrom(
                  foregroundColor: dark,
                  padding: const EdgeInsets.symmetric(
                    horizontal: 4,
                  ),
                  minimumSize: Size.zero,
                  tapTargetSize:
                      MaterialTapTargetSize.shrinkWrap,
                ),
                child: const Text(
                  'LOG IN',
                  style: TextStyle(
                    fontSize: 9,
                    fontWeight: FontWeight.w600,
                    letterSpacing: 1,
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  // ==========================================================
  // WELCOME / HERO
  // ==========================================================

  Widget _buildWelcome(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(
        24,
        35,
        24,
        40,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'WELCOME TO ZAYLO',
            style: TextStyle(
              fontSize: 10,
              letterSpacing: 2,
              color: secondaryText,
            ),
          ),

          const SizedBox(height: 10),

          Text(
            'Quiet luxury,\nloudly considered.',
            style: GoogleFonts.playfairDisplay(
              fontSize: 38,
              fontWeight: FontWeight.w600,
              height: 1.05,
              color: dark,
            ),
          ),

          const SizedBox(height: 10),

          const Text(
            'Discover premium clothing, accessories, '
            'and curated collections from trusted ZAYLO sellers.',
            style: TextStyle(
              fontSize: 13,
              height: 1.5,
              color: secondaryText,
            ),
          ),

          const SizedBox(height: 22),

          // SHOP MARKETPLACE
          SizedBox(
            height: 46,
            child: ElevatedButton(
              onPressed: () {
                _goToLogin(context);
              },
              style: ElevatedButton.styleFrom(
                backgroundColor: dark,
                foregroundColor: Colors.white,
                elevation: 0,
                padding: const EdgeInsets.symmetric(
                  horizontal: 25,
                ),
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

          const SizedBox(height: 12),

          // VIEW COLLECTION
          TextButton(
            onPressed: () {
              _goToLogin(context);
            },
            style: TextButton.styleFrom(
              foregroundColor: dark,
              padding: EdgeInsets.zero,
            ),
            child: const Text(
              'View Collection →',
              style: TextStyle(
                fontSize: 12,
                letterSpacing: 0.5,
              ),
            ),
          ),
        ],
      ),
    );
  }

  // ==========================================================
  // CATEGORIES
  // ==========================================================

  Widget _buildCategories(BuildContext context) {
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
              fontSize: 25,
              fontWeight: FontWeight.w600,
              color: dark,
            ),
          ),

          const SizedBox(height: 18),

          SizedBox(
            height: 145,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              itemCount: categories.length,
              separatorBuilder: (_, _) =>
                  const SizedBox(width: 22),
              itemBuilder: (context, index) {
                final category = categories[index];

                return GestureDetector(
                  onTap: () {
                    _goToLogin(context);
                  },
                  child: SizedBox(
                    width: 105,
                    child: Column(
                      children: [
                        ClipOval(
                          child: Image.network(
                            category['image']!,
                            width: 78,
                            height: 78,
                            fit: BoxFit.cover,
                            errorBuilder: (_, _, _) {
                              return Container(
                                width: 78,
                                height: 78,
                                color: Colors.white,
                                child: const Icon(
                                  Icons.image_outlined,
                                  color: secondaryText,
                                ),
                              );
                            },
                          ),
                        ),

                        const SizedBox(height: 9),

                        Text(
                          category['name']!.toUpperCase(),
                          textAlign: TextAlign.center,
                          style: const TextStyle(
                            fontSize: 10,
                            fontWeight: FontWeight.w600,
                            letterSpacing: 0.7,
                            color: dark,
                          ),
                        ),

                        const SizedBox(height: 3),

                        Text(
                          category['count']!,
                          style: const TextStyle(
                            fontSize: 9,
                            color: secondaryText,
                          ),
                        ),
                      ],
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

  // ==========================================================
  // FEATURED PRODUCTS
  // ==========================================================

  Widget _buildFeaturedProducts(BuildContext context) {
    final products = [
      {
        'name': 'Classic Linen Shirt',
        'price': '1,299',
        'image':
            'https://images.unsplash.com/photo-1602810318383-e386cc2a3ccf?w=600&h=700&fit=crop&auto=format',
      },
      {
        'name': 'Leather Shoulder Bag',
        'price': '2,499',
        'image':
            'https://images.unsplash.com/photo-1548036328-c9fa89d128fa?w=600&h=700&fit=crop&auto=format',
      },
      {
        'name': 'Minimal Sneakers',
        'price': '1,899',
        'image':
            'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=600&h=700&fit=crop&auto=format',
      },
      {
        'name': 'Classic Watch',
        'price': '3,299',
        'image':
            'https://images.unsplash.com/photo-1524592094714-0f0654e20314?w=600&h=700&fit=crop&auto=format',
      },
    ];

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
            'FEATURED PRODUCTS',
            style: GoogleFonts.playfairDisplay(
              fontSize: 25,
              fontWeight: FontWeight.w600,
              color: dark,
            ),
          ),

          const SizedBox(height: 5),

          const Text(
            'Discover pieces worth adding to your collection.',
            style: TextStyle(
              fontSize: 12,
              color: secondaryText,
            ),
          ),

          const SizedBox(height: 20),

          GridView.builder(
            shrinkWrap: true,
            physics:
                const NeverScrollableScrollPhysics(),
            itemCount: products.length,
            gridDelegate:
                const SliverGridDelegateWithFixedCrossAxisCount(
              crossAxisCount: 2,
              crossAxisSpacing: 12,
              mainAxisSpacing: 18,
              childAspectRatio: 0.68,
            ),
            itemBuilder: (context, index) {
              return _buildProductCard(
                context,
                products[index],
              );
            },
          ),
        ],
      ),
    );
  }

  // ==========================================================
  // PRODUCT CARD
  // ==========================================================

  Widget _buildProductCard(
    BuildContext context,
    Map<String, String> product,
  ) {
    return GestureDetector(
      onTap: () {
        _goToLogin(context);
      },
      child: Container(
        decoration: BoxDecoration(
          color: Colors.white,
          border: Border.all(
            color: const Color(0xFFECE4DB),
          ),
        ),
        child: Column(
          crossAxisAlignment:
              CrossAxisAlignment.start,
          children: [
            Expanded(
              child: Image.network(
                product['image']!,
                width: double.infinity,
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
              ),
            ),

            Padding(
              padding: const EdgeInsets.all(10),
              child: Column(
                crossAxisAlignment:
                    CrossAxisAlignment.start,
                children: [
                  Text(
                    product['name']!,
                    maxLines: 2,
                    overflow:
                        TextOverflow.ellipsis,
                    style: const TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w500,
                      color: dark,
                    ),
                  ),

                  const SizedBox(height: 6),

                  Text(
                    '₱${product['price']}',
                    style: const TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w600,
                      color: dark,
                    ),
                  ),

                  const SizedBox(height: 8),

                  SizedBox(
                    width: double.infinity,
                    height: 32,
                    child: OutlinedButton(
                      onPressed: () {
                        _goToLogin(context);
                      },
                      style:
                          OutlinedButton.styleFrom(
                        foregroundColor: dark,
                        side: const BorderSide(
                          color: Color(0xFFE5DFD8),
                        ),
                        shape:
                            const RoundedRectangleBorder(
                          borderRadius:
                              BorderRadius.zero,
                        ),
                        padding: EdgeInsets.zero,
                      ),
                      child: const Text(
                        'VIEW PRODUCT',
                        style: TextStyle(
                          fontSize: 8,
                          fontWeight:
                              FontWeight.w600,
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

  // ==========================================================
  // FEATURED SHOPS
  // ==========================================================

  Widget _buildFeaturedShops(BuildContext context) {
    final shops = [
      {
        'name': 'ZAYLO Essentials',
        'tagline':
            'Minimal pieces for everyday luxury.',
        'products': '48 PRODUCTS',
      },
      {
        'name': 'Maison Studio',
        'tagline':
            'Curated fashion with timeless appeal.',
        'products': '32 PRODUCTS',
      },
      {
        'name': 'The Leather Edit',
        'tagline':
            'Premium bags and accessories.',
        'products': '26 PRODUCTS',
      },
    ];

    return Container(
      color: const Color(0xFFF5F0EA),
      padding: const EdgeInsets.fromLTRB(
        20,
        35,
        20,
        35,
      ),
      child: Column(
        crossAxisAlignment:
            CrossAxisAlignment.start,
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
              itemCount: shops.length,
              separatorBuilder: (_, _) =>
                  const SizedBox(width: 14),
              itemBuilder: (context, index) {
                final shop = shops[index];

                return GestureDetector(
                  onTap: () {
                    _goToLogin(context);
                  },
                  child: Container(
                    width: 250,
                    padding:
                        const EdgeInsets.all(18),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      border: Border.all(
                        color: const Color(
                          0xFFECE4DB,
                        ),
                      ),
                    ),
                    child: Column(
                      crossAxisAlignment:
                          CrossAxisAlignment.start,
                      children: [
                        Text(
                          shop['name']!,
                          maxLines: 1,
                          overflow:
                              TextOverflow.ellipsis,
                          style:
                              GoogleFonts.playfairDisplay(
                            fontSize: 20,
                            fontWeight:
                                FontWeight.w600,
                            color: dark,
                          ),
                        ),

                        const SizedBox(height: 8),

                        Text(
                          shop['tagline']!,
                          maxLines: 2,
                          overflow:
                              TextOverflow.ellipsis,
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
                              shop['products']!,
                              style: const TextStyle(
                                fontSize: 9,
                                fontWeight:
                                    FontWeight.w600,
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
                  ),
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}