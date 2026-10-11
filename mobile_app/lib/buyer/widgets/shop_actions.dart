import 'package:flutter/material.dart';

import '../../core/api/api_exception.dart';
import '../../core/widgets/feedback.dart';
import '../models/catalog_models.dart';
import '../screens/auth/login_screen.dart';
import '../screens/catalog/product_detail_screen.dart';
import '../state/app_state.dart';

/// Shopping actions shared by every product card and screen.
abstract final class ShopActions {
  /// Returns true when the buyer is (or becomes) signed in.
  static Future<bool> ensureSignedIn(BuildContext context) async {
    final state = AppScope.read(context);
    if (state.signedIn) return true;
    await Navigator.of(context).push(MaterialPageRoute(builder: (_) => const LoginScreen()));
    return state.signedIn;
  }

  static void openProduct(BuildContext context, Product product) {
    Navigator.of(context).push(MaterialPageRoute(builder: (_) => ProductDetailScreen(summary: product)));
  }

  /// Pops back to the tab shell and selects a tab (0 home, 1 saved, 2 bag, 3 account).
  static void showTab(BuildContext context, int tab) {
    Navigator.of(context).popUntil((route) => route.isFirst);
    AppScope.read(context).selectedTab.value = tab;
  }

  static Future<void> toggleSaved(BuildContext context, Product product) async {
    if (!await ensureSignedIn(context) || !context.mounted) return;
    try {
      final saved = await AppScope.read(context).toggleSaved(product);
      if (context.mounted) showMessage(context, saved ? 'Saved for later.' : 'Removed from saved items.');
    } on ApiException catch (error) {
      if (context.mounted) showMessage(context, error.message);
    }
  }

  /// Adds one unit, or opens the product when an option must be chosen first.
  static Future<void> quickAdd(BuildContext context, Product product) async {
    if (product.hasOptions) return openProduct(context, product);
    if (!await ensureSignedIn(context) || !context.mounted) return;
    try {
      await AppScope.read(context).addToBag(product);
      if (context.mounted) {
        showMessage(context, 'Added to your bag.', actionLabel: 'VIEW BAG', onAction: () => showTab(context, 2));
      }
    } on ApiException catch (error) {
      if (!context.mounted) return;
      // The server is the source of truth for options; fall back to the picker.
      error.fieldErrors.containsKey('product_variant_id')
          ? openProduct(context, product)
          : showMessage(context, error.message);
    }
  }
}
