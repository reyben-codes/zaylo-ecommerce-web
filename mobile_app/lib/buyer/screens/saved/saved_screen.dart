import 'package:flutter/material.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/theme/zaylo_theme.dart';
import '../../../core/widgets/feedback.dart';
import '../../models/catalog_models.dart';
import '../../state/app_state.dart';
import '../../widgets/shop_actions.dart';
import '../../widgets/signed_out_prompt.dart';
import '../../widgets/tab_header.dart';
import '../catalog/product_card.dart';

/// The Saved tab, matching the web wishlist.
class SavedScreen extends StatefulWidget {
  const SavedScreen({super.key});

  @override
  State<SavedScreen> createState() => _SavedScreenState();
}

class _SavedScreenState extends State<SavedScreen> {
  List<Product>? _products;
  Object? _error;
  int? _lastUserId;
  int _lastSavedCount = -1;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final state = AppScope.of(context);
    // Reload when the buyer changes or saves/unsaves from another screen.
    if (state.user != null && (state.user!.id != _lastUserId || state.savedCount != _lastSavedCount)) {
      _lastUserId = state.user!.id;
      _lastSavedCount = state.savedCount;
      WidgetsBinding.instance.addPostFrameCallback((_) => _load());
    }
  }

  Future<void> _load() async {
    final state = AppScope.read(context);
    try {
      final products = await state.api.wishlist();
      if (!mounted) return;
      setState(() {
        _products = products;
        _error = null;
      });
      _lastSavedCount = products.length;
      state.syncSaved(products);
    } on ApiException catch (error) {
      if (mounted) setState(() => _error = error);
    }
  }

  @override
  Widget build(BuildContext context) {
    final state = AppScope.of(context);
    if (!state.signedIn) {
      return const SignedOutPrompt(
        icon: Icons.favorite_border,
        heading: 'Saved',
        eyebrow: 'Your favorites',
        title: 'Saved items',
        message: 'Sign in to save products you love and find them on any device.',
      );
    }
    // Hide items unsaved elsewhere without waiting for the reload.
    final products = _products?.where(state.isSaved).toList();
    return Scaffold(
      appBar: TabHeader(eyebrow: 'Your favorites', title: 'Saved', count: products?.length),
      body: products == null
          ? (_error != null ? LoadError(error: _error, onRetry: _load, title: 'Could not load saved items') : const Loader())
          : products.isEmpty
              ? EmptyState(
                  icon: Icons.favorite_border,
                  title: 'Nothing saved yet',
                  message: 'Tap the heart on any product to keep it here for later.',
                  actionLabel: 'Browse products',
                  onAction: () => ShopActions.showTab(context, 0),
                )
              : RefreshIndicator(
                  color: ZayloColors.ink,
                  onRefresh: _load,
                  child: GridView.builder(
                    physics: const AlwaysScrollableScrollPhysics(),
                    padding: EdgeInsets.fromLTRB(
                        ZayloSpace.page, ZayloSpace.page, ZayloSpace.page, ZayloSpace.page + MediaQuery.paddingOf(context).bottom),
                    gridDelegate: productGridDelegate,
                    itemCount: products.length,
                    itemBuilder: (context, index) => ProductCard(product: products[index]),
                  ),
                ),
    );
  }
}
