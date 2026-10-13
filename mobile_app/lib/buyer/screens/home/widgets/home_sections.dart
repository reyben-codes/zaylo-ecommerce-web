import 'package:flutter/material.dart';

import '../../../../core/theme/zaylo_theme.dart';
import '../../../../core/widgets/layout.dart';
import '../../../models/catalog_models.dart';
import '../../catalog/product_card.dart';

class HomeCategoryStrip extends StatelessWidget {
  const HomeCategoryStrip({super.key, required this.categories, required this.onSelected});

  final List<Category> categories;
  final ValueChanged<Category> onSelected;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.only(top: 28, bottom: 38),
        child: SizedBox(
          height: 122,
          child: ListView.separated(
            padding: const EdgeInsets.symmetric(horizontal: ZayloSpace.page),
            scrollDirection: Axis.horizontal,
            itemCount: categories.length,
            separatorBuilder: (_, __) => const SizedBox(width: 16),
            itemBuilder: (_, index) {
              final category = categories[index];
              return InkWell(
                onTap: () => onSelected(category),
                borderRadius: BorderRadius.circular(50),
                child: SizedBox(
                  width: 78,
                  child: Column(children: [
                    ClipOval(child: SizedBox(width: 72, height: 72, child: NetworkImageBox(url: category.imageUrl))),
                    const SizedBox(height: 8),
                    Text(category.label,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600)),
                    const Text('Explore', style: TextStyle(fontSize: 10, color: ZayloColors.muted)),
                  ]),
                ),
              );
            },
          ),
        ),
      );
}

class HomeSectionHeader extends StatelessWidget {
  const HomeSectionHeader({super.key, required this.title, required this.eyebrow, required this.action, required this.onPressed});

  final String title;
  final String eyebrow;
  final String action;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.fromLTRB(ZayloSpace.page, 0, 12, 17),
        child: Row(crossAxisAlignment: CrossAxisAlignment.end, children: [
          Expanded(
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Eyebrow(eyebrow),
              const SizedBox(height: 5),
              Text(title, style: Theme.of(context).textTheme.headlineSmall),
            ]),
          ),
          TextButton(onPressed: onPressed, child: Text('$action  →')),
        ]),
      );
}

/// Matches the web dashboard's red Flash Sales header.
class HomeFlashHeader extends StatelessWidget {
  const HomeFlashHeader({super.key});

  static const red = Color(0xFFC0392B);

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.fromLTRB(ZayloSpace.page, 0, ZayloSpace.page, 14),
        child: Wrap(spacing: 10, runSpacing: 6, crossAxisAlignment: WrapCrossAlignment.center, children: [
          Text('Flash Sales', style: Theme.of(context).textTheme.headlineSmall),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
            decoration: BoxDecoration(color: red, borderRadius: BorderRadius.circular(20)),
            child: const Text('🔥 LIMITED TIME',
                style: TextStyle(color: ZayloColors.white, fontSize: 9, fontWeight: FontWeight.w800, letterSpacing: .6)),
          ),
        ]),
      );
}

class HomeProductRail extends StatelessWidget {
  const HomeProductRail({super.key, required this.products});

  final List<Product> products;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.only(bottom: ZayloSpace.section),
        child: SizedBox(
          height: 168 + ProductGridDelegate.infoHeight,
          child: ListView.separated(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: ZayloSpace.page),
            itemCount: products.length,
            separatorBuilder: (_, __) => const SizedBox(width: 12),
            itemBuilder: (context, index) => SizedBox(width: 168, child: ProductCard(product: products[index])),
          ),
        ),
      );
}

class HomeTrustStrip extends StatelessWidget {
  const HomeTrustStrip({super.key});

  @override
  Widget build(BuildContext context) => Container(
        color: ZayloColors.sand,
        padding: const EdgeInsets.symmetric(horizontal: ZayloSpace.page, vertical: 23),
        child: const Column(children: [
          _TrustItem(Icons.shield_outlined, 'Secure checkout', 'Your payment details stay protected.'),
          SizedBox(height: 19),
          _TrustItem(Icons.local_shipping_outlined, 'Reliable delivery', 'Track your order from door to door.'),
          SizedBox(height: 19),
          _TrustItem(Icons.support_agent_outlined, 'Easy support', 'Help is available when you need it.'),
        ]),
      );
}

class _TrustItem extends StatelessWidget {
  const _TrustItem(this.icon, this.title, this.message);

  final IconData icon;
  final String title;
  final String message;

  @override
  Widget build(BuildContext context) => Row(children: [
        Icon(icon, size: 25),
        const SizedBox(width: 13),
        Expanded(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(title, style: const TextStyle(fontWeight: FontWeight.w700)),
            Text(message, style: const TextStyle(color: ZayloColors.muted, fontSize: 12)),
          ]),
        ),
      ]);
}

class HomeFooter extends StatelessWidget {
  const HomeFooter({super.key});

  @override
  Widget build(BuildContext context) => Container(
        color: ZayloColors.ink,
        // Stretch the footer behind the floating nav bar.
        padding: EdgeInsets.fromLTRB(ZayloSpace.page, 29, ZayloSpace.page, 35 + MediaQuery.paddingOf(context).bottom),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Image.asset('assets/images/ZAYLO_LOGO_LIGHT.png',
              width: 136, height: 38, fit: BoxFit.contain, alignment: Alignment.centerLeft),
          const SizedBox(height: 8),
          const Text('Everyday essentials, exciting finds, and trusted sellers—all together in one marketplace.',
              style: TextStyle(color: Color(0xFFD7CCC0), height: 1.4)),
          const SizedBox(height: 25),
          const Text('Driven by passion. Defined by your origin',
              style: TextStyle(color: Color(0xFFB9ACA0), fontSize: 11)),
        ]),
      );
}
