import 'package:flutter/material.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/theme/zaylo_theme.dart';
import '../../../core/widgets/feedback.dart';
import '../../../core/widgets/layout.dart';
import '../../api/buyer_api.dart';
import '../../models/catalog_models.dart';
import '../../state/app_state.dart';
import 'product_card.dart';
import 'search_field.dart';

const _sortOptions = {
  'newest': 'Newest',
  'price_low': 'Price: low to high',
  'price_high': 'Price: high to low',
};

const _catalogCategories = {
  null: 'All',
  'fashion': 'Fashion',
  'electronics': 'Electronics',
  'home_living': 'Home & Living',
  'beauty_health': 'Beauty & Health',
  'groceries': 'Groceries',
  'sports_outdoors': 'Sports',
  'toys_hobbies': 'Toys & Hobbies',
  'automotive': 'Automotive',
};

class ProductCatalogScreen extends StatefulWidget {
  const ProductCatalogScreen({super.key, this.category, this.initialSearch, this.title});

  final String? category;
  final String? initialSearch;
  final String? title;

  @override
  State<ProductCatalogScreen> createState() => _ProductCatalogScreenState();
}

class _ProductCatalogScreenState extends State<ProductCatalogScreen> {
  late final _search = TextEditingController(text: widget.initialSearch);
  final _scroll = ScrollController();
  late String? _category = widget.category;
  String _sort = 'newest';
  String? _gender;

  final List<Product> _products = [];
  int _page = 0;
  int _total = 0;
  bool _hasMore = true;
  bool _loading = false;
  Object? _error;

  /// Ignores responses from superseded searches.
  int _generation = 0;

  BuyerApi get _api => AppScope.read(context).api;

  @override
  void initState() {
    super.initState();
    _scroll.addListener(() {
      if (_scroll.position.extentAfter < 600) _loadMore();
    });
    WidgetsBinding.instance.addPostFrameCallback((_) => _reload());
  }

  @override
  void dispose() {
    _search.dispose();
    _scroll.dispose();
    super.dispose();
  }

  Future<void> _reload() async {
    _generation++;
    setState(() {
      _products.clear();
      _page = 0;
      _hasMore = true;
      _error = null;
      _loading = false;
    });
    await _loadMore();
  }

  Future<void> _loadMore() async {
    if (_loading || !_hasMore) return;
    final generation = _generation;
    setState(() => _loading = true);
    try {
      final page = await _api.products(
          category: _category, search: _search.text, sort: _sort, gender: _gender, page: _page + 1);
      if (!mounted || generation != _generation) return;
      setState(() {
        _products.addAll(page.products);
        _page = page.currentPage;
        _hasMore = page.hasMore;
        _total = page.total;
      });
    } on ApiException catch (error) {
      if (mounted && generation == _generation) setState(() => _error = error);
    } finally {
      if (mounted && generation == _generation) setState(() => _loading = false);
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
          for (final entry in _sortOptions.entries)
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
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(
          title: Text(widget.title ?? 'Shop ZAYLO',
              style: const TextStyle(fontWeight: FontWeight.bold, letterSpacing: 1.5, fontSize: 17)),
        ),
        body: Column(children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(ZayloSpace.page, 4, ZayloSpace.page, 10),
            child: SearchField(controller: _search, onSearch: _reload),
          ),
          SizedBox(
            height: 40,
            child: ListView(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: ZayloSpace.page),
              children: [
                for (final entry in _catalogCategories.entries)
                  Padding(
                    padding: const EdgeInsets.only(right: 8),
                    child: ChoiceChip(
                      label: Text(entry.value),
                      selected: _category == entry.key,
                      showCheckmark: false,
                      selectedColor: ZayloColors.ink,
                      backgroundColor: ZayloColors.white,
                      side: BorderSide(color: ZayloColors.line),
                      labelStyle: TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.w600,
                        color: _category == entry.key ? ZayloColors.white : ZayloColors.ink,
                      ),
                      onSelected: (_) {
                        if (_category == entry.key) return;
                        _category = entry.key;
                        _reload();
                      },
                    ),
                  ),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(ZayloSpace.page, 6, 8, 2),
            child: Row(children: [
              Expanded(
                child: Text(
                  _page == 0 ? '' : '$_total ${_total == 1 ? 'product' : 'products'}',
                  style: TextStyle(fontSize: 12, color: ZayloColors.muted),
                ),
              ),
              PopupMenuButton<String?>(
                tooltip: 'Filter by gender',
                initialValue: _gender,
                onSelected: (gender) {
                  if (gender == _gender) return;
                  _gender = gender;
                  _reload();
                },
                itemBuilder: (_) => const [
                  PopupMenuItem(value: null, child: Text('All genders')),
                  PopupMenuItem(value: 'men', child: Text('Men')),
                  PopupMenuItem(value: 'women', child: Text('Women')),
                  PopupMenuItem(value: 'unisex', child: Text('Unisex')),
                ],
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 10),
                  child: Row(mainAxisSize: MainAxisSize.min, children: [
                    Icon(Icons.tune, size: 17, color: ZayloColors.ink),
                    const SizedBox(width: 6),
                    Text(switch (_gender) { 'men' => 'Men', 'women' => 'Women', 'unisex' => 'Unisex', _ => 'Gender' },
                        style: TextStyle(fontWeight: FontWeight.w600, color: ZayloColors.ink, fontSize: 13)),
                  ]),
                ),
              ),
              TextButton.icon(
                onPressed: _chooseSort,
                icon: const Icon(Icons.swap_vert, size: 18),
                label: Text(_sortOptions[_sort]!),
                style: TextButton.styleFrom(foregroundColor: ZayloColors.ink),
              ),
            ]),
          ),
          Expanded(child: _results()),
        ]),
      );

  Widget _results() {
    if (_products.isEmpty) {
      if (_error != null) return LoadError(error: _error, onRetry: _reload, title: 'Could not load products');
      if (_loading || _hasMore) return const Loader();
      return EmptyState(
        icon: Icons.search_off_rounded,
        title: 'No matches',
        message: 'Try a different search or browse another category.',
        actionLabel: 'Clear filters',
        onAction: () {
          _search.clear();
          _category = null;
          _gender = null;
          _reload();
        },
      );
    }
    return RefreshIndicator(
      color: ZayloColors.ink,
      onRefresh: _reload,
      child: CustomScrollView(
        controller: _scroll,
        physics: const AlwaysScrollableScrollPhysics(),
        slivers: [
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
          SliverToBoxAdapter(
            child: Padding(
              padding: const EdgeInsets.only(bottom: 28),
              child: _error != null
                  ? Center(child: TextButton(onPressed: _loadMore, child: const Text('Could not load more. Retry')))
                  : _hasMore
                      ? const Padding(padding: EdgeInsets.all(12), child: Loader())
                      : const SizedBox.shrink(),
            ),
          ),
        ],
      ),
    );
  }
}
