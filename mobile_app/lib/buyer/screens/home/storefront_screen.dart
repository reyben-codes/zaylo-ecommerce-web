import 'dart:async';

import 'package:flutter/material.dart';

import '../../../core/theme/zaylo_theme.dart';
import '../../../core/widgets/feedback.dart';
import '../../../core/widgets/floating_nav_bar.dart';
import '../../../rider/screens/rider_login_screen.dart';
import '../../models/catalog_models.dart';
import '../../state/app_state.dart';
import '../../widgets/shop_actions.dart';
import '../auth/login_screen.dart';
import '../auth/registration_screen.dart';
import '../catalog/product_card.dart';
import '../catalog/product_catalog_screen.dart';
import '../catalog/search_field.dart';
import '../orders/orders_screen.dart';
import 'widgets/home_header.dart';
import 'widgets/home_menu_drawer.dart';
import 'widgets/home_sections.dart';

/// The Home tab. Guests see the web landing page; signed-in buyers also get
/// the web dashboard's Flash Sales and Suggested Products.
class StorefrontScreen extends StatefulWidget {
  const StorefrontScreen({super.key});

  @override
  State<StorefrontScreen> createState() => _StorefrontScreenState();
}

class _StorefrontScreenState extends State<StorefrontScreen> {
  Future<StorefrontHome>? _home;
  final _pageController = PageController();
  final _searchController = TextEditingController();
  final _topBarVisibility = NavBarVisibility();
  Timer? _heroTimer;
  int _heroIndex = 0;
  int _heroCount = 0;
  int? _loadedForUser = -1;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final state = AppScope.of(context);
    // Reload on sign-in/out: the signed-in home has extra sections.
    if (!state.restoring && state.user?.id != _loadedForUser) {
      _loadedForUser = state.user?.id;
      _home = state.api.home();
    }
  }

  @override
  void initState() {
    super.initState();
    _heroTimer = Timer.periodic(const Duration(seconds: 4), (_) {
      if (!_pageController.hasClients || _heroCount < 2) return;
      _pageController.animateToPage((_heroIndex + 1) % _heroCount,
          duration: const Duration(milliseconds: 550), curve: Curves.easeOutCubic);
    });
  }

  @override
  void dispose() {
    _heroTimer?.cancel();
    _pageController.dispose();
    _searchController.dispose();
    _topBarVisibility.dispose();
    super.dispose();
  }

  Future<void> _reload() async {
    final home = AppScope.read(context).api.home();
    setState(() {
      _home = home;
    });
    await home;
  }

  void _openCatalog({String? category, String? search, String? title}) {
    Navigator.of(context).push(MaterialPageRoute(
      builder: (_) => ProductCatalogScreen(category: category, initialSearch: search, title: title),
    ));
  }

  void _push(Widget screen) => Navigator.of(context).push(MaterialPageRoute(builder: (_) => screen));

  @override
  Widget build(BuildContext context) {
    final state = AppScope.of(context);
    return Scaffold(
      drawer: HomeMenuDrawer(
        signedIn: state.signedIn,
        onCatalog: (category, title) {
          Navigator.pop(context);
          _openCatalog(category: category, title: title);
        },
        onOrders: () {
          Navigator.pop(context);
          state.signedIn ? _push(const OrdersScreen()) : _push(const LoginScreen());
        },
        onSignIn: () {
          Navigator.pop(context);
          _push(const LoginScreen());
        },
        onRegister: () {
          Navigator.pop(context);
          _push(const RegistrationScreen());
        },
        onRiderLogin: () {
          Navigator.pop(context);
          _push(const RiderLoginScreen());
        },
      ),
      // No bottom inset: the page scrolls behind the floating nav bar.
      body: SafeArea(
        bottom: false,
        child: _home == null
            ? const Loader()
            : FutureBuilder<StorefrontHome>(
                future: _home,
                builder: (context, snapshot) {
                  if (snapshot.connectionState != ConnectionState.done) return const Loader();
                  if (snapshot.hasError) {
                    return LoadError(error: snapshot.error, onRetry: _reload, title: 'Could not open the marketplace');
                  }
                  final data = snapshot.requireData;
                  _heroCount = data.heroImages.length;
                  // The top bar hides and returns with the bottom nav bar:
                  // both follow the same scroll notifications, which still
                  // bubble up to the app shell.
                  return Stack(children: [
                    NotificationListener<ScrollNotification>(
                      onNotification: _topBarVisibility.handleScroll,
                      child: RefreshIndicator(
                        color: ZayloColors.ink,
                        edgeOffset: HomeTopBar.height,
                        onRefresh: _reload,
                        child: CustomScrollView(
                          physics: const AlwaysScrollableScrollPhysics(),
                          slivers: [
                            const SliverToBoxAdapter(child: SizedBox(height: HomeTopBar.height)),
                            SliverToBoxAdapter(
                              child: Padding(
                                padding: const EdgeInsets.fromLTRB(ZayloSpace.page, 0, ZayloSpace.page, 18),
                                child: SearchField(
                                  controller: _searchController,
                                  onSearch: () => _openCatalog(search: _searchController.text),
                                ),
                              ),
                            ),
                            if (state.user != null)
                              SliverToBoxAdapter(
                                child: Padding(
                                  padding: const EdgeInsets.fromLTRB(ZayloSpace.page, 0, ZayloSpace.page, 14),
                                  child: Text('Welcome back, ${state.user!.firstName}',
                                      style: Theme.of(context).textTheme.headlineSmall?.copyWith(fontSize: 22)),
                                ),
                              ),
                            SliverToBoxAdapter(
                              child: HomeHeroCarousel(
                                images: data.heroImages,
                                controller: _pageController,
                                activeIndex: _heroIndex,
                                onPageChanged: (index) => setState(() => _heroIndex = index),
                                onShop: () => _openCatalog(),
                                onNew: () => _openCatalog(title: 'New arrivals'),
                              ),
                            ),
                            SliverToBoxAdapter(
                              child: HomeCategoryStrip(
                                categories: data.categories,
                                onSelected: (category) => _openCatalog(category: category.id, title: category.label),
                              ),
                            ),
                            if (data.flashSales.isNotEmpty) ...[
                              const SliverToBoxAdapter(child: HomeFlashHeader()),
                              SliverToBoxAdapter(child: HomeProductRail(products: data.flashSales)),
                            ],
                            if (data.suggested.isNotEmpty) ...[
                              SliverToBoxAdapter(
                                child: HomeSectionHeader(
                                  title: 'Suggested Products',
                                  eyebrow: 'RECOMMENDED FOR YOU',
                                  action: 'View all',
                                  onPressed: () => _openCatalog(),
                                ),
                              ),
                              _grid(data.suggested),
                            ],
                            SliverToBoxAdapter(
                              child: HomeSectionHeader(
                                title: 'New Arrivals',
                                eyebrow: 'FRESHLY CURATED',
                                action: 'Shop all',
                                onPressed: () => _openCatalog(title: 'New arrivals'),
                              ),
                            ),
                            _grid(data.newArrivals),
                            const SliverToBoxAdapter(child: HomeTrustStrip()),
                            const SliverToBoxAdapter(child: HomeFooter()),
                          ],
                        ),
                      ),
                    ),
                    Positioned(
                      top: 0,
                      left: 0,
                      right: 0,
                      child: AutoHideTopBar(
                        visibility: _topBarVisibility,
                        child: HomeTopBar(cartCount: state.cartCount, onBag: () => ShopActions.showTab(context, 2)),
                      ),
                    ),
                  ]);
                },
              ),
      ),
    );
  }

  Widget _grid(List<Product> products) => SliverPadding(
        padding: const EdgeInsets.fromLTRB(ZayloSpace.page, 0, ZayloSpace.page, ZayloSpace.section),
        sliver: SliverGrid(
          gridDelegate: productGridDelegate,
          delegate: SliverChildBuilderDelegate(
            (context, index) => ProductCard(product: products[index]),
            childCount: products.length,
          ),
        ),
      );
}
