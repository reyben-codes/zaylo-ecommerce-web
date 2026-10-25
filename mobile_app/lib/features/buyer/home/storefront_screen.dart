import 'dart:async';

import 'package:flutter/material.dart';

import 'package:zaylo_mobile/auth/buyer/login_screen.dart';
import 'package:zaylo_mobile/auth/buyer/registration_screen.dart';
import 'package:zaylo_mobile/auth/rider/rider_login_screen.dart';
import 'package:zaylo_mobile/components/announcement_banner.dart';
import 'package:zaylo_mobile/components/feedback.dart';
import 'package:zaylo_mobile/features/buyer/catalog/product_card.dart';
import 'package:zaylo_mobile/features/buyer/catalog/product_catalog_screen.dart';
import 'package:zaylo_mobile/features/buyer/catalog/search_field.dart';
import 'package:zaylo_mobile/features/buyer/home/widgets/home_header.dart';
import 'package:zaylo_mobile/features/buyer/home/widgets/home_menu_drawer.dart';
import 'package:zaylo_mobile/features/buyer/home/widgets/home_sections.dart';
import 'package:zaylo_mobile/features/buyer/orders/orders_screen.dart';
import 'package:zaylo_mobile/models/buyer/catalog_models.dart';
import 'package:zaylo_mobile/navigation/floating_nav_bar.dart';
import 'package:zaylo_mobile/state/app_state.dart';
import 'package:zaylo_mobile/theme/zaylo_theme.dart';

/// The Home tab. Guests see the web landing page; signed-in buyers also get
/// the web dashboard's Flash Sales and Suggested Products.
class StorefrontScreen extends StatefulWidget {
  const StorefrontScreen({super.key, this.scrollController});

  /// Lets the app shell scroll Home back to the top when Home is tapped again.
  final ScrollController? scrollController;

  @override
  State<StorefrontScreen> createState() => _StorefrontScreenState();
}

class _StorefrontScreenState extends State<StorefrontScreen> {
  Future<StorefrontHome>? _home;
  final _topBarVisibility = NavBarVisibility();
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
  void dispose() {
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

  void _openCatalog({String? category, String? search, String? title, bool focusSearch = false}) {
    Navigator.of(context).push(MaterialPageRoute(
      builder: (_) => ProductCatalogScreen(
          category: category, initialSearch: search, title: title, focusSearch: focusSearch),
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
                          controller: widget.scrollController,
                          physics: const AlwaysScrollableScrollPhysics(),
                          slivers: [
                            const SliverToBoxAdapter(child: SizedBox(height: HomeTopBar.height)),
                            SliverToBoxAdapter(
                              child: Padding(
                                padding: const EdgeInsets.fromLTRB(ZayloSpace.page, 0, ZayloSpace.page, 18),
                                child: SearchField(onTap: () => _openCatalog(focusSearch: true)),
                              ),
                            ),
                            if (data.announcements.isNotEmpty)
                              SliverToBoxAdapter(
                                child: AnnouncementBanner(
                                  announcements: data.announcements,
                                  padding: const EdgeInsets.symmetric(horizontal: ZayloSpace.page),
                                ),
                              ),
                            SliverToBoxAdapter(
                              child: HomeHeroCarousel(
                                images: data.heroImages,
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
                        child: const HomeTopBar(),
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
