import 'package:flutter/material.dart';

import 'package:zaylo_mobile/api/api_exception.dart';
import 'package:zaylo_mobile/components/buttons.dart';
import 'package:zaylo_mobile/components/cart_icon.dart';
import 'package:zaylo_mobile/components/feedback.dart';
import 'package:zaylo_mobile/components/layout.dart';
import 'package:zaylo_mobile/features/buyer/checkout/checkout_screen.dart';
import 'package:zaylo_mobile/features/buyer/widgets/shop_actions.dart';
import 'package:zaylo_mobile/features/buyer/widgets/signed_out_prompt.dart';
import 'package:zaylo_mobile/features/buyer/widgets/tab_header.dart';
import 'package:zaylo_mobile/models/buyer/cart_models.dart';
import 'package:zaylo_mobile/state/app_state.dart';
import 'package:zaylo_mobile/theme/zaylo_theme.dart';
import 'package:zaylo_mobile/utils/formatters.dart';

/// The Bag tab. Mirrors the web cart: per-item selection, select all,
/// remove selected and checkout of only the selected items.
class BagScreen extends StatefulWidget {
  const BagScreen({super.key});

  @override
  State<BagScreen> createState() => _BagScreenState();
}

class _BagScreenState extends State<BagScreen> {
  /// Unchecked item ids; everything else is selected, as on the web.
  final Set<int> _unselected = {};
  final Set<int> _busy = {};
  bool _loading = false;
  bool _removing = false;
  Object? _error;
  int? _lastUserId;

  AppState get _state => AppScope.read(context);

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    // Refresh when a different buyer signs in, or on first sign-in.
    final userId = AppScope.of(context).user?.id;
    if (userId != null && userId != _lastUserId) {
      _lastUserId = userId;
      _unselected.clear();
      WidgetsBinding.instance.addPostFrameCallback((_) => _refresh());
    }
  }

  Future<void> _refresh() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      await _state.refreshCart();
    } on ApiException catch (error) {
      if (mounted) setState(() => _error = error);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _mutate(int itemId, Future<CartSummary> Function() action, {String? done}) async {
    setState(() => _busy.add(itemId));
    try {
      _state.setCart(await action());
      if (mounted && done != null) showMessage(context, done);
    } on ApiException catch (error) {
      if (mounted) showMessage(context, error.message);
    } finally {
      if (mounted) setState(() => _busy.remove(itemId));
    }
  }

  List<CartItem> _selected(CartSummary cart) => cart.items.where((item) => !_unselected.contains(item.id)).toList();

  Future<void> _removeSelected(List<CartItem> selected) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text('Remove ${selected.length} ${selected.length == 1 ? 'item' : 'items'}?'),
        content: const Text('They will be taken out of your bag. You can add them again anytime.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Cancel')),
          TextButton(
            onPressed: () => Navigator.pop(context, true),
            style: TextButton.styleFrom(foregroundColor: ZayloColors.danger),
            child: const Text('Remove'),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;
    setState(() => _removing = true);
    try {
      _state.setCart(await _state.api.removeCartItems(selected.map((item) => item.id).toList()));
      if (mounted) showMessage(context, 'Selected products removed from your bag.');
    } on ApiException catch (error) {
      if (mounted) showMessage(context, error.message);
    } finally {
      if (mounted) setState(() => _removing = false);
    }
  }

  void _checkout(List<CartItem> selected) {
    final tooMany = selected.where((item) => item.quantity > item.availableStock).toList();
    if (tooMany.isNotEmpty) {
      showMessage(context,
          'Only ${tooMany.first.availableStock} of ${tooMany.first.product?.name ?? 'an item'} left. Lower the quantity first.');
      return;
    }
    Navigator.of(context).push(MaterialPageRoute(
      builder: (_) => CheckoutScreen(cartItemIds: selected.map((item) => item.id).toList()),
    ));
  }

  @override
  Widget build(BuildContext context) {
    final state = AppScope.of(context);
    if (!state.signedIn) {
      return const SignedOutPrompt(
        iconWidget: CartIcon(size: 34),
        heading: 'Bag',
        eyebrow: 'Ready for checkout',
        title: 'Your bag',
        message: 'Sign in to add products to your bag and check out.',
      );
    }
    final cart = state.cart;
    // Forget selections for items that no longer exist.
    _unselected.removeWhere((id) => !cart.items.any((item) => item.id == id));
    final selected = _selected(cart);
    final allSelected = cart.items.isNotEmpty && selected.length == cart.items.length;
    final selectedQty = selected.fold<int>(0, (sum, item) => sum + item.quantity);
    final selectedTotal = selected.fold<double>(0, (sum, item) => sum + item.lineTotal);

    return Scaffold(
      appBar: TabHeader(eyebrow: 'Ready for checkout', title: 'Bag', count: cart.count),
      bottomNavigationBar: cart.items.isEmpty
          ? null
          : Container(
              decoration: BoxDecoration(
                color: ZayloColors.paper,
                border: Border(top: BorderSide(color: ZayloColors.line)),
              ),
              padding: const EdgeInsets.fromLTRB(ZayloSpace.page, 10, ZayloSpace.page, 10),
              // Sits above the floating nav bar.
              child: SafeArea(
                top: false,
                child: Row(children: [
                  Expanded(
                    child:
                        Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Text('Selected items ($selectedQty)',
                          style: TextStyle(fontSize: 12, color: ZayloColors.muted)),
                      Text(peso(selectedTotal),
                          style: TextStyle(fontSize: 19, fontWeight: FontWeight.w800, color: ZayloColors.ink)),
                      Text('Shipping calculated at checkout',
                          style: TextStyle(fontSize: 10, color: ZayloColors.taupe)),
                    ]),
                  ),
                  SizedBox(
                    width: 170,
                    child: PrimaryButton(
                      label: 'CHECKOUT (${selected.length})',
                      onPressed: selected.isEmpty ? null : () => _checkout(selected),
                    ),
                  ),
                ]),
              ),
            ),
      body: cart.items.isEmpty
          ? (_error != null
              ? LoadError(error: _error, onRetry: _refresh, title: 'Could not load your bag')
              : _loading
                  ? const Loader()
                  : EmptyState(
                      iconWidget: CartIcon(size: 34),
                      title: 'Your bag is empty',
                      message: 'Find something you love and it will wait for you here.',
                      actionLabel: 'Start shopping',
                      onAction: () => ShopActions.showTab(context, 0),
                    ))
          : RefreshIndicator(
              color: ZayloColors.ink,
              onRefresh: _refresh,
              child: ListView(
                padding: const EdgeInsets.fromLTRB(ZayloSpace.page, 4, ZayloSpace.page, 24),
                children: [
                  Row(children: [
                    Checkbox(
                      value: allSelected,
                      activeColor: ZayloColors.ink,
                      onChanged: (value) => setState(() {
                        value == true ? _unselected.clear() : _unselected.addAll(cart.items.map((item) => item.id));
                      }),
                    ),
                    Expanded(
                      child: Text('Select all (${cart.items.length})',
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: TextStyle(fontWeight: FontWeight.w600, color: ZayloColors.ink)),
                    ),
                    TextButton.icon(
                      onPressed: selected.isEmpty || _removing ? null : () => _removeSelected(selected),
                      icon: const Icon(Icons.delete_outline, size: 18),
                      label: const Text('Remove'),
                      style: TextButton.styleFrom(foregroundColor: ZayloColors.danger),
                    ),
                  ]),
                  if (_removing) LinearProgressIndicator(color: ZayloColors.ink),
                  for (final group in _bySeller(cart.items).entries) ...[
                    const SizedBox(height: 10),
                    Row(children: [
                      Icon(Icons.storefront_outlined, size: 16, color: ZayloColors.muted),
                      const SizedBox(width: 6),
                      Text(group.key,
                          style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: ZayloColors.ink)),
                    ]),
                    const SizedBox(height: 8),
                    for (final item in group.value)
                      Padding(
                        padding: const EdgeInsets.only(bottom: 10),
                        child: _BagItemTile(
                          item: item,
                          selected: !_unselected.contains(item.id),
                          busy: _busy.contains(item.id),
                          onSelected: (value) => setState(() {
                            value ? _unselected.remove(item.id) : _unselected.add(item.id);
                          }),
                          onQuantity: (quantity) =>
                              _mutate(item.id, () => _state.api.updateCartItem(item.id, quantity)),
                          onRemove: () => _mutate(item.id, () => _state.api.removeCartItem(item.id),
                              done: 'Removed from your bag.'),
                        ),
                      ),
                  ],
                ],
              ),
            ),
    );
  }

  Map<String, List<CartItem>> _bySeller(List<CartItem> items) {
    final groups = <String, List<CartItem>>{};
    for (final item in items) {
      groups.putIfAbsent(item.sellerName ?? 'ZAYLO seller', () => []).add(item);
    }
    return groups;
  }
}

class _BagItemTile extends StatelessWidget {
  const _BagItemTile({
    required this.item,
    required this.selected,
    required this.busy,
    required this.onSelected,
    required this.onQuantity,
    required this.onRemove,
  });

  final CartItem item;
  final bool selected;
  final bool busy;
  final ValueChanged<bool> onSelected;
  final ValueChanged<int> onQuantity;
  final VoidCallback onRemove;

  @override
  Widget build(BuildContext context) {
    final product = item.product;
    final overStock = item.quantity > item.availableStock;
    return Dismissible(
      key: ValueKey('bag-${item.id}'),
      direction: busy ? DismissDirection.none : DismissDirection.endToStart,
      background: Container(
        alignment: Alignment.centerRight,
        padding: const EdgeInsets.only(right: 22),
        decoration: BoxDecoration(color: Colors.redAccent, borderRadius: BorderRadius.circular(14)),
        child: const Icon(Icons.delete_outline, color: Colors.white),
      ),
      confirmDismiss: (_) async {
        onRemove();
        return false; // The list rebuilds from the server response.
      },
      child: SectionCard(
        padding: const EdgeInsets.fromLTRB(4, 10, 12, 10),
        child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Checkbox(value: selected, activeColor: ZayloColors.ink, onChanged: (value) => onSelected(value ?? false)),
          InkWell(
            onTap: product == null ? null : () => ShopActions.openProduct(context, product),
            child: ClipRRect(
              borderRadius: BorderRadius.circular(9),
              child: SizedBox(width: 76, height: 76, child: NetworkImageBox(url: product?.imageUrl)),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Expanded(
                  child: Text(product?.name ?? 'Product',
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(fontWeight: FontWeight.w600, color: ZayloColors.ink, fontSize: 14)),
                ),
                InkWell(
                  onTap: busy ? null : onRemove,
                  child: Padding(
                    padding: EdgeInsets.only(left: 6),
                    child: Icon(Icons.close, size: 18, color: ZayloColors.muted, semanticLabel: 'Remove'),
                  ),
                ),
              ]),
              if (item.variantName != null)
                Text(item.variantName!, style: TextStyle(fontSize: 12, color: ZayloColors.muted)),
              const SizedBox(height: 4),
              Text(peso(item.unitPrice), style: TextStyle(fontWeight: FontWeight.w800, color: ZayloColors.ink)),
              if (overStock)
                Text('Only ${item.availableStock} left',
                    style: const TextStyle(fontSize: 12, color: Colors.redAccent, fontWeight: FontWeight.w600)),
              const SizedBox(height: 8),
              // Wraps the total under the stepper on narrow phones.
              Wrap(spacing: 12, runSpacing: 8, crossAxisAlignment: WrapCrossAlignment.center, children: [
                QuantityStepper(
                  value: item.quantity,
                  max: item.availableStock.clamp(1, 99),
                  enabled: !busy,
                  onChanged: onQuantity,
                ),
                if (busy)
                  SizedBox(
                      width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: ZayloColors.ink))
                else
                  Text(peso(item.lineTotal),
                      style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: ZayloColors.ink)),
              ]),
            ]),
          ),
        ]),
      ),
    );
  }
}
