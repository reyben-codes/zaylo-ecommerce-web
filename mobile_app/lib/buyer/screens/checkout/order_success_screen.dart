import 'package:flutter/material.dart';

import '../../../core/theme/zaylo_theme.dart';
import '../../../core/utils/formatters.dart';
import '../../../core/widgets/buttons.dart';
import '../../../core/widgets/layout.dart';
import '../../models/order_models.dart';
import '../../widgets/shop_actions.dart';
import '../orders/orders_screen.dart';

/// Shown after a successful checkout. One order is created per seller.
class OrderSuccessScreen extends StatelessWidget {
  const OrderSuccessScreen({super.key, required this.orders, required this.paymentMethod});

  final List<Order> orders;
  final String paymentMethod;

  @override
  Widget build(BuildContext context) {
    final total = orders.fold<double>(0, (sum, order) => sum + order.total);
    return Scaffold(
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.fromLTRB(24, 48, 24, 24),
          children: [
            const CircleAvatar(
              radius: 40,
              backgroundColor: ZayloColors.ink,
              child: Icon(Icons.check_rounded, color: ZayloColors.white, size: 44),
            ),
            const SizedBox(height: 22),
            Text('Order placed', textAlign: TextAlign.center, style: Theme.of(context).textTheme.displaySmall),
            const SizedBox(height: 10),
            Text(
              paymentMethod == 'gcash'
                  ? 'Thank you! Your GCash payment will be confirmed by the seller before your order ships.'
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
                  style: const TextStyle(fontSize: 12, color: ZayloColors.muted),
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
                            style: const TextStyle(fontWeight: FontWeight.w800, color: ZayloColors.ink)),
                        Text('${order.itemCount} ${order.itemCount == 1 ? 'item' : 'items'}',
                            style: const TextStyle(fontSize: 12)),
                      ]),
                    ),
                    Text(peso(order.total), style: const TextStyle(fontWeight: FontWeight.w800, color: ZayloColors.ink)),
                  ]),
                ),
              ),
            const SizedBox(height: 18),
            PrimaryButton(
              label: 'TRACK MY ORDERS',
              onPressed: () => Navigator.of(context).pushReplacement(
                MaterialPageRoute(builder: (_) => const OrdersScreen()),
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
