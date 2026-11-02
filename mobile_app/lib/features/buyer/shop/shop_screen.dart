import 'dart:async';

import 'package:flutter/material.dart';

import 'package:zaylo_mobile/api/api_exception.dart';
import 'package:zaylo_mobile/components/buttons.dart';
import 'package:zaylo_mobile/components/feedback.dart';
import 'package:zaylo_mobile/components/layout.dart';
import 'package:zaylo_mobile/components/star_rating.dart';
import 'package:zaylo_mobile/features/buyer/catalog/product_card.dart';
import 'package:zaylo_mobile/features/buyer/catalog/search_field.dart';
import 'package:zaylo_mobile/features/buyer/messages/seller_chat_screen.dart';
import 'package:zaylo_mobile/features/buyer/shop/shop_widgets.dart';
import 'package:zaylo_mobile/features/buyer/widgets/shop_actions.dart';
import 'package:zaylo_mobile/models/buyer/catalog_models.dart';
import 'package:zaylo_mobile/models/buyer/shop_models.dart';
import 'package:zaylo_mobile/state/app_state.dart';
import 'package:zaylo_mobile/theme/zaylo_theme.dart';

enum _ShopTab { products, reviews, categories }

const _productSorts = {
  'newest': 'Newest',
  'popular': 'Best selling',
  'price_low': 'Price: low to high',
  'price_high': 'Price: high to low',
};

const _rankings = {
  'highest_rated': 'Highest rating',
  'lowest_rated': 'Lowest rating',
  'best_selling': 'Best selling',
  'least_selling': 'Least selling',
};

/// A shop's page, matching the website's: search at the top, the cover photo with the shop's photo at its left,
/// chat and follow, then tabs for its products, its products ranked by rating or sales, and its categories.
class ShopScreen extends StatefulWidget {
  const ShopScreen({super.key, required this.shopId, this.name});

  final int shopId;

  /// Shown until the shop loads.
  final String? name;

  @override
  State<ShopScreen> createState() => _ShopScreenState();
}

class _ShopScreenState extends State<ShopScreen> {
  final _search = TextEditingController();
  final _scroll = ScrollController();

  Shop? _shop;
  Object? _shopError;
  bool _followBusy = false;
  bool _openingChat = false;

  _ShopTab _tab = _ShopTab.products;
  String _sort = 'newest';
  String _ranking = 'highest_rated';
  ShopCategory? _category;

  final List<Product> _products = [];
  int _page = 0;
  bool _hasMore = true;
  bool _loading = false;
  Object? _productsError;

  /// Ignores responses from superseded searches.
  int _generation = 0;
  Timer? _typingPause;
  String _lastSearch = '';

  @override
  void initState() {
    super.initState();
    _scroll.addListener(() {
      if (_scroll.position.extentAfter < 600) _loadMore();
    });
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _loadShop();
      _reload();
    });
  }

  @override
  void dispose() {
    _typingPause?.cancel();
    _search.dispose();
    _scroll.dispose();
    super.dispose();
  }

  Future<void> _loadShop() async {
    try {
      final shop = await AppScope.read(context).api.shop(widget.shopId);
      if (mounted) {
        setState(() {
          _shop = shop;
          _shopError = null;
        });
      }
    } on ApiException catch (error) {
      if (mounted) setState(() => _shopError = error);
    }
  }

  Future<void> _reload() async {
    _typingPause?.cancel();
    _generation++;
    setState(() {
      _products.clear();
      _page = 0;
      _hasMore = true;
      _productsError = null;
      _loading = false;
    });
    await _loadMore();
  }

  Future<void> _loadMore() async {
    if (_tab == _ShopTab.categories || _loading || !_hasMore) return;
    final generation = _generation;
    final ranked = _tab == _ShopTab.reviews;
    setState(() => _loading = true);
    try {
      final page = await AppScope.read(context).api.shopProducts(
            widget.shopId,
            search: ranked ? null : _lastSearch,
            category: ranked ? null : _category?.id,
            sort: ranked ? _ranking : _sort,
            page: _page + 1,
          );
      if (!mounted || generation != _generation) return;
      setState(() {
        _products.addAll(page.products);
        _page = page.currentPage;
        _hasMore = page.hasMore;
      });
    } on ApiException catch (error) {
      if (mounted && generation == _generation) setState(() => _productsError = error);
    } finally {
      if (mounted && generation == _generation) setState(() => _loading = false);
    }
  }

  void _showTab(_ShopTab tab) {
    if (tab == _tab) return;
    _tab = tab;
    _reload();
  }

  /// Searching always shows the results on the Products tab.
  void _onSearchChanged(String text) {
    _typingPause?.cancel();
    _typingPause = Timer(const Duration(milliseconds: 300), _runSearch);
  }

  void _runSearch() {
    final search = _search.text.trim().replaceAll(RegExp(r'\s+'), ' ');
    if (!mounted || (search == _lastSearch && _tab == _ShopTab.products)) return;
    _lastSearch = search;
    _tab = _ShopTab.products;
    _reload();
  }

  void _chooseCategory(ShopCategory? category) {
    _category = category;
    _tab = _ShopTab.products;
    _reload();
  }

  Future<void> _toggleFollow(Shop shop) async {
    if (_followBusy || !await ShopActions.ensureSignedIn(context) || !mounted) return;
    setState(() => _followBusy = true);
    try {
      final result = await AppScope.read(context).api.toggleShopFollow(shop.id);
      if (!mounted) return;
      setState(() => _shop = shop.copyWith(following: result.following, followers: result.followers));
      showMessage(context, result.following ? "You're now following ${shop.name}." : 'You unfollowed ${shop.name}.');
    } on ApiException catch (error) {
      if (mounted) showMessage(context, error.message);
    } finally {
      if (mounted) setState(() => _followBusy = false);
    }
  }

  Future<void> _chat(Shop shop) async {
    if (_openingChat || !await ShopActions.ensureSignedIn(context) || !mounted) return;
    setState(() => _openingChat = true);
    try {
      final conversation = await AppScope.read(context).api.startShopChat(shop.id);
      if (!mounted) return;
      Navigator.of(context).push(MaterialPageRoute(builder: (_) => SellerChatScreen(conversation: conversation)));
    } on ApiException catch (error) {
      if (mounted) showMessage(context, error.message);
    } finally {
      if (mounted) setState(() => _openingChat = false);
    }
  }

  Future<void> _chooseSort() async {
    final choice = await showModalBottomSheet<String>(
      context: context,
      backgroundColor: ZayloColors.paper,
      builder: (context) => SafeArea(
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          const Padding(
            padding: EdgeInsets.fromLTRB(20, 18, 20, 6),
            child: Align(alignment: Alignment.centerLeft, child: Eyebrow('Sort by')),
          ),
          for (final entry in _productSorts.entries)
            ListTile(
              title: Text(entry.value),
              trailing: entry.key == _sort ? Icon(Icons.check, color: ZayloColors.ink) : null,
              onTap: () => Navigator.pop(context, entry.key),
            ),
        ]),
      ),
    );
    if (choice != null && choice != _sort) {
      _sort = choice;
      _reload();
    }
  }

  @override
  Widget build(BuildContext context) {
    final shop = _shop;
    return Scaffold(
      appBar: AppBar(
        titleSpacing: 0,
        title: Padding(
          padding: const EdgeInsets.only(right: ZayloSpace.page),
          child: SearchField(
            controller: _search,
            hint: 'Search in ${shop?.name ?? widget.name ?? 'this shop'}',
            onChanged: _onSearchChanged,
            onSearch: _runSearch,
          ),
        ),
      ),
      body: shop == null
          ? (_shopError != null
              ? LoadError(error: _shopError, onRetry: _loadShop, title: 'Shop unavailable')
              : const Loader())
          : RefreshIndicator(
              color: ZayloColors.ink,
              onRefresh: () async {
                await _loadShop();
                await _reload();
              },
              child: CustomScrollView(
                controller: _scroll,
                physics: const AlwaysScrollableScrollPhysics(),
                slivers: [
                  SliverToBoxAdapter(
                    child: _ShopHeader(
                      shop: shop,
                      followBusy: _followBusy,
                      chatBusy: _openingChat,
                      onFollow: () => _toggleFollow(shop),
                      onChat: () => _chat(shop),
                    ),
                  ),
                  SliverPersistentHeader(pinned: true, delegate: _TabsDelegate(selected: _tab, onSelected: _showTab)),
                  ..._tabContent(shop),
                ],
              ),
            ),
    );
  }

  List<Widget> _tabContent(Shop shop) => switch (_tab) {
        _ShopTab.products => [
            SliverToBoxAdapter(child: _productsBar()),
            if (_products.isNotEmpty)
              SliverPadding(
                padding: const EdgeInsets.fromLTRB(ZayloSpace.page, 6, ZayloSpace.page, 12),
                sliver: SliverGrid(
                  gridDelegate: productGridDelegate,
                  delegate: SliverChildBuilderDelegate(
                    (context, index) => ProductCard(product: _products[index]),
                    childCount: _products.length,
                  ),
                ),
              ),
            _listEnd(emptyTitle: 'No products match', emptyMessage: 'Try another word or category.'),
          ],
        _ShopTab.reviews => [
            SliverToBoxAdapter(child: _rankingChips()),
            SliverList(
              delegate: SliverChildBuilderDelegate(
                (context, index) => _RankedProductRow(product: _products[index]),
                childCount: _products.length,
              ),
            ),
            _listEnd(emptyTitle: 'No products yet', emptyMessage: '${shop.name} has not listed anything yet.'),
          ],
        _ShopTab.categories => [
            if (shop.categories.isEmpty)
              SliverToBoxAdapter(
                child: Padding(
                  padding: const EdgeInsets.all(32),
                  child: Text('${shop.name} has not listed anything yet.',
                      textAlign: TextAlign.center, style: TextStyle(color: ZayloColors.muted)),
                ),
              )
            else
              SliverList(
                delegate: SliverChildBuilderDelegate(
                  (context, index) {
                    final category = shop.categories[index];
                    return ListTile(
                      contentPadding: const EdgeInsets.symmetric(horizontal: ZayloSpace.page),
                      title: Text(category.label, style: const TextStyle(fontWeight: FontWeight.w600)),
                      subtitle: Text('${category.count} ${category.count == 1 ? 'product' : 'products'}',
                          style: TextStyle(fontSize: 12, color: ZayloColors.muted)),
                      trailing: Icon(Icons.chevron_right_rounded, color: ZayloColors.muted),
                      onTap: () => _chooseCategory(category),
                    );
                  },
                  childCount: shop.categories.length,
                ),
              ),
          ],
      };

  Widget _productsBar() {
    final category = _category;
    return Padding(
      padding: const EdgeInsets.fromLTRB(ZayloSpace.page, 6, 8, 2),
      child: Row(children: [
        if (category != null)
          Flexible(
            child: InputChip(
              label: Text(category.label, overflow: TextOverflow.ellipsis),
              onDeleted: () => _chooseCategory(null),
              deleteButtonTooltipMessage: 'Show all categories',
            ),
          ),
        const Spacer(),
        TextButton.icon(
          onPressed: _chooseSort,
          icon: const Icon(Icons.swap_vert, size: 18),
          label: Text(_productSorts[_sort]!),
        ),
      ]),
    );
  }

  Widget _rankingChips() => Padding(
        padding: const EdgeInsets.symmetric(horizontal: ZayloSpace.page, vertical: 8),
        child: Wrap(spacing: 8, runSpacing: 4, children: [
          for (final entry in _rankings.entries)
            ChoiceChip(
              label: Text(entry.value),
              selected: _ranking == entry.key,
              showCheckmark: false,
              selectedColor: ZayloColors.ink,
              backgroundColor: ZayloColors.white,
              side: BorderSide(color: ZayloColors.line),
              labelStyle: TextStyle(
                fontSize: 12,
                fontWeight: FontWeight.w600,
                color: _ranking == entry.key ? ZayloColors.white : ZayloColors.ink,
              ),
              onSelected: (_) {
                if (_ranking == entry.key) return;
                _ranking = entry.key;
                _reload();
              },
            ),
        ]),
      );

  /// What follows the list: more loading, a failure to retry, or the empty message.
  Widget _listEnd({required String emptyTitle, required String emptyMessage}) {
    final Widget child;
    if (_productsError != null) {
      child = Center(child: TextButton(onPressed: _loadMore, child: const Text('Could not load products. Retry')));
    } else if (_hasMore) {
      child = const Padding(padding: EdgeInsets.all(12), child: Loader());
    } else if (_products.isEmpty) {
      child = Padding(
        padding: const EdgeInsets.all(32),
        child: Column(children: [
          Text(emptyTitle, style: TextStyle(fontWeight: FontWeight.w700, color: ZayloColors.ink)),
          const SizedBox(height: 4),
          Text(emptyMessage, textAlign: TextAlign.center, style: TextStyle(color: ZayloColors.muted)),
        ]),
      );
    } else {
      child = const SizedBox.shrink();
    }
    return SliverToBoxAdapter(
      child: Padding(padding: EdgeInsets.only(bottom: 28 + MediaQuery.paddingOf(context).bottom), child: child),
    );
  }
}

/// The cover photo with the shop's photo over its left edge, the name and numbers, then chat and follow.
class _ShopHeader extends StatelessWidget {
  const _ShopHeader({
    required this.shop,
    required this.followBusy,
    required this.chatBusy,
    required this.onFollow,
    required this.onChat,
  });

  static const _avatarSize = 76.0;

  final Shop shop;
  final bool followBusy;
  final bool chatBusy;
  final VoidCallback onFollow;
  final VoidCallback onChat;

  @override
  Widget build(BuildContext context) {
    final rating = shop.rating;
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Stack(clipBehavior: Clip.none, children: [
        AspectRatio(
          aspectRatio: 8 / 3,
          child: shop.coverUrl != null
              ? NetworkImageBox(url: shop.coverUrl)
              : DecoratedBox(
                  decoration: BoxDecoration(
                    gradient: LinearGradient(colors: [ZayloColors.taupe, ZayloColors.ink]),
                  ),
                ),
        ),
        Positioned(
          left: ZayloSpace.page,
          bottom: -_avatarSize / 2,
          child: Container(
            padding: const EdgeInsets.all(3),
            decoration: BoxDecoration(color: ZayloColors.paper, shape: BoxShape.circle),
            child: ShopAvatar(shop: shop, size: _avatarSize),
          ),
        ),
      ]),
      Padding(
        padding: const EdgeInsets.fromLTRB(ZayloSpace.page + _avatarSize + 14, 8, ZayloSpace.page, 0),
        child: Text(shop.name, maxLines: 2, overflow: TextOverflow.ellipsis, style: Theme.of(context).textTheme.titleLarge),
      ),
      Padding(
        padding: const EdgeInsets.fromLTRB(ZayloSpace.page, 14, ZayloSpace.page, 0),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          if (shop.location != null)
            Padding(
              padding: const EdgeInsets.only(top: 6, bottom: 6),
              child: Row(children: [
                Icon(Icons.place_outlined, size: 15, color: ZayloColors.muted),
                const SizedBox(width: 4),
                Expanded(child: Text(shop.location!, style: TextStyle(fontSize: 12, color: ZayloColors.muted))),
              ]),
            ),
          Text(
            [
              '${shop.followers} ${shop.followers == 1 ? 'follower' : 'followers'}',
              '${shop.productCount} ${shop.productCount == 1 ? 'product' : 'products'}',
              if (rating != null) '★ ${rating.toStringAsFixed(1)} (${shop.reviewCount})',
              '${shop.sold} sold',
            ].join(' · '),
            style: TextStyle(fontSize: 13, color: ZayloColors.muted),
          ),
          if (shop.description?.trim().isNotEmpty == true) ...[
            const SizedBox(height: 10),
            Text(shop.description!, style: Theme.of(context).textTheme.bodyMedium),
          ],
          const SizedBox(height: 14),
          Row(children: [
            Expanded(
              child: OutlinedButton.icon(
                onPressed: chatBusy ? null : onChat,
                style: secondaryButtonStyle,
                icon: const Icon(Icons.chat_bubble_outline, size: 17),
                label: const Text('Chat with seller', maxLines: 1, overflow: TextOverflow.ellipsis),
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: shop.following
                  ? OutlinedButton.icon(
                      onPressed: followBusy ? null : onFollow,
                      style: secondaryButtonStyle,
                      icon: const Icon(Icons.check, size: 17),
                      label: const Text('Following'),
                    )
                  : FilledButton.icon(
                      onPressed: followBusy ? null : onFollow,
                      style: primaryButtonStyle,
                      icon: const Icon(Icons.add, size: 17),
                      label: const Text('Follow'),
                    ),
            ),
          ]),
          const SizedBox(height: 8),
        ]),
      ),
    ]);
  }
}

class _TabsDelegate extends SliverPersistentHeaderDelegate {
  const _TabsDelegate({required this.selected, required this.onSelected});

  static const _height = 48.0;

  final _ShopTab selected;
  final ValueChanged<_ShopTab> onSelected;

  @override
  double get minExtent => _height;

  @override
  double get maxExtent => _height;

  @override
  bool shouldRebuild(_TabsDelegate oldDelegate) => oldDelegate.selected != selected;

  @override
  Widget build(BuildContext context, double shrinkOffset, bool overlapsContent) => Container(
        decoration: BoxDecoration(
          color: ZayloColors.paper,
          border: Border(bottom: BorderSide(color: ZayloColors.line)),
        ),
        child: Row(children: [
          for (final (tab, label) in const [
            (_ShopTab.products, 'Products'),
            (_ShopTab.reviews, 'Reviews'),
            (_ShopTab.categories, 'Categories'),
          ])
            Expanded(
              child: Semantics(
                selected: tab == selected,
                button: true,
                child: InkWell(
                  onTap: () => onSelected(tab),
                  child: Container(
                    alignment: Alignment.center,
                    decoration: BoxDecoration(
                      border: Border(
                        bottom: BorderSide(color: tab == selected ? ZayloColors.ink : Colors.transparent, width: 2),
                      ),
                    ),
                    child: Text(
                      label,
                      style: TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w700,
                        color: tab == selected ? ZayloColors.ink : ZayloColors.muted,
                      ),
                    ),
                  ),
                ),
              ),
            ),
        ]),
      );
}

/// A product on the Reviews tab: its stars, reviews and sales.
class _RankedProductRow extends StatelessWidget {
  const _RankedProductRow({required this.product});

  final Product product;

  @override
  Widget build(BuildContext context) {
    final rating = product.rating;
    return InkWell(
      onTap: () => ShopActions.openProduct(context, product),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: ZayloSpace.page, vertical: 8),
        child: Row(children: [
          ClipRRect(
            borderRadius: BorderRadius.circular(8),
            child: SizedBox(width: 60, height: 60, child: NetworkImageBox(url: product.imageUrl)),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(product.name,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(fontWeight: FontWeight.w600, color: ZayloColors.ink)),
              const SizedBox(height: 4),
              Row(children: [
                if (rating != null) ...[
                  StarRow(rating: rating, size: 14),
                  const SizedBox(width: 4),
                  Text('${rating.toStringAsFixed(1)} (${product.reviewCount})',
                      style: TextStyle(fontSize: 12, color: ZayloColors.muted)),
                ] else
                  Text('No reviews yet', style: TextStyle(fontSize: 12, color: ZayloColors.muted)),
              ]),
              const SizedBox(height: 2),
              Text('${product.unitsSold ?? 0} sold', style: TextStyle(fontSize: 12, color: ZayloColors.muted)),
            ]),
          ),
        ]),
      ),
    );
  }
}
