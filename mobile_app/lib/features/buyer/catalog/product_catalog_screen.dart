import 'package:flutter/material.dart';

import 'package:zaylo_mobile/api/api_exception.dart';
import 'package:zaylo_mobile/api/buyer_api.dart';
import 'package:zaylo_mobile/components/buttons.dart';
import 'package:zaylo_mobile/components/feedback.dart';
import 'package:zaylo_mobile/components/form_fields.dart';
import 'package:zaylo_mobile/components/layout.dart';
import 'package:zaylo_mobile/features/buyer/catalog/product_card.dart';
import 'package:zaylo_mobile/features/buyer/catalog/search_field.dart';
import 'package:zaylo_mobile/models/buyer/catalog_models.dart';
import 'package:zaylo_mobile/state/app_state.dart';
import 'package:zaylo_mobile/theme/zaylo_theme.dart';

const _sortOptions = {
  'relevance': 'Best match',
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

  /// Null lets the server choose, as on the website: best match while searching, otherwise newest.
  String? _sort;
  CatalogFilters _filters = const CatalogFilters();

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
        category: _category,
        search: _search.text,
        sort: _sort,
        gender: _filters.gender,
        minPrice: _filters.minPrice,
        maxPrice: _filters.maxPrice,
        onSale: _filters.onSale,
        page: _page + 1,
      );
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

  bool get _searching => _search.text.trim().isNotEmpty;

  /// Best match without a search is the newest first, on the server too.
  String get _effectiveSort =>
      (_sort == 'relevance' && !_searching ? null : _sort) ?? (_searching ? 'relevance' : 'newest');

  Future<void> _chooseFilters() async {
    final chosen = await showModalBottomSheet<CatalogFilters>(
      context: context,
      isScrollControlled: true,
      backgroundColor: ZayloColors.paper,
      builder: (_) => _FilterSheet(initial: _filters),
    );
    if (chosen != null && chosen != _filters) {
      _filters = chosen;
      _reload();
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
            // Best match only means something while searching.
            if (entry.key != 'relevance' || _searching)
              ListTile(
                title: Text(entry.value),
                trailing: entry.key == _effectiveSort ? Icon(Icons.check, color: ZayloColors.ink) : null,
                onTap: () => Navigator.pop(context, entry.key),
              ),
        ]),
      ),
    );
    if (choice != null && choice != _effectiveSort) {
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
              TextButton.icon(
                onPressed: _chooseFilters,
                icon: Badge(
                  isLabelVisible: _filters.activeCount > 0,
                  label: Text('${_filters.activeCount}'),
                  child: const Icon(Icons.tune, size: 18),
                ),
                label: const Text('Filters'),
              ),
              TextButton.icon(
                onPressed: _chooseSort,
                icon: const Icon(Icons.swap_vert, size: 18),
                label: Text(_sortOptions[_effectiveSort]!),
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
          _filters = const CatalogFilters();
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

/// The website's catalog filters beyond category and search.
@immutable
class CatalogFilters {
  const CatalogFilters({this.gender, this.minPrice, this.maxPrice, this.onSale = false});

  final String? gender;
  final double? minPrice;
  final double? maxPrice;
  final bool onSale;

  int get activeCount =>
      [gender != null, minPrice != null || maxPrice != null, onSale].where((active) => active).length;

  @override
  bool operator ==(Object other) =>
      other is CatalogFilters &&
      other.gender == gender &&
      other.minPrice == minPrice &&
      other.maxPrice == maxPrice &&
      other.onSale == onSale;

  @override
  int get hashCode => Object.hash(gender, minPrice, maxPrice, onSale);
}

class _FilterSheet extends StatefulWidget {
  const _FilterSheet({required this.initial});

  final CatalogFilters initial;

  @override
  State<_FilterSheet> createState() => _FilterSheetState();
}

class _FilterSheetState extends State<_FilterSheet> {
  static const _genders = {null: 'All', 'women': 'Women', 'men': 'Men', 'unisex': 'Unisex'};

  late String? _gender = widget.initial.gender;
  late bool _onSale = widget.initial.onSale;
  late final _min = TextEditingController(text: _format(widget.initial.minPrice));
  late final _max = TextEditingController(text: _format(widget.initial.maxPrice));
  String? _priceError;

  static String _format(double? value) => value == null ? '' : value.toStringAsFixed(value % 1 == 0 ? 0 : 2);

  @override
  void dispose() {
    _min.dispose();
    _max.dispose();
    super.dispose();
  }

  void _apply() {
    final min = double.tryParse(_min.text.trim());
    final max = double.tryParse(_max.text.trim());
    if (min != null && max != null && max < min) {
      setState(() => _priceError = 'Maximum price must be greater than or equal to minimum price.');
      return;
    }
    Navigator.pop(context, CatalogFilters(gender: _gender, minPrice: min, maxPrice: max, onSale: _onSale));
  }

  @override
  Widget build(BuildContext context) => Padding(
        padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
        child: SafeArea(
          child: SingleChildScrollView(
            padding: const EdgeInsets.fromLTRB(ZayloSpace.page, 18, ZayloSpace.page, 16),
            child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
              Row(children: [
                Expanded(child: Text('Filters', style: Theme.of(context).textTheme.titleLarge)),
                TextButton(
                  onPressed: () => setState(() {
                    _gender = null;
                    _onSale = false;
                    _min.clear();
                    _max.clear();
                    _priceError = null;
                  }),
                  child: const Text('Reset'),
                ),
              ]),
              const SizedBox(height: 12),
              const FieldLabel('Gender'),
              Wrap(spacing: 8, runSpacing: 8, children: [
                for (final entry in _genders.entries)
                  ChoiceChip(
                    label: Text(entry.value),
                    selected: _gender == entry.key,
                    showCheckmark: false,
                    selectedColor: ZayloColors.ink,
                    backgroundColor: ZayloColors.white,
                    side: BorderSide(color: ZayloColors.line),
                    labelStyle: TextStyle(
                      fontWeight: FontWeight.w600,
                      color: _gender == entry.key ? ZayloColors.white : ZayloColors.ink,
                    ),
                    onSelected: (_) => setState(() => _gender = entry.key),
                  ),
              ]),
              const SizedBox(height: 18),
              const FieldLabel('Price (₱)'),
              Row(children: [
                Expanded(
                  child: TextField(
                    controller: _min,
                    keyboardType: const TextInputType.numberWithOptions(decimal: true),
                    decoration: zayloInputDecoration('Min'),
                  ),
                ),
                const Padding(padding: EdgeInsets.symmetric(horizontal: 10), child: Text('–')),
                Expanded(
                  child: TextField(
                    controller: _max,
                    keyboardType: const TextInputType.numberWithOptions(decimal: true),
                    decoration: zayloInputDecoration('Max'),
                  ),
                ),
              ]),
              if (_priceError != null)
                Padding(
                  padding: const EdgeInsets.only(top: 6),
                  child: Text(_priceError!, style: const TextStyle(color: Colors.redAccent, fontSize: 12)),
                ),
              const SizedBox(height: 10),
              SwitchListTile(
                contentPadding: EdgeInsets.zero,
                title: const Text('On sale only'),
                value: _onSale,
                onChanged: (value) => setState(() => _onSale = value),
              ),
              const SizedBox(height: 10),
              PrimaryButton(label: 'SHOW RESULTS', onPressed: _apply),
            ]),
          ),
        ),
      );
}
