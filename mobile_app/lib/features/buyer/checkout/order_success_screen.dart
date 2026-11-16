import 'package:flutter/material.dart';

import 'package:zaylo_mobile/components/buttons.dart';
import 'package:zaylo_mobile/components/layout.dart';
import 'package:zaylo_mobile/features/buyer/orders/orders_screen.dart';
import 'package:zaylo_mobile/features/buyer/orders/gateway_payment.dart';
import 'package:zaylo_mobile/features/buyer/widgets/shop_actions.dart';
import 'package:zaylo_mobile/models/buyer/order_models.dart';
import 'package:zaylo_mobile/state/app_state.dart';
import 'package:zaylo_mobile/theme/zaylo_theme.dart';
import 'package:zaylo_mobile/utils/formatters.dart';

/// Checkout receipt. GCash remains pending until the server confirms payment.
class OrderSuccessScreen extends StatefulWidget {
  const OrderSuccessScreen({super.key, required this.orders, required this.paymentMethod});

  final List<Order> orders;
  final String paymentMethod;

  @override
  State<OrderSuccessScreen> createState() => _OrderSuccessScreenState();
}

class _OrderSuccessScreenState extends State<OrderSuccessScreen> with WidgetsBindingObserver {
  late List<Order> orders = widget.orders;
  bool _checking = false;
  String? _checkError;
  String get paymentMethod => widget.paymentMethod;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    if (paymentMethod == 'gcash') {
      WidgetsBinding.instance.addPostFrameCallback((_) => _refreshPayments());
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed && paymentMethod == 'gcash') _refreshPayments();
  }

  Future<void> _refreshPayments() async {
    if (!mounted || _checking) return;
    setState(() { _checking = true; _checkError = null; });
    try {
      final api = AppScope.read(context).api;
      final updated = await Future.wait(orders.map((order) => api.order(order.id)));
      if (mounted) setState(() => orders = updated);
    } catch (_) {
      if (mounted) setState(() => _checkError = 'Unable to verify payment. Check again or open My Orders.');
    } finally {
      if (mounted) setState(() => _checking = false);
    }
  }

  Future<void> _pay(Order order) async {
    await openGatewayPayment(context, order.id);
    if (mounted) await _refreshPayments();
  }

  @override
  Widget build(BuildContext context) {
    final total = orders.fold<double>(0, (sum, order) => sum + order.total);
    final pendingPayment = paymentMethod == 'gcash' && orders.any((order) => !order.isPaymentConfirmed);
    return Scaffold(
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.fromLTRB(24, 48, 24, 24),
          children: [
            CircleAvatar(
              radius: 40,
              backgroundColor: ZayloColors.ink,
              child: Icon(pendingPayment ? Icons.account_balance_wallet_outlined : Icons.check_rounded,
                  color: ZayloColors.white, size: 40),
            ),
            const SizedBox(height: 22),
            Text(pendingPayment ? 'Payment required' : 'Order placed',
                textAlign: TextAlign.center, style: Theme.of(context).textTheme.displaySmall),
            const SizedBox(height: 10),
            Text(
              paymentMethod == 'gcash'
                  ? (pendingPayment
                      ? 'Complete payment to place your order. Unpaid checkouts stay in To Pay and cannot be prepared or shipped.'
                      : 'Payment confirmed. Your order is now To Ship. Funds remain held until delivery confirmation.')
                  : 'Thank you! Please prepare ${peso(total)} for cash on delivery.',
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.bodyMedium,
            ),
            if (orders.length > 1)
              Padding(
                padding: const EdgeInsets.only(top: 8),
                child: Text(
                  'Your items come from ${orders.length} sellers, so they ship as ${orders.length} separate orders.',
                  textAlign: TextAlign.center,
                  style: TextStyle(fontSize: 12, color: ZayloColors.muted),
                ),
              ),
            const SizedBox(height: 26),
            for (final order in orders)
              Padding(
                padding: const EdgeInsets.only(bottom: 10),
                child: SectionCard(
                  child: Row(children: [
                    Expanded(
                      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        Text(order.orderNumber,
                            style: TextStyle(fontWeight: FontWeight.w800, color: ZayloColors.ink)),
                        Text('${order.itemCount} ${order.itemCount == 1 ? 'item' : 'items'}',
                            style: const TextStyle(fontSize: 12)),
                        if (paymentMethod == 'gcash')
                          Text(order.isPaymentConfirmed ? 'Paid' : 'Payment not confirmed',
                              style: TextStyle(fontSize: 12, color: order.isPaymentConfirmed ? ZayloColors.success : ZayloColors.muted)),
                      ]),
                    ),
                    Text(peso(order.total), style: TextStyle(fontWeight: FontWeight.w800, color: ZayloColors.ink)),
                  ]),
                ),
              ),
            const SizedBox(height: 18),
            for (final order in orders.where((order) => order.gcash?.canPay == true))
              Padding(padding: const EdgeInsets.only(bottom: 10), child: FilledButton(
                style: FilledButton.styleFrom(backgroundColor: const Color(0xFF0065FF), foregroundColor: Colors.white),
                onPressed: _checking ? null : () => _pay(order), child: Text('Pay ${order.orderNumber} with GCash'),
              )),
            if (paymentMethod == 'gcash') ...[
              if (_checkError != null) Text(_checkError!, textAlign: TextAlign.center),
              TextButton.icon(
                onPressed: _checking ? null : _refreshPayments,
                icon: const Icon(Icons.refresh, size: 18),
                label: Text(_checking ? 'Checking payment…' : 'Check payment status'),
              ),
              const SizedBox(height: 10),
            ],
            PrimaryButton(
              label: pendingPayment ? 'VIEW UNPAID ORDERS' : 'TRACK MY ORDERS',
              onPressed: () => Navigator.of(context).pushReplacement(
                MaterialPageRoute(builder: (_) => OrdersScreen(initialTab: pendingPayment ? 'to_pay' : 'all')),
              ),
            ),
            const SizedBox(height: 10),
            SizedBox(
              width: double.infinity,
              child: OutlinedButton(
                onPressed: () => ShopActions.showTab(context, 0),
                style: secondaryButtonStyle,
                child: const Text('CONTINUE SHOPPING'),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
