import 'package:flutter/material.dart';

import 'package:zaylo_mobile/api/api_exception.dart';
import 'package:zaylo_mobile/api/buyer_api.dart';
import 'package:zaylo_mobile/components/buttons.dart';
import 'package:zaylo_mobile/components/chat/delivery_chat.dart';
import 'package:zaylo_mobile/components/complaints.dart';
import 'package:zaylo_mobile/components/feedback.dart';
import 'package:zaylo_mobile/components/layout.dart';
import 'package:zaylo_mobile/components/star_rating.dart';
import 'package:zaylo_mobile/features/buyer/orders/complaint_form_screen.dart';
import 'package:zaylo_mobile/features/buyer/orders/review_form_screen.dart';
import 'package:zaylo_mobile/features/buyer/orders/status_pill.dart';
import 'package:zaylo_mobile/features/buyer/widgets/shop_actions.dart';
import 'package:zaylo_mobile/models/buyer/catalog_models.dart';
import 'package:zaylo_mobile/models/buyer/order_models.dart';
import 'package:zaylo_mobile/models/support_models.dart';
import 'package:zaylo_mobile/state/app_state.dart';
import 'package:zaylo_mobile/theme/zaylo_theme.dart';
import 'package:zaylo_mobile/utils/formatters.dart';

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
  bool _confirming = false;

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

  /// Opens the chat with the rider, then reloads so the unread badge clears.
  Future<void> _openRiderChat(Order order) async {
    final api = _api;
    await Navigator.of(context).push(MaterialPageRoute(
      builder: (_) => DeliveryChatScreen(
        title: 'Chat with rider',
        load: () => api.orderChat(order.id),
        send: (body, clientId) => api.sendOrderMessage(order.id, body, clientId: clientId),
      ),
    ));
    if (mounted) await _load();
  }

  Future<void> _review(OrderItem item) async {
    final published = await Navigator.of(context).push<bool>(
      MaterialPageRoute(builder: (_) => ReviewFormScreen(item: item)),
    );
    if (published == true && mounted) await _load();
  }

  Future<void> _reportProblem(Order order) async {
    final complaint = await Navigator.of(context).push<Complaint>(
      MaterialPageRoute(builder: (_) => ComplaintFormScreen(order: order)),
    );
    if (complaint == null || !mounted) return;
    await _load();
    if (mounted) await _openComplaint(complaint.id);
  }

  Future<void> _openComplaint(int id) async {
    final api = _api;
    await Navigator.of(context).push(MaterialPageRoute(builder: (_) => ComplaintDetailScreen(api: api, complaintId: id)));
    if (mounted) await _load();
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
            style: TextButton.styleFrom(foregroundColor: ZayloColors.danger),
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

  /// Finishes a delivered order once the buyer has it, which pays the seller.
  Future<void> _confirmReceipt(Order order) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Order received?'),
        content: Text('Confirm that you received ${order.orderNumber}. This completes the order and pays the seller.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Not yet')),
          TextButton(onPressed: () => Navigator.pop(context, true), child: const Text('Yes, I received it')),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;
    setState(() => _confirming = true);
    try {
      final updated = await _api.confirmReceipt(order.id);
      if (!mounted) return;
      setState(() {
        _order = updated;
        _changed = true;
      });
      showMessage(context, 'Thank you! Your order is complete. You can now rate your items.');
    } on ApiException catch (error) {
      if (!mounted) return;
      showMessage(context, error.message);
      _load();
    } finally {
      if (mounted) setState(() => _confirming = false);
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
            ? (_error != null
                ? LoadError(error: _error, onRetry: _load, title: 'Could not load order')
                : const Loader())
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
                            style: TextStyle(fontWeight: FontWeight.w600, color: ZayloColors.ink)),
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
                    if (order.canConfirmReceipt || order.deliveryPhotoUrl != null) ...[
                      const SizedBox(height: 16),
                      _ReceiptCard(
                        order: order,
                        headers: _api.authHeaders,
                        busy: _confirming,
                        onConfirm: order.canConfirmReceipt ? () => _confirmReceipt(order) : null,
                      ),
                    ],
                    if (order.riderChat != null) ...[
                      const SizedBox(height: 16),
                      _RiderChatCard(chat: order.riderChat!, onOpen: () => _openRiderChat(order)),
                    ],
                    const SizedBox(height: 16),
                    const Eyebrow('Items'),
                    const SizedBox(height: 9),
                    SectionCard(
                      padding: const EdgeInsets.fromLTRB(14, 12, 14, 2),
                      child: Column(children: [
                        for (final item in order.items)
                          _ItemRow(item, onReview: item.canReview ? () => _review(item) : null),
                      ]),
                    ),
                    const SizedBox(height: 16),
                    const Eyebrow('Delivery'),
                    const SizedBox(height: 9),
                    SectionCard(
                      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        Text(order.recipientName ?? '',
                            style: TextStyle(fontWeight: FontWeight.w700, color: ZayloColors.ink)),
                        if (order.phone != null) Text(order.phone!, style: const TextStyle(fontSize: 13)),
                        if (order.shippingAddress != null)
                          Text(order.shippingAddress!, style: const TextStyle(fontSize: 13)),
                        if (order.notes != null && order.notes!.isNotEmpty) ...[
                          const SizedBox(height: 8),
                          Text('Note: ${order.notes}',
                              style: const TextStyle(fontSize: 12, fontStyle: FontStyle.italic)),
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
                          SummaryRow('Payment status',
                              order.paymentStatus![0].toUpperCase() + order.paymentStatus!.substring(1)),
                        SummaryRow('Placed', dateTimeLabel(order.placedAt)),
                        Divider(color: ZayloColors.line, height: 20),
                        SummaryRow('Subtotal', peso(order.subtotal)),
                        SummaryRow('Shipping', peso(order.shippingFee + order.shippingDiscount)),
                        if (order.shippingDiscount > 0)
                          SummaryRow('Voucher ${order.voucherCode ?? ''}'.trim(), '-${peso(order.shippingDiscount)}',
                              valueColor: ZayloColors.success),
                        Divider(color: ZayloColors.line, height: 20),
                        SummaryRow('Total', peso(order.total), emphasize: true),
                      ]),
                    ),
                    if (order.complaint != null || order.canComplain) ...[
                      const SizedBox(height: 16),
                      const Eyebrow('Need help?'),
                      const SizedBox(height: 9),
                      _ComplaintCard(
                        complaint: order.complaint,
                        onOpen: order.complaint == null
                            ? () => _reportProblem(order)
                            : () => _openComplaint(order.complaint!.id),
                      ),
                    ],
                    if (order.canCancel) ...[
                      const SizedBox(height: 22),
                      SizedBox(
                        width: double.infinity,
                        child: OutlinedButton(
                          onPressed: _cancelling ? null : () => _cancel(order),
                          style: secondaryButtonStyle.copyWith(
                            foregroundColor: WidgetStatePropertyAll(ZayloColors.danger),
                            side: WidgetStatePropertyAll(BorderSide(color: ZayloColors.danger)),
                            backgroundColor: WidgetStateProperty.resolveWith((states) =>
                                states.contains(WidgetState.pressed) ? ZayloColors.dangerSoft : Colors.transparent),
                          ),
                          child: _cancelling
                              ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2))
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
  const _ItemRow(this.item, {this.onReview});

  final OrderItem item;

  /// Set while the delivered item can still be rated.
  final VoidCallback? onReview;

  Widget _row(BuildContext context) => InkWell(
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
                    style: TextStyle(fontWeight: FontWeight.w600, color: ZayloColors.ink, fontSize: 13)),
                Text('${item.quantity} × ${peso(item.unitPrice)}', style: const TextStyle(fontSize: 12)),
              ]),
            ),
            Text(peso(item.lineTotal), style: TextStyle(fontWeight: FontWeight.w700, color: ZayloColors.ink)),
          ]),
        ),
      );

  @override
  Widget build(BuildContext context) {
    final rating = item.reviewRating;
    if (onReview == null && rating == null) return _row(context);
    return Column(crossAxisAlignment: CrossAxisAlignment.end, children: [
      _row(context),
      Padding(
        padding: const EdgeInsets.only(bottom: 10),
        child: rating != null
            ? Row(mainAxisSize: MainAxisSize.min, children: [
                Text('You rated ', style: TextStyle(fontSize: 12, color: ZayloColors.muted)),
                StarRow(rating: rating.toDouble(), size: 14),
              ])
            : OutlinedButton.icon(
                onPressed: onReview,
                icon: const Icon(Icons.star_outline_rounded, size: 18),
                label: const Text('Rate this item'),
              ),
      ),
    ]);
  }
}

/// Report a problem with the order, or follow the complaint already filed.
class _ComplaintCard extends StatelessWidget {
  const _ComplaintCard({required this.complaint, required this.onOpen});

  final OrderComplaint? complaint;
  final VoidCallback onOpen;

  @override
  Widget build(BuildContext context) {
    final filed = complaint;
    return SectionCard(
      padding: EdgeInsets.zero,
      child: ListTile(
        leading: Icon(Icons.report_gmailerrorred_outlined, color: ZayloColors.ink),
        title: Text(filed == null ? 'Report a problem' : 'Your complaint',
            style: TextStyle(fontWeight: FontWeight.w700, color: ZayloColors.ink)),
        subtitle: Text(
          filed == null
              ? 'Missing, damaged or wrong items, delivery or refund issues'
              : 'Follow the case and reply to the ZAYLO team',
          style: const TextStyle(fontSize: 12),
        ),
        trailing: filed == null
            ? Icon(Icons.chevron_right, color: ZayloColors.muted)
            : ComplaintStatusPill(status: filed.status, label: filed.statusLabel),
        onTap: onOpen,
      ),
    );
  }
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
                if (index < entries.length - 1) Expanded(child: Container(width: 2, color: ZayloColors.line)),
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
                  if (entries[index].note != null) Text(entries[index].note!, style: const TextStyle(fontSize: 12)),
                  Text(dateTimeLabel(entries[index].at), style: TextStyle(fontSize: 11, color: ZayloColors.taupe)),
                ]),
              ),
            ),
          ]),
        ),
    ]);
  }
}

/// The rider delivering the order, with a button to message them.
/// The rider's photo of the delivered parcel and, while the order waits for
/// the buyer, the button that confirms it was received.
class _ReceiptCard extends StatelessWidget {
  const _ReceiptCard({required this.order, required this.headers, required this.busy, this.onConfirm});

  final Order order;

  /// The rider's photo is private to the order, so it loads with the session's token.
  final Map<String, String> headers;
  final bool busy;

  /// Set while the buyer can still confirm the order was received.
  final VoidCallback? onConfirm;

  @override
  Widget build(BuildContext context) => SectionCard(
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          const Eyebrow('Proof of delivery'),
          if (order.deliveryPhotoUrl != null) ...[
            const SizedBox(height: 10),
            ClipRRect(
              borderRadius: BorderRadius.circular(10),
              child: AspectRatio(
                aspectRatio: 4 / 3,
                child: Image.network(
                  order.deliveryPhotoUrl!,
                  headers: headers,
                  fit: BoxFit.cover,
                  semanticLabel: "The rider's photo of your delivered parcel",
                  errorBuilder: (_, __, ___) => Container(
                    color: ZayloColors.sand,
                    alignment: Alignment.center,
                    child: const Text('Could not load the photo', style: TextStyle(fontSize: 12)),
                  ),
                ),
              ),
            ),
          ],
          if (onConfirm != null) ...[
            const SizedBox(height: 12),
            Text('Did you receive your order?', style: TextStyle(fontWeight: FontWeight.w700, color: ZayloColors.ink)),
            const SizedBox(height: 4),
            Text(
              order.autoConfirmAt == null
                  ? 'Confirm it so the seller gets paid.'
                  : 'Confirm it so the seller gets paid. If you don\'t, it is confirmed automatically on '
                      '${dateTimeLabel(order.autoConfirmAt!.toLocal())}. Not received? Report a problem before then.',
              style: const TextStyle(fontSize: 12),
            ),
            const SizedBox(height: 12),
            SizedBox(
              width: double.infinity,
              child: FilledButton(
                onPressed: busy ? null : onConfirm,
                style: primaryButtonStyle,
                child: busy
                    ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2))
                    : const Text('ORDER RECEIVED'),
              ),
            ),
          ],
        ]),
      );
}

class _RiderChatCard extends StatelessWidget {
  const _RiderChatCard({required this.chat, required this.onOpen});

  final RiderChatSummary chat;
  final VoidCallback onOpen;

  @override
  Widget build(BuildContext context) => SectionCard(
        child: Row(children: [
          CircleAvatar(
            radius: 22,
            backgroundColor: ZayloColors.sand,
            child: Icon(Icons.two_wheeler_outlined, color: ZayloColors.ink),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              const Eyebrow('Your rider'),
              const SizedBox(height: 2),
              Text(chat.riderName, style: TextStyle(fontWeight: FontWeight.w700, color: ZayloColors.ink)),
              Text(chat.open ? 'Message them about your delivery' : 'Chat closed · view messages',
                  style: const TextStyle(fontSize: 12)),
            ]),
          ),
          const SizedBox(width: 8),
          FilledButton.icon(
            onPressed: onOpen,
            icon: Badge(
              isLabelVisible: chat.unread > 0,
              label: Text('${chat.unread}'),
              child: const Icon(Icons.chat_bubble_outline, size: 18),
            ),
            label: const Text('Chat'),
          ),
        ]),
      );
}
