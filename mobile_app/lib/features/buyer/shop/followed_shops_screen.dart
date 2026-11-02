import 'package:flutter/material.dart';

import 'package:zaylo_mobile/api/api_exception.dart';
import 'package:zaylo_mobile/components/buttons.dart';
import 'package:zaylo_mobile/components/feedback.dart';
import 'package:zaylo_mobile/features/buyer/shop/shop_widgets.dart';
import 'package:zaylo_mobile/features/buyer/widgets/shop_actions.dart';
import 'package:zaylo_mobile/models/buyer/shop_models.dart';
import 'package:zaylo_mobile/state/app_state.dart';
import 'package:zaylo_mobile/theme/zaylo_theme.dart';

/// The shops the buyer follows, opened from the Saved tab.
class FollowedShopsScreen extends StatefulWidget {
  const FollowedShopsScreen({super.key});

  @override
  State<FollowedShopsScreen> createState() => _FollowedShopsScreenState();
}

class _FollowedShopsScreenState extends State<FollowedShopsScreen> {
  List<Shop>? _shops;
  Object? _error;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _load());
  }

  Future<void> _load() async {
    try {
      final shops = await AppScope.read(context).api.followedShops();
      if (mounted) {
        setState(() {
          _shops = shops;
          _error = null;
        });
      }
    } on ApiException catch (error) {
      if (mounted) setState(() => _error = error);
    }
  }

  Future<void> _unfollow(Shop shop) async {
    try {
      await AppScope.read(context).api.toggleShopFollow(shop.id);
      if (!mounted) return;
      setState(() => _shops = _shops?.where((other) => other.id != shop.id).toList());
      showMessage(context, 'You unfollowed ${shop.name}.');
    } on ApiException catch (error) {
      if (mounted) showMessage(context, error.message);
    }
  }

  @override
  Widget build(BuildContext context) {
    final shops = _shops;
    return Scaffold(
      appBar: AppBar(title: const Text('Shops Followed')),
      body: shops == null
          ? (_error != null ? LoadError(error: _error, onRetry: _load, title: 'Could not load your shops') : const Loader())
          : shops.isEmpty
              ? EmptyState(
                  icon: Icons.storefront_outlined,
                  title: 'No shops followed yet',
                  message: "Open a product, tap the shop's name, then press Follow.",
                  actionLabel: 'Browse products',
                  onAction: () => ShopActions.showTab(context, 0),
                )
              : RefreshIndicator(
                  color: ZayloColors.ink,
                  onRefresh: _load,
                  child: ListView.separated(
                    physics: const AlwaysScrollableScrollPhysics(),
                    padding: EdgeInsets.only(top: 8, bottom: 8 + MediaQuery.paddingOf(context).bottom),
                    itemCount: shops.length,
                    separatorBuilder: (_, __) => Divider(height: 1, color: ZayloColors.line),
                    itemBuilder: (context, index) => ShopRow(
                      shop: shops[index],
                      onReturn: _load,
                      trailing: OutlinedButton(
                        style: secondaryButtonStyle.copyWith(
                          minimumSize: const WidgetStatePropertyAll(Size(0, 36)),
                          padding: const WidgetStatePropertyAll(EdgeInsets.symmetric(horizontal: 12)),
                        ),
                        onPressed: () => _unfollow(shops[index]),
                        child: const Text('Unfollow'),
                      ),
                    ),
                  ),
                ),
    );
  }
}
