import 'package:flutter/material.dart';

import '../../../../core/theme/zaylo_theme.dart';
import '../../../../core/widgets/layout.dart';

class HomeTopBar extends StatelessWidget {
  const HomeTopBar({super.key, required this.cartCount, required this.onBag});

  final int cartCount;
  final VoidCallback onBag;

  /// The bar floats over the page, which reserves this much space for it.
  static const height = 70.0;

  @override
  Widget build(BuildContext context) => Container(
        height: height,
        color: ZayloColors.cream,
        padding: const EdgeInsets.fromLTRB(ZayloSpace.page, 10, ZayloSpace.page, 12),
        child: Row(children: [
          IconButton(
            tooltip: 'Menu',
            onPressed: () => Scaffold.of(context).openDrawer(),
            icon: const Icon(Icons.menu_rounded),
          ),
          const Spacer(),
          Image.asset(ZayloAssets.logo,
              height: 38, fit: BoxFit.contain, color: ZayloAssets.logoTint, colorBlendMode: BlendMode.srcIn),
          const Spacer(),
          IconButton(
            tooltip: 'Bag',
            onPressed: onBag,
            icon: Badge(
              isLabelVisible: cartCount > 0,
              label: Text('$cartCount'),
              backgroundColor: ZayloColors.ink,
              child: const Icon(Icons.shopping_bag_outlined),
            ),
          ),
        ]),
      );
}

class HomeHeroCarousel extends StatelessWidget {
  const HomeHeroCarousel({
    super.key,
    required this.images,
    required this.controller,
    required this.activeIndex,
    required this.onPageChanged,
    required this.onShop,
    required this.onNew,
  });

  final List<String> images;
  final PageController controller;
  final int activeIndex;
  final ValueChanged<int> onPageChanged;
  final VoidCallback onShop;
  final VoidCallback onNew;

  @override
  Widget build(BuildContext context) => LayoutBuilder(
        builder: (context, constraints) {
          final compact = constraints.maxWidth < 360;
          return SizedBox(
            // A wide banner that reaches both screen edges, with a floor so
            // the copy and buttons never clip on narrow phones.
            height: (constraints.maxWidth / (compact ? 1.45 : 1.5)).clamp(compact ? 320.0 : 310.0, 460.0),
            child: ClipRRect(
              child: Stack(children: [
                PageView.builder(
                  controller: controller,
                  itemCount: images.length,
                  onPageChanged: onPageChanged,
                  itemBuilder: (_, index) => NetworkImageBox(url: images[index]),
                ),
                DecoratedBox(
                  decoration: BoxDecoration(
                    gradient: LinearGradient(
                      begin: Alignment.centerLeft,
                      end: Alignment.centerRight,
                      colors: [
                        ZayloColors.cream.withValues(alpha: .97),
                        ZayloColors.cream.withValues(alpha: .74),
                        ZayloColors.cream.withValues(alpha: .04),
                      ],
                    ),
                  ),
                ),
                Padding(
                  padding: EdgeInsets.fromLTRB(20, compact ? 16 : 20, 20, compact ? 12 : 16),
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    const Text('ONE MARKETPLACE. ENDLESS FINDS.',
                        style: TextStyle(fontSize: 9, fontWeight: FontWeight.w800, letterSpacing: 1.25)),
                    const SizedBox(height: 8),
                    Text('Driven by passion.\nDefined by your origin',
                        style: Theme.of(context)
                            .textTheme
                            .displaySmall
                            ?.copyWith(fontSize: compact ? 22 : 25, height: 1.03)),
                    const SizedBox(height: 7),
                    SizedBox(
                      width: compact ? 205 : 225,
                      child: Text('Discover tech, home essentials, fashion, beauty, groceries, and more.',
                          style: TextStyle(fontSize: compact ? 11 : 12, height: 1.25)),
                    ),
                    const Spacer(),
                    FilledButton(
                      onPressed: onShop,
                      style: FilledButton.styleFrom(
                        backgroundColor: ZayloColors.ink,
                        foregroundColor: ZayloColors.white,
                        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                        minimumSize: const Size(0, 40),
                        tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                      ),
                      child: const Text('Shop the marketplace'),
                    ),
                    const SizedBox(height: 2),
                    TextButton(
                      onPressed: onNew,
                      style: TextButton.styleFrom(
                        foregroundColor: ZayloColors.ink,
                        padding: EdgeInsets.zero,
                        minimumSize: const Size(0, 24),
                        tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                      ),
                      child: const Text("See what's new  →"),
                    ),
                  ]),
                ),
                Positioned(
                  bottom: compact ? 14 : 18,
                  right: 18,
                  child: Row(
                    children: List.generate(
                      images.length,
                      (index) => AnimatedContainer(
                        duration: const Duration(milliseconds: 180),
                        margin: const EdgeInsets.only(left: 5),
                        width: activeIndex == index ? 20 : 7,
                        height: 7,
                        decoration: BoxDecoration(color: ZayloColors.ink, borderRadius: BorderRadius.circular(9)),
                      ),
                    ),
                  ),
                ),
              ]),
            ),
          );
        },
      );
}
