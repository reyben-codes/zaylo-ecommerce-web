import 'package:flutter/material.dart';

import 'package:zaylo_mobile/components/layout.dart';
import 'package:zaylo_mobile/features/buyer/shop/shop_screen.dart';
import 'package:zaylo_mobile/models/buyer/shop_models.dart';
import 'package:zaylo_mobile/theme/zaylo_theme.dart';

/// The shop's photo, or its initials without one.
class ShopAvatar extends StatelessWidget {
  const ShopAvatar({super.key, required this.shop, this.size = 48});

  final Shop shop;
  final double size;

  @override
  Widget build(BuildContext context) => ClipOval(
        child: SizedBox(
          width: size,
          height: size,
          child: shop.avatarUrl != null
              ? NetworkImageBox(url: shop.avatarUrl)
              : ColoredBox(
                  color: ZayloColors.ink,
                  child: Center(
                    child: Text(
                      shop.initials,
                      style: TextStyle(color: ZayloColors.white, fontWeight: FontWeight.w700, fontSize: size * .34),
                    ),
                  ),
                ),
        ),
      );
}

/// One shop in search results or the followed list; opens its page.
class ShopRow extends StatelessWidget {
  const ShopRow({super.key, required this.shop, this.trailing, this.onReturn});

  final Shop shop;
  final Widget? trailing;

  /// After coming back from the shop's page, which may have changed whether it is followed.
  final VoidCallback? onReturn;

  @override
  Widget build(BuildContext context) {
    final rating = shop.rating;
    return ListTile(
      contentPadding: const EdgeInsets.symmetric(horizontal: ZayloSpace.page),
      leading: ShopAvatar(shop: shop),
      title: Text(shop.name, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w700)),
      subtitle: Text(
        [
          '${shop.productCount} ${shop.productCount == 1 ? 'product' : 'products'}',
          '${shop.followers} ${shop.followers == 1 ? 'follower' : 'followers'}',
          if (rating != null) '★ ${rating.toStringAsFixed(1)}',
        ].join(' · '),
        style: TextStyle(fontSize: 12, color: ZayloColors.muted),
      ),
      trailing: trailing ?? Icon(Icons.chevron_right_rounded, color: ZayloColors.muted),
      onTap: () async {
        await Navigator.of(context).push(MaterialPageRoute(builder: (_) => ShopScreen(shopId: shop.id, name: shop.name)));
        onReturn?.call();
      },
    );
  }
}
