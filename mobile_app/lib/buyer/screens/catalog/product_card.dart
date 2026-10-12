import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';

import '../../../core/theme/zaylo_theme.dart';
import '../../../core/utils/formatters.dart';
import '../../../core/widgets/layout.dart';
import '../../models/catalog_models.dart';
import '../../state/app_state.dart';
import '../../widgets/shop_actions.dart';

const productGridDelegate = ProductGridDelegate();

/// Two columns of cards sized as a square image plus a fixed text area, so
/// the name, price and button fit at every phone width (a fixed aspect ratio
/// clips them on narrow screens).
class ProductGridDelegate extends SliverGridDelegate {
  const ProductGridDelegate();

  static const _crossSpacing = 12.0;
  static const _mainSpacing = 14.0;
  static const infoHeight = 162.0;

  @override
  SliverGridLayout getLayout(SliverConstraints constraints) {
    final tileWidth = (constraints.crossAxisExtent - _crossSpacing) / 2;
    final tileHeight = tileWidth + infoHeight;
    return SliverGridRegularTileLayout(
      crossAxisCount: 2,
      mainAxisStride: tileHeight + _mainSpacing,
      crossAxisStride: tileWidth + _crossSpacing,
      childMainAxisExtent: tileHeight,
      childCrossAxisExtent: tileWidth,
      reverseCrossAxis: axisDirectionIsReversed(constraints.crossAxisDirection),
    );
  }

  @override
  bool shouldRelayout(ProductGridDelegate oldDelegate) => false;
}

class ProductCard extends StatelessWidget {
  const ProductCard({super.key, required this.product});

  final Product product;

  @override
  Widget build(BuildContext context) {
    final saved = AppScope.of(context).isSaved(product);
    return Material(
      color: ZayloColors.paper,
      borderRadius: BorderRadius.circular(14),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: () => ShopActions.openProduct(context, product),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          AspectRatio(
            aspectRatio: 1,
            child: Stack(fit: StackFit.expand, children: [
              NetworkImageBox(url: product.imageUrl),
              if (product.badge != null || product.discountPercent != null)
                Positioned(
                  top: 9,
                  left: 9,
                  child: ProductBadge(product.discountPercent != null ? '-${product.discountPercent}%' : product.badge!),
                ),
              Positioned(
                top: 2,
                right: 0,
                child: IconButton(
                  tooltip: saved ? 'Remove from saved' : 'Save for later',
                  onPressed: () => ShopActions.toggleSaved(context, product),
                  icon: Icon(
                    saved ? Icons.favorite_rounded : Icons.favorite_border_rounded,
                    color: saved ? Colors.redAccent : ZayloColors.ink,
                  ),
                ),
              ),
            ]),
          ),
          Expanded(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(11, 10, 11, 10),
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(product.categoryLabel.toUpperCase(),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                        fontSize: 9, letterSpacing: .8, color: ZayloColors.taupe, fontWeight: FontWeight.w700)),
                const SizedBox(height: 4),
                Text(product.name,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                        fontSize: 14, height: 1.2, fontWeight: FontWeight.w600, color: ZayloColors.ink)),
                const Spacer(),
                PriceLine(product: product, size: 13),
                const SizedBox(height: 8),
                SizedBox(
                  width: double.infinity,
                  child: OutlinedButton(
                    onPressed: () => ShopActions.quickAdd(context, product),
                    style: OutlinedButton.styleFrom(
                      foregroundColor: ZayloColors.ink,
                      side: BorderSide(color: ZayloColors.ink),
                      padding: const EdgeInsets.symmetric(vertical: 7),
                      textStyle: const TextStyle(fontSize: 11, fontWeight: FontWeight.w700),
                    ),
                    child: Text(product.hasOptions ? 'Choose options' : 'Add to bag'),
                  ),
                ),
              ]),
            ),
          ),
        ]),
      ),
    );
  }
}

class PriceLine extends StatelessWidget {
  const PriceLine({super.key, required this.product, this.size = 13, this.price});

  final Product product;
  final double size;

  /// Overrides the product price, for example with the chosen variant's.
  final double? price;

  @override
  Widget build(BuildContext context) {
    final original = product.originalPrice;
    final current = price ?? product.price;
    return Wrap(crossAxisAlignment: WrapCrossAlignment.end, spacing: 6, children: [
      Text(peso(current), style: TextStyle(fontSize: size, fontWeight: FontWeight.w800, color: ZayloColors.ink)),
      if (original != null && original > current)
        Text(peso(original, cents: false),
            style: TextStyle(
                fontSize: size * .78, decoration: TextDecoration.lineThrough, color: ZayloColors.taupe)),
    ]);
  }
}

class ProductBadge extends StatelessWidget {
  const ProductBadge(this.text, {super.key});

  final String text;

  @override
  Widget build(BuildContext context) => DecoratedBox(
        decoration: BoxDecoration(color: ZayloColors.ink, borderRadius: BorderRadius.circular(3)),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 4),
          child: Text(text.toUpperCase(),
              style: TextStyle(color: ZayloColors.white, fontSize: 8, fontWeight: FontWeight.w700)),
        ),
      );
}
