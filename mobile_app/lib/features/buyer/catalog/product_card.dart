import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';

import 'package:zaylo_mobile/components/cart_icon.dart';
import 'package:zaylo_mobile/components/layout.dart';
import 'package:zaylo_mobile/features/buyer/widgets/shop_actions.dart';
import 'package:zaylo_mobile/models/buyer/catalog_models.dart';
import 'package:zaylo_mobile/state/app_state.dart';
import 'package:zaylo_mobile/theme/zaylo_theme.dart';
import 'package:zaylo_mobile/utils/formatters.dart';

const productGridDelegate = ProductGridDelegate();

/// Two columns of cards sized as a square image plus a fixed text area, so
/// the name, price and button fit at every phone width (a fixed aspect ratio
/// clips them on narrow screens).
class ProductGridDelegate extends SliverGridDelegate {
  const ProductGridDelegate();

  static const _crossSpacing = 12.0;
  static const _mainSpacing = 14.0;
  static const infoHeight = 98.0;

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

/// A product tile: the photo fading into a soft card, a save button and
/// badges over the photo, then the name, price and a quick "add to bag".
class ProductCard extends StatelessWidget {
  const ProductCard({super.key, required this.product});

  final Product product;

  static const _radius = 20.0;

  /// A shade between the page and sand, so cards stand out softly in light and dark mode.
  static Color get surface => Color.lerp(ZayloColors.cream, ZayloColors.sand, .55)!;

  @override
  Widget build(BuildContext context) {
    final saved = AppScope.of(context).isSaved(product);
    final surface = ProductCard.surface;
    final badge = product.discountPercent != null ? '-${product.discountPercent}%' : product.badge;
    // A warm brown outline and a soft shadow lift the card off the page, as on the website.
    return Material(
      color: surface,
      elevation: 1,
      shadowColor: ZayloColors.shadow.withValues(alpha: .4),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(_radius),
        side: BorderSide(color: ZayloColors.cardBorder),
      ),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: () => ShopActions.openProduct(context, product),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          AspectRatio(
            aspectRatio: 1,
            child: Stack(fit: StackFit.expand, children: [
              NetworkImageBox(url: product.imageUrl),
              // The photo fades into the card, so the name and price sit on one surface.
              Positioned(
                left: 0,
                right: 0,
                bottom: 0,
                height: 56,
                child: IgnorePointer(
                  child: DecoratedBox(
                    decoration: BoxDecoration(
                      gradient: LinearGradient(
                        begin: Alignment.topCenter,
                        end: Alignment.bottomCenter,
                        colors: [surface.withValues(alpha: 0), surface],
                      ),
                    ),
                  ),
                ),
              ),
              Positioned(
                top: 8,
                right: 8,
                child: _SquareIconButton(
                  tooltip: saved ? 'Remove from saved' : 'Save for later',
                  onPressed: () => ShopActions.toggleSaved(context, product),
                  background: ZayloColors.white,
                  icon: Icon(
                    saved ? Icons.favorite_rounded : Icons.favorite_border_rounded,
                    size: 19,
                    color: saved ? Colors.redAccent : ZayloColors.ink,
                  ),
                ),
              ),
              if (badge != null) Positioned(left: 10, bottom: 10, child: _Pill(child: Text(badge))),
              if (product.rating != null)
                Positioned(
                  right: 10,
                  bottom: 10,
                  child: Semantics(
                    label: 'Rated ${product.rating!.toStringAsFixed(1)} out of 5 from ${product.reviewCount} reviews',
                    excludeSemantics: true,
                    child: _Pill(
                      child: Row(mainAxisSize: MainAxisSize.min, children: [
                        Icon(Icons.star_rounded, size: 12, color: ZayloColors.bronze),
                        const SizedBox(width: 2),
                        Text(product.rating!.toStringAsFixed(1)),
                      ]),
                    ),
                  ),
                ),
            ]),
          ),
          Expanded(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(12, 8, 10, 10),
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(product.name,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(fontSize: 13.5, height: 1.25, fontWeight: FontWeight.w500, color: ZayloColors.muted)),
                const Spacer(),
                Row(crossAxisAlignment: CrossAxisAlignment.end, children: [
                  Expanded(child: PriceLine(product: product, size: 15)),
                  const SizedBox(width: 6),
                  Builder(
                    builder: (button) => _SquareIconButton(
                      tooltip: product.hasOptions ? 'Choose options' : 'Add to bag',
                      onPressed: () => ShopActions.quickAdd(context, product, from: button),
                      background: ZayloColors.ink,
                      size: 34,
                      icon: CartIcon(size: 18, color: ZayloColors.white),
                    ),
                  ),
                ]),
              ]),
            ),
          ),
        ]),
      ),
    );
  }
}

/// A small rounded-square button, like the save and bag buttons on the card.
class _SquareIconButton extends StatelessWidget {
  const _SquareIconButton({
    required this.tooltip,
    required this.onPressed,
    required this.background,
    required this.icon,
    this.size = 36,
  });

  final String tooltip;
  final VoidCallback onPressed;
  final Color background;
  final Widget icon;
  final double size;

  @override
  Widget build(BuildContext context) => Tooltip(
        message: tooltip,
        child: Semantics(
          button: true,
          label: tooltip,
          excludeSemantics: true,
          child: Material(
            color: background,
            borderRadius: BorderRadius.circular(11),
            child: InkWell(
              onTap: onPressed,
              borderRadius: BorderRadius.circular(11),
              child: SizedBox.square(dimension: size, child: Center(child: icon)),
            ),
          ),
        ),
      );
}

/// A white label over the photo, such as "New in", "-20%" or the rating.
class _Pill extends StatelessWidget {
  const _Pill({required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) => DecoratedBox(
        decoration: BoxDecoration(color: ZayloColors.white, borderRadius: BorderRadius.circular(20)),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 4),
          child: DefaultTextStyle.merge(
            style: TextStyle(fontSize: 10, fontWeight: FontWeight.w700, color: ZayloColors.ink),
            child: child,
          ),
        ),
      );
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
