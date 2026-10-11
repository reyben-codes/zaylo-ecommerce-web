import 'package:flutter/material.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/theme/zaylo_theme.dart';
import '../../../core/utils/formatters.dart';
import '../../../core/widgets/buttons.dart';
import '../../../core/widgets/feedback.dart';
import '../../../core/widgets/layout.dart';
import '../../api/buyer_api.dart';
import '../../models/catalog_models.dart';
import '../../models/order_models.dart';
import '../../state/app_state.dart';
import '../../widgets/shop_actions.dart';
import 'status_pill.dart';

class OrderDetailScreen extends StatefulWidget {
  const OrderDetailScreen({super.key, required this.orderId});

  final int orderId;

  @override
  State<OrderDetailScreen> createState() => _OrderDetailScreenState();
}

class _OrderDetailScreenState extends State<OrderDetailScreen> {
  Order? _order;
  Object? _error;
  bool _cancelling = false;

  /// Tells the list to refresh when we pop after a cancel.
  bool _changed = false;

  BuyerApi get _api => AppScope.read(context).api;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _load());
  }

  Future<void> _load() async {
    try {
      final order = await _api.order(widget.orderId);
      if (mounted) {
        setState(() {
          _order = order;
          _error = null;
        });
      }
    } on ApiException catch (error) {
      if (mounted) setState(() => _error = error);
    }
  }

  Future<void> _cancel(Order order) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Cancel this order?'),
        content: Text('${order.orderNumber} will be cancelled and its items returned to stock. This cannot be undone.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Keep order')),
          TextButton(
            onPressed: () => Navigator.pop(context, true),
            style: TextButton.styleFrom(foregroundColor: Colors.redAccent),
            child: const Text('Cancel order'),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;
    setState(() => _cancelling = true);
    try {
      final updated = await _api.cancelOrder(order.id);
      if (!mounted) return;
      setState(() {
        _order = updated;
        _changed = true;
      });
      showMessage(context, 'Order cancelled and stock restored.');
    } on ApiException catch (error) {
      if (!mounted) return;
      showMessage(context, error.message);
      _load();
    } finally {
      if (mounted) setState(() => _cancelling = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final order = _order;
    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop) Navigator.of(context).pop(_changed);
      },
      child: Scaffold(
        appBar: AppBar(title: Text(order?.orderNumber ?? 'Order')),
        body: order == null
            ? (_error != null ? LoadError(error: _error, onRetry: _load, title: 'Could not load order') : const Loader())
            : RefreshIndicator(
                color: ZayloColors.ink,
                onRefresh: _load,
                child: ListView(
                  padding: const EdgeInsets.all(ZayloSpace.page),
                  children: [
                    SectionCard(
                      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        Row(children: [
                          const Expanded(child: Eyebrow('Status')),
                          StatusPill(order),
                        ]),
                        const SizedBox(height: 8),
                        Text(order.statusMessage,
                            style: const TextStyle(fontWeight: FontWeight.w600, color: ZayloColors.ink)),
                        if (order.trackingNumber != null) ...[
                          const SizedBox(height: 6),
                          Text('Tracking number: ${order.trackingNumber}', style: const TextStyle(fontSize: 12)),
                        ],
                        if (order.history.isNotEmpty) ...[
                          const SizedBox(height: 14),
                          _Timeline(order.history),
                        ],
                      ]),
                    ),
                    const SizedBox(height: 16),
                    const Eyebrow('Items'),
                    const SizedBox(height: 9),
                    SectionCard(
                      padding: const EdgeInsets.fromLTRB(14, 12, 14, 2),
                      child: Column(children: [
                        for (final item in order.items) _ItemRow(item),
                      ]),
                    ),
                    const SizedBox(height: 16),
                    const Eyebrow('Delivery'),
                    const SizedBox(height: 9),
                    SectionCard(
                      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        Text(order.recipientName ?? '',
                            style: const TextStyle(fontWeight: FontWeight.w700, color: ZayloColors.ink)),
                        if (order.phone != null) Text(order.phone!, style: const TextStyle(fontSize: 13)),
                        if (order.shippingAddress != null)
                          Text(order.shippingAddress!, style: const TextStyle(fontSize: 13)),
                        if (order.notes != null && order.notes!.isNotEmpty) ...[
                          const SizedBox(height: 8),
                          Text('Note: ${order.notes}', style: const TextStyle(fontSize: 12, fontStyle: FontStyle.italic)),
                        ],
                      ]),
                    ),
                    const SizedBox(height: 16),
                    const Eyebrow('Payment'),
                    const SizedBox(height: 9),
                    SectionCard(
                      child: Column(children: [
                        SummaryRow('Method', order.paymentLabel),
                        if (order.paymentStatus != null)
                          SummaryRow('Payment status', order.paymentStatus![0].toUpperCase() + order.paymentStatus!.substring(1)),
                        SummaryRow('Placed', dateTimeLabel(order.placedAt)),
                        const Divider(color: ZayloColors.line, height: 20),
                        SummaryRow('Subtotal', peso(order.subtotal)),
                        SummaryRow('Shipping', peso(order.shippingFee + order.shippingDiscount)),
                        if (order.shippingDiscount > 0)
                          SummaryRow('Voucher ${order.voucherCode ?? ''}'.trim(), '-${peso(order.shippingDiscount)}',
                              valueColor: const Color(0xFF2E7D32)),
                        const Divider(color: ZayloColors.line, height: 20),
                        SummaryRow('Total', peso(order.total), emphasize: true),
                      ]),
                    ),
                    if (order.canCancel) ...[
                      const SizedBox(height: 22),
                      SizedBox(
                        width: double.infinity,
                        child: OutlinedButton(
                          onPressed: _cancelling ? null : () => _cancel(order),
                          style: secondaryButtonStyle.copyWith(
                            foregroundColor: const WidgetStatePropertyAll(Colors.redAccent),
                            side: const WidgetStatePropertyAll(BorderSide(color: Colors.redAccent)),
                          ),
                          child: _cancelling
                              ? const SizedBox(
                                  height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2))
                              : const Text('CANCEL ORDER'),
                        ),
                      ),
                      const Padding(
                        padding: EdgeInsets.only(top: 8),
                        child: Text('You can cancel until the seller starts preparing your order.',
                            textAlign: TextAlign.center, style: TextStyle(fontSize: 12)),
                      ),
                    ],
                    const SizedBox(height: 12),
                  ],
                ),
              ),
      ),
    );
  }
}

class _ItemRow extends StatelessWidget {
  const _ItemRow(this.item);

  final OrderItem item;

  @override
  Widget build(BuildContext context) => InkWell(
        // Opens the product again, for reordering.
        onTap: item.productSlug == null
            ? null
            : () => ShopActions.openProduct(
                  context,
                  Product(
                    id: 0,
                    slug: item.productSlug!,
                    name: item.productName,
                    categoryLabel: 'ZAYLO',
                    price: item.unitPrice,
                    imageUrl: item.imageUrl,
                  ),
                ),
        child: Padding(
          padding: const EdgeInsets.only(bottom: 10),
          child: Row(children: [
            ClipRRect(
              borderRadius: BorderRadius.circular(8),
              child: SizedBox(width: 56, height: 56, child: NetworkImageBox(url: item.imageUrl)),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(item.productName,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(fontWeight: FontWeight.w600, color: ZayloColors.ink, fontSize: 13)),
                Text('${item.quantity} × ${peso(item.unitPrice)}', style: const TextStyle(fontSize: 12)),
              ]),
            ),
            Text(peso(item.lineTotal), style: const TextStyle(fontWeight: FontWeight.w700, color: ZayloColors.ink)),
          ]),
        ),
      );
}

class _Timeline extends StatelessWidget {
  const _Timeline(this.history);

  final List<OrderHistoryEntry> history;

  @override
  Widget build(BuildContext context) {
    // Newest first, like a delivery tracker.
    final entries = history.reversed.toList();
    return Column(children: [
      for (var index = 0; index < entries.length; index++)
        IntrinsicHeight(
          child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
            SizedBox(
              width: 22,
              child: Column(children: [
                Container(
                  margin: const EdgeInsets.only(top: 3),
                  width: 11,
                  height: 11,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    color: index == 0 ? orderStatusColor(entries[index].status) : ZayloColors.line,
                  ),
                ),
                if (index < entries.length - 1)
                  Expanded(child: Container(width: 2, color: ZayloColors.line)),
              ]),
            ),
            const SizedBox(width: 8),
            Expanded(
              child: Padding(
                padding: const EdgeInsets.only(bottom: 14),
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text(entries[index].label,
                      style: TextStyle(
                          fontWeight: index == 0 ? FontWeight.w800 : FontWeight.w600,
                          color: ZayloColors.ink,
                          fontSize: 13)),
                  if (entries[index].note != null)
                    Text(entries[index].note!, style: const TextStyle(fontSize: 12)),
                  Text(dateTimeLabel(entries[index].at),
                      style: const TextStyle(fontSize: 11, color: ZayloColors.taupe)),
                ]),
              ),
            ),
          ]),
        ),
    ]);
  }
}
