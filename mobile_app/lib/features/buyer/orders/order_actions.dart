import 'package:flutter/material.dart';

import 'package:zaylo_mobile/api/api_exception.dart';
import 'package:zaylo_mobile/components/buttons.dart';
import 'package:zaylo_mobile/components/feedback.dart';
import 'package:zaylo_mobile/features/buyer/orders/complaint_form_screen.dart';
import 'package:zaylo_mobile/features/buyer/orders/review_form_screen.dart';
import 'package:zaylo_mobile/features/buyer/widgets/shop_actions.dart';
import 'package:zaylo_mobile/models/buyer/order_models.dart';
import 'package:zaylo_mobile/state/app_state.dart';
import 'package:zaylo_mobile/theme/zaylo_theme.dart';
import 'package:zaylo_mobile/utils/formatters.dart';

/// What can be done with an order, right on its card: Order received, Cancel (with a reason), Return / Refund,
/// To review and Buy again. Once the seller confirmed the order, Cancel stays visible but disabled, and tapping it
/// says why. [onChanged] runs after an action changed the order so the screen reloads it.
class OrderActions extends StatefulWidget {
  const OrderActions({
    super.key,
    required this.order,
    required this.onChanged,
    this.reasons = CancelReason.fallback,
    this.showReceipt = true,
    this.showReturn = true,
  });

  final Order order;
  final ValueChanged<Order?> onChanged;
  final List<CancelReason> reasons;

  /// The detail screen shows these in its own cards.
  final bool showReceipt;
  final bool showReturn;

  /// Whether the card has any action to show.
  static bool hasAny(Order order) =>
      (order.gcash?.canSubmit ?? false) ||
      order.canConfirmReceipt ||
      order.canCancel ||
      order.cancelLockedReason != null ||
      order.canReturn ||
      order.itemToReview != null ||
      order.canBuyAgain;

  @override
  State<OrderActions> createState() => _OrderActionsState();
}

class _OrderActionsState extends State<OrderActions> {
  String? _busy;

  Future<void> _run(String action, Future<Order?> Function() task, {String? done}) async {
    setState(() => _busy = action);
    try {
      final updated = await task();
      if (!mounted) return;
      if (done != null) showMessage(context, done);
      widget.onChanged(updated);
    } on ApiException catch (error) {
      if (!mounted) return;
      showMessage(context, error.message);
      // The order may have moved on (e.g. the seller confirmed it meanwhile): show its current state.
      widget.onChanged(null);
    } finally {
      if (mounted) setState(() => _busy = null);
    }
  }

  Future<void> _receive() async {
    final order = widget.order;
    final yes = await showDialog<bool>(
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
    if (yes != true || !mounted) return;
    final api = AppScope.read(context).api;
    await _run('receive', () => api.confirmReceipt(order.id),
        done: 'Thank you! Your order is complete. You can now rate your items.');
  }

  Future<void> _cancel() async {
    final choice = await askCancelReason(context, widget.order, widget.reasons);
    if (choice == null || !mounted) return;
    final api = AppScope.read(context).api;
    await _run('cancel', () => api.cancelOrder(widget.order.id, reason: choice.reason, note: choice.note),
        done: 'Order cancelled and stock restored. You can buy it again from Cancelled.');
  }

  /// GCash paid to the seller: the buyer types the reference number from their GCash receipt.
  Future<void> _gcashReference() async {
    final gcash = widget.order.gcash!;
    final reference = await showModalBottomSheet<String>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      backgroundColor: ZayloColors.white,
      builder: (_) => _GcashReferenceSheet(gcash: gcash),
    );
    if (reference == null || !mounted) return;
    final api = AppScope.read(context).api;
    setState(() => _busy = 'gcash');
    try {
      final message = await api.submitGcashReference(widget.order.id, reference);
      if (!mounted) return;
      showMessage(context, message, duration: const Duration(seconds: 4));
      widget.onChanged(null);
    } on ApiException catch (error) {
      if (mounted) showMessage(context, error.message);
    } finally {
      if (mounted) setState(() => _busy = null);
    }
  }

  Future<void> _buyAgain() async {
    final api = AppScope.read(context).api;
    setState(() => _busy = 'again');
    try {
      final message = await api.buyAgain(widget.order.id);
      if (!mounted) return;
      showMessage(context, message, actionLabel: 'VIEW BAG', onAction: () => ShopActions.showTab(context, 2),
          duration: const Duration(seconds: 5));
    } on ApiException catch (error) {
      if (mounted) showMessage(context, error.message);
    } finally {
      if (mounted) setState(() => _busy = null);
    }
  }

  Future<void> _open(Widget screen) async {
    await Navigator.of(context).push<bool>(MaterialPageRoute(builder: (_) => screen));
    // A review or complaint changes what the card offers next.
    if (mounted) widget.onChanged(null);
  }

  @override
  Widget build(BuildContext context) {
    final order = widget.order;
    final busy = _busy != null;
    final review = order.itemToReview;
    final buttons = <Widget>[
      if (order.gcash?.canSubmit ?? false)
        _ActionButton(
            label: order.gcash!.status == 'rejected' ? 'Re-enter GCash ref.' : 'Enter GCash ref.',
            icon: Icons.receipt_long_outlined,
            primary: true,
            busy: _busy == 'gcash',
            onPressed: busy ? null : _gcashReference),
      if (widget.showReceipt && order.canConfirmReceipt)
        _ActionButton(
            label: 'Order received', icon: Icons.check_circle_outline, primary: true, busy: _busy == 'receive', onPressed: busy ? null : _receive),
      if (order.canCancel)
        _ActionButton(label: 'Cancel', icon: Icons.close, danger: true, busy: _busy == 'cancel', onPressed: busy ? null : _cancel),
      if (order.cancelLockedReason case final why?)
        // Disabled, but a tap still explains why (a disabled button cannot be tapped itself).
        Tooltip(
          message: why,
          child: GestureDetector(
            behavior: HitTestBehavior.opaque,
            onTap: () => showMessage(context, why, duration: const Duration(seconds: 4)),
            child: Semantics(
              hint: why,
              child: const _ActionButton(label: 'Cancel', icon: Icons.lock_outline, danger: true, onPressed: null),
            ),
          ),
        ),
      if (widget.showReturn && order.canReturn)
        _ActionButton(
            label: 'Return / Refund', icon: Icons.assignment_return_outlined, onPressed: busy ? null : () => _open(ComplaintFormScreen(order: order))),
      if (review != null)
        _ActionButton(label: 'To review', icon: Icons.star_outline, primary: true, onPressed: busy ? null : () => _open(ReviewFormScreen(item: review))),
      if (order.canBuyAgain)
        _ActionButton(label: 'Buy again', icon: Icons.replay, busy: _busy == 'again', onPressed: busy ? null : _buyAgain),
    ];
    final gcash = order.gcash;
    final gcashNote = gcash != null && order.status != 'cancelled' && gcash.status != 'paid'
        ? Padding(
            padding: const EdgeInsets.only(bottom: 8),
            child: Text(
              'GCash · ${gcash.statusLabel}${gcash.reference == null ? '' : ' (ref. ${gcash.reference})'}'
              '${gcash.rejectionNote == null ? '' : '\nSeller: ${gcash.rejectionNote}'}',
              style: TextStyle(fontSize: 12, color: gcash.status == 'rejected' ? ZayloColors.danger : ZayloColors.muted),
            ),
          )
        : null;
    if (buttons.isEmpty) return gcashNote ?? const SizedBox.shrink();
    return Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
      if (gcashNote != null) gcashNote,
      Wrap(alignment: WrapAlignment.end, spacing: 8, runSpacing: 8, children: buttons),
    ]);
  }
}

class _ActionButton extends StatelessWidget {
  const _ActionButton({
    required this.label,
    required this.icon,
    required this.onPressed,
    this.primary = false,
    this.danger = false,
    this.busy = false,
  });

  final String label;
  final IconData icon;
  final VoidCallback? onPressed;
  final bool primary;
  final bool danger;
  final bool busy;

  @override
  Widget build(BuildContext context) {
    final child = busy
        ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
        : Row(mainAxisSize: MainAxisSize.min, children: [Icon(icon, size: 16), const SizedBox(width: 6), Text(label)]);
    final compact = ButtonStyle(
      minimumSize: const WidgetStatePropertyAll(Size(0, 38)),
      padding: const WidgetStatePropertyAll(EdgeInsets.symmetric(horizontal: 14)),
      textStyle: const WidgetStatePropertyAll(TextStyle(fontSize: 12.5, fontWeight: FontWeight.w700)),
    );
    if (primary) {
      return FilledButton(onPressed: onPressed, style: primaryButtonStyle.merge(compact), child: child);
    }
    return OutlinedButton(
      onPressed: onPressed,
      style: (danger
              ? secondaryButtonStyle.copyWith(
                  foregroundColor: WidgetStateProperty.resolveWith(
                      (states) => states.contains(WidgetState.disabled) ? ZayloColors.danger.withValues(alpha: .45) : ZayloColors.danger),
                  side: WidgetStateProperty.resolveWith((states) => BorderSide(
                      color: states.contains(WidgetState.disabled) ? ZayloColors.danger.withValues(alpha: .3) : ZayloColors.danger)),
                )
              : secondaryButtonStyle)
          .merge(compact),
      child: child,
    );
  }
}

/// Asks why the buyer cancels [order]: one of [reasons], with a note required for "Other reason".
Future<({String reason, String? note})?> askCancelReason(BuildContext context, Order order, List<CancelReason> reasons) =>
    showModalBottomSheet<({String reason, String? note})>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      backgroundColor: ZayloColors.white,
      builder: (_) => _CancelSheet(order: order, reasons: reasons),
    );

class _CancelSheet extends StatefulWidget {
  const _CancelSheet({required this.order, required this.reasons});

  final Order order;
  final List<CancelReason> reasons;

  @override
  State<_CancelSheet> createState() => _CancelSheetState();
}

class _CancelSheetState extends State<_CancelSheet> {
  String? _reason;
  final _note = TextEditingController();
  String? _error;

  @override
  void dispose() {
    _note.dispose();
    super.dispose();
  }

  void _submit() {
    if (_reason == null) {
      setState(() => _error = 'Choose why you are cancelling.');
      return;
    }
    if (_reason == 'other' && _note.text.trim().length < 3) {
      setState(() => _error = 'Tell us the reason in a few words.');
      return;
    }
    Navigator.pop(context, (reason: _reason!, note: _note.text.trim().isEmpty ? null : _note.text.trim()));
  }

  @override
  Widget build(BuildContext context) => SafeArea(
        child: Padding(
          padding: EdgeInsets.fromLTRB(20, 0, 20, 16 + MediaQuery.viewInsetsOf(context).bottom),
          child: SingleChildScrollView(
            child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text('Cancel ${widget.order.orderNumber}?',
                  style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w700)),
              const SizedBox(height: 4),
              const Text('Its items go back on sale. You can buy them again from Cancelled.', style: TextStyle(fontSize: 13)),
              const SizedBox(height: 12),
              Text('Why are you cancelling?', style: TextStyle(fontWeight: FontWeight.w700, color: ZayloColors.ink)),
              RadioGroup<String>(
                groupValue: _reason,
                onChanged: (value) => setState(() {
                  _reason = value;
                  _error = null;
                }),
                child: Column(children: [
                  for (final reason in widget.reasons)
                    RadioListTile<String>(
                      value: reason.key,
                      title: Text(reason.label, style: const TextStyle(fontSize: 14)),
                      contentPadding: EdgeInsets.zero,
                      dense: true,
                      visualDensity: VisualDensity.compact,
                    ),
                ]),
              ),
              TextField(
                controller: _note,
                maxLength: 255,
                minLines: 1,
                maxLines: 3,
                decoration: InputDecoration(
                  labelText: _reason == 'other' ? 'Tell us the reason' : 'Tell us more (optional)',
                  counterText: '',
                ),
              ),
              if (_error != null)
                Padding(
                  padding: const EdgeInsets.only(top: 8),
                  child: Text(_error!, style: TextStyle(color: ZayloColors.danger, fontSize: 13)),
                ),
              const SizedBox(height: 16),
              SizedBox(
                width: double.infinity,
                child: FilledButton(
                  onPressed: _submit,
                  style: primaryButtonStyle.copyWith(backgroundColor: WidgetStatePropertyAll(ZayloColors.danger)),
                  child: const Text('CANCEL ORDER'),
                ),
              ),
              TextButton(onPressed: () => Navigator.pop(context), child: const Text('Keep my order')),
            ]),
          ),
        ),
      );
}

/// Where to send the money and the 13-digit reference number from the GCash receipt.
class _GcashReferenceSheet extends StatefulWidget {
  const _GcashReferenceSheet({required this.gcash});

  final OrderGcash gcash;

  @override
  State<_GcashReferenceSheet> createState() => _GcashReferenceSheetState();
}

class _GcashReferenceSheetState extends State<_GcashReferenceSheet> {
  final _reference = TextEditingController();
  String? _error;

  @override
  void dispose() {
    _reference.dispose();
    super.dispose();
  }

  void _submit() {
    final digits = _reference.text.replaceAll(RegExp(r'\s'), '');
    if (!RegExp(r'^\d{13}$').hasMatch(digits)) {
      setState(() => _error = 'Enter the 13-digit reference number from your GCash receipt.');
      return;
    }
    Navigator.pop(context, digits);
  }

  @override
  Widget build(BuildContext context) {
    final gcash = widget.gcash;
    return SafeArea(
      child: Padding(
        padding: EdgeInsets.fromLTRB(20, 0, 20, 16 + MediaQuery.viewInsetsOf(context).bottom),
        child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text('GCash reference', style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w700)),
          const SizedBox(height: 6),
          Text(
            'Send ${peso(gcash.amount)}${gcash.sellerNumber == null ? '' : ' to GCash ${gcash.sellerNumber} (${gcash.sellerName})'}, '
            'then type the reference number from your GCash receipt.',
            style: const TextStyle(fontSize: 13),
          ),
          if (gcash.rejectionNote != null) ...[
            const SizedBox(height: 8),
            Text("Seller's note: ${gcash.rejectionNote}", style: TextStyle(fontSize: 13, color: ZayloColors.danger)),
          ],
          const SizedBox(height: 12),
          TextField(
            controller: _reference,
            autofocus: true,
            keyboardType: TextInputType.number,
            maxLength: 25,
            decoration: InputDecoration(labelText: 'Reference no.', hintText: 'e.g. 1234 567 890123', counterText: '', errorText: _error),
            onSubmitted: (_) => _submit(),
          ),
          const SizedBox(height: 14),
          SizedBox(width: double.infinity, child: FilledButton(onPressed: _submit, style: primaryButtonStyle, child: const Text('SEND REFERENCE'))),
        ]),
      ),
    );
  }
}
