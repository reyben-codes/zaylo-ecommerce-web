import 'package:flutter/material.dart';

// Package para magamit natin ang Google Fonts gaya ng Inter at Playfair Display.
// Ginagamit ang Inter para sa normal na text at Playfair Display para sa headings.
import 'package:google_fonts/google_fonts.dart';
import 'pages/login_page.dart';
import 'pages/register_page.dart';

void main() {
  // Ito ang starting point ng Flutter application.
  // Ipinapakita nito ang ZayloApp bilang pinaka-main na application.
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
      // Tinatanggal nito ang "DEBUG" banner sa upper-right corner.
      debugShowCheckedModeBanner: false,

      // Pangalan ng application.
      title: 'ZAYLO',

      // Dito natin sine-set ang overall design/theme ng application.
      theme: ThemeData(
        // Main background color ng ZAYLO website.
        scaffoldBackgroundColor: const Color(0xFFFAF7F2),

        // Main color palette ng application.
        colorScheme: ColorScheme.fromSeed(
          seedColor: const Color(0xFF1A1714),
        ),

        // Inter ang default font para sa normal text ng application.
        // Ito ang font na ginagamit sa original ZAYLO website.
        fontFamily: GoogleFonts.inter().fontFamily,
      ),

      // Ito ang unang page na makikita kapag binuksan ang application.
      home: const HomePage(),
    );
  }
}


// ============================================================
// HOME PAGE
// ============================================================

class HomePage extends StatelessWidget {
  const HomePage({super.key});

  // ==========================================================
  // ZAYLO COLOR PALETTE
  // ==========================================================

  // Main background color.
  static const background = Color(0xFFFAF7F2);

  // Dark color para sa buttons at main text.
  static const dark = Color(0xFF1A1714);

  // Brown/gold accent color ng ZAYLO.
  static const brown = Color(0xFFB28B6F);

  // Secondary color para sa descriptions at maliit na text.
  static const secondaryText = Color(0xFF6B5F54);


  // ==========================================================
  // PRODUCT CATEGORIES
  // ==========================================================

  // Listahan ng categories na ipapakita sa homepage.
  // Bawat category ay may pangalan, item count, at image.
  final List<Map<String, String>> categories = const [

    {
      'name': 'Men',
      'count': '120+ Items',
      'image':
          'https://images.unsplash.com/photo-1602810318383-e386cc2a3ccf?w=120&h=120&fit=crop&crop=face&auto=format',
    },

    {
      'name': 'Women',
      'count': '160+ Items',
      'image':
          'https://images.unsplash.com/photo-1580618672591-eb180b1a973f?w=120&h=120&fit=crop&crop=face&auto=format',
    },

    {
      'name': 'Bags',
      'count': '220+ Items',
      'image':
          'https://images.unsplash.com/photo-1548036328-c9fa89d128fa?w=120&h=120&fit=crop&crop=center&auto=format',
    },

    {
      'name': 'Shoes',
      'count': '140+ Items',
      'image':
          'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=120&h=120&fit=crop&crop=center&auto=format',
    },

    {
      'name': 'Watches',
      'count': '250+ Items',
      'image':
          'https://images.unsplash.com/photo-1524592094714-0f0654e20314?w=120&h=120&fit=crop&crop=center&auto=format',
    },

      {
        'name': 'Accessories',
        'count': '320+ Items',
        'image':
        'https://images.unsplash.com/photo-1611652022419-a9419f74343d?w=120&h=120&fit=crop&auto=format',
    },
  ];


  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: background,

      // SafeArea para hindi matakpan ng phone status bar
      // ang content ng application.
      body: SafeArea(

        // CustomScrollView para maging scrollable ang buong homepage.
        child: CustomScrollView(
          slivers: [

            // Header/Navbar section.
            SliverToBoxAdapter(
              child: _buildHeader(context),
            ),

            // Hero/banner section.
            SliverToBoxAdapter(
              child: _buildHero(context),
            ),

            // Categories section.
            SliverToBoxAdapter(
              child: _buildCategories(),
            ),

            // Buyer/Seller/Courier section.
            SliverToBoxAdapter(
              child: _buildRoles(context),
            ),
          ],
        ),
      ),
    );
  }


  // ==========================================================
  // HEADER / NAVIGATION BAR
  // ==========================================================

  Widget _buildHeader(BuildContext context) {
  return Container(
    color: Colors.white,
    padding: const EdgeInsets.symmetric(
      horizontal: 16,
      vertical: 10,
    ),

    child: Column(
      children: [

        // =====================================================
        // MAIN NAVBAR
        // =====================================================
        Row(
          children: [

            // Menu button
            IconButton(
              onPressed: () {
                _showMessage(context, 'Menu');
              },
              icon: const Icon(Icons.menu),
              color: dark,
              padding: EdgeInsets.zero,
              constraints: const BoxConstraints(
                minWidth: 34,
                minHeight: 34,
              ),
            ),

            // Space
            const Spacer(),

            // ZAYLO LOGO
            Image.asset(
              'assets/images/ZAYLO_LOGO_DARK.png',
              height: 46,
            ),

            // Space
            const Spacer(),

            // =================================================
            // RIGHT SIDE
            // Wishlist → Shopping Bag → Log In
            // =================================================
            Row(
              mainAxisSize: MainAxisSize.min,
              children: [

                // Wishlist
                IconButton(
                  onPressed: () {
                    _showMessage(context, 'Wishlist');
                  },
                  icon: const Icon(
                    Icons.favorite_border,
                    size: 20,
                  ),
                  color: dark,
                  padding: EdgeInsets.zero,
                  constraints: const BoxConstraints(
                    minWidth: 30,
                    minHeight: 34,
                  ),
                ),

                // Shopping Bag
                IconButton(
                  onPressed: () {
                    _showMessage(context, 'Shopping Bag');
                  },
                  icon: const Icon(
                    Icons.shopping_bag_outlined,
                    size: 20,
                  ),
                  color: dark,
                  padding: EdgeInsets.zero,
                  constraints: const BoxConstraints(
                    minWidth: 30,
                    minHeight: 34,
                  ),
                ),

                // LOG IN
                TextButton(
                  onPressed: () {
                    Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (context) => const LoginPage(),
                      ),
                    );
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
                      fontWeight: FontWeight.w500,
                      letterSpacing: 0.8,
                    ),
                  ),
                ),
              ],
            ),
          ],
        ),

        const SizedBox(height: 8),

        // =====================================================
        // NAVIGATION MENU
        // =====================================================
        SingleChildScrollView(
          scrollDirection: Axis.horizontal,

          child: Row(
            children: [
              _navItem(context, 'Home'),
              _navItem(context, 'Clothing'),
              _navItem(context, 'Bags'),
              _navItem(context, 'Shoes'),
              _navItem(context, 'Accessories'),
            ],
          ),
        ),

        const SizedBox(height: 12),

        // =====================================================
        // SEARCH BAR
        // =====================================================
        Container(
          height: 42,

          decoration: BoxDecoration(
            color: const Color(0xFFF4F1EC),

            borderRadius: BorderRadius.circular(30),

            border: Border.all(
              color: const Color(0xFFE5DFD8),
            ),
          ),

          child: TextField(

            onSubmitted: (value) {
              _showMessage(
                context,
                'Searching for "$value"',
              );
            },

            decoration: const InputDecoration(
              hintText: 'Search',

              prefixIcon: Icon(
                Icons.search,
                size: 20,
                color: Color(0xFF4A4037),
              ),

              border: InputBorder.none,

              contentPadding: EdgeInsets.symmetric(
                vertical: 10,
              ),
            ),
          ),
        ),
      ],
    ),
  );
}


  // ==========================================================
  // NAVIGATION ITEM
  // ==========================================================

  Widget _navItem(
    BuildContext context,
    String title,
  ) {
    return Padding(
      padding: const EdgeInsets.only(right: 24),

      child: TextButton(
        onPressed: () {

          // Temporary functionality muna.
          // Sa susunod ay papalitan natin ito ng actual navigation.
          _showMessage(context, title);
        },

        style: TextButton.styleFrom(
          foregroundColor: dark,
          padding: EdgeInsets.zero,
        ),

        child: Text(
          title.toUpperCase(),

          style: const TextStyle(
            fontSize: 11,
            fontWeight: FontWeight.w500,
            letterSpacing: 1.1,
          ),
        ),
      ),
    );
  }


  // ==========================================================
  // HERO SECTION
  // ==========================================================

  Widget _buildHero(BuildContext context) {
    return SizedBox(
      height: 430,

      // Stack para pagsamahin ang image,
      // gradient overlay, at text/buttons.
      child: Stack(
        fit: StackFit.expand,

        children: [

          // Main hero image.
          Image.network(
            'https://images.unsplash.com/photo-1583394838336-acd977736f90?w=1400&q=80&auto=format&fit=crop&crop=center',

            // Cover para mapuno ang buong hero section.
            fit: BoxFit.cover,
          ),

          // Gradient overlay para mas readable ang text.
          Container(
            decoration: BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.centerLeft,
                end: Alignment.centerRight,

                colors: [
                  // Semi-transparent white.
                  Colors.white.withValues(alpha: 0.82),

                  // Transparent sa right side.
                  Colors.transparent,
                ],
              ),
            ),
          ),

          // Hero text at buttons.
          Positioned(
            left: 28,
            top: 70,
            right: 30,

            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,

              children: [

                // Small label sa taas ng hero heading.
                const Text(
                  'BEST COLLECTION',

                  style: TextStyle(
                    fontSize: 11,
                    letterSpacing: 2.2,
                    color: Color(0xFF4A4037),
                  ),
                ),

                const SizedBox(height: 14),

                // Main hero heading.
                Text(
                  'Quiet luxury,',

                  style: GoogleFonts.playfairDisplay(
                    fontSize: 43,
                    fontWeight: FontWeight.w600,
                    height: 1.05,
                    color: dark,
                  ),
                ),

                // Second line ng heading.
                Text(
                  'loudly considered.',

                  style: GoogleFonts.playfairDisplay(
                    fontSize: 43,
                    fontStyle: FontStyle.italic,
                    fontWeight: FontWeight.w600,
                    height: 1.05,
                    color: brown,
                  ),
                ),

                const SizedBox(height: 18),

                // Description ng hero section.
                const SizedBox(
                  width: 290,

                  child: Text(
                    'Explore premium clothing and statement accessories curated for every season, every style, and every occasion.',

                    style: TextStyle(
                      fontSize: 13,
                      height: 1.6,
                      color: Color(0xFF2A241F),
                    ),
                  ),
                ),

                const SizedBox(height: 24),

                // Main call-to-action button.
                ElevatedButton(
                  onPressed: () {
                    _showMessage(
                      context,
                      'Shop the edit',
                    );
                  },

                  style: ElevatedButton.styleFrom(
                    backgroundColor: dark,
                    foregroundColor: Colors.white,
                    elevation: 0,

                    padding: const EdgeInsets.symmetric(
                      horizontal: 28,
                      vertical: 15,
                    ),

                    // Square corners gaya ng original website.
                    shape: const RoundedRectangleBorder(
                      borderRadius: BorderRadius.zero,
                    ),
                  ),

                  child: const Text(
                    'SHOP THE EDIT',

                    style: TextStyle(
                      fontSize: 10,
                      fontWeight: FontWeight.w600,
                      letterSpacing: 1.4,
                    ),
                  ),
                ),

                // Secondary button.
                TextButton(
                  onPressed: () {
                    _showMessage(
                      context,
                      'Lookbook',
                    );
                  },

                  style: TextButton.styleFrom(
                    foregroundColor: dark,

                    padding: const EdgeInsets.only(
                      top: 12,
                      left: 0,
                    ),
                  ),

                  child: const Text(
                    'View Lookbook →',

                    style: TextStyle(
                      fontSize: 12,
                      letterSpacing: 0.5,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }


  // ==========================================================
  // CATEGORY SECTION
  // ==========================================================

  Widget _buildCategories() {
    return Container(
      // Light beige background ng categories.
      color: const Color(0xFFF5F0EA),

      padding: const EdgeInsets.symmetric(
        vertical: 32,
        horizontal: 20,
      ),

      child: SizedBox(
        height: 145,

        // Horizontal scrolling categories.
        child: ListView.separated(
          scrollDirection: Axis.horizontal,

          itemCount: categories.length,

          separatorBuilder: (_, __) =>
              const SizedBox(width: 28),

          itemBuilder: (context, index) {

            // Kinukuha ang current category.
            final category = categories[index];

            return SizedBox(
              width: 95,

              child: Column(
                children: [

                  // Circular category image.
                  ClipOval(
                    child: Image.network(
                      category['image']!,

                      width: 80,
                      height: 80,

                      fit: BoxFit.cover,
                    ),
                  ),

                  const SizedBox(height: 10),

                  // Category name.
                  Text(
                    category['name']!.toUpperCase(),

                    style: const TextStyle(
                      fontSize: 11,
                      fontWeight: FontWeight.w500,
                      letterSpacing: 0.8,
                      color: dark,
                    ),
                  ),

                  const SizedBox(height: 3),

                  // Number of available items.
                  Text(
                    category['count']!,

                    style: const TextStyle(
                      fontSize: 9,
                      color: secondaryText,
                    ),
                  ),
                ],
              ),
            );
          },
        ),
      ),
    );
  }


 // ==========================================================
// BUYER / SELLER / COURIER SECTION
// ==========================================================

Widget _buildRoles(BuildContext context) {

  // Tatlong pangunahing role na maaaring piliin ng user
  // kapag gumagamit ng ZAYLO application.
  final roles = [
    {
      'icon': Icons.person_outline,
      'title': 'Buyer',
      'description':
          'Discover and shop luxury fashion from curated sellers.',
      'button': 'SHOP NOW',
    },
    {
      'icon': Icons.storefront_outlined,
      'title': 'Seller',
      'description':
          'List your products and reach thousands of buyers.',
      'button': 'START SELLING',
    },
    {
      'icon': Icons.motorcycle_outlined,
      'title': 'Courier',
      'description':
          'Deliver orders and earn competitive commissions.',
      'button': 'DELIVER NOW',
    },
  ];

  return Container(
    // Spacing sa loob ng role section.
    padding: const EdgeInsets.symmetric(
      horizontal: 20,
      vertical: 55,
    ),

    child: Column(
      children: [

        // Maliit na label bago ang main heading.
        const Text(
          'JOIN THE COMMUNITY',

          style: TextStyle(
            fontSize: 10,
            letterSpacing: 2,
            color: secondaryText,
          ),
        ),

        const SizedBox(height: 10),

        // Main heading ng role section.
        // Playfair Display ang ginagamit para tumugma
        // sa luxury design ng ZAYLO website.
        Text(
          'Find Your Role on ZAYLO',

          textAlign: TextAlign.center,

          style: GoogleFonts.playfairDisplay(
            fontSize: 28,
            fontWeight: FontWeight.w600,
            color: dark,
          ),
        ),

        const SizedBox(height: 35),

        // Grid layout para sa tatlong user roles.
        GridView.builder(
          shrinkWrap: true,

          // Ang buong page ang nagha-handle ng scrolling.
          physics: const NeverScrollableScrollPhysics(),

          itemCount: roles.length,

          gridDelegate:
            const SliverGridDelegateWithFixedCrossAxisCount(
          // Isang card lang bawat row para magkakasunod
          // pababa ang Buyer, Seller, at Courier.
          crossAxisCount: 1,

          // Vertical spacing sa pagitan ng bawat role card.
          mainAxisSpacing: 16,

          // Dahil isang column lang, mas malawak ang card.
          childAspectRatio: 2.0,
        ),

          itemBuilder: (context, index) {

            // Kinukuha ang information ng current role.
            final role = roles[index];

            return Container(
              padding: const EdgeInsets.all(18),

              // Simple white card na may subtle border
              // para tumugma sa minimalist design.
              decoration: BoxDecoration(
                color: Colors.white,

                border: Border.all(
                  color: const Color(0xFFECE4DB),
                ),
              ),

              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,

                children: [

                  // Icon para sa bawat role.
                  Icon(
                    role['icon'] as IconData,
                    size: 42,
                    color: dark,
                  ),

                  const SizedBox(height: 16),

                  // Pangalan ng role.
                  Text(
                    role['title'] as String,

                    style: const TextStyle(
                      fontSize: 17,
                      fontWeight: FontWeight.w600,
                      color: dark,
                    ),
                  ),

                  const SizedBox(height: 10),

                  // Description ng role.
                  Text(
                    role['description'] as String,

                    textAlign: TextAlign.center,

                    style: const TextStyle(
                      fontSize: 11,
                      height: 1.5,
                      color: secondaryText,
                    ),
                  ),

                  const SizedBox(height: 20),

                  // Button para sa bawat role.
                  ElevatedButton(
                    onPressed: () {

                      // Temporary message muna habang
                      // hindi pa natin ginagawa ang actual navigation.
                      _showMessage(
                        context,
                        role['title'] as String,
                      );
                    },

                    style: ElevatedButton.styleFrom(
                      backgroundColor: dark,
                      foregroundColor: Colors.white,

                      // Walang shadow para minimalist ang design.
                      elevation: 0,

                      padding: const EdgeInsets.symmetric(
                        horizontal: 15,
                        vertical: 11,
                      ),

                      // Square corners gaya ng original ZAYLO design.
                      shape: const RoundedRectangleBorder(
                        borderRadius: BorderRadius.zero,
                      ),
                    ),

                    child: Text(
                      role['button'] as String,

                      style: const TextStyle(
                        fontSize: 9,
                        fontWeight: FontWeight.w600,
                        letterSpacing: 0.8,
                      ),
                    ),
                  ),
                ],
              ),
            );
          },
        ),
      ],
    ),
  );
}


  // ==========================================================
  // TEMPORARY MESSAGE
  // ==========================================================

  // Temporary function muna para makita natin
  // kung gumagana ang buttons.
  //
  // Sa next development stage:
  // - Home → Home page
  // - Clothing → Clothing page
  // - Bags → Bags page
  // - Wishlist → Wishlist page
  // - Shopping Bag → Cart page
  // - Buyer → Buyer dashboard/login
  // - Seller → Seller dashboard/login
  // - Courier → Courier dashboard/login
  //
  // Ipapalit natin ang SnackBar na ito sa actual navigation.

  static void _showMessage(
    BuildContext context,
    String message,
  ) {
    // Tinatanggal muna ang previous SnackBar
    // para hindi magsabay-sabay ang messages.
    ScaffoldMessenger.of(context).hideCurrentSnackBar();

    // Nagpapakita ng temporary message sa bottom ng screen.
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),

        // 1 second lang makikita ang message.
        duration: const Duration(seconds: 1),
      ),
    );
  }
}