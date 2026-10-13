import 'package:flutter/material.dart';

import 'package:zaylo_mobile/api/api_exception.dart';
import 'package:zaylo_mobile/components/buttons.dart';
import 'package:zaylo_mobile/components/feedback.dart';
import 'package:zaylo_mobile/components/form_fields.dart';
import 'package:zaylo_mobile/components/layout.dart';
import 'package:zaylo_mobile/features/buyer/catalog/product_card.dart';
import 'package:zaylo_mobile/features/buyer/checkout/checkout_screen.dart';
import 'package:zaylo_mobile/features/buyer/widgets/shop_actions.dart';
import 'package:zaylo_mobile/models/buyer/catalog_models.dart';
import 'package:zaylo_mobile/state/app_state.dart';
import 'package:zaylo_mobile/theme/zaylo_theme.dart';

class ProductDetailScreen extends StatefulWidget {
  const ProductDetailScreen({super.key, required this.summary});

  /// The list payload, shown immediately while details load.
  final Product summary;

  @override
  State<ProductDetailScreen> createState() => _ProductDetailScreenState();
}

class _ProductDetailScreenState extends State<ProductDetailScreen> {
  late Future<Product> _product;
  final _gallery = PageController();
  int _imageIndex = 0;
  ProductVariant? _variant;
  int _quantity = 1;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    _product = _load();
  }

  @override
  void dispose() {
    _gallery.dispose();
    super.dispose();
  }

  Future<Product> _load() async {
    final product = await AppScope.read(context).api.product(widget.summary.slug);
    final inStock = product.variants.where((variant) => variant.inStock).toList();
    // A single option needs no choice; preselect it like the web form does.
    if (inStock.length == 1 && mounted) setState(() => _variant = inStock.first);
    return product;
  }

  int _maxQuantity(Product product) {
    final stock = _variant?.stock ?? product.stock ?? 99;
    return stock.clamp(1, 99);
  }

  Future<void> _add(Product product, {required bool buyNow}) async {
    if (product.variants.length > 1 && _variant == null) {
      showMessage(context, 'Choose an option first.');
      return;
    }
    if (!await ShopActions.ensureSignedIn(context) || !mounted) return;
    setState(() => _busy = true);
    try {
      final cart = await AppScope.read(context).addToBag(product, quantity: _quantity, variantId: _variant?.id);
      if (!mounted) return;
      if (buyNow && cart.addedItemId != null) {
        Navigator.of(context).push(MaterialPageRoute(builder: (_) => CheckoutScreen(cartItemIds: [cart.addedItemId!])));
      } else {
        showMessage(context, 'Added to your bag.',
            actionLabel: 'VIEW BAG', onAction: () => ShopActions.showTab(context, 2));
      }
    } on ApiException catch (error) {
      if (mounted) showMessage(context, error.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => FutureBuilder<Product>(
        future: _product,
        builder: (context, snapshot) {
          final product = snapshot.data ?? widget.summary;
          final loaded = snapshot.hasData;
          final saved = AppScope.of(context).isSaved(product);
          final images = product.images.isNotEmpty ? product.images : [product.imageUrl];

          return Scaffold(
            appBar: AppBar(
              title: const Text('Product'),
              actions: [
                IconButton(
                  tooltip: saved ? 'Remove from saved' : 'Save for later',
                  onPressed: () => ShopActions.toggleSaved(context, product),
                  icon: Icon(saved ? Icons.favorite_rounded : Icons.favorite_border_rounded,
                      color: saved ? Colors.redAccent : ZayloColors.ink),
                ),
                IconButton(
                  tooltip: 'Bag',
                  onPressed: () => ShopActions.showTab(context, 2),
                  icon: Badge(
                    isLabelVisible: AppScope.of(context).cartCount > 0,
                    label: Text('${AppScope.of(context).cartCount}'),
                    child: const Icon(Icons.shopping_bag_outlined),
                  ),
                ),
              ],
            ),
            bottomNavigationBar: snapshot.hasError
                ? null
                : _ActionBar(
                    busy: _busy,
                    enabled: loaded,
                    onAdd: () => _add(product, buyNow: false),
                    onBuy: () => _add(product, buyNow: true),
                  ),
            body: snapshot.hasError
                ? LoadError(
                    error: snapshot.error,
                    title: 'Product unavailable',
                    onRetry: () => setState(() {
                      _product = _load();
                    }),
                  )
                : ListView(
                    padding: const EdgeInsets.only(bottom: 24),
                    children: [
                      AspectRatio(
                        aspectRatio: 1,
                        child: Stack(children: [
                          PageView.builder(
                            controller: _gallery,
                            itemCount: images.length,
                            onPageChanged: (index) => setState(() => _imageIndex = index),
                            itemBuilder: (_, index) => NetworkImageBox(url: images[index]),
                          ),
                          if (images.length > 1)
                            Positioned(
                              bottom: 14,
                              left: 0,
                              right: 0,
                              child: Row(
                                mainAxisAlignment: MainAxisAlignment.center,
                                children: List.generate(
                                  images.length,
                                  (index) => AnimatedContainer(
                                    duration: const Duration(milliseconds: 180),
                                    margin: const EdgeInsets.symmetric(horizontal: 3),
                                    width: _imageIndex == index ? 18 : 7,
                                    height: 7,
                                    decoration: BoxDecoration(
                                      color: _imageIndex == index ? ZayloColors.ink : ZayloColors.white,
                                      borderRadius: BorderRadius.circular(9),
                                    ),
                                  ),
                                ),
                              ),
                            ),
                        ]),
                      ),
                      Padding(
                        padding: const EdgeInsets.fromLTRB(ZayloSpace.page, 20, ZayloSpace.page, 0),
                        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                          Row(children: [
                            Expanded(child: Eyebrow(product.categoryLabel)),
                            if (product.badge != null) ProductBadge(product.badge!),
                          ]),
                          const SizedBox(height: 7),
                          Text(product.name, style: Theme.of(context).textTheme.headlineSmall),
                          const SizedBox(height: 10),
                          PriceLine(product: product, size: 20, price: _variant?.price),
                          if (product.discountPercent != null && _variant == null)
                            Padding(
                              padding: const EdgeInsets.only(top: 4),
                              child: Text('You save ${product.discountPercent}%',
                                  style: TextStyle(
                                      fontSize: 12, fontWeight: FontWeight.w700, color: ZayloColors.success)),
                            ),
                          if (product.sellerName != null) ...[
                            const SizedBox(height: 14),
                            Row(children: [
                              Icon(Icons.storefront_outlined, size: 17, color: ZayloColors.muted),
                              const SizedBox(width: 6),
                              Expanded(
                                child: Text('Sold by ${product.sellerName}',
                                    style: TextStyle(fontSize: 13, color: ZayloColors.muted)),
                              ),
                            ]),
                          ],
                          if (!loaded) ...[
                            const SizedBox(height: 28),
                            const Loader(),
                          ] else ...[
                            if (product.variants.length > 1) ...[
                              const SizedBox(height: 24),
                              const FieldLabel('Choose an option'),
                              Wrap(spacing: 8, runSpacing: 8, children: [
                                for (final variant in product.variants)
                                  ChoiceChip(
                                    label: Text(variant.inStock ? variant.name : '${variant.name} · sold out'),
                                    selected: _variant?.id == variant.id,
                                    showCheckmark: false,
                                    selectedColor: ZayloColors.ink,
                                    backgroundColor: ZayloColors.white,
                                    side: BorderSide(color: ZayloColors.line),
                                    labelStyle: TextStyle(
                                      fontWeight: FontWeight.w600,
                                      color: _variant?.id == variant.id ? ZayloColors.white : ZayloColors.ink,
                                    ),
                                    onSelected: variant.inStock
                                        ? (_) => setState(() {
                                              _variant = variant;
                                              _quantity = _quantity.clamp(1, variant.stock.clamp(1, 99));
                                            })
                                        : null,
                                  ),
                              ]),
                            ],
                            const SizedBox(height: 22),
                            Row(children: [
                              Text('Quantity',
                                  style: TextStyle(fontWeight: FontWeight.w700, color: ZayloColors.ink)),
                              const Spacer(),
                              QuantityStepper(
                                value: _quantity,
                                max: _maxQuantity(product),
                                onChanged: (value) => setState(() => _quantity = value),
                              ),
                            ]),
                            const SizedBox(height: 6),
                            Align(
                              alignment: Alignment.centerRight,
                              child: Text(
                                '${_variant?.stock ?? product.stock ?? 0} available',
                                style: TextStyle(fontSize: 12, color: ZayloColors.muted),
                              ),
                            ),
                          ],
                          const SizedBox(height: 22),
                          Divider(color: ZayloColors.line),
                          const SizedBox(height: 14),
                          const Eyebrow('About this item'),
                          const SizedBox(height: 8),
                          Text(
                            product.description?.trim().isNotEmpty == true
                                ? product.description!
                                : 'A carefully selected item from the ZAYLO marketplace.',
                            style: Theme.of(context).textTheme.bodyMedium,
                          ),
                          const SizedBox(height: 22),
                          const _Assurances(),
                        ]),
                      ),
                    ],
                  ),
          );
        },
      );
}

class _ActionBar extends StatelessWidget {
  const _ActionBar({required this.busy, required this.enabled, required this.onAdd, required this.onBuy});

  final bool busy;
  final bool enabled;
  final VoidCallback onAdd;
  final VoidCallback onBuy;

  @override
  Widget build(BuildContext context) => Container(
        decoration: BoxDecoration(
          color: ZayloColors.paper,
          border: Border(top: BorderSide(color: ZayloColors.line)),
        ),
        padding: const EdgeInsets.fromLTRB(ZayloSpace.page, 12, ZayloSpace.page, 12),
        child: SafeArea(
          top: false,
          child: Row(children: [
            Expanded(
              child: OutlinedButton(
                onPressed: enabled && !busy ? onAdd : null,
                style: secondaryButtonStyle,
                child: const Text('ADD TO BAG'),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: FilledButton(
                onPressed: enabled && !busy ? onBuy : null,
                style: primaryButtonStyle,
                child: busy
                    ? SizedBox(
                        height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2, color: ZayloColors.white))
                    : const Text('BUY NOW'),
              ),
            ),
          ]),
        ),
      );
}

class _Assurances extends StatelessWidget {
  const _Assurances();

  @override
  Widget build(BuildContext context) => const SectionCard(
        child: Column(children: [
          _Assurance(Icons.local_shipping_outlined, 'Ships in 2–4 business days',
              '₱120 shipping per seller. Free with an eligible voucher.'),
          SizedBox(height: 14),
          _Assurance(Icons.payments_outlined, 'Cash on delivery or GCash', 'Pay the way that suits you.'),
          SizedBox(height: 14),
          _Assurance(Icons.undo_rounded, 'Cancel before it ships', 'Cancel anytime until the seller processes it.'),
        ]),
      );
}

class _Assurance extends StatelessWidget {
  const _Assurance(this.icon, this.title, this.message);

  final IconData icon;
  final String title;
  final String message;

  @override
  Widget build(BuildContext context) => Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Icon(icon, size: 22, color: ZayloColors.ink),
        const SizedBox(width: 12),
        Expanded(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(title, style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: ZayloColors.ink)),
            const SizedBox(height: 2),
            Text(message, style: TextStyle(fontSize: 12, color: ZayloColors.muted)),
          ]),
        ),
      ]);
}
